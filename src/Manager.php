<?php
namespace FQueue;

use Icecave\Isolator\Isolator;

class Manager
{
    private $queues = array();

    /* @var \Psr\Log\LoggerInterface */
    private $logger;

    /* @var StorageInterface */
    private $storage;

    private $cycle_usleep = 1000000; // 1 second

    private $fork_max_execution_time_init = 2; // 2 seconds + execution time for each fork

    // насколько старые записи из таблиц очередей можно удалять
    private $cleanup_seconds;

    // как часто делать cleanup
    private $cleanup_every_cycles = 30;

    private $forks_queue_pids = array();

    private $jobs_queue = array();

    /* @var Isolator */
    private $isolator;

    private $cycles_limit = null;

    private $cycles_done = 0;

    private $forks_state_file = null;

    private $container = null;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        StorageInterface $storage,
        $container = null,
        $forks_state_file = null,
        Isolator $isolator = null)
    {
        $this->logger = $logger;
        $this->storage = $storage;
        $this->isolator = Isolator::get($isolator);
        $this->container = $container;
        $this->forks_state_file = $forks_state_file;

        $this->cleanup_seconds = 60 * 60 * 24 * 2;
    }

    public function addQueue(
        $queue,
        $forks = 1,
        $tasks_per_fork = 1,
        $in_memory_queue_size = 10,
        \Psr\Log\LoggerInterface $logger = null)
    {
        if (isset($this->queues[$queue])) {
            throw new \InvalidArgumentException('queue already exists');
        }

        $this->queues[$queue] = array(
            'forks' => $forks,
            'tasks_per_fork' => $tasks_per_fork,
            'manager_queue' => $in_memory_queue_size,
            'logger' => $logger ? $logger : $this->logger,
        );
        $this->jobs_queue[$queue] = array();
    }

    public function cyclesLimit($cycles_limit)
    {
        $this->cycles_limit = $cycles_limit;
    }

    public function cyclesUsleep($cycle_usleep)
    {
        $this->cycle_usleep = $cycle_usleep;
    }

    public function cleanupSeconds($cleanup_seconds)
    {
        $this->cleanup_seconds = $cleanup_seconds;
    }

    public function cleanupEveryCycles($cleanup_every_cycles)
    {
        $this->cleanup_every_cycles = $cleanup_every_cycles;
    }

    public function forkMaxExecutionTimeInit($fork_max_execution_time_init)
    {
        $this->fork_max_execution_time_init = $fork_max_execution_time_init;
    }

    public function start()
    {
        if ($this->queues === array()) {
            throw new \InvalidArgumentException;
        }

        $this->forksStateFileInit();

        while (true) {
            $this->cleanup();
            $this->cycle();
            $this->cycles_done ++;

            if ($this->cycles_limit !== null && $this->cycles_done >= $this->cycles_limit) {
                break;
            }

            usleep($this->cycle_usleep);
        }
    }

    private function cleanup()
    {
        if ($this->cycles_done !== 0 && $this->cycles_done % $this->cleanup_every_cycles !== 0) {
            return;
        }

        $jobs_deleted = $this->storage->cleanup(time() - $this->cleanup_seconds);
        //@todo separate success deleted and fail deleted count
        $this->logger->info('storage cleanup done, jobs deleted: ' . $jobs_deleted);
    }

    private function cycle()
    {
        $this->logger->debug('cycle start ' . $this->cycles_done);

        // output debug stats
        $stats = '';
        foreach ($this->jobs_queue as $queue => $items) {
            $stats .= "$queue: " . count($items) . ' ';
        }
        $this->logger->debug('in-memory queue jobs: ' . $stats);

        $this->actualizeForks();

        // start new forks
        foreach ($this->queues as $queue => $queue_params) {
            $this->fetchQueue($queue, $queue_params);

            // cut queue part
            $jobs2fork = array_splice($this->jobs_queue[$queue], 0, $this->queues[$queue]['tasks_per_fork']);

            // calculate max_execution_time
            /* @var JobRow $jobRow */
            $max_execution_time = $this->fork_max_execution_time_init; // seconds initial for system service needs
            foreach ($jobs2fork as $index => $jobRow) {
                $job = Helper::initJob($jobRow, $this->container, $this->logger);
                if (!$job) {
                    $this->storage->markFailPermanent($jobRow);
                    unset($jobs2fork[$index]);
                    continue;
                }
                $max_execution_time += $job->getMaxExecutionTime();
            }

            if (empty($jobs2fork)) {
                $this->logger->debug("{$queue}: no jobs in queue");
                continue;
            }

            // check if one more fork is avaliable
            if (isset($this->forks_queue_pids[$queue]) && count($this->forks_queue_pids[$queue]) >= $this->queues[$queue]['forks']) {
                $this->logger->debug("{$queue}: forks limit reached");
                continue;
            }

            $this->storage->beforeFork();

            $pid = $this->isolator->pcntl_fork();
            if ($pid === -1) {
                $this->logger->emergency("{$queue}: could not fork");
                $this->isolator->exit(0);
            } elseif ($pid) {
                // we are the parent
                $context = array(
                    'queue' => $queue,
                    'pid' => $pid,
                    'jobs' => count($jobs2fork),
                    'max_execution_time' => $max_execution_time,
                );
                $this->logger->info("making new fork", $context);

                $this->forks_queue_pids[$queue][] = array(
                    'pid' => $pid,
                    'time_start' => time(),
                    'time_kill_timeout' => time() + $max_execution_time,
                    'job_ids' => array_map(function(JobRow $jobRow){return $jobRow->getId();}, $jobs2fork),
                );
                $this->saveForksState();
            } else {
                // we are fork
                $fork = new Fork(
                    $this->storage,
                    $queue_params['logger'],
                    $this->isolator,
                    $this->container,
                    $queue,
                    $jobs2fork,
                    $max_execution_time
                );
                $fork->runAndDie();
            }
        }
    }

    private function actualizeForks()
    {
        // defunc zombie processes - feel free!
        $status = null;
        while (pcntl_wait($status, WNOHANG || WUNTRACED) > 0) {
            usleep(5000);
        }

        //remove dead forks from array
        foreach ($this->forks_queue_pids as $queue => $pids) {
            foreach ($pids as $index => $pid_info) {
                if ($this->isolator->posix_kill($pid_info['pid'], SIG_DFL)) {
                    //check timeout
                    if (time() <= $pid_info['time_kill_timeout']) {
                        $this->logger->debug("{$queue}: FORK {$pid_info['pid']} alive");
                    } else {
                        $kill_result = $this->isolator->posix_kill($pid_info['pid'], SIGTERM);
                        $this->logger->error("{$queue}: FORK {$pid_info['pid']} timeout, kill result: "
                            . var_export($kill_result, true));

                        $this->storage->markTimeoutIfInProgress($pid_info['job_ids']);

                        unset($this->forks_queue_pids[$queue][$index]);
                    }
                } else {
                    $this->logger->debug("{$queue}: FORK {$pid_info['pid']} dead");
                    $this->storage->markErrorIfInProgress($pid_info['job_ids']);
                    unset($this->forks_queue_pids[$queue][$index]);
                }
            }
            // reindex array
            $this->forks_queue_pids[$queue] = array_values($this->forks_queue_pids[$queue]);
        }
        $this->saveForksState();
    }

    private function forksStateFileInit()
    {
        if ($this->forks_state_file === null) {
            return;
        }

        if (is_dir($this->forks_state_file)) {
            throw new \InvalidArgumentException('directory');
        }

        if (is_file($this->forks_state_file)) {
            $check = $this->forks_state_file;
        } else {
            $check = dirname($this->forks_state_file);
        }

        if (!is_readable($check)) {
            throw new \InvalidArgumentException('file not readable');
        }
        if (!is_writable($check)) {
            throw new \InvalidArgumentException('file not writeable');
        }

        if (is_file($this->forks_state_file)) {
            $json = file_get_contents($this->forks_state_file);
            if (!$json) {
                $this->logger->error('file_get_contents failed ' . $this->forks_state_file);
            }
            $json = json_decode($json, true);
            if (!is_array($json)) {
                $this->logger->error('json invalid');
                return;
            }
            $this->forks_queue_pids = $json;
            $this->logger->debug('pids state loaded from file');
        }
    }

    private function saveForksState()
    {
        if ($this->forks_state_file === null) {
            return;
        }

        file_put_contents($this->forks_state_file, json_encode($this->forks_queue_pids));
    }

    private function fetchQueue($queue, array $queue_params)
    {
        if (count($this->jobs_queue[$queue]) < $queue_params['manager_queue']) {
            $limit = $queue_params['manager_queue'] - count($this->jobs_queue[$queue]);

            $exclude_ids = array();
            foreach ($this->jobs_queue[$queue] as $jobRow) {
                $exclude_ids[] = $jobRow->getId();
            }

            $jobs_new = $this->storage->getJobs($queue, $exclude_ids, $limit);
            $this->jobs_queue[$queue] = array_merge($this->jobs_queue[$queue], $jobs_new);
            $this->logger->debug("{$queue}: fetched jobs: " . count($jobs_new)
                . ", total in-memory jobs: " . count($this->jobs_queue[$queue]));
        } else {
            $this->logger->debug("{$queue}: in-memory jobs limit reached: "
                . $queue_params['manager_queue']);
        }
    }
}

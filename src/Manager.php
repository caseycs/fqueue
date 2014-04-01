<?php
namespace FQueue;

use Icecave\Isolator\Isolator;

class Manager
{
    private $queues = array();

    /* @var \Psr\Log\LoggerInterface */
    private $Logger;

    /* @var StorageInterface */
    private $Storage;

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

    public function __construct(
        \Psr\Log\LoggerInterface $Logger,
        StorageInterface $Storage,
        $forks_state_file = null,
        Isolator $isolator = null)
    {
        $this->Logger = $Logger;
        $this->Storage = $Storage;
        $this->isolator = Isolator::get($isolator);
        $this->forks_state_file = $forks_state_file;

        $this->cleanup_seconds = 60 * 60 * 24 * 2;
    }

    public function addQueue(
        $queue,
        $forks = 1,
        $tasks_per_fork = 1,
        $in_memory_queue_size = 10,
        \Psr\Log\LoggerInterface $Logger = null)
    {
        assert(!isset($this->queues[$queue]));
        $this->queues[$queue] = array(
            'forks' => $forks,
            'tasks_per_fork' => $tasks_per_fork,
            'manager_queue' => $in_memory_queue_size,
            'logger' => $Logger ? $Logger : $this->Logger,
        );
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
        if ($this->queues === array()) throw new \InvalidArgumentException;

        $this->forksStateFileInit();

        while (true) {
            $this->cleanup();
            $this->cycle();
            $this->cycles_done ++;

            if ($this->cycles_limit !== null && $this->cycles_done >= $this->cycles_limit) break;

            usleep($this->cycle_usleep);
        }
    }

    private function cleanup()
    {
        if ($this->cycles_done !== 0 && $this->cycles_done % $this->cleanup_every_cycles !== 0) return;

        $jobs_deleted = $this->Storage->cleanup(time() - $this->cleanup_seconds);
        $this->Logger->info('cleanup done, jobs deleted: ' . $jobs_deleted);
    }

    private function cycle()
    {
        $this->Logger->debug('cycle start ' . $this->cycles_done);

        // stats
        $stats = '';
        foreach ($this->jobs_queue as $queue => $items) {
            $stats .= "$queue: " . count($items) . ' ';
        }
        $this->Logger->debug('in-memory queue jobs: ' . $stats);

        $this->actualizeForks();

        // start new forks
        foreach ($this->queues as $queue => $queue_params) {
            if (!isset($this->jobs_queue[$queue])) $this->jobs_queue[$queue] = array();

            // fetch queue
            if (count($this->jobs_queue[$queue]) < $queue_params['manager_queue']) {
                $limit = $queue_params['manager_queue'] - count($this->jobs_queue[$queue]);
                $jobs_new = $this->Storage->getJobs($queue, $limit);
                $this->jobs_queue[$queue] = array_merge($this->jobs_queue[$queue], $jobs_new);
                $this->Logger->debug("{$queue}: fetched jobs: " . count($jobs_new)
                    . ", total in-memory jobs: " . count($this->jobs_queue[$queue]));
            } else {
                $this->Logger->debug("{$queue}: in-memory jobs limit reached: "
                    . $queue_params['manager_queue']);
            }

            if ($this->jobs_queue[$queue] === array()) continue;

            // check if fork is avaliable
            if (isset($this->forks_queue_pids[$queue]) && count($this->forks_queue_pids[$queue]) >= $this->queues[$queue]['forks']) {
                $this->Logger->debug("{$queue}: forks limit reached");
                continue;
            }

            // cut queue part
            $jobs2fork = array_splice($this->jobs_queue[$queue], 0, $this->queues[$queue]['tasks_per_fork']);

            // calculate max_execution_time
            /* @var JobRow $JobRow */
            $max_execution_time = $this->fork_max_execution_time_init; // second initial for system service needs
            foreach ($jobs2fork as $index => $JobRow) {
                $Job = Helper::initJob($JobRow, $this->Logger);
                if (!$Job) {
                    unset($jobs2fork[$index]);
                    continue;
                }
                $max_execution_time += $Job->getMaxExecutionTime();
            }

            if ($jobs2fork === array()) {
                $this->Logger->debug("{$queue}: no jobs left, skip cycle");
                continue;
            }

            $pid = $this->isolator->pcntl_fork();
            if ($pid === -1) {
                $this->Logger->emergency("{$queue}: could not fork");
                $this->isolator->exit(0);
            } elseif ($pid) {
                // we are the parent
                $context = array(
                    'queue' => $queue,
                    'pid' => $pid,
                    'jobs' => count($jobs2fork),
                    'max_execution_time' => $max_execution_time,
                );
                $this->Logger->info("making new fork", $context);

                $this->forks_queue_pids[$queue][] = array(
                    'pid' => $pid,
                    'time_start' => time(),
                    'time_kill_timeout' => time() + $max_execution_time,
                    'job_ids' => array_map(array($this, 'extractJobId'), $jobs2fork),
                );
                $this->saveForksState();
            } else {
                // we are fork
                $Fork = new Fork($this->Storage, $queue_params['logger'], $this->isolator, $queue, $jobs2fork, $max_execution_time);
                $Fork->runAndDie();
            }
        }
    }

    private function extractJobId(JobRow $JobRow)
    {
        return $JobRow->getId();
    }

    private function actualizeForks()
    {
        // defunc zombie processes - feel free!
        $status = null;
        while (pcntl_wait($status, WNOHANG || WUNTRACED) > 0) usleep(5000);

        //remove dead forks from array
        foreach ($this->forks_queue_pids as $queue => $pids) {
            foreach ($pids as $index => $pid_info) {
                if ($this->isolator->posix_kill($pid_info['pid'], SIG_DFL)) {
                    //check timeout
                    if (time() <= $pid_info['time_kill_timeout']) {
                        $this->Logger->debug("{$queue}: FORK {$pid_info['pid']} alive");
                    } else {
                        $kill_result = $this->isolator->posix_kill($pid_info['pid'], SIGTERM);
                        $this->Logger->error("{$queue}: FORK {$pid_info['pid']} timeout, kill result: "
                            . var_export($kill_result, true));

                        $this->Storage->markTimeoutIfInProgress($pid_info['job_ids']);

                        unset($this->forks_queue_pids[$queue][$index]);
                    }
                } else {
                    $this->Logger->debug("{$queue}: FORK {$pid_info['pid']} dead");
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
        if ($this->forks_state_file === null) return;

        if (is_dir($this->forks_state_file)) throw new \InvalidArgumentException('directory');

        if (is_file($this->forks_state_file)) {
            $check = $this->forks_state_file;
        } else {
            $check = dirname($this->forks_state_file);
        }

        if (!is_readable($check)) throw new \InvalidArgumentException('file not readable');
        if (!is_writable($check)) throw new \InvalidArgumentException('file not writeable');

        if (is_file($this->forks_state_file)) {
            $json = file_get_contents($this->forks_state_file);
            if (!$json) {
                $this->Logger->error('file_get_contents failed ' . $this->forks_state_file);
            }
            $json = json_decode($json, true);
            if (!is_array($json)) {
                $this->Logger->error('json invalid');
            }
            $this->forks_queue_pids = $json;
            $this->Logger->debug('pids state loaded from file');
        }
    }

    private function saveForksState()
    {
        if ($this->forks_state_file === null) return;

        file_put_contents($this->forks_state_file, json_encode($this->forks_queue_pids));
    }
}

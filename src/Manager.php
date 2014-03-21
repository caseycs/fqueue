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

    public function __construct(\Psr\Log\LoggerInterface $Logger, StorageInterface $Storage, Isolator $isolator = null)
    {
        $this->Logger = $Logger;
        $this->Storage = $Storage;
        $this->isolator = Isolator::get($isolator);

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

    public function start()
    {
        if ($this->queues === array()) throw new \InvalidArgumentException;

        // setup tick hander

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
        $this->Logger->debug('cycle start');

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
                    . " total in-memory jobs: " . count($this->jobs_queue[$queue]));
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
            $max_execution_time = 0;
            foreach ($jobs2fork as $index => $JobRow) {
                $Job = $this->initJob($JobRow, $this->Logger);
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
                );
            } else {
                // fork
                $this->processFork($queue, $jobs2fork, $max_execution_time, $queue_params['logger']);
            }
        }

        // defunc zombie processes - feel free!
        $status = null;
        $this->isolator->pcntl_wait($status, WNOHANG || WUNTRACED);
    }

    private function actualizeForks()
    {
        //remove dead forks
        foreach ($this->forks_queue_pids as $queue => $pids) {
            foreach ($pids as $index => $pid_info) {
                if ($this->isolator->posix_kill($pid_info['pid'], SIG_DFL)) {
                    $this->Logger->debug("{$queue}: FORK {$pid_info['pid']} alive");
                    //check timeout
                    if (time() >= $pid_info['time_kill_timeout']) {
                        $kill_result = $this->isolator->posix_kill($pid_info['pid'], SIGTERM);
                        $this->Logger->error("{$queue}: FORK {$pid_info['pid']} timeout, kill result: "
                            . var_export($kill_result, true));
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
    }

    /**
     * @param $queue
     * @param JobRow[] $jobs
     * @param \Psr\Log\LoggerInterface $Logger
     */
    private function processFork($queue, array $jobs, $max_execution_time, \Psr\Log\LoggerInterface $Logger)
    {
        $sid = $this->isolator->posix_setsid();
        if ($sid < 0) {
            $this->Logger->emergency("{$queue}: FORK posix_setsid failed");
            $this->isolator->exit(0);
        }

        $context = array(
            'queue' => $queue,
            'pid' => getmypid(),
            'jobs' => count($jobs),
            'max_execution_time' => $max_execution_time,
        );
        $Logger->info("FORK", $context);

        $this->isolator->set_time_limit($max_execution_time);

        $this->Storage->onForkInit();

        $first_done = array();
        foreach ($jobs as $JobRow) {
            $Job = $this->initJob($JobRow, $Logger);
            if (!$Job) continue;

            if (!in_array($JobRow->getClass(), $first_done, true)) {
                $first_done[] = $JobRow->getClass();
                $Job->firstTimeInFork();
            }

            $result = $Job->run($Logger);
            $this->Storage->markStarted($JobRow);

            if ($result === JobRow::RESULT_SUCCESS) {
                $this->Storage->markSuccess($JobRow);
            } elseif ($result === JobRow::RESULT_FAIL) {
                $this->Storage->markFail($JobRow);
            } else {
                $Logger->error('invalid run result - ' . var_export($result, true)
                    . ' - ' . $JobRow->getClass()
                    . ' #' . $JobRow->getId());
            }
        }

        $this->isolator->exit(0);
    }

    /**
     * @return JobInterface
     */
    private function initJob(JobRow $JobRow, \Psr\Log\LoggerInterface $Logger)
    {
        if (!class_exists($JobRow->getClass())) {
            $Logger->emergency('job class not found:' . $JobRow->getClass());
            return false;
        }
        /* @var JobInterface $Job */
        $class = $JobRow->getClass();
        $Job = new $class;
        $Job->init($JobRow->getParams());
        return $Job;
    }
}

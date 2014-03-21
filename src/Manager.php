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

        //setup tick hander

        while (true) {
            $this->cleanup();
            $this->cycle();
            $this->cycles_done ++;

            if ($this->cycles_limit === null || $this->cycles_done >= $this->cycles_limit) break;

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
        //stats
        $stats = '';
        foreach ($this->jobs_queue as $queue => $items) {
            $stats .= "$queue:" . count($items) . ' ';
        }
        $this->Logger->debug('in-memory queue size: ' . $stats);

        //start new forks
        foreach ($this->queues as $queue => $queue_params) {
            if (!isset($this->jobs_queue[$queue])) $this->jobs_queue[$queue] = array();

            //fetch queue
            if (count($this->jobs_queue[$queue]) < $queue_params['manager_queue']) {
                $limit = $queue_params['manager_queue'] - count($this->jobs_queue[$queue]);
                $jobs_new = $this->Storage->getJobs($queue, $limit);
                $this->jobs_queue[$queue] = array_merge($this->jobs_queue[$queue], $jobs_new);
                $this->Logger->debug("{$queue}: in-memory queue: "
                    . count($this->jobs_queue[$queue])
                    . ", fetched: " . count($jobs_new));
            } else {
                $this->Logger->debug("{$queue}: in-memory jobs limit reached: "
                    . $queue_params['manager_queue']);
            }

            if ($this->jobs_queue[$queue] === array()) continue;

            //check if fork is avaliable
            if (isset($this->forks_queue_pids[$queue]) && count($this->forks_queue_pids[$queue]) >= $this->queues[$queue]['forks']) {
                $this->Logger->debug("{$queue}: forks limit reached");
                continue;
            }

            //cut queue part
            $jobs2fork = array_splice($this->jobs_queue[$queue], 0, $this->queues[$queue]['tasks_per_fork']);

            //calculate max_execution_time
            /* @var JobRow $JobRow */
            $max_execution_time = 0;
            foreach ($jobs2fork as $index => $JobRow) {
                $Job = $this->initJob($JobRow, $this->Logger);
                if (!$Job) unset($jobs2fork[$index]);
                $max_execution_time += $Job->getMaxExecutionTime();
            }
            ($max_execution_time);

            //make new fork
            $this->Logger->debug("{$queue}: make new fork, jobs:" . count($jobs2fork));
            $pid = $this->isolator->pcntl_fork();

            if ($pid === -1) {
                $this->Logger->emergency("{$queue}: could not fork");
                exit(0);
            } elseif ($pid) {
                // we are the parent
                $this->forks_queue_pids[$queue][] = $pid;
            } else {
                $sid = $this->isolator->posix_setsid();
                if ($sid < 0) {
                    $this->Logger->emergency("{$queue}: FORK posix_setsid failed");
                    exit(0);
                }

                $this->processFork($queue, $jobs2fork, $max_execution_time, $queue_params['logger']);
                exit(0);
            }
        }

        //defunc zombie processes - feel free!
        $this->isolator->pcntl_wait($status, WNOHANG || WUNTRACED);

        //remove dead forks
        foreach ($this->forks_queue_pids as $queue => $pids) {
            foreach ($pids as $index => $pid) {
                //unset dead
                if (!$this->isolator->posix_kill($pid, 0)) unset($this->forks_queue_pids[$queue][$index]);
                //force kill timeouted
            }
            //reindex array
            $forks_queue_pids[$queue] = array_values($this->forks_queue_pids[$queue]);
        }
    }

    /**
     * @param $queue
     * @param JobRow[] $jobs
     * @param \Psr\Log\LoggerInterface $Logger
     */
    private function processFork($queue, array $jobs, $max_execution_time, \Psr\Log\LoggerInterface $Logger)
    {
        $Logger->info("{$queue}: FORK pid: " . getmypid()
            . ", jobs:" . count($jobs)
            . ', max_execution_time: ' . $max_execution_time);

        set_time_limit($max_execution_time);

        $this->Storage->onForkInit();

        foreach ($jobs as $JobRow) {
            $Job = $this->initJob($JobRow, $Logger);
            if (!$Job) continue;

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

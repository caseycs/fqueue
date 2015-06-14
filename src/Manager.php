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

    private $cycleUsleep = 1000000; // 1 second

    private $forkMaxExecutionTimeInit = 2; // 2 seconds + execution time for each fork

    // насколько старые записи из таблиц очередей можно удалять
    private $cleanupStorageSeconds;

    // как часто делать cleanup
    private $cleanupEveryCycles = 30;

    private $forks = array();

    /* @var Isolator */
    private $isolator;

    /* @var Helper */
    private $helper;

    private $cyclesLimit = null;

    private $cyclesDone = 0;

    private $forksStateFile = null;

    private $container = null;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        StorageInterface $storage,
        $container = null,
        $forksStateFile = null,
        Isolator $isolator = null,
        EventHandler $eventHandler = null)
    {
        $this->logger = $logger;
        $this->storage = $storage;
        $this->isolator = Isolator::get($isolator);
        $this->container = $container;
        $this->forksStateFile = $forksStateFile;
        $this->eventHandler = $eventHandler ? $eventHandler : new EventHandler;

        $this->helper = new Helper($container, $storage, $this->eventHandler);

        $this->cleanupStorageSeconds = 60 * 60 * 24 * 2;
    }

    public function addQueue(
        $queue,
        $forksCount = 1,
        $tasksPerFork = 1,
        \Closure $createLogger)
    {
        if (isset($this->queues[$queue])) {
            throw new \InvalidArgumentException('queue already exists');
        }

        $this->queues[$queue] = array(
            'forks_count' => $forksCount,
            'tasks_per_fork' => $tasksPerFork,
            'create_logger' => $createLogger,
        );
    }

    public function cyclesLimit($cyclesLimit)
    {
        $this->cyclesLimit = $cyclesLimit;
    }

    public function cyclesUsleep($cycleUsleep)
    {
        $this->cycleUsleep = $cycleUsleep;
    }

    public function cleanupSeconds($cleanupStorageSeconds)
    {
        $this->cleanupStorageSecondsseconds = $cleanupStorageSeconds;
    }

    public function cleanupEveryCycles($cleanupEveryCycles)
    {
        $this->cleanupEveryCycles = $cleanupEveryCycles;
    }

    public function forkMaxExecutionTimeInit($forkMaxExecutionTimeInit)
    {
        $this->forkMaxExecutionTimeInit = $forkMaxExecutionTimeInit;
    }

    public function start()
    {
        if ($this->queues === array()) {
            throw new \InvalidArgumentException;
        }

        $this->forksStateFileInit();

        while (true) {
            $this->cleanupStorage();
            $this->cycle();
            $this->cyclesDone ++;

            if ($this->cyclesLimit !== null && $this->cyclesDone >= $this->cyclesLimit) {
                break;
            }

            usleep($this->cycleUsleep);
        }
    }

    private function cleanupStorage()
    {
        if ($this->cyclesDone !== 0 && $this->cyclesDone % $this->cleanupEveryCycles !== 0) {
            return;
        }

        $jobs_deleted = $this->storage->cleanup(time() - $this->cleanupStorageSeconds);
        $this->eventHandler->cleanupStorage($jobs_deleted);
        //@todo separate success deleted and fail deleted count
        $this->logger->info('storage cleanup done, jobs deleted: ' . $jobs_deleted);
    }

    private function cycle()
    {
        $this->logger->debug('cycle start ' . $this->cyclesDone);

        $this->actualizeForks();

        // start new forks
        foreach ($this->queues as $queue => $queueParams) {
            $jobs2fork = $this->storage->getJobs($queue, $queueParams['tasks_per_fork']);
            $this->logger->debug("{$queue}: fetched jobs: " . count($jobs2fork));

            if (empty($jobs2fork)) {
                $this->logger->debug("{$queue}: no jobs in queue");
                continue;
            }

            // calculate max_execution_time
            /* @var JobRow $jobRow */
            $max_execution_time = $this->forkMaxExecutionTimeInit; // seconds initial for system service needs
            foreach ($jobs2fork as $index => $jobRow) {
                $job = $this->helper->initJob($queue, $jobRow, $this->logger);
                if (!$job) {
                    unset($jobs2fork[$index]);
                    continue;
                }
                $max_execution_time += $job->getMaxExecutionTime();
            }

            // check if one more fork is avaliable
            if (isset($this->forks[$queue]) && count($this->forks[$queue]) >= $this->queues[$queue]['forks_count']) {
                $this->logger->debug("{$queue}: forks count limit reached");
                continue;
            }

            $this->storage->beforeFork();
            $this->eventHandler->beforeFork();

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

                $this->forks[$queue][] = array(
                    'pid' => $pid,
                    'time_start' => time(),
                    'time_kill_timeout' => time() + $max_execution_time,
                    'jobs' => $jobs2fork,
                );
                $this->saveForksState();
            } else {
                // we are fork
                $fork = new Fork(
                    $this->storage,
                    $queueParams['create_logger']->__invoke(getmypid()),
                    $this->isolator,
                    $this->eventHandler,
                    $this->helper,
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
        foreach ($this->forks as $queue => $forks) {
            foreach ($forks as $index => $fork_info) {
                if ($this->isolator->posix_kill($fork_info['pid'], SIG_DFL)) {
                    //check timeout
                    if (time() <= $fork_info['time_kill_timeout']) {
                        $this->logger->debug("{$queue}: FORK {$fork_info['pid']} alive");
                    } else {
                        $kill_result = $this->isolator->posix_kill($fork_info['pid'], SIGTERM);
                        $this->logger->error("{$queue}: FORK {$fork_info['pid']} timeout, kill result: "
                            . var_export($kill_result, true));

                        /** @var JobRow $job */
                        foreach ($fork_info['jobs'] as $job) {
                            if ($this->storage->markTimeoutIfInProgress($queue, $job)) {
                                $this->logger->debug("{$queue}: JOB {$job->getId()} timeout");
                                $this->eventHandler->jobTimeout($queue, $job);
                            }
                        }

                        unset($this->forks[$queue][$index]);
                    }
                } else {
                    $this->logger->debug("{$queue}: FORK {$fork_info['pid']} dead");

                    /** @var JobRow $job */
                    foreach ($fork_info['jobs'] as $job) {
                        if ($this->storage->markErrorIfInProgress($queue, $job)) {
                            $this->logger->debug("{$queue}: JOB {$job->getId()} error");
                            $this->eventHandler->jobError($queue, $job);
                        }
                    }
                    unset($this->forks[$queue][$index]);
                }
            }
            // reindex array
            $this->forks[$queue] = array_values($this->forks[$queue]);
        }
        $this->saveForksState();
    }

    private function forksStateFileInit()
    {
        if ($this->forksStateFile === null) {
            return;
        }

        if (is_dir($this->forksStateFile)) {
            throw new \InvalidArgumentException('directory');
        }

        if (is_file($this->forksStateFile)) {
            $check = $this->forksStateFile;
        } else {
            $check = dirname($this->forksStateFile);
        }

        if (!is_readable($check)) {
            throw new \InvalidArgumentException('file not readable');
        }
        if (!is_writable($check)) {
            throw new \InvalidArgumentException('file not writeable');
        }

        if (is_file($this->forksStateFile)) {
            $serializedBody = file_get_contents($this->forksStateFile);
            if (!$serializedBody) {
                $this->logger->error('file_get_contents failed ' . $this->forksStateFile);
            }
            $body = unserialize($serializedBody);
            if (!is_array($body)) {
                $this->logger->error('json invalid');
                return;
            }
            $this->forks = $body;
            $this->logger->debug('pids state loaded from file');
        }
    }

    private function saveForksState()
    {
        if ($this->forksStateFile === null) {
            return;
        }

        file_put_contents($this->forksStateFile, serialize($this->forks));
    }
}

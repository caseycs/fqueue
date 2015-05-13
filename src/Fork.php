<?php
namespace FQueue;

use Icecave\Isolator\Isolator;

class Fork
{
    /* @var \Psr\Log\LoggerInterface */
    private $logger;

    /* @var StorageInterface */
    private $storage;

    private $queue = null;

    /**
     * @var JobRow[]
     */
    private $jobs = array();

    private $max_execution_time;

    private $container;

    /* @var Isolator */
    private $isolator;

    public function __construct(
        StorageInterface $storage,
        \Psr\Log\LoggerInterface $logger,
        Isolator $isolator,
        $container,
        $queue,
        array $jobs,
        $max_execution_time)
    {
        $this->storage = $storage;
        $this->logger = $logger;
        $this->isolator = $isolator;
        $this->container = $container;

        $this->queue = $queue;
        $this->jobs = $jobs;
        $this->max_execution_time = $max_execution_time;
    }

    public function runAndDie()
    {
        $this->init();
        $this->startJobs();
        $this->finish();
    }

    private function init()
    {
        $sid = $this->isolator->posix_setsid();
        if ($sid < 0) {
            $this->logger->emergency("FORK posix_setsid failed");
            $this->isolator->exit(0);
        }

        $context = array(
            'queue' => $this->queue,
            'pid' => getmypid(),
            'jobs' => count($this->jobs),
            'max_execution_time' => $this->max_execution_time,
        );
        $this->logger->info("for init", $context);

        $this->isolator->set_time_limit($this->max_execution_time);
    }

    private function startJobs()
    {
        foreach ($this->jobs as $jobRow) {
            $job = Helper::initJob($jobRow, $this->container, $this->logger);
            if (!$job) {
                continue;
            }

            $this->storage->markInProgress($jobRow);
            $result = $job->run($this->logger);

            $context = array('class' => $jobRow->getClass(), 'id' => $jobRow->getId());

            if ($result === JobRow::STATUS_SUCCESS) {
                $this->logger->info("success", $context);
                $this->storage->markSuccess($jobRow);
            } elseif ($result === JobRow::STATUS_FAIL_TEMPORARY) {
                $this->logger->error("fail", $context);
                $this->storage->markFailTemporary($jobRow);
            } elseif ($result === JobRow::STATUS_FAIL_PERMANENT) {
                $this->logger->error("fail permanent", $context);
                $this->storage->markFailPermanent($jobRow);
            } else {
                $this->storage->markError($jobRow);
                $this->logger->emergency('invalid result', $context);
            }
        }
    }

    private function finish()
    {
        $this->isolator->exit(0);
    }
}

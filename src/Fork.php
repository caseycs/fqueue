<?php
namespace FQueue;

use Icecave\Isolator\Isolator;

class Fork
{
    /* @var \Psr\Log\LoggerInterface */
    private $logger;

    /* @var StorageInterface */
    private $storage;

    /** @var string */
    private $queue;

    /**
     * @var JobRow[]
     */
    private $jobs = array();

    /** @var int */
    private $max_execution_time;

    private $container;

    /* @var Isolator */
    private $isolator;

    /* @var EventHandler */
    private $eventHandler;

    /* @var Helper */
    private $helper;

    public function __construct(
        StorageInterface $storage,
        \Psr\Log\LoggerInterface $logger,
        Isolator $isolator,
        EventHandler $eventHandler,
        Helper $helper,
        $container,
        $queue,
        array $jobs,
        $max_execution_time)
    {
        $this->storage = $storage;
        $this->logger = $logger;
        $this->isolator = $isolator;
        $this->eventHandler = $eventHandler;
        $this->helper = $helper;
        $this->container = $container;

        $this->queue = $queue;
        $this->jobs = $jobs;
        $this->max_execution_time = $max_execution_time;

        $this->eventHandler->workerBorn($this->queue);
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
        $this->logger->info("fork init", $context);

        $this->isolator->set_time_limit($this->max_execution_time);
    }

    private function startJobs()
    {
        foreach ($this->jobs as $jobRow) {
            $job = $this->helper->initJob($this->queue, $jobRow, $this->logger);
            if (!$job) {
                continue;
            }

            $this->storage->markInProgress($this->queue, $jobRow);
            $this->eventHandler->jobStart($this->queue, $jobRow);

            $context = array('job' => array('class' => $jobRow->getClass(), 'id' => $jobRow->getId()));

            try {
                $result = $job->run($this->logger);
            } catch (\Exception $e) {
                $this->storage->markError($this->queue, $jobRow);
                $this->eventHandler->jobError($this->queue, $jobRow);

                $context['exception'] = array(
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                );

                $this->logger->emergency('job exception', $context);
                continue;
            }

            if ($result === JobRow::STATUS_SUCCESS) {
                $this->logger->info("success", $context);
                $this->storage->markSuccess($this->queue, $jobRow);
                $this->eventHandler->jobSuccess($this->queue, $jobRow);
            } elseif ($result === JobRow::STATUS_FAIL_TEMPORARY) {
                $this->logger->error("fail", $context);
                $this->storage->markFailTemporary($this->queue, $jobRow);
                $this->eventHandler->jobFailTemporary($this->queue, $jobRow);
            } elseif ($result === JobRow::STATUS_FAIL_PERMANENT) {
                $this->logger->error("fail permanent", $context);
                $this->storage->markFailPermanent($this->queue, $jobRow);
                $this->eventHandler->jobFailPermanent($this->queue, $jobRow);
            } else {
                $this->logger->emergency('invalid result', $context);
                $this->storage->markReturnInvalid($this->queue, $jobRow);
                $this->eventHandler->jobReturnInvalid($this->queue, $jobRow);
            }
        }
    }

    private function finish()
    {
        $this->eventHandler->workerDead($this->queue);
        $this->isolator->exit(0);
    }
}

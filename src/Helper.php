<?php
namespace FQueue;

class Helper
{
    public function __construct($container, StorageInterface $storage, EventHandler $eventHandler)
    {
        $this->container = $container;
        $this->storage = $storage;
        $this->eventHandler = $eventHandler;
    }

    /**
     * @return JobInterface
     */
    public function initJob($queue, JobRow $jobRow, \Psr\Log\LoggerInterface $Logger)
    {
        if (!class_exists($jobRow->getClass())) {
            $Logger->emergency('job class not found: ' . $jobRow->getClass());
            $this->eventHandler->jobInitFail($queue, $jobRow);
            $this->storage->markFailInit($queue, $jobRow);
            return false;
        }
        /* @var JobInterface $Job */
        $class = $jobRow->getClass();
        $Job = new $class($this->container);
        if (!$Job->init($jobRow->getParams())) {
            $Logger->emergency('job init fail: ' . $jobRow->getClass() . ' ', $jobRow->getParams());
            $this->eventHandler->jobInitFail($queue, $jobRow);
            $this->storage->markFailInit($queue, $jobRow);
            return false;
        }
        return $Job;
    }
}

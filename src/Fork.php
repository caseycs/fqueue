<?php
namespace FQueue;

use Icecave\Isolator\Isolator;

class Fork
{
    /* @var \Psr\Log\LoggerInterface */
    private $Logger;

    /* @var StorageInterface */
    private $Storage;

    private $queue = null;

    /**
     * @var JobRow[]
     */
    private $jobs = array();

    private $jobs_classes_initialized = array();

    private $max_execution_time;

    /* @var Isolator */
    private $Isolator;

    public function __construct(
        StorageInterface $Storage,
        \Psr\Log\LoggerInterface $Logger,
        Isolator $Isolator,
        $queue,
        array $jobs,
        $max_execution_time)
    {
        $this->Storage = $Storage;
        $this->Logger = $Logger;
        $this->Isolator = $Isolator;

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
        $sid = $this->Isolator->posix_setsid();
        if ($sid < 0) {
            $this->Logger->emergency("FORK posix_setsid failed");
            $this->Isolator->exit(0);
        }

        $context = array(
            'queue' => $this->queue,
            'pid' => getmypid(),
            'jobs' => count($this->jobs),
            'max_execution_time' => $this->max_execution_time,
        );
        $this->Logger->info("for init", $context);

        $this->Isolator->set_time_limit($this->max_execution_time);

        $this->Storage->onForkInit();
    }

    private function startJobs()
    {
        foreach ($this->jobs as $JobRow) {
            $Job = Helper::initJob($JobRow, $this->Logger);
            if (!$Job) continue;

            if (!in_array($JobRow->getClass(), $this->jobs_classes_initialized, true)) {
                $this->jobs_classes_initialized[] = $JobRow->getClass();
                $Job->firstTimeInFork();
            }

            $this->Storage->markStarted($JobRow);
            $result = $Job->run($this->Logger);

            $context = array('class' => $JobRow->getClass(), 'id' => $JobRow->getId());

            if ($result === JobRow::RESULT_SUCCESS) {
                $this->Logger->info("success", $context);
                $this->Storage->markSuccess($JobRow);
            } elseif ($result === JobRow::RESULT_FAIL) {
                $this->Logger->error("fail", $context);
                $this->Storage->markFail($JobRow);
            } else {
                $this->Storage->markError($JobRow);
                $this->Logger->emergency('invalid result', $context);
            }
        }
    }

    private function finish()
    {
        $this->Isolator->exit(0);
    }
}

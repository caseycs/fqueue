<?php
namespace FQueue;

class EventHandler
{
    public function beforeFork()
    {
    }

    public function cleanupStorage($jobsDeleted)
    {
    }

    public function workerBorn($queue)
    {
    }

    public function workerDead($queue)
    {
    }

    public function jobInitFail($queue, JobRow $jobRow)
    {
    }

    public function jobStart($queue, JobRow $jobRow)
    {
    }

    public function jobFinish($queue, JobRow $jobRow)
    {
    }

    public function jobSuccess($queue, JobRow $jobRow)
    {
    }

    public function jobFailTemporary($queue, JobRow $jobRow)
    {
    }

    public function jobFailPermanent($queue, JobRow $jobRow)
    {
    }

    public function jobReturnInvalid($queue, JobRow $jobRow)
    {
    }

    public function jobTimeout($queue, JobRow $jobRow)
    {
    }

    public function jobError($queue, JobRow $jobRow)
    {
    }
}

<?php
namespace FQueue;

class TestStorage implements \FQueue\StorageInterface
{
    public $queue;

    public $cnt_mark_timeout = 0;

    public $timeout_ids = array();

    public function __construct(array $queue = array())
    {
        $this->queue = $queue;
    }

    public function getJobs($queue, $limit)
    {
        return array_splice($this->queue[$queue], 0, $limit);
    }

    public function cleanup($last_unixtime)
    {

    }

    public function markStarted(JobRow $JobRow)
    {

    }

    public function markSuccess(JobRow $JobRow)
    {

    }

    public function markFail(JobRow $JobRow)
    {

    }

    public function markError(JobRow $JobRow)
    {

    }

    public function markTimeoutIfInProgress(array $ids)
    {
        $this->cnt_mark_timeout ++;
        $this->timeout_ids = array_merge($this->timeout_ids, $ids);
    }

    public function onForkInit()
    {
    }
}

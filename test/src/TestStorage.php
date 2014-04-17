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

    public function enqueue($queue, $class, array $init_params)
    {

    }

    public function getJobs($queue, array $exclude_ids, $limit)
    {
        $result = array_splice($this->queue[$queue], 0, $limit);
        return $result;
    }

    public function cleanup($last_unixtime)
    {

    }

    public function markInProgress(JobRow $JobRow)
    {

    }

    public function markSuccess(JobRow $JobRow)
    {

    }

    public function markFailTemporary(JobRow $JobRow)
    {

    }

    public function markFailPermanent(JobRow $JobRow)
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

    public function beforeFork()
    {
    }
}

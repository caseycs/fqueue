<?php
namespace FQueue;

class TestStorage implements \FQueue\StorageInterface
{
    public function getJobs($queue, $limit)
    {
        return array();
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

    public function onForkInit()
    {

    }
}

<?php
namespace FQueue;

class StorageMysql implements StorageInterface
{
    public function getJobs($queue, $limit)
    {
        $JobRow = new JobRow;
        return array($JobRow);
    }

    public function cleanup($last_unixtime)
    {
        return 0;
    }

    public function onForkInit()
    {
        return 0;
    }

    function markStarted(JobRow $JobRow)
    {
    }

    function markSuccess(JobRow $JobRow)
    {
    }

    function markFail(JobRow $JobRow)
    {
    }
}

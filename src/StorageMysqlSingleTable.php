<?php
namespace FQueue;

/*
expected table structure:

*/

class StorageMysqlSingleTable implements StorageInterface
{
    private $host, $port, $user, $pass, $database, $table;

    public function __construct($host, $port, $user, $pass, $database, $table)
    {
    }

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

    function markError(JobRow $JobRow)
    {
    }

    public function markTimeoutIfInProgress(array $job_ids)
    {
    }
}

<?php
namespace FQueue;

interface StorageInterface
{
    function enqueue($queue, $class, array $init_params = array());
    /**
     * @return JobRow[]
     */
    function getJobs($queue, array $exclude_ids, $limit);
    function cleanup($last_unixtime);
    function markInProgress(JobRow $JobRow);
    function markSuccess(JobRow $JobRow);
    function markFailTemporary(JobRow $JobRow);
    function markFailPermanent(JobRow $JobRow);
    function markError(JobRow $JobRow);
    function markTimeoutIfInProgress(array $job_ids);
    function beforeFork();
}

<?php
namespace FQueue;

interface StorageInterface
{
    function enqueue($queue, $class, array $init_params = array());

    /**
     * @return JobRow[]
     */
    function getJobs($queue, $limit);
    function cleanup($lastUnixtime);
    function markInProgress($queue, JobRow $JobRow);
    function markSuccess($queue, JobRow $JobRow);
    function markFailInit($queue, JobRow $JobRow);
    function markFailTemporary($queue, JobRow $JobRow);
    function markFailPermanent($queue, JobRow $JobRow);
    function markError($queue, JobRow $JobRow);
    function markReturnInvalid($queue, JobRow $JobRow);
    function markTimeoutIfInProgress($queue, JobRow $JobRow);
    function markErrorIfInProgress($queue, JobRow $JobRow);
    function beforeFork();
}

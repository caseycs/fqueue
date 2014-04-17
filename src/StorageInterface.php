<?php
namespace FQueue;

interface StorageInterface
{
    function getJobs($queue, $limit);
    function cleanup($last_unixtime);
    function markInProgress(JobRow $JobRow);
    function markSuccess(JobRow $JobRow);
    function markFailTemporary(JobRow $JobRow);
    function markFailPermanent(JobRow $JobRow);
    function markError(JobRow $JobRow);
    function markTimeoutIfInProgress(array $job_ids);
    function beforeFork();
}

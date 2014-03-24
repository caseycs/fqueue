<?php
namespace FQueue;

interface StorageInterface
{
    function getJobs($queue, $limit);
    function cleanup($last_unixtime);
    function markStarted(JobRow $JobRow);
    function markSuccess(JobRow $JobRow);
    function markFail(JobRow $JobRow);
    function markError(JobRow $JobRow);
    function onForkInit();
}

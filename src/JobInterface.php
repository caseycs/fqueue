<?php
namespace FQueue;

interface JobInterface
{
    function firstTimeInFork();
    function init(array $args);
    function run(\Psr\Log\LoggerInterface $Logger);
    function getMaxExecutionTime();
}

<?php
namespace FQueue;

interface JobInterface
{
    function onForkInit();
    function init(array $args);
    function run(\Psr\Log\LoggerInterface $Logger);
    function getMaxExecutionTime();
}

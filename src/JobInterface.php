<?php
namespace FQueue;

interface JobInterface
{
    /* dependency injection container, for example Pimple */
    function __construct($container);
    function init(array $params);
    function run(\Psr\Log\LoggerInterface $Logger);
    function getMaxExecutionTime();
    function getMaxRetries();
}

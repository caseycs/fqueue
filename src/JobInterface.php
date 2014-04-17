<?php
namespace FQueue;

interface JobInterface
{
    /* dependency injection container, for example Pimple */
    function __construct($container);
    function init(array $args);
    function run(\Psr\Log\LoggerInterface $Logger);
    function getMaxExecutionTime();
}

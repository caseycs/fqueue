<?php
namespace FQueue;

class TestJobSuccess implements \FQueue\JobInterface
{
    public function __construct($container)
    {
    }

    public function init(array $args)
    {
        return true;
    }

    public function run(\Psr\Log\LoggerInterface $Logger)
    {
        return JobRow::STATUS_SUCCESS;
    }

    public function getMaxExecutionTime()
    {
        return 1;
    }

    public function getMaxRetries()
    {
        return 1;
    }
}

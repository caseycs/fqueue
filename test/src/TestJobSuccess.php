<?php
namespace FQueue;

class TestJobSuccess implements \FQueue\JobInterface
{
    public function __construct($container)
    {
    }

    public function init(array $params)
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

    public static function getRetries()
    {
        return 1;
    }

    public static function getRetryTimeout()
    {
        return 1;
    }

}

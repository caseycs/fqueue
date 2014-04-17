<?php
namespace FQueue;

class TestJobFailPermanent implements \FQueue\JobInterface
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
        return JobRow::STATUS_FAIL_PERMANENT;
    }

    public function getMaxExecutionTime()
    {
        return 1;
    }

    public static function getRetries()
    {
        return 2;
    }

    public static function getRetryTimeout()
    {
        return 2;
    }
}

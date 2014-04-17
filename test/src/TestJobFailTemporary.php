<?php
namespace FQueue;

class TestJobFailTemporary implements \FQueue\JobInterface
{
    const RETRY_TIMEOUT = 2;

    public function __construct($container)
    {
    }

    public function init(array $params)
    {
        return true;
    }

    public function run(\Psr\Log\LoggerInterface $Logger)
    {
        return JobRow::STATUS_FAIL_TEMPORARY;
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
        return self::RETRY_TIMEOUT;
    }
}

<?php
namespace FQueue;

class TestJobFailInvalidResult implements \FQueue\JobInterface
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
        return 'aaaaaa';
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

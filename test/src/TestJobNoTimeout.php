<?php
namespace FQueue;

class TestJobNoTimeout implements \FQueue\JobInterface
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
        sleep(10);
    }

    public function getMaxExecutionTime()
    {
        return 20;
    }

    public function getMaxRetries()
    {
        return 1;
    }
}

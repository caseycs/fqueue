<?php
namespace FQueue;

class TestJobTimeout implements \FQueue\JobInterface
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
        sleep(10);
    }

    public function getMaxExecutionTime()
    {
        return 0;
    }

    public function getMaxRetries()
    {
        return 1;
    }
}

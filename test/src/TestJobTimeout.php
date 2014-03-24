<?php
namespace FQueue;

class TestJobTimeout implements \FQueue\JobInterface
{
    public function firstTimeInFork()
    {
    }

    public function init(array $args)
    {
    }

    public function run(\Psr\Log\LoggerInterface $Logger)
    {
        sleep(10);
    }

    public function getMaxExecutionTime()
    {
        return 0;
    }
}

<?php
namespace FQueue;

class TestJobNoTimeout implements \FQueue\JobInterface
{
    public function firstTimeInFork()
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
        return 20;
    }
}

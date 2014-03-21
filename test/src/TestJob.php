<?php
namespace FQueue;

class TestJob implements \FQueue\JobInterface
{
    public function firstTimeInFork()
    {
    }

    public function init(array $args)
    {
    }

    public function run(\Psr\Log\LoggerInterface $Logger)
    {
    }

    public function getMaxExecutionTime()
    {
        return 1;
    }
}

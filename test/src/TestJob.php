<?php
namespace FQueue;

class TestJob implements \FQueue\JobInterface
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
        return JobRow::RESULT_SUCCESS;
    }

    public function getMaxExecutionTime()
    {
        return 1;
    }
}

<?php
namespace FQueue;

class TestJobSuccess implements \FQueue\JobInterface
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
        return JobRow::RESULT_SUCCESS;
    }

    public function getMaxExecutionTime()
    {
        return 1;
    }
}

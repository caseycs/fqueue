<?php
namespace FQueue;

class TestJobFailTemporary implements \FQueue\JobInterface
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
        return JobRow::RESULT_FAIL_TEMPORARY;
    }

    public function getMaxExecutionTime()
    {
        return 1;
    }
}

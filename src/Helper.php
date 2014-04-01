<?php
namespace FQueue;

use Icecave\Isolator\Isolator;

class Helper
{
    /**
     * @return JobInterface
     */
    public static function initJob(JobRow $JobRow, \Psr\Log\LoggerInterface $Logger)
    {
        if (!class_exists($JobRow->getClass())) {
            $Logger->emergency('job class not found:' . $JobRow->getClass());
            return false;
        }
        /* @var JobInterface $Job */
        $class = $JobRow->getClass();
        $Job = new $class;
        $Job->init($JobRow->getParams());
        return $Job;
    }
}
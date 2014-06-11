<?php
namespace FQueue;

use Icecave\Isolator\Isolator;

class Helper
{
    /**
     * @return JobInterface
     */
    public static function initJob(JobRow $JobRow, $container, \Psr\Log\LoggerInterface $Logger)
    {
        if (!class_exists($JobRow->getClass())) {
            $Logger->emergency('job class not found: ' . $JobRow->getClass());
            return false;
        }
        /* @var JobInterface $Job */
        $class = $JobRow->getClass();
        $Job = new $class($container);
        if (!$Job->init($JobRow->getParams())) {
            $Logger->emergency('job init fail: ' . $JobRow->getClass() . ' ', $JobRow->getParams());
            return false;
        }
        return $Job;
    }
}

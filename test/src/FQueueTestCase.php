<?php
namespace FQueue;

abstract class FQueueTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Monolog\Handler\TestHandler
     */
    protected $Handler;

    protected function getLogger()
    {
        $Logger = new \Monolog\Logger('test');
        $this->Handler = new \Monolog\Handler\TestHandler();
        $Logger->pushHandler($this->Handler);
        return $Logger;
    }
}

<?php
namespace FQueue;

abstract class FQueueTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Monolog\Logger
     */
    protected $Logger;

    /**
     * @var \Monolog\Handler\TestHandler
     */
    protected $Handler;

    protected function getLogger()
    {
        if (!$this->Logger) {
            $this->Logger = new \Monolog\Logger('test');
        }
        if (!$this->Handler) {
            $this->Handler = new \Monolog\Handler\TestHandler();
            $this->Logger->pushHandler($this->Handler);
        }
        return $this->Logger;
    }
}

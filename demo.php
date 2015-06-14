<?php
require 'vendor/autoload.php';

class Job implements FQueue\JobInterface
{
    public function __construct($container) {}
    public function init(array $params) {return true;}
    public function run(\Psr\Log\LoggerInterface $Logger) {
        $Logger->info('Job success');
        return FQueue\JobRow::STATUS_SUCCESS;
    }
    public function getMaxExecutionTime() {return 5;}
    public static function getRetries() {return 1;}
    public static function getRetryTimeout() {return 1;}
}

class Storage implements FQueue\StorageInterface
{
    public function __construct()
    {
        $this->jobs = array(
            new FQueue\JobRow('Job', array(), (int)rand(1,999)),
            new FQueue\JobRow('Job', array(), (int)rand(1,999)),
        );
    }

    public function enqueue($queue, $class, array $init_params = array()) {}
    public function getJobs($queue, array $exclude_ids, $limit){
        $result = array_pop($this->jobs);
        return $result ? array($result) : array();
    }
    function cleanup($last_unixtime){return 0;}
    function markInProgress($queue, FQueue\JobRow $JobRow){}
    function markSuccess($queue, FQueue\JobRow $JobRow){}
    function markFailTemporary($queue, FQueue\JobRow $JobRow){}
    function markFailPermanent($queue, FQueue\JobRow $JobRow){}
    function markFailInit($queue, FQueue\JobRow $JobRow){}
    function markReturnInvalid($queue, FQueue\JobRow $JobRow){}
    function markError($queue, FQueue\JobRow $JobRow){}
    function markTimeoutIfInProgress($queue, FQueue\JobRow $JobRow){}
    function markErrorIfInProgress($queue, FQueue\JobRow $JobRow){}
    function beforeFork(){}
}

$LoggerManager = new Monolog\Logger('manager');
$Storage = new Storage;

$Manager = new FQueue\Manager($LoggerManager, $Storage, null, 'demo_forks_stage.serialized');

$LoggerQueue1 = new Monolog\Logger('queue1');
$Manager->addQueue('queue1', 1, 1, 10, $LoggerQueue1);

$LoggerQueue2 = new Monolog\Logger('queue2');
$Manager->addQueue('queue2', 1, 1, 10, $LoggerQueue2);

$Manager->start();

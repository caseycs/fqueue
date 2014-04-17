<?php
require 'vendor/autoload.php';

class Job implements FQueue\JobInterface
{
    public function __construct($container) {}
    public function init(array $args) {return true;}
    public function run(\Psr\Log\LoggerInterface $Logger) {
        $Logger->info('Job success!');
        return FQueue\JobRow::RESULT_SUCCESS;
    }
    public function getMaxExecutionTime() {
        return 1;
    }
}

class Storage implements FQueue\StorageInterface
{
    public function getJobs($queue, $limit){
        return array(new FQueue\JobRow('Job', array(), (int)rand(1,999)));
    }
    function cleanup($last_unixtime){return 0;}
    function markStarted(FQueue\JobRow $JobRow){}
    function markSuccess(FQueue\JobRow $JobRow){}
    function markFail(FQueue\JobRow $JobRow){}
    function markError(FQueue\JobRow $JobRow){}
    function markTimeoutIfInProgress(array $ids){}
    function onForkInit(){}
}

$LoggerManager = new Monolog\Logger('manager');
$Storage = new Storage;

$Manager = new FQueue\Manager($LoggerManager, $Storage, null, 'demo_forks_stage.json');

$LoggerQueue1 = new Monolog\Logger('queue1');
$Manager->addQueue('queue1', 1, 1, 10, $LoggerQueue1);

$LoggerQueue2 = new Monolog\Logger('queue2');
$Manager->addQueue('queue2', 1, 1, 10, $LoggerQueue2);

$LoggerQueue2 = new Monolog\Logger('queue3');
$Manager->addQueue('queue3', 1, 1, 10, $LoggerQueue2);

$Manager->start();

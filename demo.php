<?php
require 'vendor/autoload.php';

class Job implements FQueue\JobInterface
{
    public function __construct($container) {}
    public function init(array $params) {return true;}
    public function run(\Psr\Log\LoggerInterface $Logger) {
        $Logger->info('Job success!');
        return FQueue\JobRow::STATUS_SUCCESS;
    }
    public function getMaxExecutionTime() {return 1;}
    public static function getRetries() {return 1;}
}

class Storage implements FQueue\StorageInterface
{
    public function getJobs($queue, array $exclude_ids, $limit){
        return array(new FQueue\JobRow('Job', array(), (int)rand(1,999)));
    }
    function cleanup($last_unixtime){return 0;}
    function markInProgress(FQueue\JobRow $JobRow){}
    function markSuccess(FQueue\JobRow $JobRow){}
    function markFailTemporary(FQueue\JobRow $JobRow){}
    function markFailPermanent(FQueue\JobRow $JobRow){}
    function markError(FQueue\JobRow $JobRow){}
    function markTimeoutIfInProgress(array $ids){}
    function beforeFork(){}
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

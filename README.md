# FQueue

Forking queue on PHP

## Usage example:

```php
$LoggerManager = new Monolog\Logger('manager');
$Storage = new Storage;

$Manager = new FQueue\Manager($LoggerManager, $Storage);

$LoggerQueue1 = new Monolog\Logger('queue1');
$Manager->addQueue('queue1', 1, 1, 10, $LoggerQueue1);

$LoggerQueue2 = new Monolog\Logger('queue2');
$Manager->addQueue('queue2', 1, 1, 10, $LoggerQueue2);

$Manager->start();
```

See full source code in [demo.php](demo.php)

Output will be:

```
[2014-03-22 02:31:30] manager.INFO: cleanup done, jobs deleted:  [] []
[2014-03-22 02:31:30] manager.DEBUG: cycle start [] []
[2014-03-22 02:31:30] manager.DEBUG: in-memory queue jobs:  [] []
[2014-03-22 02:31:30] manager.DEBUG: queue1: fetched jobs: 1 total in-memory jobs: 1 [] []
[2014-03-22 02:31:30] manager.INFO: making new fork {"queue":"queue1","pid":41544,"jobs":1,"max_execution_time":1} []
[2014-03-22 02:31:30] manager.DEBUG: queue2: fetched jobs: 1 total in-memory jobs: 1 [] []
[2014-03-22 02:31:30] manager.INFO: making new fork {"queue":"queue2","pid":41545,"jobs":1,"max_execution_time":1} []
[2014-03-22 02:31:30] queue1.INFO: FORK {"queue":"queue1","pid":41544,"jobs":1,"max_execution_time":1} []
[2014-03-22 02:31:30] queue1.INFO: Job success! [] []
[2014-03-22 02:31:30] queue2.INFO: FORK {"queue":"queue2","pid":41545,"jobs":1,"max_execution_time":1} []
[2014-03-22 02:31:30] queue2.INFO: Job success! [] []
...
```

Master process made 2 forks - with 1 job for every queue, every queue finished their job,
and then all start from the beginnig.

## TODO:
 * fork tests
 * configurable mysql storage from the box
 * configurable mongo storage from the box
 * CI
 * mark unfinished jobs as timeouted when kill fork by timeout
 * worflow with retries
 * correct sigterm flow - wait for all forks done

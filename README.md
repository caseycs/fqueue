# FQueue

[![Build Status](https://travis-ci.org/caseycs/fqueue.svg?branch=master)](https://travis-ci.org/caseycs/fqueue)

Forking queue processor on PHP.

Let's look in typical web project - for example e-commerce online shop. There are a lot of reasons,
why you need queues: sending emails, sync orders with CRM&ERP, recalculate prices on markup rules change,
add new positions on suppluer update, and so on.

Some tasks are fast (send email), some tasks are slow (import price with few thounds positions).
Some tasks can be processed in parallel (send order to CRM) and some - only one task evey time (recalculate prices).
Some tasks require retry in fail (send email), and some not.

You need a system to run all this kinds of jobs, make it configurable, make it simple. You got it!

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

## Concepts


## TODO

Important

 * fork tests
 * workflow with retries
 * correct sigterm workflow - wait for all forks done
 * configurable mysql storage from the box
 * mark unfinished jobs as timeouted when kill fork by timeout

Maybe sometimes

 * configurable mongo storage from the box

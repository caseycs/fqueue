# FQueue

[![Build Status](https://travis-ci.org/caseycs/fqueue.svg?branch=master)](https://travis-ci.org/caseycs/fqueue)

Forking queue processor on PHP.

Let's look in typical web project - for example e-commerce online shop. There are a lot of reasons,
why you need queues: sending emails, sync orders with CRM&ERP, recalculate prices on markup rules change,
add new positions on supplier update, and so on.

If you use Amazon infrastructure, then best practice is using native service - like SQS. If you are large enough
to use RabbitMQ, ActiveMQ, or another special queue server - ok, that's great! But for small project you can avoid
using them and store your jobs in native project DB - like mysql - and use FQueue to manage them.

Some tasks are fast (send email), some tasks are slow (import price with few thounds positions).
Some tasks can be processed in parallel (send order to CRM) and some - only one task evey time (recalculate prices).
Some tasks require retry in fail (send email), and some not.

You need a system to run all this kinds of jobs, make it configurable, make it simple. You got it!

## Usage example

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
[2014-04-13 02:01:03] manager.INFO: storage cleanup done, jobs deleted: 0 [] []
[2014-04-13 02:01:03] manager.DEBUG: cycle start 0 [] []
[2014-04-13 02:01:03] manager.DEBUG: in-memory queue jobs: queue1: 0 queue2: 0 queue3: 0  [] []
[2014-04-13 02:01:03] manager.DEBUG: queue1: fetched jobs: 1, total in-memory jobs: 1 [] []
[2014-04-13 02:01:03] manager.INFO: making new fork {"queue":"queue1","pid":43136,"jobs":1,"max_execution_time":3} []
[2014-04-13 02:01:03] manager.DEBUG: queue2: fetched jobs: 1, total in-memory jobs: 1 [] []
[2014-04-13 02:01:03] manager.INFO: making new fork {"queue":"queue2","pid":43137,"jobs":1,"max_execution_time":3} []
[2014-04-13 02:01:03] queue1.INFO: for init {"queue":"queue1","pid":43136,"jobs":1,"max_execution_time":3} []
[2014-04-13 02:01:03] queue1.INFO: Job success! [] []
[2014-04-13 02:01:03] queue1.INFO: success {"class":"Job","id":646} []
[2014-04-13 02:01:03] queue2.INFO: for init {"queue":"queue2","pid":43137,"jobs":1,"max_execution_time":3} []
[2014-04-13 02:01:03] queue2.INFO: Job success! [] []
[2014-04-13 02:01:03] queue2.INFO: success {"class":"Job","id":446} []
[2014-04-13 02:01:03] manager.DEBUG: queue3: fetched jobs: 1, total in-memory jobs: 1 [] []
[2014-04-13 02:01:03] manager.INFO: making new fork {"queue":"queue3","pid":43138,"jobs":1,"max_execution_time":3} []
[2014-04-13 02:01:03] queue3.INFO: for init {"queue":"queue3","pid":43138,"jobs":1,"max_execution_time":3} []
[2014-04-13 02:01:03] queue3.INFO: Job success! [] []
[2014-04-13 02:01:03] queue3.INFO: success {"class":"Job","id":543} []
...
```

Master process made 3 forks - with 1 job for every queue, every queue finished their job,
and then all start from the beginnig.

## Concepts

 * Queue - this is how you split your tasks. The simples example - you can split your jobs between fast and slow queues, to avoid waiting slow task to be done to process the fast one. Each queue has few attributes: parallel process number, tasks per fork and im-memory jobs max size in manager process.
 * Job - every job is a class, which implements `FQueue\JobInterface`
 * Storage - this is a connection between jobs process manager and your storage - it can be mysql, mongo, redis or something else. A class, which implements `FQueue\StorageInterface`
 * Manager - core of the system - `FQueue\Manager` class. You initialize it, execute and enjoy. It runs inifinite loop by himself, but I recommend to use [runit](http://smarden.org/runit/) or [supervisord](http://supervisord.org/) to keep it running for fail-safe.

## TODO

Important

 * configurable mysql storage from the box
 * task insertion logic

Maybe sometime

 * configurable mongo storage from the box

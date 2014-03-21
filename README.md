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

## TODO:
 * fork tests
 * configurable mysql storage from the box
 * configurable mongo storage from the box
 * + demo
 * CI
 * mark unfinished jobs as timeouted when kill fork by timeout
 * worflow with retries
 * correct sigterm flow - wait for all forks done

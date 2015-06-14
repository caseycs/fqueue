<?php
$pid = pcntl_fork();
if ($pid === -1) {
    echo "could not fork" . PHP_EOL;
} elseif ($pid) {
    echo "parent" . PHP_EOL;
    echo getmypid() . PHP_EOL;
} else {
    // we are fork
        echo getmypid() . PHP_EOL;
    echo "fork" . PHP_EOL;
}

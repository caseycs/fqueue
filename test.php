<?php
//declare(ticks = 1);
//function sig_handler($signo) {
//    if($signo == SIGUSR1) {
//        // Handle SIGUSR1
//        var_dump('SIGUSR1');
//    } elseif($signo == SIGCHLD) {
//        // Handle SIGTERM
//        $sigterm = true;
//        var_dump('SIGCHLD');
//    } elseif($signo == SIGTERM) {
//        // Handle SIGTERM
//        $sigterm = true;
//        var_dump('SIGTERM');
//    } elseif($signo == SIGHUP) {
//        // Handle SIGHUP
//        $sighup = true;
//        var_dump('SIGHUP');
//    }
//}
//pcntl_signal(SIGUSR1, "sig_handler");
//pcntl_signal(SIGTERM, "sig_handler");
//pcntl_signal(SIGHUP, "sig_handler");
//pcntl_signal(SIGCHLD, "sig_handler");

$queues = array(
    'price_markup' => array(
        'forks' => 1,
        'tasks_per_fork' => 1,
    ),
    'letter' => array(
        'forks' => 2,
        'tasks_per_fork' => 10,
    ),
    'order_export' => array(
        'forks' => 2,
        'tasks_per_fork' => 10,
    ),
    'price_update' => array(
        'forks' => 1,
        'tasks_per_fork' => 1,
    ),
    'price_export' => array(
        'forks' => 1,
        'tasks_per_fork' => 1,
    ),
);

//1. форк на N задач, но не более N форков на очередь
//2. раз в N секунд лезть за новыми задачами
//2. плавный перезапуск, дожидаясь окончания всех форков

$jobs_queue = array();
$forks_queue_pids = array();

$forks_check_interval = 5;
$new_tasks_check_interval = 5;
$mark_job_error_by_timeout_interval = 5;

while (true) {
    $tmp = array_keys($queues);
    $queue_key = array_rand($tmp);
    $queue = $tmp[$queue_key];

    $job_params = (int)rand(1,10000);

    //new job in queue
    $jobs_queue[$queue][] = $job_params;

//var_dump($jobs_queue);die;

//    echo "forks: ";
//    print_r($forks_queue_pids);

    //stats
    $stats = '';
    foreach ($jobs_queue as $queue => &$items) {
        $stats .= "$queue:" . count($items) . ' ';
    }
    echo "$stats\n";

    //start new forks
    foreach ($jobs_queue as $queue => &$items) {
        if ($items === array()) continue;
//        echo "queue: ";
//        print_r($jobs_queue);

        //check if fork is avaliable
        if (isset($forks_queue_pids[$queue]) && count($forks_queue_pids[$queue]) >= $queues[$queue]['forks']) {
//            echo "forks limit reached, waiting\n";
            continue;
        }

        //cut queue part
        $jobs2fork = array_splice($items, 0, $queues[$queue]['tasks_per_fork']);

        //make new fork
        echo "fork $queue " . count($jobs2fork) . "\n";
        $pid = pcntl_fork();

        if ($pid === -1) {
            die('could not fork');
        } elseif ($pid) {
            // we are the parent
            $forks_queue_pids[$queue][] = $pid;
        } else {
//            echo "i'm child!\n";

            $sid = posix_setsid();
//            var_dump($sid);

            if ($sid < 0) {
                echo "posix_setsid failed\n";
                exit(0);
            }

//            print_r($jobs2fork);

            sleep(2);
//            echo "child done!\n";
            exit(0);
        }
    }

    //defunc zombie processes - feel free!
    pcntl_wait($status, WNOHANG OR WUNTRACED);

    //remove dead forks
    foreach ($forks_queue_pids as $queue => &$pids) {
        foreach ($pids as $index => $pid) {
            if (!posix_kill($pid, 0)) unset($pids[$index]);
        }
        //reindex array
        $pids = array_values($pids);
    }

    usleep(100000);
}

<?php
namespace FQueue;

// see CREATE TABLE in storage_single_table.sql
class StorageMysqlSingleTable implements StorageInterface
{
    private $host, $port, $user, $pass, $database_table;

    /**
     * @var \PDO
     */
    private $pdo;

    public function __construct($database, $table)
    {
        $this->database_table = ($database ? $database . '.' : '') . $table;
    }

    public function setPDO(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function setConnectionParams($host, $port, $user, $pass)
    {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
    }

    public function enqueue($queue, $class, array $init_params)
    {
        $this->init();

        $sth = $this->pdo->prepare("INSERT INTO {$this->database_table}
            (queue, class, status, params, create_time, retries)
            VALUES (?, ?, ?, ?, ?, 0)");
        $params = array(
            $queue,
            $class,
            JobRow::STATUS_NEW,
            json_encode($init_params),
            date('Y-m-d H:i:s')
        );
        $sth->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function getJobs($queue, $limit)
    {
        $this->init();

        $queue = $this->pdo->quote($queue);

        $statuses = $this->pdo->quote(JobRow::STATUS_FAIL_TEMPORARY)
            . ',' . $this->pdo->quote(JobRow::STATUS_NEW);

        $sth = $this->pdo->query("SELECT * FROM {$this->database_table}
            WHERE queue = {$queue} AND status  IN($statuses)
            ORDER BY create_time ASC
            LIMIT {$limit}");

        $result = array();

        while ($row = $sth->fetch(\PDO::FETCH_ASSOC)) {
            $JobRow = new JobRow;
            $JobRow->setId((int)$row['id']);
            $JobRow->setClass($row['class']);
            $JobRow->setParams(json_decode($row['params'], true));
            $JobRow->setRetries((int)$row['retries']);
            $result[] = $JobRow;
        }

        return $result;
    }

    public function cleanup($last_unixtime)
    {
        $this->init();

        $finish_time = strtotime('Y-m-d H:i:s', $last_unixtime);
        $statuses = $this->pdo->quote(JobRow::STATUS_FAIL_PERMANENT)
            . ',' . $this->pdo->quote(JobRow::STATUS_ERROR);

        $sth = $this->pdo->prepare("DELETE FROM {$this->database_table}
            WHERE finish_time < ? AND (status IN {$statuses}");
        $count = $sth->execute(array($statuses, $finish_time));
        return $count;
    }

    public function beforeFork()
    {
        $this->pdo = null;
    }

    public function markInProgress(JobRow $JobRow)
    {
        $this->init();

        $sth = $this->pdo->prepare("UPDATE {$this->database_table}
            SET start_time = NOW(), status = ?, retries = retries + 1
            WHERE id = ?");
        $sth->execute(array(JobRow::STATUS_IN_PROGRESS, $JobRow->getId()));
    }

    public function markSuccess(JobRow $JobRow)
    {
        $this->init();

        $sth = $this->pdo->prepare("UPDATE {$this->database_table}
            SET finish_time = NOW(), status = ? WHERE id = ?");
        $sth->execute(array(JobRow::STATUS_SUCCESS, $JobRow->getId()));
    }

    public function markFailTemporary(JobRow $JobRow)
    {
        $this->init();

        $sth = $this->pdo->prepare("UPDATE {$this->database_table}
            SET finish_time = NOW(), status = ? WHERE id = ?");
        $sth->execute(array(JobRow::STATUS_FAIL_TEMPORARY, $JobRow->getId()));
    }

    public function markFailPermanent(JobRow $JobRow)
    {
        $this->init();

        $sth = $this->pdo->prepare("UPDATE {$this->database_table}
            SET finish_time = NOW(), status = ? WHERE id = ?");
        $sth->execute(array(JobRow::STATUS_FAIL_PERMANENT, $JobRow->getId()));
    }

    public function markError(JobRow $JobRow)
    {
        $this->init();

        $sth = $this->pdo->prepare("UPDATE {$this->database_table}
            SET finish_time = NOW(), status = ? WHERE id = ?");
        $sth->execute(array(JobRow::STATUS_ERROR, $JobRow->getId()));
    }

    public function markTimeoutIfInProgress(array $job_ids)
    {
        $this->init();

        $sth = $this->pdo->prepare("UPDATE {$this->database_table}
            SET finish_time = NOW(), status = ?
            WHERE id IN(?) AND status = ?");
        $params = array(
            JobRow::STATUS_TIMEOUT,
            join(',', $job_ids),
            JobRow::STATUS_IN_PROGRESS
        );
        $count = $sth->execute($params);

        return $count;
    }

    private function init()
    {
        if ($this->pdo) return;

        $dsn = "mysql:dbname={$this->database};host={$this->host}";
        $this->pdo = new \PDO(
            $dsn,
            $this->user,
            $this->pass,
            array(\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'')
        );
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }
}

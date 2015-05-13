<?php
namespace FQueue;

// see CREATE TABLE in storage_single_table.sql
class StorageMysqlSingleTable implements StorageInterface
{
    protected $host, $port, $user, $pass, $database, $table;

    /**
     * @var \PDO
     */
    protected $pdo;

    public function __construct($database, $table)
    {
        $this->database = $database;
        $this->table = $table;
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

    public function enqueue($queue, $class, array $init_params = array())
    {
        $this->init();

        $sth = $this->pdo->prepare("INSERT INTO {$this->database}.{$this->table}
            SET
              queue = ?,
              class = ?,
              status = ?,
              params = ?,
              retries_remaining = ?,
              retry_timeout = ?,
              create_time = NOW(),
              next_retry_time = NOW()");

        $params = array(
            $queue,
            $class,
            JobRow::STATUS_NEW,
            json_encode($init_params),
            $class::getRetries(),
            $class::getRetryTimeout(),
        );
        $sth->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function getJobs($queue, array $exclude_ids, $limit)
    {
        $this->init();

        $queue = $this->pdo->quote($queue);
        if (empty($exclude_ids)) {
            $exclude_ids = '';
        } else {
            $exclude_ids = 'AND id NOT IN (' . join(',', $exclude_ids) . ')';
        }

        $sth = $this->pdo->query("SELECT * FROM {$this->database}.{$this->table}
            WHERE
              queue = {$queue}
              AND retries_remaining > 0
              AND next_retry_time <= NOW()
              {$exclude_ids}
            ORDER BY next_retry_time ASC
            LIMIT {$limit}");

        $result = array();

        while ($row = $sth->fetch(\PDO::FETCH_ASSOC)) {
            $statuses_valid = array(
                JobRow::STATUS_NEW,
                JobRow::STATUS_FAIL_TEMPORARY,
                JobRow::STATUS_TIMEOUT,
            );
            assert(in_array($row['status'], $statuses_valid, true));

            $JobRow = new JobRow;
            $JobRow->setId((int)$row['id']);
            $JobRow->setClass($row['class']);
            $JobRow->setParams(json_decode($row['params'], true));
            $result[] = $JobRow;
        }

        return $result;
    }

    public function cleanup($last_unixtime)
    {
        $this->init();

        $finish_time = date('Y-m-d H:i:s', $last_unixtime);

        $sth = $this->pdo->prepare("DELETE FROM {$this->database}.{$this->table}
            WHERE
              finish_time <= ?
              AND (retries_remaining = 0 OR status = ?)");
        $sth->execute(array($finish_time, JobRow::STATUS_SUCCESS));

        return $sth->rowCount();
    }

    public function beforeFork()
    {
        //disconnect from db
        $this->pdo = null;
    }

    public function markInProgress(JobRow $JobRow)
    {
        $this->init();

        $sth = $this->pdo->prepare("UPDATE {$this->database}.{$this->table}
            SET
              start_time = NOW(),
              status = ?,
              next_retry_time = null,
              retries_remaining = IF(retries_remaining > 0, retries_remaining - 1, 0)
            WHERE id = ?");
        $sth->execute(array(JobRow::STATUS_IN_PROGRESS, $JobRow->getId()));
    }

    public function markSuccess(JobRow $JobRow)
    {
        $this->init();

        $sth = $this->pdo->prepare("UPDATE {$this->database}.{$this->table}
            SET
              finish_time = NOW(),
              status = ?,
              next_retry_time = null
            WHERE
              id = ?
              AND status = ?");
        $sth->execute(array(JobRow::STATUS_SUCCESS, $JobRow->getId(), JobRow::STATUS_IN_PROGRESS));
        assert($sth->rowCount() === 1);
    }

    public function markFailTemporary(JobRow $JobRow)
    {
        $this->init();

        $sth = $this->pdo->prepare("UPDATE {$this->database}.{$this->table}
            SET
              finish_time = NOW(),
              status = ?,
              next_retry_time = if(retries_remaining = 0, null, NOW() + interval retry_timeout second)
            WHERE
              id = ?
              AND status = ?");
        $sth->execute(array(JobRow::STATUS_FAIL_TEMPORARY, $JobRow->getId(), JobRow::STATUS_IN_PROGRESS));
        assert($sth->rowCount() === 1);
    }

    public function markFailPermanent(JobRow $JobRow)
    {
        $this->init();

        $sth = $this->pdo->prepare("UPDATE {$this->database}.{$this->table}
            SET
              finish_time = NOW(),
              status = ?,
              retries_remaining = 0,
              next_retry_time = null
            WHERE
              id = ?
              AND status IN(?,?)");
        $sth->execute(array(JobRow::STATUS_FAIL_PERMANENT, $JobRow->getId(), JobRow::STATUS_NEW, JobRow::STATUS_IN_PROGRESS));
        assert($sth->rowCount() === 1);
    }

    public function markError(JobRow $JobRow)
    {
        $this->init();

        $sth = $this->pdo->prepare("UPDATE {$this->database}.{$this->table}
            SET
              finish_time = NOW(),
              status = ?,
              next_retry_time = null,
              retries_remaining = 0
            WHERE
              id = ?
              AND status = ?");
        $sth->execute(array(JobRow::STATUS_ERROR, $JobRow->getId(), JobRow::STATUS_IN_PROGRESS));
        assert($sth->rowCount() === 1);
    }

    public function markTimeoutIfInProgress(array $job_ids)
    {
        $this->init();

        $sth = $this->pdo->prepare("UPDATE {$this->database}.{$this->table}
            SET
              finish_time = NOW(),
              status = ?,
              next_retry_time = if(retries_remaining = 0, null, next_retry_time + interval timeout seconds)
            WHERE id IN(?) AND status = ?");
        $params = array(
            JobRow::STATUS_TIMEOUT,
            join(',', $job_ids),
            JobRow::STATUS_IN_PROGRESS
        );
        $count = $sth->execute($params);

        return $count;
    }

    public function markErrorIfInProgress(array $job_ids)
    {
        $this->init();

        $sth = $this->pdo->prepare("UPDATE {$this->database}.{$this->table}
            SET
              finish_time = NOW(),
              status = ?,
              next_retry_time = if(retries_remaining = 0, null, next_retry_time + interval timeout seconds)
            WHERE id IN(?) AND status = ?");
        $params = array(
            JobRow::STATUS_ERROR,
            join(',', $job_ids),
            JobRow::STATUS_IN_PROGRESS
        );
        $count = $sth->execute($params);

        return $count;
    }

    protected function init()
    {
        if ($this->pdo) {
            return;
        }

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

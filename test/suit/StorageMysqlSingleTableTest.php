<?php
class StorageMysqlSingleTableTest extends \PHPUnit_Framework_TestCase
{
    public function test_enqueue()
    {
        $PDO = $this->getPdo();

        $StorageMysqlSingleTable = new FQueue\StorageMysqlSingleTable('', 'test');
        $StorageMysqlSingleTable->setPDO($PDO);

        $id1 = $StorageMysqlSingleTable->enqueue('queue1', 'FQueue\TestJobSuccess', array('a' => 'b'), 1);
        $this->assertInternalType('int', $id1);
        $this->assertNotEmpty($id1);

        $row = $PDO->query('select * from test where id=' . $id1)->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame('queue1', $row['queue']);
        $this->assertSame('FQueue\TestJobSuccess', $row['class']);
        $this->assertSame(json_encode(array('a' => 'b')), $row['params']);
        $this->assertSame(\FQueue\JobRow::STATUS_NEW, $row['status']);
    }

    public function test_getJobs()
    {
        $PDO = $this->getPdo();

        $StorageMysqlSingleTable = new FQueue\StorageMysqlSingleTable('', 'test');
        $StorageMysqlSingleTable->setPDO($PDO);

        $id11 = $StorageMysqlSingleTable->enqueue('queue1', 'FQueue\TestJobSuccess', array('a' => 'b'));
        $id21 = $StorageMysqlSingleTable->enqueue('queue2', 'FQueue\TestJobSuccess', array('a' => 'b'));
        $id22 = $StorageMysqlSingleTable->enqueue('queue2', 'FQueue\TestJobSuccess', array('a' => 'b'));

        $jobs = $StorageMysqlSingleTable->getJobs('queue2', array(), 1);
        $this->assertCount(1, $jobs);
        $this->assertSame($id21, $jobs[0]->getId());

        $jobs = $StorageMysqlSingleTable->getJobs('queue2', array(), 2);
        $this->assertCount(2, $jobs);
        $this->assertSame($id21, $jobs[0]->getId());
        $this->assertSame($id22, $jobs[1]->getId());

        $jobs = $StorageMysqlSingleTable->getJobs('queue1', array(), 1);
        $this->assertCount(1, $jobs);
        $this->assertSame($id11, $jobs[0]->getId());
    }

    public function getPdo()
    {
        $PDO = new PDO('sqlite::memory:');
        $PDO->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $PDO->exec('create table test (
          id integer primary key,
          create_time datetime,
          retry_time datetime,
          start_time datetime,
          finish_time datetime,
          class varchar,
          queue varchar,
          params TEXT,
          retries_remaining int,
          status varchar
        );');
        return $PDO;
    }
}

<?php
class StorageMysqlSingleTableTest extends \PHPUnit_Framework_TestCase
{
    private $host = 'localhost';
    private $user = '';
    private $pass = '';
    private $database = 'test';
    private $table = 'fqueue';

    /**
     * @var \PDO
     */
    private $PDO;

    public function setUp()
    {
        $this->getPDO()->exec('drop table if exists fqueue');
        $this->getPDO()->exec(file_get_contents(__DIR__ . '/../../storage_single_table.sql'));
    }

    public function test_enqueue()
    {
        $StorageMysqlSingleTable = new FQueue\StorageMysqlSingleTable($this->database, $this->table);
        $StorageMysqlSingleTable->setPDO($this->getPDO());

        $id1 = $StorageMysqlSingleTable->enqueue('queue1', 'FQueue\TestJobSuccess', array('a' => 'b'), 1);
        $this->assertInternalType('int', $id1);
        $this->assertNotEmpty($id1);

        $row = $this->getPDO()->query("select * from {$this->table} where id={$id1}")->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame('queue1', $row['queue']);
        $this->assertSame('FQueue\TestJobSuccess', $row['class']);
        $this->assertSame(json_encode(array('a' => 'b')), $row['params']);
        $this->assertSame($row['create_time'], $row['next_retry_time']);
        $this->assertSame(\FQueue\JobRow::STATUS_NEW, $row['status']);
    }

    public function test_getJobs_queue()
    {
        $StorageMysqlSingleTable = new FQueue\StorageMysqlSingleTable($this->database, $this->table);
        $StorageMysqlSingleTable->setPDO($this->getPDO());

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

    public function test_getJobs_exclude()
    {
        $StorageMysqlSingleTable = new FQueue\StorageMysqlSingleTable($this->database, $this->table);
        $StorageMysqlSingleTable->setPDO($this->getPDO());

        $id21 = $StorageMysqlSingleTable->enqueue('queue2', 'FQueue\TestJobSuccess', array('a' => 'b'));
        $id22 = $StorageMysqlSingleTable->enqueue('queue2', 'FQueue\TestJobSuccess', array('a' => 'b'));

        $jobs = $StorageMysqlSingleTable->getJobs('queue2', array($id21), 2);
        $this->assertCount(1, $jobs);
        $this->assertSame($id22, $jobs[0]->getId());
    }

    public function test_markInProgress()
    {
        $StorageMysqlSingleTable = new FQueue\StorageMysqlSingleTable($this->database, $this->table);
        $StorageMysqlSingleTable->setPDO($this->getPDO());

        $StorageMysqlSingleTable->enqueue('queue2', 'FQueue\TestJobSuccess', array('a' => 'b'));
        $jobs = $StorageMysqlSingleTable->getJobs('queue2', array(), 1);

        $StorageMysqlSingleTable->markInProgress($jobs[0]);

        $this->assertCount(0, $StorageMysqlSingleTable->getJobs('queue2', array(), 1));
        $this->assertSame(0, $StorageMysqlSingleTable->cleanup(time()));
    }

    public function test_markSuccess()
    {
        $StorageMysqlSingleTable = new FQueue\StorageMysqlSingleTable($this->database, $this->table);
        $StorageMysqlSingleTable->setPDO($this->getPDO());

        $StorageMysqlSingleTable->enqueue('queue2', 'FQueue\TestJobSuccess', array('a' => 'b'));
        $jobs = $StorageMysqlSingleTable->getJobs('queue2', array(), 1);

        $StorageMysqlSingleTable->markInProgress($jobs[0]);
        $StorageMysqlSingleTable->markSuccess($jobs[0]);

        $this->assertCount(0, $StorageMysqlSingleTable->getJobs('queue2', array(), 1));
        $this->assertSame(1, $StorageMysqlSingleTable->cleanup(time()));
    }

    public function test_markFailPermanent()
    {
        $StorageMysqlSingleTable = new FQueue\StorageMysqlSingleTable($this->database, $this->table);
        $StorageMysqlSingleTable->setPDO($this->getPDO());

        $StorageMysqlSingleTable->enqueue('queue2', 'FQueue\TestJobSuccess', array('a' => 'b'));
        $jobs = $StorageMysqlSingleTable->getJobs('queue2', array(), 1);

        $StorageMysqlSingleTable->markInProgress($jobs[0]);
        $StorageMysqlSingleTable->markFailPermanent($jobs[0]);

        $this->assertCount(0, $StorageMysqlSingleTable->getJobs('queue2', array(), 1));
        $this->assertSame(1, $StorageMysqlSingleTable->cleanup(time()));
    }

    public function test_markFailTemporary()
    {
        $StorageMysqlSingleTable = new FQueue\StorageMysqlSingleTable($this->database, $this->table);
        $StorageMysqlSingleTable->setPDO($this->getPDO());

        $StorageMysqlSingleTable->enqueue('queue2', 'FQueue\TestJobFailTemporary', array('a' => 'b'));
        $jobs = $StorageMysqlSingleTable->getJobs('queue2', array(), 1);

        $StorageMysqlSingleTable->markInProgress($jobs[0]);
        $StorageMysqlSingleTable->markFailTemporary($jobs[0]);

        $this->assertCount(0, $StorageMysqlSingleTable->getJobs('queue2', array(), 1));

        sleep(\FQueue\TestJobFailTemporary::RETRY_TIMEOUT);

        $this->assertCount(1, $StorageMysqlSingleTable->getJobs('queue2', array(), 1));
        $this->assertSame(0, $StorageMysqlSingleTable->cleanup(time()));

        $StorageMysqlSingleTable->markInProgress($jobs[0]);
        $StorageMysqlSingleTable->markFailTemporary($jobs[0]);

        $this->assertSame(1, $StorageMysqlSingleTable->cleanup(time()));
    }

    public function test_markError()
    {
        $StorageMysqlSingleTable = new FQueue\StorageMysqlSingleTable($this->database, $this->table);
        $StorageMysqlSingleTable->setPDO($this->getPDO());

        $StorageMysqlSingleTable->enqueue('queue2', 'FQueue\TestJobSuccess', array('a' => 'b'));
        $jobs = $StorageMysqlSingleTable->getJobs('queue2', array(), 1);

        $StorageMysqlSingleTable->markInProgress($jobs[0]);
        $StorageMysqlSingleTable->markError($jobs[0]);

        $this->assertCount(0, $StorageMysqlSingleTable->getJobs('queue2', array(), 1));
        $this->assertSame(1, $StorageMysqlSingleTable->cleanup(time()));
    }

    private function getPDO()
    {
        if (!$this->PDO) {
            $this->PDO = new \PDO(
                "mysql:dbname={$this->database};host=" . $this->host,
                $this->user,
                $this->pass
            );
            $this->PDO->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }
        return $this->PDO;
    }
}

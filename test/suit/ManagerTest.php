<?php
class ManagerTest extends \FQueue\FQueueTestCase
{
    public function test_job_class_invalid()
    {
        $Storage = $this->getMock('FQueue\TestStorage');

        $Job = new FQueue\JobRow();
        $Job->setClass('unexsited_class');
        $Job->setParams(array());
        $Job->setId(1);

        $Storage
            ->expects($this->once())
            ->method('getJobs')
            ->with($this->equalTo('test'), $this->equalTo(10))
            ->will($this->returnValue(array($Job)));

        $Isolator = $this->getMock('Icecave\Isolator\Isolator', array('pcntl_wait', 'pcntl_fork'));
        $Isolator->expects($this->any())->method('pcntl_wait');
        $Isolator->expects($this->never())->method('pcntl_fork');

        $Manager = new FQueue\Manager($this->getLogger(), $Storage, null, $Isolator);
        $Manager->addQueue('test', 1, 1, 10, $this->getLogger());
        $Manager->cyclesLimit(1);
        $Manager->start();
    }

    public function test_start_fork_with_1_job()
    {
        $Storage = $this->getMock('FQueue\TestStorage');

        $Job = new FQueue\JobRow();
        $Job->setClass('FQueue\TestJob');
        $Job->setParams(array());
        $Job->setId(1);

        $Storage
            ->expects($this->once())
            ->method('getJobs')
            ->with($this->equalTo('test'), $this->equalTo(10))
            ->will($this->returnValue(array($Job)));

        $pid = 111;

        $Isolator = $this->getMock('Icecave\Isolator\Isolator', array('pcntl_wait', 'pcntl_fork', 'posix_kill'));
        $Isolator
            ->expects($this->any())
            ->method('pcntl_wait')
            ->with($this->anything(), WNOHANG || WUNTRACED);
        $Isolator->expects($this->once())->method('pcntl_fork')->will($this->returnValue($pid));

        $Manager = new FQueue\Manager($this->getLogger(), $Storage, null, $Isolator);
        $Manager->addQueue('test', 1, 1, 10, $this->getLogger());
        $Manager->cyclesLimit(1);
        $Manager->start();
    }

    public function test_job_timeout()
    {
        $Job = new FQueue\JobRow();
        $Job->setClass('FQueue\TestJobTimeout');
        $Job->setParams(array());
        $Job->setId(111);

        $Storage = new FQueue\TestStorage(array('test' => array($Job)));

        $Manager = new FQueue\Manager($this->getLogger(), $Storage);
        $Manager->addQueue('test', 1, 1, 10, $this->getLogger());
        $Manager->cyclesUsleep(1000000);
        $Manager->cyclesLimit(4);
        $Manager->forkMaxExecutionTimeInit(0);
        $Manager->start();

        // check that fork is dead
        foreach ($this->Handler->getRecords() as $record) {
            if (isset($record['context']['pid'])) {
                $pid = $record['context']['pid'];
                break;
            }
        }

        $this->assertTrue(isset($pid));
        $this->assertFalse(posix_kill($pid, SIG_DFL));

        $this->assertSame(1, $Storage->cnt_mark_timeout);
        $this->assertSame(array(111), $Storage->timeout_ids);
    }

    public function test_job_no_timeout()
    {
        $Storage = $this->getMock('FQueue\TestStorage');

        $Job = new FQueue\JobRow();
        $Job->setClass('FQueue\TestJobNoTimeout');
        $Job->setParams(array());
        $Job->setId(1);

        $Storage
            ->expects($this->at(1))
            ->method('getJobs')
            ->with($this->equalTo('test'), $this->equalTo(10))
            ->will($this->returnValue(array($Job)));
        $Storage
            ->expects($this->at(2))
            ->method('getJobs')
            ->with($this->equalTo('test'), $this->equalTo(10))
            ->will($this->returnValue(array()));
        $Storage
            ->expects($this->at(3))
            ->method('getJobs')
            ->with($this->equalTo('test'), $this->equalTo(10))
            ->will($this->returnValue(array()));
        $Storage
            ->expects($this->at(4))
            ->method('getJobs')
            ->with($this->equalTo('test'), $this->equalTo(10))
            ->will($this->returnValue(array()));

        $Manager = new FQueue\Manager($this->getLogger(), $Storage);
        $Manager->addQueue('test', 1, 1, 10, $this->getLogger());
        $Manager->cyclesUsleep(1000000);
        $Manager->cyclesLimit(4);
        $Manager->start();

        // check that fork is alive
        foreach ($this->Handler->getRecords() as $record) {
            if (isset($record['context']['pid'])) {
                $pid = $record['context']['pid'];
                break;
            }
        }

        $this->assertTrue(isset($pid));
        $this->assertTrue(posix_kill($pid, SIG_DFL));
    }
}

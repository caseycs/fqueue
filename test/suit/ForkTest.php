<?php
class ForkTest extends \FQueue\FQueueTestCase
{
    public function test_normal_flow()
    {
        $Storage = $this->getMock('FQueue\TestStorage');
        $Isolator = $this->getMock('Icecave\Isolator\Isolator');

        $Storage->expects($this->exactly(2))->method('markInProgress');
        $Storage->expects($this->exactly(2))->method('markSuccess');

        $JobRow = new FQueue\JobRow('FQueue\TestJobSuccess');

        $jobs = array(
            $JobRow,
            $JobRow,
        );

        $Storage = new FQueue\Fork(
            $Storage,
            $this->getLogger(),
            $Isolator,
            null,
            'test',
            $jobs,
            500
        );
        $Storage->runAndDie();
    }

    public function test_job_fail_temporary()
    {
        $Storage = $this->getMock('FQueue\TestStorage');
        $Isolator = $this->getMock('Icecave\Isolator\Isolator');

        $Storage->expects($this->exactly(1))->method('markInProgress');
        $Storage->expects($this->exactly(1))->method('markFailTemporary');

        $JobRow = new FQueue\JobRow('FQueue\TestJobFailTemporary');

        $jobs = array(
            $JobRow,
        );

        $Storage = new FQueue\Fork(
            $Storage,
            $this->getLogger(),
            $Isolator,
            null,
            'test',
            $jobs,
            500
        );
        $Storage->runAndDie();
    }

    public function test_job_fail_temporary_last()
    {
        $Storage = $this->getMock('FQueue\TestStorage');
        $Isolator = $this->getMock('Icecave\Isolator\Isolator');

        $Storage->expects($this->exactly(1))->method('markInProgress');
        $Storage->expects($this->exactly(1))->method('markFailPermanent');

        $JobRow = new FQueue\JobRow('FQueue\TestJobFailTemporaryLast');

        $jobs = array(
            $JobRow,
        );

        $Storage = new FQueue\Fork(
            $Storage,
            $this->getLogger(),
            $Isolator,
            null,
            'test',
            $jobs,
            500
        );
        $Storage->runAndDie();
    }

    public function test_job_fail_permanent()
    {
        $Storage = $this->getMock('FQueue\TestStorage');
        $Isolator = $this->getMock('Icecave\Isolator\Isolator');

        $Storage->expects($this->exactly(1))->method('markInProgress');
        $Storage->expects($this->exactly(1))->method('markFailPermanent');

        $JobRow = new FQueue\JobRow('FQueue\TestJobFailPermanent');

        $jobs = array(
            $JobRow,
        );

        $Storage = new FQueue\Fork(
            $Storage,
            $this->getLogger(),
            $Isolator,
            null,
            'test',
            $jobs,
            500
        );
        $Storage->runAndDie();
    }

    public function test_job_invalid_result()
    {
        $Storage = $this->getMock('FQueue\TestStorage');
        $Isolator = $this->getMock('Icecave\Isolator\Isolator');

        $Storage->expects($this->exactly(1))->method('markInProgress');
        $Storage->expects($this->exactly(1))->method('markError');

        $JobRow = new FQueue\JobRow('FQueue\TestJobFailInvalidResult');

        $jobs = array(
            $JobRow,
        );

        $Storage = new FQueue\Fork(
            $Storage,
            $this->getLogger(),
            $Isolator,
            null,
            'test',
            $jobs,
            500
        );
        $Storage->runAndDie();
    }
}

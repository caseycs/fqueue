<?php
class ForkTest extends \FQueue\FQueueTestCase
{
    public function test_normal_flow()
    {
        $Storage = $this->getMock('FQueue\TestStorage');
        $Isolator = $this->getMock('Icecave\Isolator\Isolator');

        $Storage->expects($this->exactly(2))->method('markStarted');
        $Storage->expects($this->exactly(2))->method('markSuccess');

        $JobRow = new FQueue\JobRow('FQueue\TestJob');

        $jobs = array(
            $JobRow,
            $JobRow,
        );

        $Storage = new FQueue\Fork(
            $Storage,
            $this->getLogger(),
            $Isolator,
            'test',
            $jobs,
            500
        );
        $Storage->runAndDie();
    }

    public function test_job_fail()
    {
        $this->markTestIncomplete();
    }

    public function test_job_invalid_result()
    {
        $this->markTestIncomplete();
    }

    public function test_time_limit()
    {
        $this->markTestIncomplete();
    }
}

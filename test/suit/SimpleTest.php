<?php
class SimpleTest extends \FQueue\FQueueTestCase
{
    /**
     * @expectedException InvalidArgumentException
     */
    public function test_no_queues()
    {
        $Storage = new FQueue\TestStorage();

        $Manager = new FQueue\Manager($this->getLogger(), $Storage);
        $Manager->start();
    }

    public function test_empty_jobs()
    {
        $Storage = $this->getMock('FQueue\TestStorage');
        $Storage
            ->expects($this->once())
            ->method('getJobs')
            ->with($this->equalTo('test'), $this->equalTo(10))
            ->will($this->returnValue(array()));

        $Manager = new FQueue\Manager($this->getLogger(), $Storage);
        $Manager->addQueue('test', 1, 1, 10, $this->getLogger());

        $Manager->cyclesLimit(1);
        $Manager->start();
    }

    public function test_2cycles()
    {
        $Storage = $this->getMock('FQueue\TestStorage');

        $Storage
            ->expects($this->exactly(2))
            ->method('getJobs')
            ->with($this->equalTo('test'), $this->equalTo(10))
            ->will($this->returnValue(array()));

        $Manager = new FQueue\Manager($this->getLogger(), $Storage);
        $Manager->addQueue('test', 1, 1, 10, $this->getLogger());
        $Manager->cyclesUsleep(0);
        $Manager->cyclesLimit(2);
        $Manager->start();
    }
}

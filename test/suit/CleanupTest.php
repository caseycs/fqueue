<?php
class CleanupTest extends \FQueue\FQueueTestCase
{
    public function test_first_cleanup()
    {
        $Storage = $this->getMock('FQueue\TestStorage');
        $Storage
            ->expects($this->once())
            ->method('cleanup')
            ->with($this->equalTo(time() - 50));

        $Storage
            ->expects($this->once())
            ->method('getJobs')
            ->with($this->equalTo('test'), $this->equalTo(10))
            ->will($this->returnValue(array()));

        $Manager = new FQueue\Manager($this->getLogger(), $Storage);
        $Manager->cleanupSeconds(50);
        $Manager->addQueue('test', 1, 1, 10, $this->getLogger());

        $Manager->cyclesLimit(1);
        $Manager->start();
    }

    public function test_cleanup_every_n_cycles()
    {
        $Storage = $this->getMock('FQueue\TestStorage');
        $Storage
            ->expects($this->exactly(5))
            ->method('cleanup');

        $Storage
            ->expects($this->exactly(10))
            ->method('getJobs')
            ->with($this->equalTo('test'), $this->equalTo(10))
            ->will($this->returnValue(array()));

        $Manager = new FQueue\Manager($this->getLogger(), $Storage);
        $Manager->cleanupEveryCycles(2);
        $Manager->cyclesUsleep(0);
        $Manager->addQueue('test', 1, 1, 10, $this->getLogger());

        $Manager->cyclesLimit(10);
        $Manager->start();
    }
}

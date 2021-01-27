<?php

namespace Oro\Bundle\EntityMergeBundle\Tests\Unit\Model\Step;

use Oro\Bundle\EntityMergeBundle\Event\FieldDataEvent;
use Oro\Bundle\EntityMergeBundle\MergeEvents;
use Oro\Bundle\EntityMergeBundle\Model\Step\MergeFieldsStep;

class MergerFieldsStepTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MergeFieldsStep
     */
    protected $step;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $eventDispatcher;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $strategy;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->strategy = $this->createMock('Oro\Bundle\EntityMergeBundle\Model\Strategy\StrategyInterface');
        $this->step = new MergeFieldsStep($this->strategy, $this->eventDispatcher);
    }

    public function testRun()
    {
        $data = $this->createEntityData();

        $fooField = $this->createFieldData();
        $barField = $this->createFieldData();

        $data->expects($this->once())
            ->method('getFields')
            ->will($this->returnValue(array($fooField, $barField)));

        $this->strategy->expects($this->exactly(2))->method('merge');

        $this->strategy->expects($this->at(0))
            ->method('merge')
            ->with($fooField);

        $this->strategy->expects($this->at(1))
            ->method('merge')
            ->with($barField);

        $this->eventDispatcher->expects($this->at(0))
            ->method('dispatch')
            ->with(new FieldDataEvent($fooField), MergeEvents::BEFORE_MERGE_FIELD);

        $this->eventDispatcher->expects($this->at(1))
            ->method('dispatch')
            ->with(new FieldDataEvent($fooField), MergeEvents::AFTER_MERGE_FIELD);

        $this->eventDispatcher->expects($this->at(2))
            ->method('dispatch')
            ->with(new FieldDataEvent($barField), MergeEvents::BEFORE_MERGE_FIELD);

        $this->eventDispatcher->expects($this->at(3))
            ->method('dispatch')
            ->with(new FieldDataEvent($barField), MergeEvents::AFTER_MERGE_FIELD);

        $this->step->run($data);
    }

    protected function createEntityData()
    {
        return $this->getMockBuilder('Oro\Bundle\EntityMergeBundle\Data\EntityData')
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function createFieldData()
    {
        return $this->getMockBuilder('Oro\Bundle\EntityMergeBundle\Data\FieldData')
            ->disableOriginalConstructor()
            ->getMock();
    }
}

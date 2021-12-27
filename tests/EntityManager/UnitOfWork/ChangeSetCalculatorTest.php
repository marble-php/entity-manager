<?php
namespace Marble\Tests\EntityManager\UnitOfWork;

use Marble\EntityManager\UnitOfWork\ChangeSetCalculator;
use Marble\EntityManager\UnitOfWork\EntityInfo;
use Marble\EntityManager\UnitOfWork\ObjectNeedle;
use Marble\Tests\EntityManager\TestImpl\Entity\AnotherTestEntity;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class ChangeSetCalculatorTest extends MockeryTestCase
{
    public function testCalculation(): void
    {
        $calculator = new ChangeSetCalculator(new ObjectNeedle());
        $info       = Mockery::mock(EntityInfo::class);
        $entity     = new AnotherTestEntity();

        $entity->setTitle("Beautiful title!");

        $info->allows('getEntity')->andReturn($entity);
        $info->allows('getLastSavedData')->andReturn([
            "id"      => $entity->getId(),
            "title"   => "Test!",
            "another" => null,
        ]);

        $changed = $calculator->findChangedProperties($info);

        $this->assertCount(1, $changed);
        $this->assertEquals("title", $changed[0]);

        $entity->setTitle("Test!");
        $entity->setAnother(new AnotherTestEntity());

        $changed = $calculator->findChangedProperties($info);

        $this->assertCount(1, $changed);
        $this->assertEquals("another", $changed[0]);

        $entity->setTitle("Something else...");

        $changed = $calculator->findChangedProperties($info);

        $this->assertCount(2, $changed);
        $this->assertEquals("title", $changed[0]);
        $this->assertEquals("another", $changed[1]);
    }
}

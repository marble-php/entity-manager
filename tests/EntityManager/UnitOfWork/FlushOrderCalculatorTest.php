<?php
namespace Marble\Tests\EntityManager\UnitOfWork;

use DateTime;
use Marble\Entity\Entity;
use Marble\EntityManager\UnitOfWork\ObjectNeedle;
use Marble\EntityManager\UnitOfWork\FlushOrderCalculator;
use Marble\EntityManager\UnitOfWork\ReferenceTreeBuilder;
use Marble\EntityManager\UnitOfWork\ReferenceTree;
use Marble\Tests\EntityManager\TestImpl\Entity\AnotherTestEntity;
use Marble\Tests\EntityManager\TestImpl\Entity\BasicTestEntity;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class FlushOrderCalculatorTest extends MockeryTestCase
{
    public function testFlushOrderCalculation(): void
    {
        $entities = [
            $t1 = new AnotherTestEntity(),
            $t2 = new AnotherTestEntity(),
            $t3 = new AnotherTestEntity(),
            $t4 = new AnotherTestEntity(),
            $t5 = new BasicTestEntity(),
            $t6 = new AnotherTestEntity(),
            $t7 = new AnotherTestEntity(),
            $t8 = new AnotherTestEntity(),
            $t9 = new AnotherTestEntity(),
        ];

        $t5->setName('John Doe');
        $t5->setDateOfBirth(new DateTime());

        $t4->setAnother($t3);
        $t3->setAnother($t8);
        $t5->addAnother($t1);
        $t5->addAnother($t3);
        $t2->setAnother($t4);
        $t1->setAnother($t6);
        $t6->setAnother($t9);

        $mapper = new ReferenceTreeBuilder(new ObjectNeedle());
        $nodes  = array_map(fn(Entity $entity): ReferenceTree => $mapper->buildTree($entity), $entities);

        $commitOrderCalculator = new FlushOrderCalculator();
        $sortedEntities        = array_values($commitOrderCalculator->calculate(...$nodes));

        $this->assertSame($t7, $sortedEntities[0]);
        $this->assertSame($t8, $sortedEntities[1]);
        $this->assertSame($t9, $sortedEntities[2]);
        $this->assertSame($t3, $sortedEntities[3]);
        $this->assertSame($t4, $sortedEntities[4]);
        $this->assertSame($t6, $sortedEntities[5]);
        $this->assertSame($t1, $sortedEntities[6]);
        $this->assertSame($t2, $sortedEntities[7]);
        $this->assertSame($t5, $sortedEntities[8]);
    }
}

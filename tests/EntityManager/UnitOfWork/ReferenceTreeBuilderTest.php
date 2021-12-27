<?php
namespace Marble\Tests\EntityManager\UnitOfWork;

use DateTime;
use Marble\Entity\Entity;
use Marble\EntityManager\UnitOfWork\ObjectNeedle;
use Marble\EntityManager\UnitOfWork\ReferenceTreeBuilder;
use Marble\Exception\LogicException;
use Marble\Tests\EntityManager\TestImpl\Entity\AnotherTestEntity;
use Marble\Tests\EntityManager\TestImpl\Entity\BasicTestEntity;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class ReferenceTreeBuilderTest extends MockeryTestCase
{
    public function testObjectTree(): void
    {
        $mapper = new ReferenceTreeBuilder(new ObjectNeedle());

        $t1 = new BasicTestEntity();
        $t1->setName('John Doe');
        $t1->setDateOfBirth(new DateTime());
        $t1->addAnother(new AnotherTestEntity());
        $t1->addAnother($t2 = new AnotherTestEntity());
        $t2->setAnother($t3 = new AnotherTestEntity());

        $map = $mapper->buildTree($t1);

        $this->assertCount(2, $map->getReferences());
        $this->assertCount(0, $map->getReferences()['others.0']->getReferences());
        $this->assertCount(1, $map->getReferences()['others.1']->getReferences());
        $this->assertCount(0, $map->getReferences()['others.1']->getReferences()['another']->getReferences());
        $this->assertSame($t3, $map->getReferences()['others.1']->getReferences()['another']->getEntity());
    }

    public function testCircularObjectReference(): void
    {
        $mapper = new ReferenceTreeBuilder(new ObjectNeedle());

        $t1 = new AnotherTestEntity();
        $t1->setAnother($t2 = new AnotherTestEntity());
        $t2->setAnother($t1);

        $this->expectException(LogicException::class);
        $mapper->buildTree($t1);
    }

    public function testMapperDoesntEvaluateSameEntityTwice(): void
    {
        $needle = Mockery::mock(ObjectNeedle::class);
        $mapper = new ReferenceTreeBuilder($needle);

        $t1 = new AnotherTestEntity();
        $t1->setAnother($t2 = new AnotherTestEntity());
        $t2->setAnother($t3 = new AnotherTestEntity());

        $needle->allows('extract')->once()->with($t1)->andReturn(['id' => $t1->getId(), 'another' => $t2]);
        $needle->allows('extract')->once()->with($t1->getId())->andReturn([]);
        $needle->allows('extract')->once()->with($t2)->andReturn(['id' => $t2->getId(), 'another' => $t3]);
        $needle->allows('extract')->once()->with($t2->getId())->andReturn([]);
        $needle->allows('extract')->once()->with($t3)->andReturn(['id' => $t3->getId(), 'another' => null]);
        $needle->allows('extract')->once()->with($t3->getId())->andReturn([]);

        $m1 = $mapper->buildTree($t1);
        $m2 = $mapper->buildTree($t2);

        $this->assertSame($m2, $m1->getReferences()['another']);
    }

    public function testCascaderIgnoresGivenEntities(): void
    {
        $needle = Mockery::mock(ObjectNeedle::class);
        $mapper = new ReferenceTreeBuilder($needle);

        $t1 = new AnotherTestEntity();
        $t1->setAnother($t2 = new AnotherTestEntity());

        $needle->allows('extract')->once()->with($t1)->andReturn(['id' => $t1->getId(), 'another' => $t2]);
        $needle->allows('extract')->once()->with($t1->getId())->andReturn([]);

        $m1 = $mapper->buildTree($t1, fn (Entity $entity): bool => $entity === $t2);

        $this->assertSame($t1, $m1->getEntity());
        $this->assertCount(0, $m1->getReferences());
    }

    public function testCascaderIgnoresGivenEntitiesDeeper(): void
    {
        $needle = Mockery::mock(ObjectNeedle::class);
        $mapper = new ReferenceTreeBuilder($needle);

        $t1 = new AnotherTestEntity();
        $t1->setAnother($t2 = new AnotherTestEntity());
        $t2->setAnother($t3 = new AnotherTestEntity());

        $needle->allows('extract')->once()->with($t1)->andReturn(['id' => $t1->getId(), 'another' => $t2]);
        $needle->allows('extract')->once()->with($t1->getId())->andReturn([]);
        $needle->allows('extract')->once()->with($t2)->andReturn(['id' => $t2->getId(), 'another' => $t3]);
        $needle->allows('extract')->once()->with($t2->getId())->andReturn([]);

        $m1 = $mapper->buildTree($t1, fn (Entity $entity): bool => $entity === $t3);

        $this->assertSame($t1, $m1->getEntity());
        $this->assertCount(1, $m1->getReferences());
        $this->assertSame($t2, $m1->getReferences()['another']->getEntity());
        $this->assertCount(0, $m1->getReferences()['another']->getReferences());
    }
}

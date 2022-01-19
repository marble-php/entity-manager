<?php
namespace Marble\Tests\EntityManager;

use Marble\Entity\EntityReference;
use Marble\EntityManager\Exception\EntityNotFoundException;
use Marble\Entity\SimpleId;
use Marble\EntityManager\EntityManager;
use Marble\EntityManager\Repository\DefaultRepositoryFactory;
use Marble\EntityManager\Repository\Repository;
use Marble\EntityManager\UnitOfWork\UnitOfWork;
use Marble\Tests\EntityManager\TestImpl\Entity\AbstractTestEntity;
use Marble\Tests\EntityManager\TestImpl\Entity\BasicTestEntity;
use Marble\Tests\EntityManager\TestImpl\Entity\EntityWithSimpleId;
use Marble\Tests\EntityManager\TestImpl\Entity\ExtendedBasicTestEntity;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class EntityManagerTest extends MockeryTestCase
{
    public function testFetching(): void
    {
        $repositoryFactory = Mockery::mock(DefaultRepositoryFactory::class);
        $unitOfWork        = Mockery::mock(UnitOfWork::class);
        $entityManager     = new EntityManager($repositoryFactory, $unitOfWork);
        $repo              = Mockery::mock(Repository::class);

        $t1   = new EntityWithSimpleId(1);
        $ref1 = EntityReference::create($t1);

        $repositoryFactory->allows('getRepository')->with($entityManager, $ref1->getClassName())->twice()->andReturn($repo);
        $repo->allows('fetchOne')->with($ref1->getId())->once()->andReturn($t1);

        $t2 = $entityManager->fetch($ref1);

        $this->assertSame($t2, $t1);
        $this->assertTrue($ref1->refersTo($t1));

        $ref2 = new EntityReference(EntityWithSimpleId::class, new SimpleId(1));

        $repo->allows('fetchOne')->with($ref2->getId())->once()->andReturnNull();

        $this->expectException(EntityNotFoundException::class);
        $entityManager->fetch($ref2);
    }

    public function testPersisting(): void
    {
        $repositoryFactory = Mockery::mock(DefaultRepositoryFactory::class);
        $unitOfWork        = Mockery::mock(UnitOfWork::class);
        $entityManager     = new EntityManager($repositoryFactory, $unitOfWork);

        $t1 = new EntityWithSimpleId(1);
        $t2 = new EntityWithSimpleId(2);

        $unitOfWork->allows('register')->with($t1)->once();
        $unitOfWork->allows('register')->with($t2)->once();

        $entityManager->persist($t1, $t2);

        $unitOfWork->allows('queueRemoval')->with($t1)->once();
        $unitOfWork->allows('queueRemoval')->with($t2)->once();

        $entityManager->remove($t2, $t1);

        $unitOfWork->allows('flush')->once();

        $entityManager->flush();
    }

    public function testEntityReference(): void
    {
        $t1 = new BasicTestEntity();
        $t2 = new ExtendedBasicTestEntity();

        $ref1 = EntityReference::create($t1);
        $this->assertTrue($ref1->refersTo($t1));
        $this->assertFalse($ref1->refersTo($t2));
        $this->assertTrue($ref1->refersTo(AbstractTestEntity::class));
        $this->assertTrue($ref1->refersTo(BasicTestEntity::class));
        $this->assertFalse($ref1->refersTo(ExtendedBasicTestEntity::class));

        $ref2 = new EntityReference(AbstractTestEntity::class, $t2->getId());
        $this->assertFalse($ref2->refersTo($t1));
        $this->assertTrue($ref2->refersTo($t2));
        $this->assertTrue($ref2->refersTo(AbstractTestEntity::class));
        $this->assertFalse($ref2->refersTo(BasicTestEntity::class));
        $this->assertFalse($ref2->refersTo(ExtendedBasicTestEntity::class));
    }
}

<?php
namespace Marble\Tests\EntityManager;

use Marble\Entity\EntityReference;
use Marble\EntityManager\Exception\EntityNotFoundException;
use Marble\Entity\SimpleId;
use Marble\EntityManager\EntityManager;
use Marble\EntityManager\Repository\DefaultRepositoryFactory;
use Marble\EntityManager\Repository\Repository;
use Marble\EntityManager\UnitOfWork\UnitOfWork;
use Marble\Tests\EntityManager\TestImpl\Entity\BasicTestEntity;
use Marble\Tests\EntityManager\TestImpl\Entity\EntityWithSimpleId;
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
        $this->assertTrue($ref1->refersTo(EntityWithSimpleId::class));
        $this->assertFalse($ref1->refersTo(BasicTestEntity::class));

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
}

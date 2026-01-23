<?php

namespace Marble\Tests\EntityManager\Repository;

use Marble\Entity\Ulid;
use Marble\EntityManager\Cache\QueryResultCache;
use Marble\EntityManager\Contract\EntityIoProvider;
use Marble\EntityManager\Contract\EntityReader;
use Marble\EntityManager\EntityManager;
use Marble\EntityManager\Read\Criteria;
use Marble\EntityManager\Read\DataCollector;
use Marble\EntityManager\Read\ReadContext;
use Marble\EntityManager\Read\ResultRow;
use Marble\EntityManager\Repository\DefaultRepository;
use Marble\EntityManager\Repository\RepositoryFactory;
use Marble\EntityManager\UnitOfWork\UnitOfWork;
use Marble\Exception\LogicException;
use Marble\Tests\EntityManager\TestImpl\Entity\AbstractTestEntity;
use Marble\Tests\EntityManager\TestImpl\Entity\AnotherTestEntity;
use Marble\Tests\EntityManager\TestImpl\Entity\BasicTestEntity;
use Marble\Tests\EntityManager\TestImpl\Repository\CustomTestRepository;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class RepositoryFactoryTest extends MockeryTestCase
{
    use RepositoryTestingTrait;

    public function testEntityRequired(): void
    {
        $ioProvider    = Mockery::mock(EntityIoProvider::class);
        $entityManager = Mockery::mock(EntityManager::class);
        $factory       = new RepositoryFactory($ioProvider);

        $this->expectException(LogicException::class);
        $factory->getRepository($entityManager, Ulid::class);
    }

    public function testRepositoryRequiresReader(): void
    {
        $ioProvider    = Mockery::mock(EntityIoProvider::class);
        $entityManager = Mockery::mock(EntityManager::class);
        $factory       = new RepositoryFactory($ioProvider);

        $ioProvider->allows('getCustomRepository')->with(AnotherTestEntity::class)->once()->andReturnNull();
        $ioProvider->allows('getReader')->with(AnotherTestEntity::class)->once()->andReturnNull();

        $this->expectException(LogicException::class);
        $factory->getRepository($entityManager, AnotherTestEntity::class);
    }

    public function testRepositoryCreatesRepos(): void
    {
        $ioProvider    = Mockery::mock(EntityIoProvider::class);
        $entityManager = Mockery::mock(EntityManager::class);
        $factory       = new RepositoryFactory($ioProvider);
        $reader1       = Mockery::mock(EntityReader::class);

        $ioProvider->allows('getReader')->with(AnotherTestEntity::class)->once()->andReturn($reader1);
        $ioProvider->allows('getCustomRepository')->with(AnotherTestEntity::class)->once()->andReturnNull();
        $reader1->allows('getEntityClassName')->atLeast()->once()->andReturn(AnotherTestEntity::class);

        $repo1 = $factory->getRepository($entityManager, AnotherTestEntity::class);

        $this->assertInstanceOf(DefaultRepository::class, $repo1);
        $this->assertEquals($reader1->getEntityClassName(), $repo1->getEntityClassName());

        // Second repo uses a reader for BasicTestEntity
        // Because of the static method, we can't just create another mock of EntityReader and expect it to
        // return a different entity class name than the first EntityReader mock we created above.
        // So we need to create an anonymous implementation and pass that into the repo constructor instead.

        $reader2 = new class implements EntityReader {
            public static function getEntityClassName(): string
            {
                return BasicTestEntity::class;
            }

            public function read(?object $query, DataCollector $dataCollector, ReadContext $context): void
            {
            }
        };

        $ioProvider->allows('getReader')->with(BasicTestEntity::class)->once()->andReturn($reader2);
        $ioProvider->allows('getCustomRepository')->with(BasicTestEntity::class)->once()->andReturnNull();

        $repo2 = $factory->getRepository($entityManager, BasicTestEntity::class);

        $this->assertInstanceOf(DefaultRepository::class, $repo2);
        $this->assertEquals($reader2->getEntityClassName(), $repo2->getEntityClassName());
        $this->assertNotSame($repo1, $repo2);
        $this->assertEquals($reader1->getEntityClassName(), $repo1->getEntityClassName());

        $repo3 = $factory->getRepository($entityManager, AnotherTestEntity::class);

        $this->assertSame($repo1, $repo3);
    }

    public function testRepositoriesCanBeForAbstractClasses(): void
    {
        $ioProvider    = Mockery::mock(EntityIoProvider::class);
        $entityManager = Mockery::mock(EntityManager::class);
        $unitOfWork    = Mockery::mock(UnitOfWork::class);
        $factory       = new RepositoryFactory($ioProvider);

        $ioProvider->allows('getReader')->once()->andReturn($reader1 = Mockery::mock(EntityReader::class));
        $ioProvider->allows('getCustomRepository')->with(AbstractTestEntity::class)->once()->andReturnNull();
        $reader1->allows('getEntityClassName')->atLeast()->once()->andReturn(AbstractTestEntity::class);

        $repo = $factory->getRepository($entityManager, AbstractTestEntity::class);

        $this->assertInstanceOf(DefaultRepository::class, $repo);

        $entityManager->allows('getQueryResultCache')->andReturn($cache = Mockery::mock(QueryResultCache::class));
        $entityManager->allows('getUnitOfWork')->andReturn($unitOfWork);
        $unitOfWork->allows('getEntityFromIdentityMap')->andReturn($t1 = new BasicTestEntity());
        $reader1->allows('read')->once()->with($query = $this->makeQuery(), $this->collect(
            new ResultRow(new Ulid(), ['name' => 'John Doe'], BasicTestEntity::class),
        ), $entityManager);
        $cache->allows('get')->once()->with($repo, $query, true)->andReturn(null);
        $cache->allows('save')->once()->with($repo, $query, true, $t1)->andReturn(null);

        $t2 = $repo->fetchOne($query);
        $this->assertSame($t1, $t2);
    }

    public function testCustomRepositoryAsClassName(): void
    {
        $ioProvider    = Mockery::mock(EntityIoProvider::class);
        $entityManager = Mockery::mock(EntityManager::class);
        $unitOfWork    = Mockery::mock(UnitOfWork::class);
        $factory       = new RepositoryFactory($ioProvider);

        $entityManager->allows('getQueryResultCache')->andReturn($cache = Mockery::mock(QueryResultCache::class));
        $entityManager->allows('getUnitOfWork')->andReturn($unitOfWork);
        $ioProvider->allows('getCustomRepository')->with(AnotherTestEntity::class)->once()->andReturn(CustomTestRepository::class);
        $ioProvider->allows('getReader')->with(AnotherTestEntity::class)->once()->andReturn($reader1 = Mockery::mock(EntityReader::class));
        $reader1->allows('getEntityClassName')->atLeast()->once()->andReturn(AnotherTestEntity::class);
        $reader1->allows('read')->once()->with(Criteria::class, $this->collect(
            new ResultRow(new Ulid(), ['title' => 'test']),
        ), $entityManager);
        $unitOfWork->allows('getEntityFromIdentityMap')->andReturn($t1 = new AnotherTestEntity());

        $repo = $factory->getRepository($entityManager, AnotherTestEntity::class);
        $this->assertInstanceOf(CustomTestRepository::class, $repo);

        $cache->allows('get')->once()->with($repo, Criteria::class, true)->andReturn(null);
        $cache->allows('save')->once()->with($repo, Criteria::class, true, $t1)->andReturn(null);

        $t2 = $repo->fetchOneByTitle("test");
        $this->assertSame($t1, $t2);
        $this->assertNull($t1->getTitle());
    }

    public function testCustomRepositoryAsInstance(): void
    {
        $ioProvider    = Mockery::mock(EntityIoProvider::class);
        $entityManager = Mockery::mock(EntityManager::class);
        $factory       = new RepositoryFactory($ioProvider);

        $ioProvider->allows('getCustomRepository')->with(AnotherTestEntity::class)->once()->andReturn($repo = Mockery::mock(CustomTestRepository::class));
        $repo->allows('getEntityClassName')->atLeast()->once()->andReturn(AnotherTestEntity::class);

        $repo2 = $factory->getRepository($entityManager, AnotherTestEntity::class);
        $this->assertSame($repo, $repo2);
    }
}

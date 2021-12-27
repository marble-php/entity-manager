<?php
namespace Marble\Tests\EntityManager\Repository;

use Marble\Entity\Ulid;
use Marble\EntityManager\Contract\EntityIoProvider;
use Marble\EntityManager\Contract\EntityReader;
use Marble\EntityManager\EntityManager;
use Marble\EntityManager\Read\Criteria;
use Marble\EntityManager\Read\ResultRow;
use Marble\EntityManager\Repository\DefaultRepository;
use Marble\EntityManager\Repository\DefaultRepositoryFactory;
use Marble\EntityManager\UnitOfWork\UnitOfWork;
use Marble\Exception\LogicException;
use Marble\Tests\EntityManager\TestImpl\Entity\AbstractTestEntity;
use Marble\Tests\EntityManager\TestImpl\Entity\AnotherTestEntity;
use Marble\Tests\EntityManager\TestImpl\Entity\BasicTestEntity;
use Marble\Tests\EntityManager\TestImpl\Repository\CustomTestRepository;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class DefaultRepositoryFactoryTest extends MockeryTestCase
{
    use RepositoryTestingTrait;

    public function testEntityRequired(): void
    {
        $ioProvider    = Mockery::mock(EntityIoProvider::class);
        $entityManager = Mockery::mock(EntityManager::class);
        $factory       = new DefaultRepositoryFactory($ioProvider);

        $this->expectException(LogicException::class);
        $factory->getRepository($entityManager, Ulid::class);
    }

    public function testRepositoryRequiresReader(): void
    {
        $ioProvider    = Mockery::mock(EntityIoProvider::class);
        $entityManager = Mockery::mock(EntityManager::class);
        $factory       = new DefaultRepositoryFactory($ioProvider);

        $ioProvider->allows('getReader')->with(AnotherTestEntity::class)->once()->andReturnNull();

        $this->expectException(LogicException::class);
        $factory->getRepository($entityManager, AnotherTestEntity::class);
    }

    public function testRepositoryCreatesDefaultRepos(): void
    {
        $ioProvider    = Mockery::mock(EntityIoProvider::class);
        $entityManager = Mockery::mock(EntityManager::class);
        $factory       = new DefaultRepositoryFactory($ioProvider);
        $reader1       = Mockery::mock(EntityReader::class);

        $ioProvider->allows('getCustomRepositoryClass')->with(AnotherTestEntity::class)->once()->andReturnNull();
        $ioProvider->allows('getReader')->with(AnotherTestEntity::class)->once()->andReturn($reader1);
        $reader1->allows('getEntityClassName')->atLeast()->once()->andReturn(AnotherTestEntity::class);

        $repo1 = $factory->getRepository($entityManager, AnotherTestEntity::class);

        $this->assertInstanceOf(DefaultRepository::class, $repo1);
        $this->assertEquals($reader1->getEntityClassName(), $repo1->getEntityClassName());

        $reader2 = Mockery::mock(EntityReader::class);

        $ioProvider->allows('getCustomRepositoryClass')->with(BasicTestEntity::class)->once()->andReturnNull();
        $ioProvider->allows('getReader')->with(BasicTestEntity::class)->once()->andReturn($reader2);
        $reader2->allows('getEntityClassName')->atLeast()->once()->andReturn(BasicTestEntity::class);

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
        $factory       = new DefaultRepositoryFactory($ioProvider);

        $ioProvider->allows('getCustomRepositoryClass')->once()->andReturnNull();
        $ioProvider->allows('getReader')->once()->andReturn($reader1 = Mockery::mock(EntityReader::class));
        $reader1->allows('getEntityClassName')->atLeast()->once()->andReturn(AbstractTestEntity::class);

        $repo = $factory->getRepository($entityManager, AbstractTestEntity::class);

        $this->assertInstanceOf(DefaultRepository::class, $repo);

        $entityManager->allows('getUnitOfWork')->andReturn($unitOfWork);
        $unitOfWork->allows('getEntityFromIdentityMap')->andReturn($t1 = new BasicTestEntity());
        $reader1->allows('read')->once()->with($query = $this->makeQuery(), $this->collect(
            new ResultRow(new Ulid(), ['name' => 'John Doe'], BasicTestEntity::class),
        ), $entityManager);

        $t2 = $repo->fetchOne($query);
        $this->assertSame($t1, $t2);
    }

    public function testCustomRepository(): void
    {
        $ioProvider    = Mockery::mock(EntityIoProvider::class);
        $entityManager = Mockery::mock(EntityManager::class);
        $unitOfWork    = Mockery::mock(UnitOfWork::class);
        $factory       = new DefaultRepositoryFactory($ioProvider);

        $entityManager->allows('getUnitOfWork')->andReturn($unitOfWork);
        $ioProvider->allows('getCustomRepositoryClass')->with(AnotherTestEntity::class)->once()->andReturn(CustomTestRepository::class);
        $ioProvider->allows('getReader')->with(AnotherTestEntity::class)->once()->andReturn($reader1 = Mockery::mock(EntityReader::class));
        $reader1->allows('getEntityClassName')->atLeast()->once()->andReturn(AnotherTestEntity::class);
        $reader1->allows('read')->once()->with(Criteria::class, $this->collect(
            new ResultRow(new Ulid(), ['title' => 'test']),
        ), $entityManager);
        $unitOfWork->allows('getEntityFromIdentityMap')->andReturn($t1 = new AnotherTestEntity());

        $repo = $factory->getRepository($entityManager, AnotherTestEntity::class);

        $this->assertInstanceOf(CustomTestRepository::class, $repo);
        $t2 = $repo->fetchOneByTitle("test");
        $this->assertSame($t1, $t2);
        $this->assertNull($t1->getTitle());
    }
}

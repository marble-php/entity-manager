<?php
declare(strict_types=1);

namespace Marble\Tests\EntityManager;

use Marble\Entity\SimpleId;
use Marble\EntityManager\Contract\EntityIoProvider;
use Marble\EntityManager\Contract\EntityReader;
use Marble\EntityManager\EntityManager;
use Marble\EntityManager\Read\DataCollector;
use Marble\Exception\LogicException;
use Marble\Tests\EntityManager\TestImpl\Entity\AbstractTestEntity;
use Marble\Tests\EntityManager\TestImpl\Entity\AnotherTestEntity;
use Marble\Tests\EntityManager\TestImpl\Entity\BasicTestEntity;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class AbstractEntitiesIntegrationTest extends MockeryTestCase
{
    public function testFetchConcreteFromAbstractRepository(): void
    {
        $entityManager = new EntityManager(
            $ioProvider = Mockery::mock(EntityIoProvider::class),
        );

        $ioProvider->shouldReceive('getCustomRepository')->andReturn(null);

        $ioProvider->shouldReceive('getReader')
            ->with(AbstractTestEntity::class)
            ->andReturn($reader = Mockery::mock(EntityReader::class));

        $reader->shouldReceive('getEntityClassName')->andReturn(AbstractTestEntity::class);

        $id = new SimpleId('123');
        $reader->shouldReceive('read')
            ->once()
            ->withArgs(function ($query, $dataCollector) use ($id): bool {
                if ($query !== $id || !$dataCollector instanceof DataCollector) {
                    return false;
                }
                $dataCollector->put($id, ['name' => 'Test Name'], BasicTestEntity::class);
                return true;
            });

        $repository = $entityManager->getRepository(AbstractTestEntity::class);
        $entity     = $repository->fetchOne($id);

        $this->assertInstanceOf(BasicTestEntity::class, $entity);
        $this->assertEquals('Test Name', $entity->getName());
        $this->assertEquals($id, $entity->getId());

        // Verify it's in the identity map for both abstract and concrete classes
        $this->assertSame($entity, $entityManager->getUnitOfWork()->getEntityFromIdentityMap(AbstractTestEntity::class, $id));
        $this->assertSame($entity, $entityManager->getUnitOfWork()->getEntityFromIdentityMap(BasicTestEntity::class, $id));
    }

    public function testIdentityMapAcrossAbstractAndConcreteRepositories(): void
    {
        $entityManager = new EntityManager(
            $ioProvider = Mockery::mock(EntityIoProvider::class),
        );

        $ioProvider->shouldReceive('getCustomRepository')->andReturn(null);

        // Reader for Abstract
        $ioProvider->shouldReceive('getReader')
            ->with(AbstractTestEntity::class)
            ->andReturn($abstractReader = Mockery::mock(EntityReader::class));
        $abstractReader->shouldReceive('getEntityClassName')->andReturn(AbstractTestEntity::class);

        // Reader for Concrete
        $ioProvider->shouldReceive('getReader')
            ->with(BasicTestEntity::class)
            ->andReturn($concreteReader = Mockery::mock(EntityReader::class));
        $concreteReader->shouldReceive('getEntityClassName')->andReturn(BasicTestEntity::class);

        $id = new SimpleId('123');

        // Scenario 1: Fetch via Abstract first
        $abstractReader->shouldReceive('read')
            ->once()
            ->withArgs(function ($query, $dataCollector) use ($id): bool {
                $dataCollector->put($id, ['name' => 'Test Name'], BasicTestEntity::class);
                return true;
            });

        $entity1 = $entityManager->getRepository(AbstractTestEntity::class)->fetchOne($id);
        $entity2 = $entityManager->getRepository(BasicTestEntity::class)->fetchOne($id);

        $this->assertSame($entity1, $entity2);

        // Clear EM and swap order
        $entityManager->clear();

        // Scenario 2: Fetch via Concrete first
        $concreteReader->shouldReceive('read')
            ->once()
            ->withArgs(function ($query, $dataCollector) use ($id): bool {
                $dataCollector->put($id, ['name' => 'Test Name'], BasicTestEntity::class);
                return true;
            });

        $entity3 = $entityManager->getRepository(BasicTestEntity::class)->fetchOne($id);
        $entity4 = $entityManager->getRepository(AbstractTestEntity::class)->fetchOne($id);

        $this->assertSame($entity3, $entity4);
    }

    public function testPersistConcreteFetchAbstract(): void
    {
        $entityManager = new EntityManager(
            $ioProvider = Mockery::mock(EntityIoProvider::class),
        );

        $ioProvider->shouldReceive('getCustomRepository')->andReturn(null);

        $ioProvider->shouldReceive('getReader')
            ->with(AbstractTestEntity::class)
            ->andReturn($reader = Mockery::mock(EntityReader::class));
        $reader->shouldReceive('getEntityClassName')->andReturn(AbstractTestEntity::class);

        $entity = new BasicTestEntity();
        $id     = $entity->getId();

        $entityManager->persist($entity);

        $fetched = $entityManager->getRepository(AbstractTestEntity::class)->fetchOne($id);

        $this->assertSame($entity, $fetched);
    }

    public function testAbstractRepositoryThrowsIfNoReader(): void
    {
        $entityManager = new EntityManager(
            $ioProvider = Mockery::mock(EntityIoProvider::class),
        );

        $ioProvider->shouldReceive('getCustomRepository')->andReturn(null);

        $ioProvider->shouldReceive('getReader')
            ->with(AbstractTestEntity::class)
            ->andReturn(null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Abstract entity classes require an entity reader just like concrete entity classes.');

        $entityManager->getRepository(AbstractTestEntity::class);
    }

    public function testFetchThrowsIfChildClassInvalid(): void
    {
        $entityManager = new EntityManager(
            $ioProvider = Mockery::mock(EntityIoProvider::class),
        );

        $ioProvider->shouldReceive('getCustomRepository')->andReturn(null);

        $ioProvider->shouldReceive('getReader')
            ->with(AbstractTestEntity::class)
            ->andReturn($reader = Mockery::mock(EntityReader::class));
        $reader->shouldReceive('getEntityClassName')->andReturn(AbstractTestEntity::class);

        $id = new SimpleId('123');

        $reader->shouldReceive('read')
            ->once()
            ->andReturnUsing(function ($query, DataCollector $dataCollector) use ($id): void {
                // AnotherTestEntity is NOT a subclass of AbstractTestEntity
                // We use putClass directly to trigger the exception in ResultSetBuilder
                $dataCollector->putClass($id, AnotherTestEntity::class);
            });

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Concrete class Marble\Tests\EntityManager\TestImpl\Entity\AnotherTestEntity specified '
            . 'for identifier 123 is not a subclass of Marble\Tests\EntityManager\TestImpl\Entity\AbstractTestEntity.');

        $entityManager->getRepository(AbstractTestEntity::class)->fetchOne($id);
    }
}

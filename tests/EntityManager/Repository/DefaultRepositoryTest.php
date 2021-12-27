<?php
namespace Marble\Tests\EntityManager\Repository;

use Marble\Entity\SimpleId;
use Marble\Entity\Ulid;
use Marble\EntityManager\Contract\EntityIoProvider;
use Marble\EntityManager\Contract\EntityReader;
use Marble\EntityManager\EntityManager;
use Marble\EntityManager\Read\ResultRow;
use Marble\EntityManager\Repository\DefaultRepository;
use Marble\EntityManager\Repository\DefaultRepositoryFactory;
use Marble\EntityManager\UnitOfWork\UnitOfWork;
use Marble\Exception\LogicException;
use Marble\Tests\EntityManager\TestImpl\Entity\AbstractTestEntity;
use Marble\Tests\EntityManager\TestImpl\Entity\AnotherTestEntity;
use Marble\Tests\EntityManager\TestImpl\Entity\BasicTestEntity;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class DefaultRepositoryTest extends MockeryTestCase
{
    use RepositoryTestingTrait;

    private function makeEntityManager(): EntityManager
    {
        return new EntityManager(
            Mockery::mock(DefaultRepositoryFactory::class),
            new UnitOfWork(
                Mockery::mock(EntityIoProvider::class),
                null,
            ),
        );
    }

    public function testRepositoryUsesEntityReader(): void
    {
        $em = $this->makeEntityManager();

        // First repo uses a reader for BasicTestEntity
        $reader = Mockery::mock(EntityReader::class);
        $reader->allows('getEntityClassName')->atLeast()->once()->andReturn(BasicTestEntity::class);
        $reader->allows('read')->once()->with($id = new Ulid(), $this->collect(new ResultRow($id, ['name' => 'John Doe'])), $em);
        $repository = new DefaultRepository($reader, $em);

        $t1 = $repository->fetchOne($id);

        $this->assertEquals(BasicTestEntity::class, $repository->getEntityClassName());
        $this->assertInstanceOf(BasicTestEntity::class, $t1);
        $this->assertSame($id, $t1->getId());
        $this->assertEquals('John Doe', $t1->getName());
        $this->assertNull($t1->getDateOfBirth());

        // Second repos uses a reader for AnotherTestEntity
        $reader2 = Mockery::mock(EntityReader::class);
        $reader2->allows('getEntityClassName')->atLeast()->once()->andReturn(AnotherTestEntity::class);
        $reader2->allows('read')->once()->with($id2 = new Ulid(), $this->collect(new ResultRow($id2, ['title' => 'Hooray!'])), $em);
        $repository2 = new DefaultRepository($reader2, $em);

        $t2 = $repository2->fetchOne($id2);

        $this->assertEquals(AnotherTestEntity::class, $repository2->getEntityClassName());
        $this->assertInstanceOf(AnotherTestEntity::class, $t2);
        $this->assertSame($id2, $t2->getId());
        $this->assertEquals('Hooray!', $t2->getTitle());
    }

    public function testFetchOneUsesIdentityMap(): void
    {
        $em     = $this->makeEntityManager();
        $reader = Mockery::mock(EntityReader::class);
        $reader->allows('getEntityClassName')->atLeast()->once()->andReturn(BasicTestEntity::class);
        $reader->allows('read')->once()->with($id = new Ulid(), $this->collect(new ResultRow($id, ['name' => 'John Doe'])), $em);
        $repository = new DefaultRepository($reader, $em);

        $t1 = $repository->fetchOne($id);
        $t2 = $repository->fetchOne($id);

        $this->assertSame($t1, $t2);

        $reader->allows('read')->once()->with($query = $this->makeQuery(), $this->collect(new ResultRow($id, ['name' => 'Something else...'])), $em);

        $t3 = $repository->fetchOne($query);

        $this->assertInstanceOf(BasicTestEntity::class, $t3);
        $this->assertSame($t1, $t3);
        $this->assertEquals('John Doe', $t3->getName()); // no re-hydration

        // Mockery will assert that the `read` method was called exactly once.
    }

    public function testFetchManyUsesIdentityMap(): void
    {
        $em     = $this->makeEntityManager();
        $reader = Mockery::mock(EntityReader::class);
        $reader->allows('getEntityClassName')->atLeast()->once()->andReturn(BasicTestEntity::class);
        $reader->allows('read')->once()->with($id = new Ulid(), $this->collect(new ResultRow($id, ['name' => 'Jane Doe'])), $em);
        $reader->allows('read')->once()->with($query = $this->makeQuery(), $this->collect(
            new ResultRow(new Ulid(), ['name' => 'John Doe']),
            new ResultRow($id, ['name' => 'Jane Doe']),
        ), $em);

        $repository = new DefaultRepository($reader, $em);

        $t1 = $repository->fetchOne($id);
        $l2 = $repository->fetchMany($query);

        $this->assertSame($t1, $l2[1]);
    }

    public function testFetchOneUsesQueryCache(): void
    {
        $em     = $this->makeEntityManager();
        $reader = Mockery::mock(EntityReader::class);
        $reader->allows('getEntityClassName')->atLeast()->once()->andReturn(BasicTestEntity::class);
        $reader->allows('read')->once()->with($query = $this->makeQuery(), $this->collect(new ResultRow(new Ulid(), ['name' => 'John Doe'])), $em);
        $repository = new DefaultRepository($reader, $em);

        $t1 = $repository->fetchOne($query);
        $t2 = $repository->fetchOne($query);

        $this->assertInstanceOf(BasicTestEntity::class, $t1);
        $this->assertSame($t1, $t2);

        // Mockery will assert that the `read` method was called exactly once.
    }

    public function testFetchManyUsesQueryCache(): void
    {
        $em     = $this->makeEntityManager();
        $reader = Mockery::mock(EntityReader::class);
        $reader->allows('getEntityClassName')->atLeast()->once()->andReturn(BasicTestEntity::class);
        $reader->allows('read')->once()->with($query = $this->makeQuery(), $this->collect(
            new ResultRow(new Ulid(), ['name' => 'John Doe']),
            new ResultRow(new Ulid(), ['name' => 'Jane Doe']),
        ), $em);
        $repository = new DefaultRepository($reader, $em);

        $l1 = $repository->fetchMany($query);
        $l2 = $repository->fetchMany($query);

        $this->assertCount(2, $l1);
        $this->assertCount(2, $l2);
        $this->assertInstanceOf(BasicTestEntity::class, $l1[0]);
        $this->assertInstanceOf(BasicTestEntity::class, $l1[1]);
        $this->assertSame($l1[0], $l2[0]);
        $this->assertSame($l1[1], $l2[1]);

        // Mockery will assert that the `read` method was called exactly once.
    }

    public function testFetchByQueryRegistersEntityInIdentityMap(): void
    {
        $em     = $this->makeEntityManager();
        $reader = Mockery::mock(EntityReader::class);
        $reader->allows('getEntityClassName')->atLeast()->once()->andReturn(BasicTestEntity::class);
        $reader->allows('read')->once()->with($query = $this->makeQuery(), $this->collect(new ResultRow($id = new Ulid(), ['name' => 'John Doe'])), $em);
        $repository = new DefaultRepository($reader, $em);

        $t1 = $repository->fetchOne($query);
        $t2 = $repository->fetchOne($id);

        $this->assertInstanceOf(BasicTestEntity::class, $t1);
        $this->assertSame($t1, $t2);

        // Mockery will assert that the `read` method was called exactly once.
    }

    public function testFetchByIdRequiresOneResult(): void
    {
        $em     = $this->makeEntityManager();
        $reader = Mockery::mock(EntityReader::class);
        $reader->allows('getEntityClassName')->atLeast()->once()->andReturn(BasicTestEntity::class);
        $reader->allows('read')->once()->with($id = new Ulid(), $this->collect(
            new ResultRow($id, ['name' => 'John Doe']),
            new ResultRow(new Ulid(), ['name' => 'Jane Doe']),
        ), $em);

        $repository = new DefaultRepository($reader, $em);

        $this->expectException(LogicException::class);
        $repository->fetchOne($id);
    }

    public function testFetchByIdRequiresResultWithSameId(): void
    {
        $em     = $this->makeEntityManager();
        $reader = Mockery::mock(EntityReader::class);
        $reader->allows('getEntityClassName')->atLeast()->once()->andReturn(BasicTestEntity::class);
        $reader->allows('read')->once()->with($id = new SimpleId("1"), $this->collect(new ResultRow(new SimpleId("2"), ['name' => 'Jane Doe'])), $em);
        $repository = new DefaultRepository($reader, $em);

        $this->expectException(LogicException::class);
        $repository->fetchOne($id);
    }

    public function testEntityReaderForAbstractEntityMustSpecifyConcreteClass(): void
    {
        $em     = $this->makeEntityManager();
        $reader = Mockery::mock(EntityReader::class);
        $reader->allows('getEntityClassName')->atLeast()->once()->andReturn(AbstractTestEntity::class);
        $reader->allows('read')->once()->with($id1 = new Ulid(), $this->collect(new ResultRow($id1, ['name' => 'John Doe'], BasicTestEntity::class)), $em);
        $reader->allows('read')->once()->with($id2 = new Ulid(), $this->collect(new ResultRow($id2, ['name' => 'Jane Doe'])), $em);
        $repository = new DefaultRepository($reader, $em);

        $t1 = $repository->fetchOne($id1);
        $this->assertInstanceOf(BasicTestEntity::class, $t1);

        $this->expectException(LogicException::class);
        $repository->fetchOne($id2);
    }
}

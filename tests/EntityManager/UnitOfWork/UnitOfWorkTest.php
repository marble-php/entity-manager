<?php
namespace Marble\Tests\EntityManager\UnitOfWork;

use DateTime;
use Marble\Entity\SimpleId;
use Marble\Entity\Ulid;
use Marble\EntityManager\Contract\EntityIoProvider;
use Marble\EntityManager\Contract\EntityWriter;
use Marble\EntityManager\Event\EntityPersistedEvent;
use Marble\EntityManager\Event\EntityRegisteredEvent;
use Marble\EntityManager\Event\EntityRemovedEvent;
use Marble\EntityManager\Event\FetchedEntityInstantiatedEvent;
use Marble\EntityManager\Event\NewEntityRegisteredEvent;
use Marble\EntityManager\Event\PostFlushEvent;
use Marble\EntityManager\Event\PreFlushEvent;
use Marble\EntityManager\Exception\EntitySkippedException;
use Marble\EntityManager\UnitOfWork\ObjectNeedle;
use Marble\EntityManager\UnitOfWork\UnitOfWork;
use Marble\EntityManager\Write\DeleteContext;
use Marble\EntityManager\Write\HasChanged;
use Marble\EntityManager\Write\Persistable;
use Marble\EntityManager\Write\WriteContext;
use Marble\Exception\LogicException;
use Marble\Tests\EntityManager\TestImpl\Entity\AbstractTestEntity;
use Marble\Tests\EntityManager\TestImpl\Entity\AnotherTestEntity;
use Marble\Tests\EntityManager\TestImpl\Entity\BasicTestEntity;
use Marble\Tests\EntityManager\TestImpl\Entity\EntityWithSimpleId;
use Marble\Tests\EntityManager\TestImpl\Entity\TestEntityWithRequiredPropertyWithoutDefault;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

class UnitOfWorkTest extends MockeryTestCase
{
    public function testEntityIsCreatedWithCompleteData(): void
    {
        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $unitOfWork = new UnitOfWork(Mockery::mock(EntityIoProvider::class), $dispatcher);

        $dispatcher->allows('dispatch')->with(FetchedEntityInstantiatedEvent::class)->once();
        $dispatcher->allows('dispatch')->with(EntityRegisteredEvent::class)->once();

        $entity = $unitOfWork->instantiate(BasicTestEntity::class, $id = new Ulid(), [
            'name'        => 'Jane Doe',
            'dateOfBirth' => new DateTime('2021-12-02'),
        ]);

        $this->assertInstanceOf(BasicTestEntity::class, $entity);
        $this->assertTrue($entity->getId()->equals($id));
        $this->assertEquals('Jane Doe', $entity->getName());
        $this->assertEquals(new DateTime('2021-12-02'), $entity->getDateOfBirth());
        $this->assertNotNull($fromIdMap = $unitOfWork->getEntityFromIdentityMap(BasicTestEntity::class, $id));
        $this->assertSame($entity, $fromIdMap);
        $this->assertNotNull($fromIdMap = $unitOfWork->getEntityFromIdentityMap(AbstractTestEntity::class, $id));
        $this->assertSame($entity, $fromIdMap);
    }

    public function testEntityIsCreatedWithoutOptionalData(): void
    {
        $unitOfWork = new UnitOfWork(Mockery::mock(EntityIoProvider::class), null);
        $entity     = $unitOfWork->instantiate(BasicTestEntity::class, $id = new Ulid(), []);

        $this->assertInstanceOf(BasicTestEntity::class, $entity);
        $this->assertTrue($entity->getId()->equals($id));
        $this->assertNull($entity->getName());
        $this->assertNull($entity->getDateOfBirth());
        $this->assertNotNull($fromIdMap = $unitOfWork->getEntityFromIdentityMap(BasicTestEntity::class, $id));
        $this->assertSame($entity, $fromIdMap);
        $this->assertNotNull($fromIdMap = $unitOfWork->getEntityFromIdentityMap(AbstractTestEntity::class, $id));
        $this->assertSame($entity, $fromIdMap);
    }

    public function testEntityHydrationFailsWithoutRequiredValue(): void
    {
        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $unitOfWork = new UnitOfWork(Mockery::mock(EntityIoProvider::class), $dispatcher);

        $this->expectException(LogicException::class);
        $unitOfWork->instantiate(TestEntityWithRequiredPropertyWithoutDefault::class, new Ulid(), [
            // Not passing a value for `city`, a typed, required property without default.
            'dateOfBirth' => new DateTime('2021-12-03'),
        ]);
    }

    public function testClassNameMustBeEntity(): void
    {
        $objectClass = new class {
        };

        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $unitOfWork = new UnitOfWork(Mockery::mock(EntityIoProvider::class), $dispatcher);
        $this->expectException(LogicException::class);
        $unitOfWork->instantiate($objectClass::class, new Ulid(), []);
    }

    public function testHydratedEntityMustHaveGivenId(): void
    {
        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $unitOfWork = new UnitOfWork(Mockery::mock(EntityIoProvider::class), $dispatcher);
        $this->expectException(LogicException::class);
        $unitOfWork->instantiate(AnotherTestEntity::class, new Ulid(), ["id" => new Ulid()]);
    }

    public function testReferencesAreRegistered(): void
    {
        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $unitOfWork = new UnitOfWork(Mockery::mock(EntityIoProvider::class), $dispatcher);

        $dispatcher->allows('dispatch')->with(NewEntityRegisteredEvent::class)->times(3);

        $t1 = new AnotherTestEntity();
        $t1->setAnother($t2 = new AnotherTestEntity());
        $t2->setAnother($t3 = new AnotherTestEntity());

        $unitOfWork->register($t1);

        $this->assertSame($t1, $unitOfWork->getEntityFromIdentityMap(AnotherTestEntity::class, $t1->getId()));
        $this->assertSame($t2, $unitOfWork->getEntityFromIdentityMap(AnotherTestEntity::class, $t2->getId()));
        $this->assertSame($t3, $unitOfWork->getEntityFromIdentityMap(AnotherTestEntity::class, $t3->getId()));

        $dispatcher->allows('dispatch')->with(FetchedEntityInstantiatedEvent::class)->once();
        $dispatcher->allows('dispatch')->with(EntityRegisteredEvent::class)->twice(); // $t5 will be assumed FETCHED

        $t4 = $unitOfWork->instantiate(EntityWithSimpleId::class, new SimpleId(4), [
            "another" => $t5 = new EntityWithSimpleId(5),
        ]);

        $this->assertSame($t5, $t4->getAnother());
        $this->assertSame($t5, $unitOfWork->getEntityFromIdentityMap(EntityWithSimpleId::class, $t5->getId()));
    }

    public function testReferencesAreReplaced(): void
    {
        $unitOfWork = new UnitOfWork(Mockery::mock(EntityIoProvider::class), null);

        $t1 = new EntityWithSimpleId(1);
        $t2 = new EntityWithSimpleId(2);
        $t2->setAnother($t3 = new EntityWithSimpleId(1));

        $unitOfWork->register($t1);
        $unitOfWork->register($t2);

        $this->assertSame($t1, $t2->getAnother());
        $this->assertNotSame($t3, $t2->getAnother());
    }

    public function testReferenceReplacementChecksCircularRef(): void
    {
        $unitOfWork = new UnitOfWork(Mockery::mock(EntityIoProvider::class), null);

        $t1 = new EntityWithSimpleId(1);
        $t1->setAnother($t2 = new EntityWithSimpleId(2));
        $t2->setAnother(new EntityWithSimpleId(1));

        $this->expectException(LogicException::class);
        $unitOfWork->register($t1);
    }

    public function testIdentityMap(): void
    {
        $unitOfWork = new UnitOfWork(Mockery::mock(EntityIoProvider::class), null);

        $t1 = new AnotherTestEntity();

        $unitOfWork->register($t1);

        $t2 = $unitOfWork->getEntityFromIdentityMap(AnotherTestEntity::class, $t1->getId());

        $this->assertSame($t1, $t2);

        $unitOfWork->register($t1); // This is fine.

        $t3 = $unitOfWork->getEntityFromIdentityMap(AnotherTestEntity::class, $t1->getId());

        $this->assertSame($t1, $t3);

        $t2 = clone $t1;

        // A different entity with same class and identifier is not allowed.
        $this->expectException(LogicException::class);
        $unitOfWork->register($t2);
    }

    public function testEntityIsInIdentityMapUnderParentClass(): void
    {
        $unitOfWork = new UnitOfWork(Mockery::mock(EntityIoProvider::class), null);

        $t1 = new BasicTestEntity();
        $t1->setName("Jane Doe");
        $t1->setDateOfBirth(new DateTime());

        $unitOfWork->register($t1);

        $t2 = $unitOfWork->getEntityFromIdentityMap(AbstractTestEntity::class, $t1->getId());

        $this->assertSame($t1, $t2);
    }

    /** @noinspection PhpPossiblePolymorphicInvocationInspection */
    public function testWrite(): void
    {
        $ioProvider = Mockery::mock(EntityIoProvider::class);
        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $unitOfWork = new UnitOfWork($ioProvider, $dispatcher);

        $dispatcher->allows('dispatch')->with(FetchedEntityInstantiatedEvent::class)->times(2);
        $dispatcher->allows('dispatch')->with(EntityRegisteredEvent::class)->times(2);

        $t1 = $unitOfWork->instantiate(EntityWithSimpleId::class, new SimpleId(1), []);
        $t2 = $unitOfWork->instantiate(EntityWithSimpleId::class, new SimpleId(2), ['another' => $t1]);
        $t2->setAnother($t3 = new EntityWithSimpleId(3));
        $unitOfWork->queueRemoval($t1);

        $persistable3 = Mockery::on(fn(Persistable $persistable): bool => $persistable->getEntity() === $t3);
        $persistable2 = Mockery::on(function (Persistable $persistable) use ($t1, $t2): bool {
            if ($persistable->getEntity() === $t2 && $persistable instanceof HasChanged) {
                $this->assertCount(2, $persistable->getOriginalData());
                $this->assertArrayHasKey('id', $persistable->getOriginalData());
                $this->assertArrayHasKey('another', $persistable->getOriginalData());
                $this->assertEquals($t1, $persistable->getOriginalData()['another']);
                $this->assertCount(1, $persistable->getChangedProperties());
                $this->assertEquals('another', $persistable->getChangedProperties()[0]);
                $this->assertCount(2, $persistable->getData());
                $this->assertArrayHasKey('id', $persistable->getData());
                $this->assertArrayHasKey('another', $persistable->getData());
                return true;
            }

            return false;
        });

        $persistedEvent2 = Mockery::on(fn(EntityPersistedEvent $event): bool => $event->getEntity() === $t2);
        $persistedEvent3 = Mockery::on(fn(EntityPersistedEvent $event): bool => $event->getEntity() === $t3);
        $writer          = Mockery::mock(EntityWriter::class);

        $ioProvider->allows('getWriter')->with(EntityWithSimpleId::class)->andReturn($writer);
        $writer->allows('write')->with($persistable2, WriteContext::class)->once();
        $writer->allows('write')->with($persistable3, WriteContext::class)->once();
        $writer->allows('delete')->once();
        $dispatcher->allows('dispatch')->with(PreFlushEvent::class)->once();
        $dispatcher->allows('dispatch')->with(EntityRegisteredEvent::class)->once();
        $dispatcher->allows('dispatch')->with($persistedEvent2)->once();
        $dispatcher->allows('dispatch')->with($persistedEvent3)->once();
        $dispatcher->allows('dispatch')->with(EntityRemovedEvent::class)->once();
        $dispatcher->allows('dispatch')->with(PostFlushEvent::class)->once();

        $unitOfWork->flush();

        $this->assertSame($t3, $unitOfWork->getEntityFromIdentityMap(EntityWithSimpleId::class, $t3->getId()));

        // No writes/deletes needed.
        $dispatcher->allows('dispatch')->with(PreFlushEvent::class)->once();
        $dispatcher->allows('dispatch')->with(PostFlushEvent::class)->once();

        $unitOfWork->flush();

        $t2->setAnother($t4 = new EntityWithSimpleId(4));
        $writer->allows('write')->twice();
        $dispatcher->allows('dispatch')->with(PreFlushEvent::class)->once();
        $dispatcher->allows('dispatch')->with(EntityRegisteredEvent::class)->once();
        $dispatcher->allows('dispatch')->with(EntityPersistedEvent::class)->twice();
        $dispatcher->allows('dispatch')->with(PostFlushEvent::class)->once();
        $unitOfWork->flush();
        $this->assertSame($t4, $unitOfWork->getEntityFromIdentityMap(EntityWithSimpleId::class, $t4->getId()));
        $this->assertNotNull($unitOfWork->getEntityFromIdentityMap(EntityWithSimpleId::class, $t3->getId()));

        $t2->setAnother($t3);
        $unitOfWork->queueRemoval($t4);
        $writer->allows('write')->once();
        $writer->allows('delete')->once();
        $dispatcher->allows('dispatch')->with(PreFlushEvent::class)->once();
        $dispatcher->allows('dispatch')->with(EntityPersistedEvent::class)->once();
        $dispatcher->allows('dispatch')->with(EntityRemovedEvent::class)->once();
        $dispatcher->allows('dispatch')->with(PostFlushEvent::class)->once();
        $unitOfWork->flush();
        $this->assertNull($unitOfWork->getEntityFromIdentityMap(EntityWithSimpleId::class, $t4->getId()));
    }

    public function testRemoval(): void
    {
        $ioProvider = Mockery::mock(EntityIoProvider::class);
        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $unitOfWork = new UnitOfWork($ioProvider, $dispatcher);
        $needle     = new ObjectNeedle();

        $dispatcher->allows('dispatch')->with(EntityRegisteredEvent::class)->once();

        $t1 = new AnotherTestEntity();

        $unitOfWork->register($t1, $needle->extract($t1));
        $unitOfWork->queueRemoval($t1);

        $dispatcher->allows('dispatch')->with(EntityRegisteredEvent::class)->once();

        $t2 = new AnotherTestEntity();

        $unitOfWork->register($t2, $needle->extract($t2));
        $unitOfWork->queueRemoval($t2);
        $unitOfWork->register($t2); // Undo removal

        $writer = Mockery::mock(EntityWriter::class);

        $ioProvider->allows('getWriter')->andReturn($writer);
        $writer->allows('delete')->with($t1, $unitOfWork)->once();
        $dispatcher->allows('dispatch')->with(PreFlushEvent::class)->once();
        $dispatcher->allows('dispatch')->with(PostFlushEvent::class)->once();
        $dispatcher->allows('dispatch')->with(EntityRemovedEvent::class)->once();

        $unitOfWork->flush();
    }

    public function testWriteContext(): void
    {
        $ioProvider = Mockery::mock(EntityIoProvider::class);
        $writer     = Mockery::mock(EntityWriter::class);
        $unitOfWork = new UnitOfWork($ioProvider, null);

        $t1 = new EntityWithSimpleId(1);
        $t1->setAnother($t2 = new EntityWithSimpleId(2));

        $persistable2 = Mockery::on(fn(Persistable $persistable): bool => $persistable->getEntity() === $t2);
        $persistable1 = Mockery::on(fn(Persistable $persistable): bool => $persistable->getEntity() === $t1);

        $context2 = Mockery::on(function (WriteContext $context) use ($t2): bool {
            $context->markPersisted($t2); // superfluous, won't do anything
            // Mock will throw EntitySkippedException.
            return true;
        });

        $context1 = Mockery::on(function (WriteContext $context) use ($t2): bool {
            $context->markPersisted($t2); // not superfluous
            return true;
        });

        $ioProvider->allows('getWriter')->andReturn($writer);
        $writer->allows('write')->with($persistable2, $context2)->once()->andThrow(EntitySkippedException::class);
        $writer->allows('write')->with($persistable1, $context1)->once();

        $unitOfWork->register($t1);
        $unitOfWork->flush();
    }

    public function testEntityNotPersisted(): void
    {
        $ioProvider = Mockery::mock(EntityIoProvider::class);
        $writer     = Mockery::mock(EntityWriter::class);
        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $unitOfWork = new UnitOfWork($ioProvider, $dispatcher);

        $dispatcher->allows('dispatch')->with(NewEntityRegisteredEvent::class)->twice();

        $t1 = new EntityWithSimpleId(1);
        $t1->setAnother($t2 = new EntityWithSimpleId(2));
        $unitOfWork->register($t1);

        $persistable2 = Mockery::on(fn(Persistable $persistable): bool => $persistable->getEntity() === $t2);
        $persistable1 = Mockery::on(fn(Persistable $persistable): bool => $persistable->getEntity() === $t1);

        $ioProvider->allows('getWriter')->andReturn($writer);
        $writer->allows('write')->with($persistable2, WriteContext::class)->once()->andThrow(EntitySkippedException::class);
        $writer->allows('write')->with($persistable1, WriteContext::class)->once();

        $dispatcher->allows('dispatch')->with(PreFlushEvent::class)->once();
        $dispatcher->allows('dispatch')->with(Mockery::on(fn(EntityPersistedEvent $event): bool => $event->getEntity() === $t1))->once();

        $this->expectException(LogicException::class);
        $unitOfWork->flush();
    }

    public function testRemovingWithContext(): void
    {
        $ioProvider = Mockery::mock(EntityIoProvider::class);
        $writer     = Mockery::mock(EntityWriter::class);
        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $ioProvider->allows('getWriter')->andReturn($writer);

        $dispatcher->allows('dispatch')->with(EntityRegisteredEvent::class)->twice();

        $t1 = new EntityWithSimpleId(1);
        $t2 = new EntityWithSimpleId(2);

        $unitOfWork = new UnitOfWork($ioProvider, $dispatcher);
        $unitOfWork->register($t1, []);
        $unitOfWork->register($t2, []);

        $persistable1 = Mockery::on(fn(Persistable $persistable): bool => $persistable->getEntity() === $t1);
        $persistable2 = Mockery::on(fn(Persistable $persistable): bool => $persistable->getEntity() === $t2);

        $context1 = Mockery::on(function (WriteContext $context) use ($t2): bool {
            $context->queueRemoval($t2);
            return true;
        });

        $context2 = Mockery::on(function (WriteContext $context) use ($t1): bool {
            $context->queueRemoval($t1);
            return true;
        });

        // When T1 is being written, T2 will be queued for removal.
        // Therefore, T2 will not be written, so T1 will not be queued for removal.

        $writer->allows('write')->with($persistable1, $context1)->once();
        $writer->allows('write')->with($persistable2, $context2)->never();
        $writer->allows('delete')->with($t1, DeleteContext::class)->never();
        $writer->allows('delete')->with($t2, DeleteContext::class)->once();
        $dispatcher->allows('dispatch')->with(PreFlushEvent::class)->once();
        $dispatcher->allows('dispatch')->with(Mockery::on(fn(EntityPersistedEvent $event): bool => $event->getEntity() === $t1))->once();
        $dispatcher->allows('dispatch')->with(Mockery::on(fn(EntityRemovedEvent $event): bool => $event->getEntity() === $t2))->once();
        $dispatcher->allows('dispatch')->with(PostFlushEvent::class)->once();

        $unitOfWork->flush();

        $this->assertSame($t1, $unitOfWork->getEntityFromIdentityMap(EntityWithSimpleId::class, new SimpleId(1)));
        $this->assertNull($unitOfWork->getEntityFromIdentityMap(EntityWithSimpleId::class, new SimpleId(2)));
    }

    public function testMarkRemoved(): void
    {
        $ioProvider = Mockery::mock(EntityIoProvider::class);
        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $writer     = Mockery::mock(EntityWriter::class);
        $ioProvider->allows('getWriter')->andReturn($writer);

        $t1 = new EntityWithSimpleId(1);
        $t2 = new EntityWithSimpleId(2);

        $dispatcher->allows('dispatch')->with(EntityRegisteredEvent::class)->twice();

        $unitOfWork = new UnitOfWork($ioProvider, $dispatcher);
        $unitOfWork->register($t1, []);
        $unitOfWork->register($t2, []);

        $persistable1 = Mockery::on(fn(Persistable $persistable): bool => $persistable->getEntity() === $t1);
        $persistable2 = Mockery::on(fn(Persistable $persistable): bool => $persistable->getEntity() === $t2);

        $context1 = Mockery::on(function (WriteContext $context) use ($t2): bool {
            $context->queueRemoval($t2);
            $context->cancelRemoval($t2);
            $context->markRemoved($t2);
            return true;
        });

        $writer->allows('write')->with($persistable1, $context1)->once();
        $writer->allows('write')->with($persistable2, WriteContext::class)->never();
        $writer->allows('delete')->with($persistable2, DeleteContext::class)->never();
        $dispatcher->allows('dispatch')->with(PreFlushEvent::class)->once();
        $dispatcher->allows('dispatch')->with(Mockery::on(fn(EntityRemovedEvent $event): bool => $event->getEntity() === $t2))->once();
        $dispatcher->allows('dispatch')->with(Mockery::on(fn(EntityPersistedEvent $event): bool => $event->getEntity() === $t1))->once();
        $dispatcher->allows('dispatch')->with(PostFlushEvent::class)->once();

        $unitOfWork->flush();
    }
}

<?php
namespace Marble\EntityManager\UnitOfWork;

use Doctrine\Instantiator\Exception\ExceptionInterface;
use Doctrine\Instantiator\Instantiator;
use Marble\Entity\Entity;
use Marble\Entity\Identifier;
use Marble\EntityManager\Contract\EntityIoProvider;
use Marble\EntityManager\Event\EntityPersistedEvent;
use Marble\EntityManager\Event\EntityRemovedEvent;
use Marble\EntityManager\Event\FetchedEntityInstantiatedEvent;
use Marble\EntityManager\Event\EntityRegisteredEvent;
use Marble\EntityManager\Event\NewEntityRegisteredEvent;
use Marble\EntityManager\Event\PostFlushEvent;
use Marble\EntityManager\Event\PreFlushEvent;
use Marble\EntityManager\Exception\EntitySkippedException;
use Marble\EntityManager\Write\EntityUpdateContainer;
use Marble\EntityManager\Write\EntityWriteContainer;
use Marble\EntityManager\Write\WriteContext;
use Marble\Exception\LogicException;
use Psr\EventDispatcher\EventDispatcherInterface;

class UnitOfWork implements WriteContext
{
    public const DEFAULT_ENTITY_ID_PROPERTY = 'id';

    private ObjectNeedle $needle;
    private Instantiator $instantiator;
    private ChangeSetCalculator $changeCalculator;

    /**
     * @template T of Entity
     * @var array<class-string<T>, ClassInfo<T>>
     */
    private array $classInfos = [];

    /**
     * @template T of Entity
     * @var array<class-string<T>, array<string, T>>
     */
    private array $identityMap = [];

    /**
     * Keys are spl_object_id's.
     *
     * @var array<int, EntityInfo>
     */
    private array $entities = [];

    private bool $flushing = false;

    public function __construct(
        private EntityIoProvider          $ioProvider,
        private ?EventDispatcherInterface $dispatcher,
    ) {
        $this->needle           = new ObjectNeedle();
        $this->instantiator     = new Instantiator();
        $this->changeCalculator = new ChangeSetCalculator($this->needle);
    }

    /**
     * @template T of Entity
     * @param class-string<T> $className
     * @param Identifier      $id
     * @return T|null
     */
    public function getEntityFromIdentityMap(string $className, Identifier $id): ?Entity
    {
        return $this->identityMap[$className][(string) $id] ?? null;
    }

    /**
     * @template T of Entity
     * @param class-string<T>      $className
     * @param Identifier           $identifier
     * @param array<string, mixed> $data
     * @return T
     */
    public function instantiate(string $className, Identifier $identifier, array $data): Entity
    {
        if (!is_subclass_of($className, Entity::class)) {
            throw new LogicException(sprintf("Class %s does not implement the %s interface.", $className, Entity::class));
        }

        try {
            /** @var Entity $entity */
            $entity = $this->instantiator->instantiate($className);
        } catch (ExceptionInterface $exception) {
            throw new LogicException($exception->getMessage(), 0, $exception);
        }

        if (!in_array($identifier, $data, true)) {
            // Include identifier in hydration if it's not included yet.
            $data[self::DEFAULT_ENTITY_ID_PROPERTY] ??= $identifier;
        }

        $this->needle->hydrate($entity, $data);

        if ($entity->getId() === null) {
            throw new LogicException(sprintf("Hydrated %s entity should have identifier %s, has no identifier.", $className, $identifier));
        } elseif ($entity->getId() !== $identifier) {
            throw new LogicException(sprintf("Hydrated %s entity should have identifier %s, has identifier %s instead.", $className, $identifier, $entity->getId()));
        }

        $this->dispatcher?->dispatch(new FetchedEntityInstantiatedEvent($entity));
        $this->register($entity, $data);

        return $entity;
    }

    /**
     * Manage an entity within this unit of work.
     *
     * This method also registers any associated entities.
     */
    public function register(Entity $entity, ?array $loadedData = null): void
    {
        $info = $this->entities[$oid = spl_object_id($entity)] ?? new EntityInfo($entity, $loadedData);

        if ($entity->getId() !== null) {
            // If a NEW entity without an identifier is registered, it won't be added to the identity map,
            // but that's OK, because in that case we needn't protect against duplicate instantiations.
            // In fact, only FETCHED entities must be added to the identity map, but there's no harm
            // in adding NEW entities with identifiers as well.

            if ($existing = $this->getEntityFromIdentityMap($entity::class, $entity->getId())) {
                if ($existing !== $entity) {
                    throw new LogicException(sprintf("A different %s entity with identifier %s was already registered in the current unit of work.",
                        $entity::class, $entity->getId()));
                }
            } else {
                $this->identityMap[$entity::class][(string) $entity->getId()] = $entity;

                foreach ($this->getClassInfo($entity::class)->getParentClasses() as $parent) {
                    // Entities in the same entity class hierarchy must not ever have the same identifier.
                    $this->identityMap[$parent][(string) $entity->getId()] = $entity;
                }
            }
        }

        if (isset($this->entities[$oid])) {
            // Entity already registered.
            if (!$this->flushing && $info->isToBeRemoved()) {
                // Cancel removal.
                $info->setToBeRemoved(false);
            }

            return;
        }

        $this->entities[$oid] = $info;
        $referenceFinder      = new ReferenceFinder($this->needle);
        $referenceReplacer    = new ReferenceReplacer();

        $referenceFinder->forEach($entity, function (Entity $subentity, array $path) use ($info, $referenceReplacer): void {
            if ($existing = $this->getEntityFromIdentityMap($subentity::class, $subentity->getId())) {
                if ($existing !== $subentity) {
                    // An equivalent but not identical entity is already known.
                    // To prevent this expensive operation, use the appropriate repository to fetch the associated entity.
                    $referenceReplacer->replaceReference($info->getEntity(), $path, $existing);
                    // Check that no circular reference was introduced.
                    (new ReferenceTreeBuilder($this->needle))->buildTree($info->getEntity());
                }
            } else {
                // Unknown entity found.

                if ($info->getState() === EntityState::NEW) {
                    // If root entity is NEW, this sub-entity must also be new (otherwise it would have been known already).
                    $subentityLoadedData = null;
                } else {
                    // If root entity is FETCHED, this sub-entity must have been instantiated manually (otherwise it would have been known already).
                    // Still, we'll assume that the sub-entity was FETCHED from the data source as well, and is not really a NEW entity.
                    // We can get its last-saved data by extracting from the entity.
                    $subentityLoadedData = $this->needle->extract($subentity);
                }

                $this->register($subentity, $subentityLoadedData);
            }
        });

        $this->dispatcher?->dispatch(
            $info->getState() === EntityState::NEW ? new NewEntityRegisteredEvent($entity) : new EntityRegisteredEvent($entity)
        );
    }

    /**
     * Schedule one entity for removal.
     *
     * This method does not schedule any associated entities for removal.
     * We cannot perform a cascade-delete. Without additional configuration about entity
     * associations, we cannot know which associations should be removed and which should not.
     * It is up to the relevant EntityWriter to do an appropriate cascade-delete.
     */
    public function queueRemoval(Entity $entity): void
    {
        if ($info = $this->entities[spl_object_id($entity)] ?? null) {
            $info->setToBeRemoved(true);
        }
    }

    // INTERACTING WITH THE PERSISTENCE LAYER

    public function flush(): void
    {
        if ($this->flushing) {
            throw new LogicException("The unit of work is already flushing.");
        }

        $this->flushing = true;

        try {
            $this->dispatcher?->dispatch(new PreFlushEvent());

            $referenceTreeBuilder = new ReferenceTreeBuilder($this->needle);
            $referenceTrees       = [];

            foreach ($this->entities as $info) {
                if (!$info->isToBeRemoved()) {
                    // Will throw an exception if a circular association is found.
                    $referenceTrees += $referenceTreeBuilder->makeTrees($info->getEntity(),
                        fn(Entity $entity): bool => ($this->entities[spl_object_id($entity)] ?? null)?->isToBeRemoved() ?? false);
                }
            }

            $flushOrder = (new FlushOrderCalculator())->calculate(...$referenceTrees);

            // First all insertions.

            $inserted = [];

            foreach ($flushOrder as $oid => $entity) {
                $info = $this->entities[$oid] ?? null;

                if ($info === null || $info->getState() === EntityState::NEW) {
                    if ($info === null) {
                        $this->register($entity);
                        $info = $this->entities[$oid];
                    }

                    $this->write($info);

                    // Entity may have received an identifier on insertion.
                    $this->register($entity);

                    // Keep track of insertions here, because the update step cannot distinguish
                    // between entities INSERTED now and those INSERTED in a previous flush.
                    $inserted[$oid] = $entity;
                }
            }

            // Then all updates.

            foreach ($flushOrder as $oid => $entity) {
                if (!array_key_exists($oid, $inserted)) {
                    $this->write($this->entities[$oid]);
                }
            }

            // And finally removals.

            foreach ($this->entities as $info) {
                if ($info->isToBeRemoved()) {
                    $this->delete($info);
                }
            }

            foreach ($this->entities as $oid => $info) {
                if ($info->isToBeRemoved()) {
                    throw new LogicException(sprintf("Entity %s was queued for removal but has not been removed.", LogicException::strEntity($info->getEntity())));
                } elseif ($info->getState() === EntityState::NEW) {
                    throw new LogicException(sprintf("New entity %s has not been inserted.", LogicException::strEntity($info->getEntity())));
                } elseif ($info->hasChanged()) {
                    throw new LogicException(sprintf("Changed entity %s has not been updated.", LogicException::strEntity($info->getEntity())));
                } elseif ($info->getState() === EntityState::REMOVED) {
                    // Forget about this entity.
                    unset($this->entities[$oid]);

                    $entity = $info->getEntity();

                    if ($entity->getId() !== null) {
                        unset($this->identityMap[$entity::class][(string) $entity->getId()]);
                    }
                }
            }

            $this->dispatcher?->dispatch(new PostFlushEvent());
        } finally {
            $this->flushing = false;
        }
    }

    private function write(EntityInfo $entityInfo): void
    {
        $entity = $entityInfo->getEntity();
        $writer = $this->ioProvider->getWriter($entity::class);

        if ($writer === null) {
            // Another entity writer is responsible for writing this entity.
            return;
        } elseif ($entityInfo->isToBeRemoved() || $entityInfo->getState() === EntityState::REMOVED) {
            // Entity is queued for removal or already marked as removed by an entity writer.
            return;
        }

        if ($entityInfo->getState() === EntityState::NEW) {
            $data        = $this->needle->extract($entity);
            $persistable = new EntityWriteContainer($entity, $data);
        } else {
            // Calculate change set.
            $changes = $this->changeCalculator->findChangedProperties($entityInfo);

            if (empty($changes)) {
                return; // nothing to do
            }

            $entityInfo->setHasChanged(true);

            $data        = $this->needle->extract($entity);
            $persistable = new EntityUpdateContainer($entity, $data, $entityInfo->getLastSavedData(), $changes);
        }

        try {
            $writer->write($persistable, $this);
            $this->markPersisted($entity);
        } catch (EntitySkippedException) {
            //
        }
    }

    private function delete(EntityInfo $entityInfo): void
    {
        $entity = $entityInfo->getEntity();

        if ($entityInfo->getState() === EntityState::REMOVED) {
            throw new LogicException(sprintf("Entity %s is already removed.", LogicException::strEntity($entity)));
        }

        try {
            if ($entityInfo->getState() !== EntityState::NEW) {
                $writer = $this->ioProvider->getWriter($entity::class);

                $writer->delete($entity, $this);
            }

            $this->markRemoved($entity);
        } catch (EntitySkippedException) {
            //
        }
    }

    // ALLOWING ENTITY WRITERS TO WRITE/DELETE ASSOCIATED ENTITIES

    public function markPersisted(Entity $entity): void
    {
        if (!$this->flushing) {
            throw new LogicException(sprintf("The %s() method must only be called during flush.", __METHOD__));
        }

        $info = $this->entities[$oid = spl_object_id($entity)] ?? null;

        if ($info === null) {
            $this->register($entity);
            $info = $this->entities[$oid];
        } elseif ($info->getState() === EntityState::REMOVED) {
            throw new LogicException(sprintf("Entity %s has been removed.", LogicException::strEntity($entity)));
        }

        $info->setLastSavedData($this->needle->extract($entity));
        $info->setState($info->getState()->toPersisted());
        $this->dispatcher?->dispatch(new EntityPersistedEvent($entity));
    }

    public function markRemoved(Entity $entity): void
    {
        if (!$this->flushing) {
            throw new LogicException(sprintf("The %s() method must only be called during flush.", __METHOD__));
        }

        $info = $this->entities[spl_object_id($entity)] ?? null;

        if ($info !== null && $info->getState() !== EntityState::REMOVED) {
            $info->setState(EntityState::REMOVED);
            $this->dispatcher?->dispatch(new EntityRemovedEvent($entity));
        }
    }

    public function cancelRemoval(Entity $entity): void
    {
        if ($info = $this->entities[spl_object_id($entity)] ?? null) {
            $info->setToBeRemoved(false);
        }
    }

    private function getClassInfo(string $className): ClassInfo
    {
        return $this->classInfos[$className] ?? $this->classInfos[$className] = new ClassInfo($className);
    }
}

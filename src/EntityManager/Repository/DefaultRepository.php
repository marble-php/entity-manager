<?php
namespace Marble\EntityManager\Repository;

use Marble\Entity\Entity;
use Marble\Entity\Identifier;
use Marble\EntityManager\Cache\QueryResultCache;
use Marble\EntityManager\Contract\EntityReader;
use Marble\EntityManager\EntityManager;
use Marble\EntityManager\Read\Criteria;
use Marble\EntityManager\Read\ResultRow;
use Marble\EntityManager\Read\ResultSetBuilder;
use Marble\EntityManager\UnitOfWork\UnitOfWork;
use Marble\Exception\LogicException;

/**
 * @template T of Entity
 * @implements Repository<T>
 */
class DefaultRepository implements Repository
{
    /**
     * @param EntityReader<T> $reader
     * @param EntityManager   $entityManager
     */
    public function __construct(
        private EntityReader  $reader,
        private EntityManager $entityManager,
    ) {
        $entityClass = $reader->getEntityClassName();

        if (!class_exists($entityClass)) {
            throw new LogicException(sprintf("Unknown class %s returned by %s::getEntityClassName().",
                $entityClass, $reader::class));
        } elseif (!is_subclass_of($entityClass, Entity::class)) {
            throw new LogicException(sprintf("Class %s returned by %s::getEntityClassName() does not implement the %s interface.",
                $entityClass, $reader::class, Entity::class));
        }
    }

    /**
     * @return class-string<T>
     */
    final public function getEntityClassName(): string
    {
        return $this->reader->getEntityClassName();
    }

    public function add(Entity $entity): void
    {
        $className = $this->getEntityClassName();

        if (!$entity instanceof $className) {
            throw new LogicException(sprintf("Entity %s not accepted by repository for entity %s.", LogicException::strEntity($entity), $className));
        }

        $this->entityManager->persist($entity);
    }

    public function remove(Entity $entity): void
    {
        $className = $this->getEntityClassName();

        if (!$entity instanceof $className) {
            throw new LogicException(sprintf("Entity %s not accepted by repository for entity %s.", LogicException::strEntity($entity), $className));
        }

        $this->entityManager->remove($entity);
    }

    private function getUnitOfWork(): UnitOfWork
    {
        return $this->entityManager->getUnitOfWork();
    }

    private function getCache(): QueryResultCache
    {
        return $this->entityManager->getQueryResultCache();
    }

    final public function fetchOne(object $query): ?Entity
    {
        if ($query instanceof Identifier) {
            if ($entity = $this->getUnitOfWork()->getEntityFromIdentityMap($this->getEntityClassName(), $query)) {
                return $entity;
            }
        } else {
            $cached = $this->getCache()->get($this, $query, true);

            if ($cached !== null) {
                return empty($cached) ? null : reset($cached);
            }
        }

        $this->reader->read($query, $resultSetBuilder = new ResultSetBuilder($this->reader), $this->entityManager);

        $resultSet = $resultSetBuilder->build();
        $entity    = null;

        if ($row = $resultSet->first()) {
            if ($query instanceof Identifier && $resultSet->count() > 1) {
                throw new LogicException(sprintf("%d %s entities returned for identifier %s.", $resultSet->count(), $this->getEntityClassName(), (string) $query));
            } elseif ($query instanceof Identifier && !$row->identifier->equals($query)) {
                throw new LogicException(sprintf(
                    "Entity reader %s for entity %s gave a result when queried for identifier %s, but the returned entity has identifier %s.",
                    $this->reader::class, $this->getEntityClassName(), (string) $query, (string) $row->identifier
                ));
            }

            $entity = $this->makeEntity($row);
        }

        if (!$query instanceof Identifier) {
            $this->getCache()->save($this, $query, true, ...($entity === null ? [] : [$entity]));
        }

        return $entity;
    }

    public function fetchOneBy(array $criteria): ?Entity
    {
        return $this->fetchOne(new Criteria($criteria));
    }

    final public function fetchMany(?object $query): array
    {
        if ($query instanceof Identifier) {
            throw new LogicException(sprintf("Query argument to %s must not be an identifier.", __METHOD__));
        }

        $cached = $this->getCache()->get($this, $query, false);

        if ($cached !== null) {
            return $cached;
        }

        $this->reader->read($query, $resultSetBuilder = new ResultSetBuilder($this->reader), $this->entityManager);

        $resultSet = $resultSetBuilder->build();
        $entities  = [];

        foreach ($resultSet as $row) {
            $entities[] = $this->makeEntity($row);
        }

        $this->getCache()->save($this, $query, false, ...$entities);

        return $entities;
    }

    /**
     * @return T
     */
    private function makeEntity(ResultRow $row): Entity
    {
        if ($row->childClass !== null && !is_a($row->childClass, $this->getEntityClassName(), true)) {
            throw new LogicException(sprintf("Concrete class %s specified for identifier %s is not a subclass of %s.",
                $row->childClass, (string) $row->identifier, $this->getEntityClassName()));
        }

        return $this->getUnitOfWork()->getEntityFromIdentityMap($this->getEntityClassName(), $row->identifier)
            ?? $this->getUnitOfWork()->instantiate($row->childClass ?? $this->getEntityClassName(), $row->identifier, $row->data);
    }

    public function fetchManyBy(array $criteria): array
    {
        return $this->fetchMany(new Criteria($criteria));
    }

    final public function fetchAll(): array
    {
        return $this->fetchMany(null);
    }
}

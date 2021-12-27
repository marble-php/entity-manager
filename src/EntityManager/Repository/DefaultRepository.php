<?php
namespace Marble\EntityManager\Repository;

use Marble\Entity\Entity;
use Marble\Entity\Identifier;
use Marble\EntityManager\Contract\EntityReader;
use Marble\EntityManager\EntityManager;
use Marble\EntityManager\Read\Criteria;
use Marble\EntityManager\Read\ResultRow;
use Marble\EntityManager\Read\ResultSetBuilder;
use Marble\EntityManager\UnitOfWork\UnitOfWork;
use Marble\Exception\LogicException;
use SebastianBergmann\Exporter\Exporter;

/**
 * @template T of Entity
 */
class DefaultRepository implements Repository
{
    private Exporter $exporter;

    /**
     * Keys are hashes of serialized queries plus a suffix indicating one or many.
     *
     * @var array<string, T[]>
     */
    private array $queryResultCache = [];

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

        $this->exporter = new Exporter();
    }

    final public function getEntityClassName(): string
    {
        return $this->reader->getEntityClassName();
    }

    private function getUnitOfWork(): UnitOfWork
    {
        return $this->entityManager->getUnitOfWork();
    }

    final public function fetchOne(object $query): ?Entity
    {
        if ($query instanceof Identifier) {
            if ($entity = $this->getUnitOfWork()->getEntityFromIdentityMap($this->getEntityClassName(), $query)) {
                return $entity;
            }
        } else {
            $cached = $this->getCachedQueryResults($query, true);

            if ($cached !== null) {
                return empty($cached) ? null : reset($cached);
            }
        }

        $this->reader->read($query, $resultSetBuilder = new ResultSetBuilder($this->reader), $this->entityManager);

        $resultSet = $resultSetBuilder->build();
        $entity    = null;

        if ($resultSet->count() > 0) {
            $row = $resultSet->first();

            if ($query instanceof Identifier && $resultSet->count() > 1) {
                throw new LogicException(sprintf("%d %s entities returned for identifier %s.", $resultSet->count(), $this->getEntityClassName(), $query));
            } elseif ($query instanceof Identifier && !$row->identifier->equals($query)) {
                throw new LogicException(sprintf(
                    "Entity reader %s for entity %s gave a result when queried for identifier %s, but the returned entity has identifier %s.",
                    $this->reader::class, $this->getEntityClassName(), $query, $row->identifier
                ));
            }

            $entity = $this->makeEntity($row);
        }

        if (!$query instanceof Identifier) {
            $this->rememberQueryResults($query, true, $entity);
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

        $cached = $this->getCachedQueryResults($query, false);

        if ($cached !== null) {
            return $cached;
        }

        $this->reader->read($query, $resultSetBuilder = new ResultSetBuilder($this->reader), $this->entityManager);

        $resultSet = $resultSetBuilder->build();
        $entities  = [];

        foreach ($resultSet as $row) {
            $entities[] = $this->makeEntity($row);
        }

        $this->rememberQueryResults($query, false, ...$entities);

        return $entities;
    }

    private function makeEntity(ResultRow $row): Entity
    {
        if ($row->childClass !== null && !is_a($row->childClass, $this->getEntityClassName(), true)) {
            throw new LogicException(sprintf("Concrete class %s specified for identifier %s is not a subclass of %s.",
                $row->childClass, $row->identifier, $this->getEntityClassName()));
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

    /**
     * @param object|null $query
     * @param bool        $one
     * @param T           ...$entities
     */
    private function rememberQueryResults(?object $query, bool $one, ?Entity ...$entities): void
    {
        $this->queryResultCache[$this->makeCacheKey($query, $one)] = $entities;
    }

    /**
     * @param object|null $query
     * @param bool        $one
     * @return T[]|null
     */
    private function getCachedQueryResults(?object $query, bool $one): ?array
    {
        return $this->queryResultCache[$this->makeCacheKey($query, $one)] ?? null;
    }

    private function makeCacheKey(?object $query, bool $one): string
    {
        $export = $this->exporter->export($query, 2);

        return md5($export) . $one;
    }

    final public function clearQueryResultCache(): void
    {
        $this->queryResultCache = [];
    }
}

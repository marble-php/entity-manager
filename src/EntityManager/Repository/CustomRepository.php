<?php

namespace Marble\EntityManager\Repository;

use Marble\Entity\Entity;
use Marble\EntityManager\EntityManager;

/**
 * @template T of Entity
 * @implements Repository<T>
 * @api
 */
abstract class CustomRepository implements Repository
{
    /**
     * @var DefaultRepository<T>|null
     */
    private ?DefaultRepository $repository = null;

    /**
     * @param EntityManager   $entityManager
     * @param class-string<T> $entityClass
     */
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly string $entityClass,
    ) {
    }

    /**
     * @return DefaultRepository<T>
     */
    private function repository(): DefaultRepository
    {
        if ($this->repository === null) {
            /** @var DefaultRepository<T> $defaultRepository */
            $defaultRepository = $this->entityManager->getRepository($this->entityClass, false);

            $this->repository = $defaultRepository;
        }

        return $this->repository;
    }

    /**
     * @return class-string<T>
     */
    #[\Override]
    final public function getEntityClassName(): string
    {
        return $this->entityClass;
    }

    #[\Override]
    public function add(Entity $entity): void
    {
        $this->repository()->add($entity);
    }

    #[\Override]
    public function remove(Entity $entity): void
    {
        $this->repository()->remove($entity);
    }

    #[\Override]
    public function fetchOne(object $query)
    {
        return $this->repository()->fetchOne($query);
    }

    #[\Override]
    public function fetchOneBy(array $criteria)
    {
        return $this->repository()->fetchOneBy($criteria);
    }

    #[\Override]
    public function fetchMany(?object $query): array
    {
        return $this->repository()->fetchMany($query);
    }

    #[\Override]
    public function fetchManyBy(array $criteria): array
    {
        return $this->repository()->fetchManyBy($criteria);
    }

    #[\Override]
    public function fetchAll(): array
    {
        return $this->repository()->fetchAll();
    }
}

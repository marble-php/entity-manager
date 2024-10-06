<?php
namespace Marble\EntityManager;

use Marble\Entity\Entity;
use Marble\Entity\EntityReference;
use Marble\EntityManager\Cache\QueryResultCache;
use Marble\EntityManager\Exception\EntityNotFoundException;
use Marble\EntityManager\Read\ReadContext;
use Marble\EntityManager\Repository\DefaultRepositoryFactory;
use Marble\EntityManager\Repository\Repository;
use Marble\EntityManager\UnitOfWork\UnitOfWork;

class EntityManager implements ReadContext
{
    public function __construct(
        private DefaultRepositoryFactory $repositoryFactory,
        private UnitOfWork               $unitOfWork,
        private QueryResultCache         $queryResultCache = new QueryResultCache(),
    ) {
    }

    public function getUnitOfWork(): UnitOfWork
    {
        return $this->unitOfWork;
    }

    public function getQueryResultCache(): QueryResultCache
    {
        return $this->queryResultCache;
    }

    public function getRepository(string $className): Repository
    {
        return $this->repositoryFactory->getRepository($this, $className);
    }

    public function fetch(EntityReference $reference): Entity
    {
        return $this->getRepository($reference->getClassName())->fetchOne($reference->getId())
            ?? throw new EntityNotFoundException(sprintf("%s with identifier %s does not exist.",
                $reference->getClassName(), (string) $reference->getId()));
    }

    public function persist(Entity ...$entities): void
    {
        foreach ($entities as $entity) {
            $this->unitOfWork->register($entity);
        }
    }

    public function remove(Entity ...$entities): void
    {
        foreach ($entities as $entity) {
            $this->unitOfWork->queueRemoval($entity);
        }
    }

    public function flush(): void
    {
        $this->unitOfWork->flush();
        $this->queryResultCache->clear();
    }

    public function clear(): void
    {
        $this->unitOfWork->clear();
        $this->queryResultCache->clear();
    }
}

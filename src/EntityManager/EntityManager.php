<?php

declare(strict_types=1);

namespace Marble\EntityManager;

use Marble\Entity\Entity;
use Marble\Entity\EntityReference;
use Marble\EntityManager\Cache\QueryResultCache;
use Marble\EntityManager\Contract\EntityIoProvider;
use Marble\EntityManager\Exception\EntityNotFoundException;
use Marble\EntityManager\Read\ReadContext;
use Marble\EntityManager\Repository\Repository;
use Marble\EntityManager\Repository\RepositoryFactory;
use Marble\EntityManager\UnitOfWork\UnitOfWork;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @api
 */
final class EntityManager implements ReadContext
{
    private readonly UnitOfWork $unitOfWork;
    private readonly RepositoryFactory $repositoryFactory;

    public function __construct(
        EntityIoProvider                  $ioProvider,
        ?EventDispatcherInterface         $dispatcher = null,
        private readonly QueryResultCache $queryResultCache = new QueryResultCache(),
    ) {
        $this->unitOfWork        = new UnitOfWork($ioProvider, $dispatcher);
        $this->repositoryFactory = new RepositoryFactory($ioProvider);
    }

    public function getUnitOfWork(): UnitOfWork
    {
        return $this->unitOfWork;
    }

    public function getQueryResultCache(): QueryResultCache
    {
        return $this->queryResultCache;
    }

    /**
     * @template T of Entity
     * @param class-string<T> $className
     * @return Repository<T>
     */
    #[\Override]
    public function getRepository(string $className, bool $allowCustom = true): Repository
    {
        return $this->repositoryFactory->getRepository($this, $className, $allowCustom);
    }

    /**
     * @template T of Entity
     * @param EntityReference<T> $reference
     * @return T
     * @throws EntityNotFoundException
     */
    #[\Override]
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

<?php

namespace Marble\Tests\EntityManager;

use Closure;
use Marble\EntityManager\Cache\QueryResultCache;
use Marble\EntityManager\Contract\EntityIoProvider;
use Marble\EntityManager\EntityManager;
use Marble\EntityManager\Repository\RepositoryFactory;
use Marble\EntityManager\UnitOfWork\UnitOfWork;
use Psr\EventDispatcher\EventDispatcherInterface;

trait EntityManagerTestingTrait
{
    private function makeEntityManager(
        EntityIoProvider $ioProvider,
        ?UnitOfWork $unitOfWork = null,
        ?RepositoryFactory $repositoryFactory = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?QueryResultCache $queryResultCache = null,
    ): EntityManager {
        if ($queryResultCache !== null) {
            $entityManager = new EntityManager($ioProvider, $eventDispatcher, $queryResultCache);
        } else {
            $entityManager = new EntityManager($ioProvider, $eventDispatcher);
        }

        // Replace real objects with mocks.
        Closure::bind(static function () use ($entityManager, $repositoryFactory, $unitOfWork) {
            if ($unitOfWork !== null) {
                $entityManager->unitOfWork = $unitOfWork;
            }

            if ($repositoryFactory !== null) {
                $entityManager->repositoryFactory = $repositoryFactory;
            }
        }, null, EntityManager::class)();

        return $entityManager;
    }
}

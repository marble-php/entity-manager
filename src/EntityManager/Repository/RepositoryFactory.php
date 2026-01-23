<?php

namespace Marble\EntityManager\Repository;

use Marble\Entity\Entity;
use Marble\EntityManager\Contract\EntityIoProvider;
use Marble\EntityManager\Contract\EntityReader;
use Marble\EntityManager\EntityManager;
use Marble\Exception\LogicException;
use ReflectionClass;
use ReflectionException;

final class RepositoryFactory
{
    /**
     * @var array<string, Repository<Entity>>
     */
    private array $repositories = [];

    public function __construct(
        private readonly EntityIoProvider $ioProvider,
    ) {
    }

    /**
     * @template T of Entity
     * @param EntityManager   $entityManager
     * @param class-string<T> $className
     * @return Repository<T>
     */
    public function getRepository(EntityManager $entityManager, string $className): Repository
    {
        $key = $className . spl_object_id($entityManager);

        if (array_key_exists($key, $this->repositories)) {
            $repository = $this->repositories[$key];
        } elseif (!class_exists($className)) {
            throw new LogicException(sprintf("Class %s does not exist.", $className));
        } elseif (!is_subclass_of($className, Entity::class)) {
            throw new LogicException(sprintf("Class %s does not implement the %s interface.", $className, Entity::class));
        } else {
            $repository = $this->createRepository($entityManager, $className);
        }

        /** @var Repository<T> $repository */
        return $this->repositories[$key] = $repository;
    }

    /**
     * @template T of Entity
     * @param EntityManager   $entityManager
     * @param class-string<T> $entityClassName
     * @return Repository<T>
     */
    private function createRepository(EntityManager $entityManager, string $entityClassName): Repository
    {
        $repository = $this->ioProvider->getCustomRepository($entityClassName);

        if ($repository instanceof DefaultRepository) {
            $this->validateRepositoryEntity($repository, $entityClassName);

            return $repository;
        }

        $reader = $this->ioProvider->getReader($entityClassName);

        if ($reader === null) {
            $errorMessage = sprintf("No reader returned by %s for %s.", $this->ioProvider::class, $entityClassName);

            try {
                if ((new ReflectionClass($entityClassName))->isAbstract()) {
                    $errorMessage .= " Abstract entity classes require an entity reader just like concrete entity classes."
                        . " Specify the appropriate concrete child class when putting the entity data into the data collector.";
                }
            } catch (ReflectionException) {
                // never mind...
            }

            throw new LogicException($errorMessage);
        }

        if ($repository !== null) {
            $repository = $this->instantiateCustomRepository($repository, $reader, $entityManager);

            $this->validateRepositoryEntity($repository, $entityClassName);

            return $repository;
        }

        return new DefaultRepository($reader, $entityManager);
    }

    /**
     * @template T of Entity
     * @param class-string<DefaultRepository<T>> $repositoryClassName
     * @param EntityReader                       $entityReader
     * @param EntityManager                      $entityManager
     * @return DefaultRepository<T>
     */
    private function instantiateCustomRepository(string $repositoryClassName, EntityReader $entityReader, EntityManager $entityManager): DefaultRepository
    {
        if (!class_exists($repositoryClassName)) {
            throw new LogicException(sprintf("Custom repository class %s for entity %s does not exist.",
                $repositoryClassName, $entityReader->getEntityClassName()));
        } elseif (!is_subclass_of($repositoryClassName, DefaultRepository::class)) {
            throw new LogicException(sprintf("Custom repository class %s for entity %s does not extend %s.",
                $repositoryClassName, $entityReader->getEntityClassName(), DefaultRepository::class));
        }

        // Like Doctrine ORM, we'll assume that constructor parameters are unchanged.
        // If your custom repository does need additional constructor-injected dependencies, return an instance from the EntityIoProvider.

        /**
         * @psalm-suppress UnsafeInstantiation
         * @var DefaultRepository<T> $repository
         */
        $repository = new $repositoryClassName($entityReader, $entityManager);

        return $repository;
    }

    private function validateRepositoryEntity(DefaultRepository $repository, string $entityClassName): void
    {
        if ($repository->getEntityClassName() !== $entityClassName) {
            throw new LogicException(sprintf("Custom repository %s is not for entity %s but for %s.",
                $repository::class, $entityClassName, $repository->getEntityClassName()));
        }
    }
}

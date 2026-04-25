<?php

declare(strict_types=1);

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
     * @param bool            $allowCustom If false, will only return default repository
     * @return Repository<T>
     */
    public function getRepository(EntityManager $entityManager, string $className, bool $allowCustom = true): Repository
    {
        $key       = $className . '|' . spl_object_id($entityManager);
        $customKey = $key . '|custom';

        if ($allowCustom && array_key_exists($customKey, $this->repositories)) {
            $repository = $this->repositories[$customKey];
        } elseif (!$allowCustom && array_key_exists($key, $this->repositories)) {
            $repository = $this->repositories[$key];
        } elseif (!class_exists($className)) {
            throw new LogicException(sprintf("Class %s does not exist.", $className));
        } elseif (!is_subclass_of($className, Entity::class)) {
            throw new LogicException(sprintf("Class %s does not implement the %s interface.", $className, Entity::class));
        } else {
            $repository = $this->createRepository($entityManager, $className, $allowCustom);

            if ($repository instanceof DefaultRepository) {
                $this->repositories[$key] = $repository;
            }

            if ($allowCustom) {
                // @phpstan-ignore assign.propertyType
                $this->repositories[$customKey] = $repository;
            }
        }

        /** @var Repository<T> */
        return $repository;
    }

    /**
     * @template T of Entity
     * @param EntityManager   $entityManager
     * @param class-string<T> $entityClassName
     * @param bool            $allowCustom
     * @return Repository<T>
     */
    private function createRepository(EntityManager $entityManager, string $entityClassName, bool $allowCustom = true): Repository
    {
        if ($allowCustom) {
            /** @var CustomRepository<Entity>|string|null $repository */
            $repository = $this->ioProvider->getCustomRepository($entityClassName);

            if ($repository !== null) {
                if (!$repository instanceof CustomRepository) {
                    $repository = $this->instantiateCustomRepository($repository, $entityClassName, $entityManager);
                }

                /** @var CustomRepository<Entity> $repository */
                $this->validateRepositoryEntity($repository, $entityClassName);

                /** @var CustomRepository<T> */
                return $repository;
            }
        }

        return new DefaultRepository($this->getEntityReader($entityClassName), $entityManager);
    }

    /**
     * @template T of Entity
     * @param class-string<T> $entityClassName
     * @return EntityReader<T>
     */
    private function getEntityReader(string $entityClassName): EntityReader
    {
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

        return $reader;
    }

    /**
     * @template T of Entity
     * @param string          $repositoryClassName
     * @param class-string<T> $entityClassName
     * @param EntityManager   $entityManager
     * @return CustomRepository<T>
     */
    private function instantiateCustomRepository(
        string        $repositoryClassName,
        string        $entityClassName,
        EntityManager $entityManager,
    ): CustomRepository {
        if (!class_exists($repositoryClassName)) {
            throw new LogicException(sprintf("Custom repository class %s for entity %s does not exist.",
                $repositoryClassName, $entityClassName));
        } elseif (!is_subclass_of($repositoryClassName, CustomRepository::class)) {
            throw new LogicException(sprintf("Custom repository class %s for entity %s does not extend %s.",
                $repositoryClassName, $entityClassName, CustomRepository::class));
        }

        // Like Doctrine ORM, we'll assume that constructor parameters are unchanged.
        // We cannot mark the constructor final because we want to allow extension.
        // If your custom repository does need additional constructor-injected dependencies,
        // return an instance from the EntityIoProvider.

        $repository = new $repositoryClassName($entityManager, $entityClassName);

        /** @var CustomRepository<T> */
        return $repository;
    }

    /**
     * @param Repository<Entity>   $repository
     * @param class-string<Entity> $entityClassName
     * @return void
     */
    private function validateRepositoryEntity(Repository $repository, string $entityClassName): void
    {
        if ($repository->getEntityClassName() !== $entityClassName) {
            throw new LogicException(sprintf("Custom repository %s is not for entity %s but for %s.",
                $repository::class, $entityClassName, $repository->getEntityClassName()));
        }
    }
}

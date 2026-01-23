<?php

namespace Marble\EntityManager\Repository;

use Marble\Entity\Entity;
use Marble\EntityManager\Contract\EntityIoProvider;
use Marble\EntityManager\Contract\EntityReader;
use Marble\EntityManager\EntityManager;
use Marble\Exception\LogicException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;

final class RepositoryFactory
{
    /**
     * @var array<string, Repository<Entity>>
     */
    private array $repositories = [];

    public function __construct(
        private readonly EntityIoProvider    $ioProvider,
        private readonly ?ContainerInterface $container = null,
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
     * @param class-string<T> $className
     * @return Repository<T>
     */
    private function createRepository(EntityManager $entityManager, string $className): Repository
    {
        $reader = $this->ioProvider->getReader($className);

        if ($reader === null) {
            $errorMessage = sprintf("No reader returned by %s for %s.", $this->ioProvider::class, $className);

            try {
                if ((new ReflectionClass($className))->isAbstract()) {
                    $errorMessage .= " Abstract entity classes require an entity reader just like concrete entity classes."
                        . " Specify the appropriate concrete child class when putting the entity data into the data collector.";
                }
            } catch (ReflectionException) {
                // never mind...
            }

            throw new LogicException($errorMessage);
        }

        if ($customRepositoryClass = $this->ioProvider->getCustomRepositoryClass($className)) {
            // Not validating the repository class here, because it may be an arbitrary container key.
            $repository = $this->createCustomRepository($customRepositoryClass, $reader, $entityManager);

            if (!is_subclass_of($repository, DefaultRepository::class)) {
                throw new LogicException(sprintf("Custom repository %s for entity %s does not extend %s.",
                    $repository::class, $className, DefaultRepository::class));
            }  elseif ($repository->getEntityClassName() !== $reader->getEntityClassName()) {
                throw new LogicException(sprintf("Custom repository %s is not for entity %s but for %s.",
                    $repository::class, $reader->getEntityClassName(), $repository->getEntityClassName()));
            }

            return $repository;
        }

        return new DefaultRepository($reader, $entityManager);
    }

    /**
     * @template T of Entity
     * @param class-string<Repository<T>> $repositoryClassName
     * @param EntityReader                $entityReader
     * @param EntityManager               $entityManager
     * @return Repository<T>
     */
    private function createCustomRepository(string $repositoryClassName, EntityReader $entityReader, EntityManager $entityManager): Repository
    {
        if ($this->container?->has($repositoryClassName)) {
            try {
                /** @psalm-suppress MixedAssignment */
                $repository = $this->container->get($repositoryClassName);

                if (!$repository instanceof Repository) {
                    throw new LogicException(sprintf("Class %s does not implement %s.", get_debug_type($repository), Repository::class));
                }

                /** @var Repository<T> $repository */
                return $repository;
            } catch (ContainerExceptionInterface $e) {
                throw new LogicException($e->getMessage(), 0, $e);
            }
        }

        if (!is_subclass_of($repositoryClassName, DefaultRepository::class)) {
            throw new LogicException(sprintf("Custom repository class %s for entity %s does not extend %s.",
                $repositoryClassName, $entityReader->getEntityClassName(), DefaultRepository::class));
        }

        // Like Doctrine ORM, we'll assume that constructor parameters are unchanged.
        // If your custom repository does need additional dependencies, use the container.

        return new $repositoryClassName($entityReader, $entityManager);
    }
}

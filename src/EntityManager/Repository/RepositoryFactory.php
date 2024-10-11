<?php

namespace Marble\EntityManager\Repository;

use Marble\Entity\Entity;
use Marble\EntityManager\Contract\EntityIoProvider;
use Marble\EntityManager\EntityManager;
use Marble\Exception\LogicException;
use ReflectionClass;
use ReflectionException;

class RepositoryFactory
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
     * @param EntityManager $entityManager
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
     * @param EntityManager $entityManager
     * @param class-string<T> $className
     * @return Repository<T>
     */
    protected function createRepository(EntityManager $entityManager, string $className): Repository
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

        return new DefaultRepository($reader, $entityManager);
    }
}

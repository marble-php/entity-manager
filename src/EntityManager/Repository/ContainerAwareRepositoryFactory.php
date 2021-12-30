<?php
namespace Marble\EntityManager\Repository;

use Marble\Entity\Entity;
use Marble\EntityManager\EntityManager;
use Marble\EntityManager\Contract\EntityIoProvider;
use Marble\EntityManager\Contract\EntityReader;
use Marble\Exception\LogicException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

class ContainerAwareRepositoryFactory extends DefaultRepositoryFactory
{
    public function __construct(EntityIoProvider $ioProvider, private ContainerInterface $container)
    {
        parent::__construct($ioProvider);
    }

    /**
     * @template T of Entity
     * @param class-string<Repository<T>> $repositoryClassName
     * @param EntityReader                $entityReader
     * @param EntityManager               $entityManager
     * @return Repository<T>
     * @throws ContainerExceptionInterface
     */
    protected function createCustomRepository(string $repositoryClassName, EntityReader $entityReader, EntityManager $entityManager): Repository
    {
        if ($this->container->has($repositoryClassName)) {
            /** @psalm-suppress MixedAssignment */
            $repository = $this->container->get($repositoryClassName);

            if (!$repository instanceof Repository) {
                throw new LogicException(sprintf("Class %s does not implement %s.", get_debug_type($repository), Repository::class));
            } elseif ($repository->getEntityClassName() !== $entityReader->getEntityClassName()) {
                throw new LogicException(sprintf("Repository %s is not for entity %s.", $repository::class, $entityReader->getEntityClassName()));
            }

            /** @var Repository<T> $repository */
            return $repository;
        }

        return parent::createCustomRepository($repositoryClassName, $entityReader, $entityManager);
    }
}

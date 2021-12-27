<?php
namespace Marble\EntityManager\Repository;

use Marble\EntityManager\EntityManager;
use Marble\EntityManager\Contract\EntityIoProvider;
use Marble\EntityManager\Contract\EntityReader;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

class ContainerAwareRepositoryFactory extends DefaultRepositoryFactory
{
    public function __construct(EntityIoProvider $ioProvider, private ContainerInterface $container)
    {
        parent::__construct($ioProvider);
    }

    /**
     * @throws ContainerExceptionInterface
     */
    protected function createCustomRepository(string $repositoryClassName, EntityReader $entityReader, EntityManager $entityManager): Repository
    {
        if ($this->container->has($repositoryClassName)) {
            return $this->container->get($repositoryClassName);
        }

        return parent::createCustomRepository($repositoryClassName, $entityReader, $entityManager);
    }
}

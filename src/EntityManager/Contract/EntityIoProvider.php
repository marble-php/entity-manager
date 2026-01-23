<?php
namespace Marble\EntityManager\Contract;

use Marble\Entity\Entity;
use Marble\EntityManager\Repository\DefaultRepository;
use Marble\EntityManager\Repository\Repository;

interface EntityIoProvider
{
    /**
     * @template T of Entity
     * @param class-string<T> $className
     * @return EntityReader<T>|null
     */
    public function getReader(string $className): ?EntityReader;

    /**
     * @template T of Entity
     * @param class-string<T> $className
     * @return EntityWriter<T>|null
     */
    public function getWriter(string $className): ?EntityWriter;

    /**
     * @template T of Entity
     * @param class-string<T> $className
     * @return DefaultRepository<T>|class-string<DefaultRepository<T>>|null
     */
    public function getCustomRepository(string $className): DefaultRepository|string|null;
}

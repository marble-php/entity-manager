<?php
namespace Marble\EntityManager\Contract;

use Marble\Entity\Entity;
use Marble\EntityManager\Repository\CustomRepository;

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
     * @return CustomRepository<T>|class-string<CustomRepository<T>>|null
     */
    public function getCustomRepository(string $className): CustomRepository|string|null;
}

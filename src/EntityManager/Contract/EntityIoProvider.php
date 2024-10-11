<?php
namespace Marble\EntityManager\Contract;

use Marble\Entity\Entity;

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
}

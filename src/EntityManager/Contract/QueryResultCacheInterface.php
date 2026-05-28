<?php

namespace Marble\EntityManager\Contract;

use Marble\Entity\Entity;
use Marble\EntityManager\Repository\Repository;

interface QueryResultCacheInterface
{
    /**
     * @template T of Entity
     * @param Repository<T> $repository
     * @param object|null   $query
     * @param bool          $one
     * @param T             ...$entities
     */
    public function save(Repository $repository, ?object $query, bool $one, Entity ...$entities): void;

    /**
     * @template T of Entity
     * @param Repository<T> $repository
     * @param object|null   $query
     * @param bool          $one
     * @return list<T>|null
     */
    public function get(Repository $repository, ?object $query, bool $one): ?array;

    public function clear(): void;
}

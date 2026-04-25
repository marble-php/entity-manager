<?php

declare(strict_types=1);

namespace Marble\EntityManager\Cache;

use Marble\Entity\Entity;
use Marble\EntityManager\Repository\Repository;

/**
 * @api
 */
class QueryResultCache
{
    /**
     * First-level keys are entity class names.
     * Second-level keys are hashes of serialized queries plus a suffix indicating one or many.
     *
     * @var array<class-string<Entity>, array<string, list<Entity>>>
     */
    private array $cache = [];

    /**
     * @template T of Entity
     * @param Repository<T> $repository
     * @param object|null   $query
     * @param bool          $one
     * @param T             ...$entities
     */
    public function save(Repository $repository, ?object $query, bool $one, Entity ...$entities): void
    {
        $this->cache[$repository->getEntityClassName()][$this->makeCacheKey($query, $one)] = array_values($entities);
    }

    /**
     * @template T of Entity
     * @param Repository<T> $repository
     * @param object|null   $query
     * @param bool          $one
     * @return list<T>|null
     */
    public function get(Repository $repository, ?object $query, bool $one): ?array
    {
        /** @var array<string, list<T>> $entityCache */
        $entityCache = $this->cache[$repository->getEntityClassName()] ?? [];

        return $entityCache[$this->makeCacheKey($query, $one)] ?? null;
    }

    private function makeCacheKey(?object $query, bool $one): string
    {
        if ($query === null) {
            return 'null|' . ($one ? '1' : 'm');
        }

        $array      = $this->toArrayRecursive($query);
        $serialized = serialize($array);

        return get_class($query) . '|' . md5($serialized) . '|' . ($one ? '1' : 'm');
    }

    /**
     * @return array<array-key, mixed>
     */
    private function toArrayRecursive(object $object): array
    {
        $array = (array) $object;

        foreach ($array as $key => $value) {
            if (is_object($value)) {
                $array[$key] = $this->toArrayRecursive($value);
            }
        }

        return $array;
    }

    public function clear(): void
    {
        $this->cache = [];
    }
}

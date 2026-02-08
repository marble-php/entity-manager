<?php

declare(strict_types=1);

namespace Marble\EntityManager\Cache;

use Marble\Entity\Entity;
use Marble\EntityManager\Repository\Repository;

/**
 * @template T of Entity
 * @api
 */
class QueryResultCache
{
    /**
     * First-level keys are entity class names.
     * Second-level keys are hashes of serialized queries plus a suffix indicating one or many.
     *
     * @var array<class-string<T>, array<string, list<T>>>
     */
    private array $cache = [];

    /**
     * @param Repository<T> $repository
     * @param object|null   $query
     * @param bool          $one
     * @param list<T>       $entities
     */
    public function save(Repository $repository, ?object $query, bool $one, Entity ...$entities): void
    {
        $this->cache[$repository->getEntityClassName()][$this->makeCacheKey($query, $one)] = array_values($entities);
    }

    /**
     * @param Repository<T> $repository
     * @param object|null   $query
     * @param bool          $one
     * @return list<T>|null
     */
    public function get(Repository $repository, ?object $query, bool $one): ?array
    {
        $entityCache = $this->cache[$repository->getEntityClassName()] ?? null;

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

    private function toArrayRecursive(object $object): array
    {
        $array = (array) $object;

        /** @psalm-suppress MixedAssignment */
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

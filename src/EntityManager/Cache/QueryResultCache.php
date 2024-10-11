<?php

namespace Marble\EntityManager\Cache;

use Marble\Entity\Entity;
use Marble\EntityManager\Repository\Repository;
use SebastianBergmann\Exporter\Exporter;

/**
 * @template T of Entity
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

    private Exporter $exporter;

    public function __construct()
    {
        $this->exporter = new Exporter();
    }

    /**
     * @param Repository<T> $repository
     * @param object|null $query
     * @param bool $one
     * @param list<T> $entities
     */
    public function save(Repository $repository, ?object $query, bool $one, Entity ...$entities): void
    {
        $this->cache[$repository->getEntityClassName()][$this->makeCacheKey($query, $one)] = array_values($entities);
    }

    /**
     * @param Repository<T> $repository
     * @param object|null $query
     * @param bool $one
     * @return list<T>|null
     */
    public function get(Repository $repository, ?object $query, bool $one): ?array
    {
        $entityCache = $this->cache[$repository->getEntityClassName()] ?? null;

        return $entityCache[$this->makeCacheKey($query, $one)] ?? null;
    }

    private function makeCacheKey(?object $query, bool $one): string
    {
        $export = $this->exporter->export($query, 2);

        return md5($export) . $one;
    }

    public function clear(): void
    {
        $this->cache = [];
    }
}

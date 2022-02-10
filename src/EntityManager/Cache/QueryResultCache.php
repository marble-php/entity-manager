<?php
namespace Marble\EntityManager\Cache;

use Marble\Entity\Entity;
use Marble\EntityManager\Repository\Repository;
use SebastianBergmann\Exporter\Exporter;

class QueryResultCache
{
    private Exporter $exporter;

    public function __construct()
    {
        $this->exporter = new Exporter();
    }

    /**
     * First-level keys are entity class names.
     * Second-level keys are hashes of serialized queries plus a suffix indicating one or many.
     *
     * @var array<class-string, array<string, list<Entity>>>
     */
    private array $cache = [];

    /**
     * @template T of Entity
     * @param Repository<T> $repository
     * @param object|null   $query
     * @param bool          $one
     * @param list<T>       $entities
     */
    public function save(Repository $repository, ?object $query, bool $one, Entity ...$entities): void
    {
        /** @var array<class-string<T>, array<string, list<T>>> $this->cache */
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
        /** @var array<class-string<T>, array<string, list<T>>> $this->cache */
        return $this->cache[$repository->getEntityClassName()][$this->makeCacheKey($query, $one)] ?? null;
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

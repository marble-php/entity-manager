<?php

namespace Marble\Tests\EntityManager\Cache;

use Marble\Entity\Entity;
use Marble\EntityManager\Cache\QueryResultCache;
use Marble\EntityManager\Read\Criteria;
use Marble\EntityManager\Read\SortDirection;
use Marble\EntityManager\Repository\Repository;
use Marble\Tests\EntityManager\TestImpl\Query\SomeQuery;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class QueryResultCacheTest extends MockeryTestCase
{
    public function testCacheHitByValue(): void
    {
        $cache = new QueryResultCache();
        $repo  = Mockery::mock(Repository::class);
        $repo->allows('getEntityClassName')->andReturn(Entity::class);

        $query  = new Criteria(['foo' => 'bar']);
        $result = $cache->get($repo, $query, true);
        $this->assertNull($result);

        $cache->save($repo, $query, true, $entity1 = Mockery::mock(Entity::class));
        $result = $cache->get($repo, $query, true);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame($entity1, $result[0]);

        $query  = new Criteria(['foo' => 'bar']); // new instance, same values
        $result = $cache->get($repo, $query, true);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame($entity1, $result[0]); // still a hit

        $query  = new Criteria(['foo' => 'bar'], sortDirection: SortDirection::DESC);
        $result = $cache->get($repo, $query, true);
        $this->assertNull($result);
    }

    public function testWeirdQueries(): void
    {
        $cache = new QueryResultCache();
        $repo  = Mockery::mock(Repository::class);
        $repo->allows('getEntityClassName')->andReturn(Entity::class);

        $query  = new SomeQuery(other: new SomeQuery());
        $result = $cache->get($repo, $query, true);
        $this->assertNull($result);

        $cache->save($repo, $query, true, $entity1 = Mockery::mock(Entity::class));
        $result = $cache->get($repo, $query, true);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame($entity1, $result[0]);

        $query  = new SomeQuery(other: new SomeQuery()); // new instances, same values
        $result = $cache->get($repo, $query, true);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame($entity1, $result[0]); // still a hit

        $query  = new SomeQuery(other: new SomeQuery(other: new SomeQuery()));
        $result = $cache->get($repo, $query, true);
        $this->assertNull($result);
        $cache->save($repo, $query, true, $entity2 = Mockery::mock(Entity::class));
        $result = $cache->get($repo, $query, true);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame($entity2, $result[0]);

        $query  = $query->resource = null; // changing resource to null should be considered a difference, so no cache hit.
        $result = $cache->get($repo, $query, true);
        $this->assertNull($result);
    }
}

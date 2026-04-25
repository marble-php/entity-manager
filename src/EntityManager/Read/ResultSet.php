<?php

declare(strict_types=1);

namespace Marble\EntityManager\Read;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Marble\Entity\Entity;
use Traversable;

/**
 * @template T of Entity
 * @implements IteratorAggregate<array-key, ResultRow>
 */
final class ResultSet implements Countable, IteratorAggregate
{
    /** @var list<ResultRow<T>> */
    private array $results;

    /**
     * @param ResultRow<T> ...$results
     */
    public function __construct(ResultRow ...$results)
    {
        $this->results = array_values($results);
    }

    #[\Override]
    public function count(): int
    {
        return count($this->results);
    }

    /**
     * @return ResultRow<T>|null
     */
    public function first(): ?ResultRow
    {
        return empty($this->results) ? null : reset($this->results);
    }

    /**
     * @return Traversable<array-key, ResultRow<T>>
     */
    #[\Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->results);
    }
}

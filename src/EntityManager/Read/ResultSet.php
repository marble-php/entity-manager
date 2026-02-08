<?php

declare(strict_types=1);

namespace Marble\EntityManager\Read;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @template-implements IteratorAggregate<array-key, ResultRow>
 */
final class ResultSet implements Countable, IteratorAggregate
{
    /** @var ResultRow[] */
    private array $results;

    public function __construct(ResultRow ...$results)
    {
        $this->results = $results;
    }

    #[\Override]
    public function count(): int
    {
        return count($this->results);
    }

    public function first(): ?ResultRow
    {
        return empty($this->results) ? null : reset($this->results);
    }

    /**
     * @return Traversable<array-key, ResultRow>
     */
    #[\Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->results);
    }
}

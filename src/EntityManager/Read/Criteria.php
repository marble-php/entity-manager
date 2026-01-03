<?php

namespace Marble\EntityManager\Read;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use Marble\Exception\LogicException;
use Traversable;

/**
 * @codeCoverageIgnore
 * @template-implements ArrayAccess<string, mixed>
 * @template-implements IteratorAggregate<string, mixed>
 * @api
 */
class Criteria implements ArrayAccess, IteratorAggregate
{
    /**
     * @param array<string, mixed> $criteria
     * @param mixed $sortBy
     * @param SortDirection $sortDirection
     */
    public function __construct(
        private array         $criteria,
        private mixed         $sortBy = null,
        private SortDirection $sortDirection = SortDirection::ASC,
    ) {
    }

    public function getSortBy(): mixed
    {
        return $this->sortBy;
    }

    public function setSortBy(mixed $sortBy): void
    {
        $this->sortBy = $sortBy;
    }

    public function getSortDirection(): SortDirection
    {
        return $this->sortDirection;
    }

    public function setSortDirection(SortDirection $sortDirection): void
    {
        $this->sortDirection = $sortDirection;
    }

    #[\Override]
    public function offsetExists(mixed $offset): bool
    {
        $offset = $this->parseOffset($offset);

        return isset($this->criteria[$offset]);
    }

    #[\Override]
    public function offsetGet(mixed $offset): mixed
    {
        $offset = $this->parseOffset($offset);

        /** @psalm-suppress MixedReturnStatement */
        return $this->criteria[$offset];
    }

    #[\Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $offset = $this->parseOffset($offset);

        $this->criteria[$offset] = $value;
    }

    #[\Override]
    public function offsetUnset(mixed $offset): void
    {
        $offset = $this->parseOffset($offset);

        unset($this->criteria[$offset]);
    }

    private function parseOffset(mixed $offset): string
    {
        if (!is_string($offset)) {
            throw new LogicException(sprintf("Criteria key must be string (%s provided).", get_debug_type($offset)));
        }

        return $offset;
    }

    #[\Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->criteria);
    }
}

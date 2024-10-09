<?php

namespace Marble\EntityManager\Read;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use Marble\Exception\LogicException;
use Traversable;

/**
 * @codeCoverageIgnore
 */
class Criteria implements ArrayAccess, IteratorAggregate
{
    private mixed $sortBy = null;
    private SortDirection $sortDirection = SortDirection::ASC;

    /**
     * @param array<string, mixed> $criteria
     */
    public function __construct(private array $criteria)
    {
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

    public static function make(array $criteria, mixed $sortBy = null, SortDirection $sortDirection = SortDirection::ASC): static
    {
        $criteria = new static($criteria);

        $criteria->setSortBy($sortBy);
        $criteria->setSortDirection($sortDirection);

        return $criteria;
    }

    public function offsetExists(mixed $offset): bool
    {
        $offset = $this->parseOffset($offset);

        return isset($this->criteria[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        $offset = $this->parseOffset($offset);

        /** @psalm-suppress MixedReturnStatement */
        return $this->criteria[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $offset = $this->parseOffset($offset);

        $this->criteria[$offset] = $value;
    }

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

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->criteria);
    }
}

<?php
namespace Marble\EntityManager\Read;

use ArrayAccess;

/**
 * @codeCoverageIgnore
 */
class Criteria implements ArrayAccess
{
    public function __construct(private array $criteria = [])
    {
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->criteria[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->criteria[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->criteria[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->criteria[$offset]);
    }
}

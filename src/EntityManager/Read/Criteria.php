<?php
namespace Marble\EntityManager\Read;

use ArrayAccess;
use Marble\Exception\LogicException;

/**
 * @codeCoverageIgnore
 */
class Criteria implements ArrayAccess
{
    /**
     * @param array<string, mixed> $criteria
     */
    public function __construct(private array $criteria)
    {
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
}

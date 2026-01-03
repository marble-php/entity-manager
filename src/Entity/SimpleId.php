<?php
namespace Marble\Entity;

/**
 * @api
 */
class SimpleId implements Identifier
{
    public function __construct(private readonly string $id)
    {
    }

    #[\Override]
    public function equals(mixed $other): bool
    {
        return $other instanceof static && $this->id === (string) $other;
    }

    public function __toString(): string
    {
        return $this->id;
    }
}

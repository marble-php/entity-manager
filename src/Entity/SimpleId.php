<?php
namespace Marble\Entity;

class SimpleId implements Identifier
{
    public function __construct(private string $id)
    {
    }

    public function equals(mixed $other): bool
    {
        return $other instanceof static && $this->id === (string) $other;
    }

    public function __toString(): string
    {
        return $this->id;
    }
}

<?php
namespace Marble\Entity;

use Stringable;

interface Identifier extends Stringable
{
    public function equals(mixed $other): bool;
}

<?php

declare(strict_types=1);

namespace Marble\Entity;

use Stringable;

interface Identifier extends Stringable
{
    public function equals(mixed $other): bool;
}

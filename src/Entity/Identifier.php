<?php

declare(strict_types=1);

namespace Marble\Entity;

use Stringable;

/**
 * @template T of Entity
 */
interface Identifier extends Stringable
{
    public function equals(mixed $other): bool;
}

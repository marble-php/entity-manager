<?php

declare(strict_types=1);

namespace Marble\EntityManager\Write;

use Marble\Entity\Entity;

/**
 * @template U of Entity
 * @extends Persistable<U>
 */
interface HasChanged extends Persistable
{
    /**
     * @return array<string, mixed>
     */
    public function getOriginalData(): array;

    /**
     * @return list<string>
     */
    public function getChangedProperties(): array;
}

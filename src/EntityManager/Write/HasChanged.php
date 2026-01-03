<?php
namespace Marble\EntityManager\Write;

use Marble\Entity\Entity;

/**
 * @extends Persistable<Entity>
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

<?php

declare(strict_types=1);

namespace Marble\EntityManager\Event;

use Marble\Entity\Entity;

class EntityPersistedEvent extends EntityEvent
{
    /**
     * @param Entity               $entity
     * @param array<string, mixed> $data
     * @param list<string>         $changedProperties
     */
    public function __construct(
        Entity $entity,
        private readonly array $data,
        private readonly array $changedProperties,
    ) {
        parent::__construct($entity);
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return list<string>
     */
    public function getChangedProperties(): array
    {
        return $this->changedProperties;
    }
}

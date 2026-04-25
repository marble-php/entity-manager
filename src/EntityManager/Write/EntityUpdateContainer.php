<?php

declare(strict_types=1);

namespace Marble\EntityManager\Write;

use Marble\Entity\Entity;

/**
 * @template V of Entity
 * @extends EntityWriteContainer<V>
 * @implements HasChanged<V>
 */
final class EntityUpdateContainer extends EntityWriteContainer implements HasChanged
{
    /**
     * @param V                    $entity
     * @param array<string, mixed> $data
     * @param array<string, mixed> $originalData
     * @param list<string>         $changedProperties
     */
    public function __construct(
        Entity                 $entity,
        array                  $data,
        private readonly array $originalData,
        private readonly array $changedProperties,
    ) {
        parent::__construct($entity, $data);
    }

    #[\Override]
    public function getOriginalData(): array
    {
        return $this->originalData;
    }

    #[\Override]
    public function getChangedProperties(): array
    {
        return $this->changedProperties;
    }
}

<?php

namespace Marble\EntityManager\Write;

use Marble\Entity\Entity;

/**
 * @template T of Entity
 * @extends EntityWriteContainer<T>
 */
final class EntityUpdateContainer extends EntityWriteContainer implements HasChanged
{
    /**
     * @param T $entity
     * @param array<string, mixed> $data
     * @param array<string, mixed> $originalData
     * @param array<array-key, string> $changedProperties
     */
    public function __construct(
        Entity                 $entity,
        array                  $data,
        private readonly array $originalData,
        private readonly array $changedProperties
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
        return array_values($this->changedProperties);
    }
}

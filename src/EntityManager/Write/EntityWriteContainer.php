<?php

declare(strict_types=1);

namespace Marble\EntityManager\Write;

use Marble\Entity\Entity;

/**
 * @template T of Entity
 * @implements Persistable<T>
 */
class EntityWriteContainer implements Persistable
{
    /**
     * @param T                    $entity
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly Entity $entity,
        private readonly array  $data,
    ) {
    }

    #[\Override]
    public function getEntity(): Entity
    {
        return $this->entity;
    }

    #[\Override]
    public function getData(): array
    {
        return $this->data;
    }
}

<?php
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

    public function getEntity(): Entity
    {
        return $this->entity;
    }

    public function getData(): array
    {
        return $this->data;
    }
}

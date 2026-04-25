<?php

declare(strict_types=1);

namespace Marble\EntityManager\Write;

use Marble\Entity\Entity;

/**
 * @template Q of Entity
 * @implements Persistable<Q>
 */
class EntityWriteContainer implements Persistable
{
    /**
     * @param Q                    $entity
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

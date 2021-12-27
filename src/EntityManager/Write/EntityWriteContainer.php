<?php
namespace Marble\EntityManager\Write;

use Marble\Entity\Entity;

class EntityWriteContainer implements Persistable
{
    public function __construct(private Entity $entity, private array $data)
    {
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

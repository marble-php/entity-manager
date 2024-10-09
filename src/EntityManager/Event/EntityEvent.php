<?php
namespace Marble\EntityManager\Event;

use Marble\Entity\Entity;

abstract class EntityEvent extends Event
{
    public function __construct(private readonly Entity $entity)
    {
    }

    public function getEntity(): Entity
    {
        return $this->entity;
    }
}

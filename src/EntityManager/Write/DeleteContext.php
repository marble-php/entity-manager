<?php
namespace Marble\EntityManager\Write;

use Marble\Entity\Entity;

interface DeleteContext
{
    public function markRemoved(Entity $entity): void;
}

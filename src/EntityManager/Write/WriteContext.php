<?php
namespace Marble\EntityManager\Write;

use Marble\Entity\Entity;

interface WriteContext extends DeleteContext
{
    public function markPersisted(Entity $entity): void;

    public function queueRemoval(Entity $entity): void;

    public function cancelRemoval(Entity $entity): void;
}

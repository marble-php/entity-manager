<?php
namespace Marble\EntityManager\Contract;

use Marble\Entity\Entity;
use Marble\EntityManager\Write\DeleteContext;
use Marble\EntityManager\Exception\EntitySkippedException;
use Marble\EntityManager\Write\WriteContext;
use Marble\EntityManager\Write\Persistable;

/**
 * @template T of Entity
 */
interface EntityWriter
{
    /**
     * @param Persistable<T> $persistable
     * @param WriteContext   $context
     * @throws EntitySkippedException
     */
    public function write(Persistable $persistable, WriteContext $context): void;

    /**
     * @param T             $entity
     * @param DeleteContext $context
     * @throws EntitySkippedException
     */
    public function delete(Entity $entity, DeleteContext $context): void;
}

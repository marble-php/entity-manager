<?php

namespace Marble\EntityManager\Read;

use Marble\Entity\Entity;
use Marble\Entity\EntityReference;
use Marble\EntityManager\Exception\EntityNotFoundException;
use Marble\EntityManager\Repository\Repository;

interface ReadContext
{
    /**
     * @template T of Entity
     * @param class-string<T> $className
     * @return Repository<T>
     */
    public function getRepository(string $className): Repository;

    /**
     * @template T of Entity
     * @param EntityReference<T> $reference
     * @return T
     * @throws EntityNotFoundException
     */
    public function fetch(EntityReference $reference): Entity;
}

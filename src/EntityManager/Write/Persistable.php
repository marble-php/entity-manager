<?php
namespace Marble\EntityManager\Write;

use Marble\Entity\Entity;

/**
 * @template T of Entity
 */
interface Persistable
{
    /**
     * @return T
     */
    public function getEntity(): Entity;

    /**
     * @return array<string, mixed>
     */
    public function getData(): array;
}

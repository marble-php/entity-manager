<?php
namespace Marble\EntityManager\Contract;

use Marble\Entity\Entity;
use Marble\EntityManager\Read\DataCollector;
use Marble\EntityManager\Read\ReadContext;

/**
 * @template T of Entity
 */
interface EntityReader
{
    /**
     * @return class-string<T>
     */
    public static function getEntityClassName(): string;

    public function read(?object $query, DataCollector $dataCollector, ReadContext $context): void;
}

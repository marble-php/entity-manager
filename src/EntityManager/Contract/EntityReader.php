<?php

declare(strict_types=1);

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

    /**
     * @param DataCollector<T> $dataCollector
     */
    public function read(?object $query, DataCollector $dataCollector, ReadContext $context): void;
}

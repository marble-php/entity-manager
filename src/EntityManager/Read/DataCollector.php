<?php

declare(strict_types=1);

namespace Marble\EntityManager\Read;

use Marble\Entity\Entity;
use Marble\Entity\Identifier;

/**
 * @template T of Entity
 */
interface DataCollector
{
    /**
     * @param Identifier<T>        $identifier
     * @param array<string, mixed> $data
     * @param class-string<T>|null $subclass
     */
    public function put(Identifier $identifier, array $data, ?string $subclass = null): void;

    /**
     * @param Identifier<T> $identifier
     * @param string        $propertyName
     * @param mixed         $value
     * @return void
     */
    public function putProperty(Identifier $identifier, string $propertyName, mixed $value): void;

    /**
     * @param Identifier<T>   $identifier
     * @param class-string<T> $class
     */
    public function putClass(Identifier $identifier, string $class): void;
}

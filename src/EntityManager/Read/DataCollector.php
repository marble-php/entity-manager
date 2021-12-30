<?php
namespace Marble\EntityManager\Read;

use Marble\Entity\Entity;
use Marble\Entity\Identifier;

interface DataCollector
{
    /**
     * @param array<string, mixed>      $data
     * @param class-string<Entity>|null $subclass
     */
    public function put(Identifier $identifier, array $data, ?string $subclass = null): void;

    public function putProperty(Identifier $identifier, string $propertyName, mixed $value): void;

    /**
     * @param Identifier           $identifier
     * @param class-string<Entity> $class
     */
    public function putClass(Identifier $identifier, string $class): void;
}

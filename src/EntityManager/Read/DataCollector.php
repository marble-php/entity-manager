<?php
namespace Marble\EntityManager\Read;

use Marble\Entity\Identifier;

interface DataCollector
{
    public function put(Identifier $identifier, array $data, ?string $subclass = null): void;

    public function putProperty(Identifier $identifier, string $propertyName, mixed $value): void;

    public function putClass(Identifier $identifier, string $class): void;
}

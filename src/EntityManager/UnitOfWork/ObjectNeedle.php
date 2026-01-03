<?php

namespace Marble\EntityManager\UnitOfWork;

use Marble\Exception\LogicException;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\VarExporter\Hydrator;

final class ObjectNeedle
{
    /** @var array<class-string, ReflectionClass> */
    private array $classReflections = [];

    /**
     * @param array<string, mixed> $data
     */
    public function hydrate(object $object, array $data): void
    {
        $propertiesByClass = [];
        $reflection        = $this->getReflection($object);

        do {
            $className = $reflection->getName();

            foreach ($reflection->getProperties() as $property) {
                $propertyName = $property->getName();

                if ($property->getDeclaringClass()->getName() === $className) {
                    if (array_key_exists($propertyName, $data)) {
                        /** @psalm-suppress MixedAssignment */
                        $propertiesByClass[$className][$propertyName] = $data[$propertyName];
                    } elseif ($property->getType()?->allowsNull()) {
                        $propertiesByClass[$className][$propertyName] = null;
                    }
                }
            }
        } while ($reflection = $reflection->getParentClass());

        Hydrator::hydrate($object, [], $propertiesByClass);
    }

    private function getReflection(object $object): ReflectionClass
    {
        try {
            return $this->classReflections[$object::class] ??= new ReflectionClass($object::class);
        } catch (ReflectionException $e) {
            throw new LogicException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function extract(object $object): array
    {
        $reflection = $this->getReflection($object);
        $result     = [];

        do {
            foreach ($reflection->getProperties() as $property) {
                if ($property->isInitialized($object)) {
                    /** @psalm-suppress MixedAssignment */
                    $result[$property->getName()] = $property->getValue($object);
                }
            }
        } while ($reflection = $reflection->getParentClass());

        return $result;
    }
}

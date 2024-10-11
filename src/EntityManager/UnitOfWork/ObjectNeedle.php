<?php
namespace Marble\EntityManager\UnitOfWork;

use GeneratedHydrator\Configuration;
use Laminas\Hydrator\HydratorInterface;
use Marble\Exception\LogicException;
use Throwable;

class ObjectNeedle
{
    /**
     * @var array<class-string, HydratorInterface>
     */
    private array $hydrators = [];

    private function getHydrator(object $object): HydratorInterface
    {
        $className = $object::class;

        if (isset($this->hydrators[$className])) {
            return $this->hydrators[$className];
        }

        $config = new Configuration($className);

        // TODO: move away from ocramius/generated-hydrator, will not support PHP 8.4
        return $this->hydrators[$className] = $config->createFactory()->getHydrator();
    }

    public function hydrate(object $object, array $data): void
    {
        $this->getHydrator($object)->hydrate($data, $object);
    }

    /**
     * @return array<string, mixed>
     * @psalm-suppress MixedReturnTypeCoercion
     */
    public function extract(object $object): array
    {
        return $this->getHydrator($object)->extract($object);
    }
}

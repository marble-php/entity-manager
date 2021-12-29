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

        return $this->hydrators[$className] = $config->createFactory()->getHydrator();
    }

    public function hydrate(object $object, array $data): void
    {
        try {
            $this->getHydrator($object)->hydrate($data, $object);
        } catch (Throwable $throwable) {
            preg_match('/Typed property (.*) must not be accessed before initialization/', $throwable->getMessage(), $matches);

            $message = count($matches) < 2 ? $throwable->getMessage()
                : sprintf("Entity property %s is typed, required and without default, but the hydrator did not receive a value for it.", $matches[1]);

            throw new LogicException($message, 0, $throwable);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function extract(object $object): array
    {
        return $this->getHydrator($object)->extract($object);
    }
}

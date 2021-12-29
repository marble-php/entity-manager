<?php
namespace Marble\EntityManager\UnitOfWork;

use Closure;
use Marble\Entity\Entity;
use Marble\Exception\LogicException;
use stdClass;

class ReferenceReplacer
{
    public function replaceReference(Entity $entity, array $path, Entity $reference): void
    {
        if (empty($path)) {
            throw new LogicException(sprintf("Path to replace %s in %s must not be empty.", $reference::class, $entity::class));
        }

        $target =& $entity;

        foreach ($path as $i => $segment) {
            if (is_object($target)) {
                if ($target instanceof stdClass) {
                    // Property must be public.
                    $target =& $target->{$segment};
                } else {
                    $target =& $this->getPropertyByReference($target, $segment);
                }
            } elseif (is_array($target) && isset($target[$segment])) {
                $target =& $target[$segment];
            } else {
                throw new LogicException(sprintf("No object or array found at $.%s in entity %s.", implode('.', array_slice($path, 0, $i)), $entity::class));
            }
        }

        if (!$target instanceof $reference) {
            throw new LogicException(sprintf("Cannot replace %s at $.%s in entity %s with %s.",
                get_debug_type($target), implode('.', $path), $entity::class, $reference::class));
        } elseif ($target->getId() !== null && $reference->getId() !== null && !$target->getId()->equals($reference->getId())) {
            throw new LogicException(sprintf("Cannot replace %s at $.%s in entity %s with %s.",
                LogicException::strEntity($target), implode('.', $path), $entity::class, LogicException::strEntity($reference)));
        }

        // Now that $target is aliasing the variable at $path that holds the subentity,
        // changing the value of target also changes the value of that variable.
        $target = $reference;
    }

    public function &getPropertyByReference(object $object, string $property): mixed
    {
        $getter = function &() use ($property): mixed {
            return $this->{$property};
        };

        /** @var callable $bound */
        $bound = Closure::bind($getter, $object, $object);

        return $bound();
    }
}

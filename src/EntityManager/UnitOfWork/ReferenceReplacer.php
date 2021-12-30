<?php
namespace Marble\EntityManager\UnitOfWork;

use Closure;
use Marble\Entity\Entity;
use Marble\Exception\LogicException;
use stdClass;

class ReferenceReplacer
{
    /**
     * @psalm-suppress MixedAssignment
     */
    public function replaceReference(Entity $entity, array $path, Entity $reference): void
    {
        if (empty($path)) {
            throw new LogicException(sprintf("Path to replace %s in %s must not be empty.", $reference::class, LogicException::strEntity($entity)));
        }

        $target =& $entity;

        foreach (array_values($path) as $index => $segment) {
            if (is_object($target)) {
                if (!is_string($segment)) {
                    throw new LogicException(sprintf("Property name of %s in %s must be string (%s provided).",
                        get_debug_type($target), LogicException::strEntity($entity), get_debug_type($segment)));
                } elseif ($target instanceof stdClass) {
                    // Property must be public.
                    $target =& $target->{$segment};
                } else {
                    $target =& $this->getPropertyByReference($target, $segment);
                }
            } elseif (is_array($target)) {
                if (!is_string($segment) && !is_int($segment)) {
                    throw new LogicException(sprintf("Key of %s in %s must be string or int (%s provided).",
                        get_debug_type($target), LogicException::strEntity($entity), get_debug_type($segment)));
                } elseif (!isset($target[$segment])) {
                    throw new LogicException(sprintf("Key of %s must be string (%s provided).", get_debug_type($target), $segment));
                }

                $target =& $target[$segment];
            } else {
                /** @var list<string|int> $path */
                throw new LogicException(sprintf("No object or array found at $.%s in %s.",
                    implode('.', array_slice($path, 0, $index)), LogicException::strEntity($entity)));
            }
        }

        /** @var list<string|int> $path */

        if (!$target instanceof $reference) {
            throw new LogicException(sprintf("Cannot replace %s at $.%s in entity %s with %s.",
                get_debug_type($target), implode('.', $path), $entity::class, $reference::class));
        }

        /** @var Entity $target */
        $targetId    = $target->getId();
        $referenceId = $reference->getId();

        if ($targetId !== null && $referenceId !== null && !$targetId->equals($referenceId)) {
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

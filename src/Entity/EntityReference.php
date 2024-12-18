<?php
namespace Marble\Entity;

use Marble\Exception\LogicException;

/**
 * @template T of Entity
 */
class EntityReference
{
    /**
     * @param class-string<T> $className
     */
    public function __construct(
        private readonly string $className,
        private readonly Identifier $identifier,
    ) {
        if (!class_exists($className)) {
            throw new LogicException(sprintf("Class %s does not exist.", $className));
        } elseif (!is_subclass_of($className, Entity::class)) {
            throw new LogicException(sprintf("Class %s does not implement the %s interface.", $className, Entity::class));
        }
    }

    /**
     * @return class-string<T>
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    public function getId(): Identifier
    {
        return $this->identifier;
    }

    public function refersTo(string|Entity $entity): bool
    {
        if (is_string($entity)) {
            // Check if our class is equal to, or a subclass of, the given class.
            /** @var class-string $entity */
            return is_a($this->className, $entity, true);
        } else {
            // Our class may be an abstract or concrete superclass of the actual entity.
            return is_a($entity, $this->className) && $this->getId()->equals($entity->getId());
        }
    }

    /**
     * @template S of Entity
     * @param S $entity
     * @return EntityReference<S>
     */
    public static function create(Entity $entity): self
    {
        $id = $entity->getId();

        if ($id === null) {
            throw new LogicException(sprintf("Identifier required for entity reference to %s.", $entity::class));
        }

        return new self($entity::class, $id);
    }
}

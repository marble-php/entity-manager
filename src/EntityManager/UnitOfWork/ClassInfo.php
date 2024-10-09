<?php
namespace Marble\EntityManager\UnitOfWork;

use Marble\Entity\Entity;
use Marble\Exception\LogicException;

/**
 * @template T of Entity
 */
class ClassInfo
{
    /**
     * @var list<class-string<Entity>>|null
     */
    private ?array $parentClasses = null;

    /**
     * @param class-string<T> $className
     */
    public function __construct(private readonly string $className)
    {
        if (!is_subclass_of($className, Entity::class)) {
            throw new LogicException(sprintf("Class %s does not implement the %s interface.", $this->className, Entity::class));
        }
    }

    /**
     * @return class-string<T>
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * List of parent classes that implement the Entity interface.
     * Direct parent first, then grandparent, et cetera. Excludes the original class itself.
     * Note that no two entities that share a common Entity ancestor should ever have the same identifier.
     *
     * @return list<class-string<Entity>>
     */
    public function getParentClasses(): array
    {
        if ($this->parentClasses === null) {
            $this->parentClasses = [];

            foreach (class_parents($this->className) as $parent) {
                if (!is_subclass_of($parent, Entity::class)) {
                    break; // Stop at the nearest ancestor that does not implement the Entity interface.
                }

                $this->parentClasses[] = $parent;
            }
        }

        return $this->parentClasses;
    }
}

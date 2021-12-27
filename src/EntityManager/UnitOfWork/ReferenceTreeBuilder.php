<?php
namespace Marble\EntityManager\UnitOfWork;

use Marble\Entity\Entity;
use Marble\Exception\LogicException;

class ReferenceTreeBuilder extends ReferenceFinder
{
    /**
     * Evaluation stack. As the evaluation cascades through associated entities,
     * this array represents the path from the "root" entity down to the entity
     * currently being evaluated.
     *
     * @var array<int, Entity>
     */
    private array $evaluating = [];

    /**
     * This property makes the instance non-reusable.
     *
     * @var array<int, ReferenceTree>
     */
    private array $trees = [];

    /**
     * @param null|callable(Entity): bool $ignore
     * @return array<int, ReferenceTree>
     */
    public function makeTrees(Entity $entity, ?callable $ignore = null): array
    {
        $this->buildTree($entity, $ignore);

        return $this->trees;
    }

    /**
     * @param null|callable(Entity): bool $ignore
     */
    public function buildTree(Entity $entity, ?callable $ignore = null): ReferenceTree
    {
        $oid = spl_object_id($entity);

        if (array_key_exists($oid, $this->evaluating)) {
            // Take the end part of the stack that forms the reference circle.
            $entities = array_slice($this->evaluating, array_search($entity, array_values($this->evaluating)));
            $path     = array_map(fn(Entity $entity) => $entity::class . ':' . $entity->getId(), [...$entities, $entity]);

            throw new LogicException(sprintf("Circular entity association detected: %s.", implode(" -> ", $path)));
        }

        $this->evaluating[$oid] = $entity;

        try {
            if (array_key_exists($oid, $this->trees)) {
                return $this->trees[$oid];
            }

            $references = $this->collect($entity, function (Entity $subentity) use ($ignore): ?ReferenceTree {
                if (!is_callable($ignore) || !$ignore($subentity)) {
                    return $this->buildTree($subentity, $ignore);
                } else {
                    return null;
                }
            });

            return $this->trees[$oid] = new ReferenceTree($entity, ...$references);
        } finally {
            unset($this->evaluating[$oid]);
        }
    }
}
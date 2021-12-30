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
            $offset = array_search($entity, array_values($this->evaluating), true);

            assert($offset !== false);

            $entities = array_slice($this->evaluating, $offset);
            $path     = array_map(fn(Entity $entity) => LogicException::strEntity($entity), [...$entities, $entity]);

            throw new LogicException(sprintf("Circular entity association detected: %s.", implode(" -> ", $path)));
        }

        $this->evaluating[$oid] = $entity;

        try {
            if (array_key_exists($oid, $this->trees)) {
                return $this->trees[$oid];
            }

            $this->trees[$oid] = $tree = new ReferenceTree($entity);
            $references        = $this->collect($entity, function (Entity $subentity) use ($ignore): ?ReferenceTree {
                if (!is_callable($ignore) || !$ignore($subentity)) {
                    return $this->buildTree($subentity, $ignore);
                } else {
                    return null;
                }
            });

            foreach ($references as $path => $referenceTree) {
                $tree->putReference($path, $referenceTree);
            }

            return $tree;
        } finally {
            unset($this->evaluating[$oid]);
        }
    }
}

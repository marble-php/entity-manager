<?php
namespace Marble\EntityManager\UnitOfWork;

use Marble\Entity\Entity;

class FlushOrderCalculator
{
    /**
     * @param ReferenceTree ...$nodes
     * @return array<int, Entity>
     */
    public function calculate(ReferenceTree ...$nodes): array
    {
        $sortedEntities = [];

        while (!empty($nodes)) {
            foreach ($nodes as $index => $node) {
                foreach ($node->getReferences() as $reference) {
                    // We only need to look 1 level deep.

                    if (!array_key_exists(spl_object_id($reference->getEntity()), $sortedEntities)) {
                        // This node's entity has a reference to another entity that is not ranked yet.
                        continue 2;
                    }
                }

                // This node's entity either contains no entity associations, or all of them have been ranked,
                // so that they will be persisted before this entity.

                $sortedEntities[spl_object_id($node->getEntity())] = $node->getEntity();
                unset($nodes[$index]);
            }
        }

        return $sortedEntities;
    }
}

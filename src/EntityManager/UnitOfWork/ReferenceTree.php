<?php
namespace Marble\EntityManager\UnitOfWork;

use Marble\Entity\Entity;

class ReferenceTree
{
    /**
     * @var list<ReferenceTree>
     */
    private array $references;

    public function __construct(private Entity $entity, ReferenceTree ...$references)
    {
        $this->references = array_values($references);
    }

    public function getEntity(): Entity
    {
        return $this->entity;
    }

    /**
     * @return list<ReferenceTree>
     */
    public function getReferences(): array
    {
        return $this->references;
    }
}

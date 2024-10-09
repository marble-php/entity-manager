<?php
namespace Marble\EntityManager\UnitOfWork;

use Marble\Entity\Entity;

class ReferenceTree
{
    /**
     * @var array<string, ReferenceTree>
     */
    private array $references = [];

    public function __construct(private readonly Entity $entity)
    {
    }
    
    public function putReference(string $path, ReferenceTree $tree): void
    {
        $this->references[$path] = $tree;
    }

    public function getEntity(): Entity
    {
        return $this->entity;
    }

    /**
     * @return array<string, ReferenceTree>
     */
    public function getReferences(): array
    {
        return $this->references;
    }
}

<?php
namespace Marble\EntityManager\Write;

use Marble\Entity\Entity;

class EntityUpdateContainer extends EntityWriteContainer implements HasChanged
{
    public function __construct(Entity $entity, array $data, private array $originalData, private array $changedProperties)
    {
        parent::__construct($entity, $data);
    }

    public function getOriginalData(): array
    {
        return $this->originalData;
    }

    public function getChangedProperties(): array
    {
        return $this->changedProperties;
    }
}

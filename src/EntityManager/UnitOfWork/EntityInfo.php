<?php
namespace Marble\EntityManager\UnitOfWork;

use Marble\Entity\Entity;
use Marble\Exception\LogicException;

class EntityInfo
{
    private EntityState $state;
    private bool $toBeRemoved = false;
    private bool $hasChanged = false;

    /**
     * @param Entity                    $entity
     * @param array<string, mixed>|null $lastSavedData
     */
    public function __construct(private Entity $entity, private ?array $lastSavedData = null)
    {
        $this->state = $lastSavedData === null ? EntityState::NEW : EntityState::FETCHED;

        if ($this->state === EntityState::FETCHED && $entity->getId() === null) {
            throw new LogicException(sprintf("Fetched % entity has no identifier.", $entity::class));
        }
    }

    public function getEntity(): Entity
    {
        return $this->entity;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLastSavedData(): ?array
    {
        return $this->lastSavedData;
    }

    /**
     * @param array<string, mixed> $lastSavedData
     */
    public function setLastSavedData(array $lastSavedData): void
    {
        $this->lastSavedData = $lastSavedData;
    }

    public function getState(): EntityState
    {
        return $this->state;
    }

    public function setState(EntityState $state): void
    {
        $this->state       = $state;
        $this->toBeRemoved = false;
        $this->hasChanged  = false;
    }

    public function isToBeRemoved(): bool
    {
        return $this->toBeRemoved;
    }

    public function setToBeRemoved(bool $toBeRemoved): void
    {
        $this->toBeRemoved = $toBeRemoved;
    }

    public function hasChanged(): bool
    {
        return $this->hasChanged;
    }

    public function setHasChanged(bool $hasChanged): void
    {
        $this->hasChanged = $hasChanged;
    }
}

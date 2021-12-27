<?php
namespace Marble\Tests\EntityManager\TestImpl\Entity;

use Marble\Entity\Entity;
use Marble\Entity\SimpleId;

class EntityWithSimpleId implements Entity
{
    private SimpleId $id;
    private ?self $another = null;

    public function __construct(string $id)
    {
        $this->id = new SimpleId($id);
    }

    public function getId(): SimpleId
    {
        return $this->id;
    }

    public function getAnother(): ?EntityWithSimpleId
    {
        return $this->another;
    }

    public function setAnother(?EntityWithSimpleId $another): void
    {
        $this->another = $another;
    }
}

<?php
namespace Marble\Tests\EntityManager\TestImpl\Entity;

use Marble\Entity\Entity;
use Marble\Entity\Identifier;
use Marble\Entity\Ulid;

class AnotherTestEntity implements Entity
{
    private Identifier $id;
    private ?string $title = null;
    private ?self $another = null;

    public function __construct()
    {
        $this->id = new Ulid();
    }

    public function getId(): ?Identifier
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getAnother(): ?AnotherTestEntity
    {
        return $this->another;
    }

    public function setAnother(?AnotherTestEntity $another): void
    {
        $this->another = $another;
    }
}

<?php
namespace Marble\Tests\EntityManager\TestImpl\Entity;

use DateTime;
use Marble\Entity\Identifier;
use Marble\Entity\Ulid;

class BasicTestEntity extends AbstractTestEntity
{
    private Identifier $id;
    private ?string $name;
    private ?DateTime $dateOfBirth;
    private array $others = [];

    public function __construct()
    {
        $this->id = new Ulid();
    }

    public function getId(): Identifier
    {
        return $this->id;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setDateOfBirth(?DateTime $dateOfBirth): void
    {
        $this->dateOfBirth = $dateOfBirth;
    }

    public function getDateOfBirth(): ?DateTime
    {
        return $this->dateOfBirth;
    }

    public function addAnother(AnotherTestEntity $another): void
    {
        $this->others[] = $another;
    }
}

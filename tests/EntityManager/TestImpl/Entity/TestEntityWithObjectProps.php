<?php
namespace Marble\Tests\EntityManager\TestImpl\Entity;

use Marble\Entity\Entity;
use Marble\Entity\Identifier;
use stdClass;

class TestEntityWithObjectProps implements Entity
{
    private object $foo;

    public function __construct()
    {
        $this->foo = new stdClass();
    }

    public function getFoo(): object
    {
        return $this->foo;
    }

    public function setFoo(object $foo): void
    {
        $this->foo = $foo;
    }

    public function getId(): ?Identifier
    {
        return null;
    }
}

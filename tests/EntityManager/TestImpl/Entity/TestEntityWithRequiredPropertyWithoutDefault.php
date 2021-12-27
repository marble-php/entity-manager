<?php
namespace Marble\Tests\EntityManager\TestImpl\Entity;

class TestEntityWithRequiredPropertyWithoutDefault
{
    private string $city;

    public function setCity(string $city): void
    {
        $this->city = $city;
    }

    public function getCity(): string
    {
        return $this->city;
    }
}

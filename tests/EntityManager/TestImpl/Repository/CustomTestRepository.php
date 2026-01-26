<?php

namespace Marble\Tests\EntityManager\TestImpl\Repository;

use Marble\EntityManager\Repository\CustomRepository;
use Marble\Tests\EntityManager\TestImpl\Entity\AnotherTestEntity;

/**
 * @implements CustomRepository<AnotherTestEntity>
 */
class CustomTestRepository extends CustomRepository
{
    public function fetchOneByTitle(string $title): AnotherTestEntity
    {
        return $this->fetchOneBy(["title" => $title]);
    }
}

<?php
namespace Marble\Tests\EntityManager\TestImpl\Repository;

use Marble\EntityManager\Repository\DefaultRepository;
use Marble\Tests\EntityManager\TestImpl\Entity\AnotherTestEntity;

/**
 * @implements DefaultRepository<AnotherTestEntity>
 */
class CustomTestRepository extends DefaultRepository
{
    public function fetchOneByTitle(string $title): AnotherTestEntity
    {
        return $this->fetchOneBy(["title" => $title]);
    }
}

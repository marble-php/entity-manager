<?php
namespace Marble\Tests\EntityManager\TestImpl\Entity;

use Marble\Entity\Entity;

abstract class AbstractTestEntity implements Entity
{
    private int $unusedProbably = 123;
}

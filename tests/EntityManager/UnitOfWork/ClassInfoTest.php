<?php
namespace Marble\Tests\EntityManager\UnitOfWork;

use Marble\Entity\SimpleId;
use Marble\EntityManager\UnitOfWork\ClassInfo;
use Marble\Exception\LogicException;
use Marble\Tests\EntityManager\TestImpl\Entity\AbstractTestEntity;
use Marble\Tests\EntityManager\TestImpl\Entity\BasicTestEntity;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class ClassInfoTest extends MockeryTestCase
{
    public function testClassInfoMustBeForEntity(): void
    {
        $this->expectException(LogicException::class);
        new ClassInfo(SimpleId::class);
    }

    public function testParentClasses(): void
    {
        $info = new ClassInfo(BasicTestEntity::class);

        $this->assertEquals(BasicTestEntity::class, $info->getClassName());

        $parentClasses = $info->getParentClasses();

        $this->assertCount(1, $parentClasses);
        $this->assertEquals(AbstractTestEntity::class, $parentClasses[0]);
    }
}

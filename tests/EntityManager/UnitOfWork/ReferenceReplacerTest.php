<?php
namespace Marble\Tests\EntityManager\UnitOfWork;

use DateTime;
use Marble\EntityManager\UnitOfWork\ReferenceReplacer;
use Marble\Exception\LogicException;
use Marble\Tests\EntityManager\TestImpl\Entity\AnotherTestEntity;
use Marble\Tests\EntityManager\TestImpl\Entity\BasicTestEntity;
use Marble\Tests\EntityManager\TestImpl\Entity\TestEntityWithObjectProps;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use stdClass;

class ReferenceReplacerTest extends MockeryTestCase
{
    public function testGetPropertyByReference(): void
    {
        $replacer = new ReferenceReplacer();

        $t1 = new BasicTestEntity();
        $t1->setName('John Doe');
        $t1->setDateOfBirth(new DateTime());

        $ref =& $replacer->getPropertyByReference($t1, 'name');

        $this->assertEquals("John Doe", $ref);
        $this->assertEquals("John Doe", $t1->getName());

        $ref = "Jane Doe";

        $this->assertEquals("Jane Doe", $ref);
        $this->assertEquals("Jane Doe", $t1->getName());
    }

    public function testReplaceReference(): void
    {
        $replacer = new ReferenceReplacer();

        $t1 = new TestEntityWithObjectProps();
        $t1->setFoo($foo = new stdClass());
        $foo->bar = [123, $o2 = new stdClass()];
        $o2->t2   = $t2 = new TestEntityWithObjectProps();
        $t3       = new TestEntityWithObjectProps();

        $this->assertSame($t2, $t1->getFoo()->bar[1]->t2);

        $replacer->replaceReference($t1, ['foo', 'bar', 1, 't2'], $t3);

        $this->assertNotSame($t2, $t1->getFoo()->bar[1]->t2);
        $this->assertSame($t3, $t1->getFoo()->bar[1]->t2);
    }

    public function testIncorrectPath(): void
    {
        $replacer = new ReferenceReplacer();

        $t1 = new TestEntityWithObjectProps();
        $t1->setFoo($foo = new stdClass());
        $foo->bar = [123, $o2 = new stdClass()];
        $o2->t2   = new TestEntityWithObjectProps();

        $this->expectException(LogicException::class);
        $replacer->replaceReference($t1, ['foo', 'bar', 0, 't2'], new TestEntityWithObjectProps());
    }

    public function testEmptyPath(): void
    {
        $replacer = new ReferenceReplacer();

        $t1 = new TestEntityWithObjectProps();

        $this->expectException(LogicException::class);
        $replacer->replaceReference($t1, [], new TestEntityWithObjectProps());
    }

    public function testNonMatchingTarget(): void
    {
        $replacer = new ReferenceReplacer();

        $t1 = new TestEntityWithObjectProps();
        $t1->setFoo($foo = new stdClass());
        $foo->bar = [123, $o2 = new stdClass()];
        $o2->t2   = new AnotherTestEntity();

        $this->expectException(LogicException::class);
        $replacer->replaceReference($t1, ['foo', 'bar', 1, 't2'], new TestEntityWithObjectProps());
    }
}

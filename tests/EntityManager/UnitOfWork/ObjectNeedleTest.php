<?php
namespace Marble\Tests\EntityManager\UnitOfWork;

use DateTime;
use Marble\EntityManager\UnitOfWork\ObjectNeedle;
use Marble\Exception\LogicException;
use Marble\Tests\EntityManager\TestImpl\Entity\AnotherTestEntity;
use Marble\Tests\EntityManager\TestImpl\Entity\BasicTestEntity;
use Marble\Tests\EntityManager\TestImpl\Entity\TestEntityWithRequiredPropertyWithoutDefault;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class ObjectNeedleTest extends MockeryTestCase
{
    public function testExtract(): void
    {
        $t1 = new BasicTestEntity();
        $t1->setName('Required');
        $t1->setDateOfBirth($d1 = new DateTime('2021-12-10'));
        $t1->addAnother($t2 = new AnotherTestEntity());
        $t1->addAnother(new AnotherTestEntity());
        $t2->setTitle("Hi there!");

        $needle = new ObjectNeedle();

        $data = $needle->extract($t1);

        $this->assertArrayHasKey('others', $data);
        $this->assertIsArray($data['others']);
        $this->assertSame($t2, $data['others'][0]);
        $this->assertArrayHasKey('dateOfBirth', $data);
        $this->assertSame($d1, $data['dateOfBirth']);
    }

    public function testEntityHydrationFailsWithoutRequiredValue(): void
    {
        $needle = new ObjectNeedle();

        // Not passing a value for `city`, a typed, required property without default.
        $this->expectException(LogicException::class);
        $needle->hydrate(new TestEntityWithRequiredPropertyWithoutDefault(), []);
    }

}

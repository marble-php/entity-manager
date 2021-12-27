<?php
namespace Marble\Tests\EntityManager\Read;

use Marble\Entity\Ulid;
use Marble\EntityManager\Contract\EntityReader;
use Marble\EntityManager\Read\ResultSetBuilder;
use Marble\Exception\LogicException;
use Marble\Tests\EntityManager\TestImpl\Entity\AbstractTestEntity;
use Marble\Tests\EntityManager\TestImpl\Entity\AnotherTestEntity;
use Marble\Tests\EntityManager\TestImpl\Entity\BasicTestEntity;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class ResultSetBuilderTest extends MockeryTestCase
{
    public function testPutting(): void
    {
        $reader = Mockery::mock(EntityReader::class);
        $reader->allows('getEntityClassName')->andReturn(AnotherTestEntity::class);

        $builder = new ResultSetBuilder($reader);
        $builder->put($id1 = new Ulid(), ["foo" => "bar"]);
        $builder->putProperty($id1, "baz", 123);

        $result = $builder->build();

        $this->assertCount(1, $result);

        $row = $result->first();

        $this->assertNotNull($row);
        $this->assertSame($id1, $row->identifier);
        $this->assertCount(2, $row->data);
        $this->assertEquals(["foo" => "bar", "baz" => 123], $row->data);
        $this->assertNull($row->childClass);

        foreach ($result as $iteration) {
            $this->assertSame($row, $iteration);
        }
    }

    public function testConcreteClass(): void
    {
        $reader = Mockery::mock(EntityReader::class);
        $reader->allows('getEntityClassName')->andReturn(AbstractTestEntity::class);

        $builder = new ResultSetBuilder($reader);
        $builder->put($id1 = new Ulid(), ["foo" => "bar"], BasicTestEntity::class);

        $result = $builder->build();

        $this->assertSame($id1, $result->first()->identifier);
        $this->assertEquals(BasicTestEntity::class, $result->first()->childClass);
    }

    public function testIncorrectConcreteClass(): void
    {
        $reader = Mockery::mock(EntityReader::class);
        $reader->allows('getEntityClassName')->andReturn(AbstractTestEntity::class);

        $builder = new ResultSetBuilder($reader);

        $this->expectException(LogicException::class);
        $builder->putClass(new Ulid(), AnotherTestEntity::class);
    }
}

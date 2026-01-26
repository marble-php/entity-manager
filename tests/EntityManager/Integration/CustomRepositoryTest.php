<?php

namespace Marble\Tests\EntityManager\Integration;

use Marble\Entity\SimpleId;
use Marble\EntityManager\Contract\EntityIoProvider;
use Marble\EntityManager\Contract\EntityReader;
use Marble\EntityManager\EntityManager;
use Marble\EntityManager\Read\Criteria;
use Marble\EntityManager\Read\DataCollector;
use Marble\Tests\EntityManager\TestImpl\Entity\AnotherTestEntity;
use Marble\Tests\EntityManager\TestImpl\Repository\CustomTestRepository;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class CustomRepositoryTest extends MockeryTestCase
{
    public function testDefaultAndCustomRepositoriesCanCoexist(): void
    {
        $entityManager = new EntityManager(
            $ioProvider = Mockery::mock(EntityIoProvider::class),
        );

        $ioProvider->shouldReceive('getReader')->once()->andReturn($reader = Mockery::mock(EntityReader::class));
        $ioProvider->shouldReceive('getWriter')->never()->andReturn($writer = Mockery::mock(EntityReader::class));
        $ioProvider->shouldReceive('getCustomRepository')->with(AnotherTestEntity::class)->once()->andReturn(CustomTestRepository::class);

        $reader->shouldReceive('getEntityClassName')->andReturn(AnotherTestEntity::class);
        // TODO: fix query cache
        $reader->shouldReceive('read')->twice()->withArgs(function ($query, $resultSetBuilder, $em) use ($entityManager): bool {
            if (
                $em !== $entityManager or
                !$resultSetBuilder instanceof DataCollector or
                !($query instanceof Criteria && isset($query['title']) && $query['title'] === 'test')
            ) {
                return false;
            }

            $resultSetBuilder->put(new SimpleId(1), [
                'title' => 'test',
            ]);

            return true;
        });

        $t1 = $entityManager->getRepository(AnotherTestEntity::class)->fetchOneByTitle('test');
        $t2 = $entityManager->getRepository(AnotherTestEntity::class, false)->fetchOneBy(['title' => 'test']);

        $this->assertSame($t1, $t2);
    }
}

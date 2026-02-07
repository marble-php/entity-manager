<?php

namespace Marble\Tests\EntityManager\Repository;

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

class CustomRepositoryIntegrationTest extends MockeryTestCase
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

        // Reader should only read once, second fetch should take from query cache.
        $reader->shouldReceive('read')->once()->withArgs(function ($query, $resultSetBuilder, $em) use ($entityManager): bool {
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

        // Even if custom repository is instantiated differently (e.g. by container),
        // the underlying default repository is one and the same.

        $customRepo = new CustomTestRepository($entityManager, AnotherTestEntity::class);
        $t3         = $customRepo->fetchOneByTitle('test'); // taken from identity map

        $this->assertSame($t1, $t3);

        $t4 = new AnotherTestEntity();
        $entityManager->getRepository(AnotherTestEntity::class)->add($t4);
        $t5 = $entityManager->getRepository(AnotherTestEntity::class, false)->fetchOne($t4->getId());

        $this->assertSame($t4, $t5);
    }
}

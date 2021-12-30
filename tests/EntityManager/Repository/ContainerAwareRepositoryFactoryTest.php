<?php
namespace Marble\Tests\EntityManager\Repository;

use Marble\EntityManager\Contract\EntityIoProvider;
use Marble\EntityManager\Contract\EntityReader;
use Marble\EntityManager\EntityManager;
use Marble\EntityManager\Repository\ContainerAwareRepositoryFactory;
use Marble\Tests\EntityManager\TestImpl\Entity\AnotherTestEntity;
use Marble\Tests\EntityManager\TestImpl\Repository\CustomTestRepository;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Psr\Container\ContainerInterface;

class ContainerAwareRepositoryFactoryTest extends MockeryTestCase
{
    public function testGetRepositoryFromContainer(): void
    {
        $entityManager = Mockery::mock(EntityManager::class);
        $ioProvider    = Mockery::mock(EntityIoProvider::class);
        $container     = Mockery::mock(ContainerInterface::class);
        $reader        = Mockery::mock(EntityReader::class);

        $reader->allows('getEntityClassName')->atLeast()->once()->andReturn(AnotherTestEntity::class);

        $repo1 = new CustomTestRepository($reader, $entityManager);

        $container->allows('has')->once()->with(CustomTestRepository::class)->andReturn(true);
        $container->allows('get')->once()->with(CustomTestRepository::class)->andReturn($repo1);
        $ioProvider->allows('getReader')->with(AnotherTestEntity::class)->once()->andReturn($reader);
        $ioProvider->allows('getCustomRepositoryClass')->with(AnotherTestEntity::class)->once()->andReturn(CustomTestRepository::class);

        $factory = new ContainerAwareRepositoryFactory($ioProvider, $container);
        $repo2   = $factory->getRepository($entityManager, AnotherTestEntity::class);

        $this->assertSame($repo1, $repo2);
    }
}

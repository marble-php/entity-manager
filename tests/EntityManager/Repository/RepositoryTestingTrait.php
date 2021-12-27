<?php
namespace Marble\Tests\EntityManager\Repository;

use Marble\EntityManager\Read\DataCollector;
use Marble\EntityManager\Read\ResultRow;
use Mockery;
use Mockery\Matcher\Closure;

trait RepositoryTestingTrait
{
    /**
     * Creates a validator for the DataCollector argument to `EntityReader::read`,
     * that also interacts with the DataCollector to mock data collection behavior.
     */
    private function collect(ResultRow ...$rows): Closure
    {
        return Mockery::on(function (DataCollector $collector) use ($rows): bool {
            foreach ($rows as $row) {
                $collector->put($row->identifier, $row->data, $row->childClass);
            }

            return true;
        });
    }

    private function makeQuery(): object
    {
        return new class {
        };
    }
}

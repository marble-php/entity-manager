<?php

declare(strict_types=1);

namespace Marble\EntityManager\UnitOfWork;

use Marble\Exception\LogicException;
use SebastianBergmann\Comparator\ComparisonFailure;
use SebastianBergmann\Comparator\Exception;
use SebastianBergmann\Comparator\Factory;

final class ChangeSetCalculator
{
    private const FLOAT_PRECISION = .000001;

    private Factory $comparatorFactory;

    public function __construct(private readonly ObjectNeedle $needle)
    {
        $this->comparatorFactory = Factory::getInstance();
    }

    /**
     * @param EntityInfo $entityInfo
     * @return list<string>
     */
    public function findChangedProperties(EntityInfo $entityInfo): array
    {
        $entity    = $entityInfo->getEntity();
        $extracted = $this->needle->extract($entity);
        $lastSaved = $entityInfo->getLastSavedData();
        $changed   = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($extracted as $key => $value) {
            $delta = method_exists($entity, 'getChangeDelta') ? (float) $entity->getChangeDelta($key) : self::FLOAT_PRECISION;

            if (!$this->areEqual($value, $lastSaved[$key] ?? null, $delta)) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    private function areEqual(mixed $one, mixed $two, float $delta): bool
    {
        try {
            $comparator = $this->comparatorFactory->getComparatorFor($one, $two);

            $comparator->assertEquals($one, $two, $delta);

            return true;
        } catch (Exception $exception) {
            throw new LogicException($exception->getMessage(), 0, $exception);
        } catch (ComparisonFailure) {
            return false;
        }
    }
}

<?php
namespace Marble\EntityManager\UnitOfWork;

use Marble\Exception\LogicException;
use SebastianBergmann\Comparator\ComparisonFailure;
use SebastianBergmann\Comparator\Exception;
use SebastianBergmann\Comparator\Factory;

class ChangeSetCalculator
{
    private Factory $comparatorFactory;

    public function __construct(private ObjectNeedle $needle)
    {
        $this->comparatorFactory = Factory::getInstance();
    }

    /**
     * @param EntityInfo $entityInfo
     * @return list<string>
     */
    public function findChangedProperties(EntityInfo $entityInfo): array
    {
        $extracted = $this->needle->extract($entityInfo->getEntity());
        $lastSaved = $entityInfo->getLastSavedData();
        $changed   = [];

        foreach ($extracted as $key => $value) {
            if (!$this->areEqual($value, $lastSaved[$key] ?? null)) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    private function areEqual($one, $two): bool
    {
        try {
            $comparator = $this->comparatorFactory->getComparatorFor($one, $two);

            $comparator->assertEquals($one, $two);

            return true;
        } catch (Exception $exception) {
            throw new LogicException($exception->getMessage(), 0, $exception);
        } catch (ComparisonFailure) {
            return false;
        }
    }
}

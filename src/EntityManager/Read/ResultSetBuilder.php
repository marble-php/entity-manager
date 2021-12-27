<?php
namespace Marble\EntityManager\Read;

use Marble\Entity\Identifier;
use Marble\EntityManager\Contract\EntityReader;
use Marble\Exception\LogicException;

final class ResultSetBuilder implements DataCollector
{
    private array $data = [];
    private array $identifiers = [];
    private array $childClasses = [];

    public function __construct(private EntityReader $reader)
    {
    }

    public function put(Identifier $identifier, array $data, ?string $subclass = null): void
    {
        if ($subclass) {
            $this->putClass($identifier, $subclass);
        }

        foreach ($data as $propertyName => $value) {
            $this->putProperty($identifier, $propertyName, $value);
        }
    }

    public function putProperty(Identifier $identifier, string $propertyName, mixed $value): void
    {
        $this->identifiers[(string) $identifier]         = $identifier;
        $this->data[(string) $identifier][$propertyName] = $value;
    }

    public function putClass(Identifier $identifier, string $class): void
    {
        if (!class_exists($class)) {
            throw new LogicException(sprintf("Entity reader %s specified unknown class %s for identifier %s.",
                $this->reader::class, $class, $identifier));
        } elseif (!is_a($class, $this->reader->getEntityClassName(), true)) {
            throw new LogicException(sprintf("Concrete class %s specified for identifier %s is not a subclass of %s.",
                $class, $identifier, $this->reader->getEntityClassName()));
        }

        $this->childClasses[(string) $identifier] = $class;
    }

    public function build(): ResultSet
    {
        $rows = [];

        foreach ($this->identifiers as $id => $identifier) {
            $rows[] = new ResultRow($identifier, $this->data[$id], $this->childClasses[$id] ?? null);
        }

        return new ResultSet(...$rows);
    }
}

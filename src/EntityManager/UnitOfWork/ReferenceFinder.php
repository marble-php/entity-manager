<?php
namespace Marble\EntityManager\UnitOfWork;

use Marble\Entity\Entity;
use stdClass;

class ReferenceFinder
{
    public function __construct(private ObjectNeedle $needle)
    {
    }

    /**
     * @param callable(Entity, list<string|int>): mixed $fn
     * @return array<string, mixed>
     */
    public function collect(Entity $entity, callable $fn): array
    {
        $data = $this->needle->extract($entity);

        return $this->cascade($data, $fn, []);
    }

    /**
     * @param callable(Entity, list<string|int>): void $fn
     */
    public function forEach(Entity $entity, callable $fn): void
    {
        $this->collect($entity, $fn);
    }

    /**
     * @param array<string|int, mixed>                  $data
     * @param callable(Entity, list<string|int>): mixed $fn
     * @return array<string, mixed>
     */
    private function cascade(array $data, callable $fn, array $path): array
    {
        $collection = [];

        foreach ($data as $key => $value) {
            $currentPathString = implode('.', [...$path, $key]);

            try {
                $path[$currentPathString] = $key;

                if ($value instanceof Entity) {
                    if ($result = $fn($value, array_values($path))) {
                        $collection[$currentPathString] = $result;
                    }
                } elseif (is_array($value) || is_object($value)) {
                    $array = is_object($value) && !$value instanceof stdClass ? $this->needle->extract($value) : (array) $value;

                    $collection += $this->cascade($array, $fn, $path);
                }
            } finally {
                unset($path[$currentPathString]);
            }
        }

        return $collection;
    }
}

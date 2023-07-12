<?php
namespace Marble\EntityManager\Repository;

use Marble\Entity\Entity;

/**
 * @template T of Entity
 */
interface Repository
{
    /**
     * @return class-string<T>
     */
    public function getEntityClassName(): string;

    /**
     * @param T $entity
     */
    public function add(Entity $entity): void;

    /**
     * @param T $entity
     */
    public function remove(Entity $entity): void;

    /**
     * @return T|null
     */
    public function fetchOne(object $query);

    /**
     * @param array<string, scalar> $criteria
     * @return T|null
     */
    public function fetchOneBy(array $criteria);

    /**
     * @return list<T>
     */
    public function fetchMany(?object $query): array;

    /**
     * @param array<string, scalar> $criteria
     * @return list<T>
     */
    public function fetchManyBy(array $criteria): array;

    /**
     * @return list<T>
     */
    public function fetchAll(): array;
}

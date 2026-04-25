<?php

declare(strict_types=1);

namespace Marble\EntityManager\Read;

use Marble\Entity\Entity;
use Marble\Entity\Identifier;

/**
 * @template T of Entity
 */
final class ResultRow
{
    /**
     * @param Identifier<T>        $identifier
     * @param array<string, mixed> $data
     * @param class-string<T>|null $childClass
     */
    public function __construct(
        public readonly Identifier $identifier,
        public readonly array      $data,
        public readonly ?string    $childClass = null,
    ) {
    }
}

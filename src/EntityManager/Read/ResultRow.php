<?php
namespace Marble\EntityManager\Read;

use Marble\Entity\Entity;
use Marble\Entity\Identifier;

final class ResultRow
{
    /**
     * @param array<string, mixed>      $data
     * @param class-string<Entity>|null $childClass
     */
    public function __construct(
        public readonly Identifier $identifier,
        public readonly array $data,
        public readonly ?string $childClass = null,
    ) {
    }
}

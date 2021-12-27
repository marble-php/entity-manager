<?php
namespace Marble\EntityManager\Read;

use Marble\Entity\Identifier;

final class ResultRow
{
    public function __construct(
        public readonly Identifier $identifier,
        public readonly array $data,
        public readonly ?string $childClass = null,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Marble\Entity;

use Symfony\Component\Uid\UuidV4 as SymfonyUuidV4;

/**
 * @template T of Entity
 * @implements Identifier<T>
 * @api
 */
class UuidV4 extends SymfonyUuidV4 implements Identifier
{
}

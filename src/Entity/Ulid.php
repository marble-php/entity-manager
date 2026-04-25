<?php

declare(strict_types=1);

namespace Marble\Entity;

use Symfony\Component\Uid\Ulid as SymfonyUlid;

/**
 * @template T of Entity
 * @implements Identifier<T>
 * @api
 */
class Ulid extends SymfonyUlid implements Identifier
{
}

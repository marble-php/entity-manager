<?php

declare(strict_types=1);

namespace Marble\Entity;

use Symfony\Component\Uid\Ulid as SymfonyUlid;

/**
 * @api
 */
class Ulid extends SymfonyUlid implements Identifier
{
}

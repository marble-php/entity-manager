<?php

declare(strict_types=1);

namespace Marble\Entity;

use Symfony\Component\Uid\UuidV7 as SymfonyUuidV7;

/**
 * @api
 */
class UuidV7 extends SymfonyUuidV7 implements Identifier
{
}

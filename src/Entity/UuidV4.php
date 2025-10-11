<?php
namespace Marble\Entity;

use Symfony\Component\Uid\UuidV4 as SymfonyUuidV4;

class UuidV4 extends SymfonyUuidV4 implements Identifier
{
}

<?php
namespace Marble\Entity;

use Symfony\Component\Uid\Ulid as SymfonyUlid;

class Ulid extends SymfonyUlid implements Identifier
{
}

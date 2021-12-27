<?php
namespace Marble\Exception;

use Marble\Entity\Entity;

class LogicException extends \LogicException implements MarbleException
{
    public static function strEntity(Entity $entity): string
    {
        return $entity::class . ':' . ($entity->getId() ?? '?');
    }
}

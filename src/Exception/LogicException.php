<?php
namespace Marble\Exception;

use Marble\Entity\Entity;

final class LogicException extends \LogicException implements MarbleException
{
    public static function strEntity(Entity $entity): string
    {
        return $entity::class . ':' . (string) ($entity->getId() ?? '?');
    }
}

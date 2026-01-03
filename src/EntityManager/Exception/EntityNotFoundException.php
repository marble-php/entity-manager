<?php
namespace Marble\EntityManager\Exception;

use Marble\Exception\MarbleException;
use RuntimeException;

final class EntityNotFoundException extends RuntimeException implements MarbleException
{
}

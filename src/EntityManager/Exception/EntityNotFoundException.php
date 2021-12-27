<?php
namespace Marble\EntityManager\Exception;

use Marble\Exception\MarbleException;
use RuntimeException;

class EntityNotFoundException extends RuntimeException implements MarbleException
{
}

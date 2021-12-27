<?php
namespace Marble\EntityManager\Exception;

use Marble\Exception\MarbleException;
use RuntimeException;

class EntitySkippedException extends RuntimeException implements MarbleException
{
}

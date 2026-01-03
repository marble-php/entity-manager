<?php
namespace Marble\EntityManager\Exception;

use Marble\Exception\MarbleException;
use RuntimeException;

final class EntitySkippedException extends RuntimeException implements MarbleException
{
}

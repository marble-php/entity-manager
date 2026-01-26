<?php

namespace Marble\Tests\EntityManager\TestImpl\Query;

trait SomeTrait
{
    private string $member = 'This will be serialized into the query cache key.';
}

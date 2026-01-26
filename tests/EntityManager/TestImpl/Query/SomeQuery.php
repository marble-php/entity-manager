<?php

namespace Marble\Tests\EntityManager\TestImpl\Query;

class SomeQuery
{
    use SomeTrait;

    public mixed $binary;

    public function __construct(
        public float $inf = -INF,
        public float $nan = NAN,
        public float $pi  = M_PI,
        public ?SomeQuery $other = null,
        public mixed $resource = null,
    ) {
        $this->resource ??= fopen('php://stderr', 'w');
        $this->binary   = chr(0) . chr(1) . chr(2) . chr(3) . chr(4) . chr(5);
    }
}

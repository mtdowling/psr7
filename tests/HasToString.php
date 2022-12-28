<?php

declare(strict_types=1);

namespace HeGuzzleHttp\Tests\Psr7;

class HasToString
{
    public function __toString(): string
    {
        return 'foo';
    }
}

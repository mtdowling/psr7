<?php

namespace GuzzleHttp\Tests\Psr7;

class HasToString
{
    public function __toString()
    {
        return 'foo';
    }
}

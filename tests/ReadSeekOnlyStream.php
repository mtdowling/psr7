<?php

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\Stream;

final class ReadSeekOnlyStream extends Stream
{
    public function __construct()
    {
        parent::__construct(fopen('php://memory', 'wb'));
    }

    public function isSeekable()
    {
        return true;
    }

    public function isReadable()
    {
        return false;
    }
}

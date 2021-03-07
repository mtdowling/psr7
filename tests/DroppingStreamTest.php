<?php

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\BufferStream;
use GuzzleHttp\Psr7\DroppingStream;

class DroppingStreamTest extends BaseTest
{
    public function testBeginsDroppingWhenSizeExceeded()
    {
        $stream = new BufferStream();
        $drop = new DroppingStream($stream, 5);
        self::assertSame(3, $drop->write('hel'));
        self::assertSame(2, $drop->write('lo'));
        self::assertSame(5, $drop->getSize());
        self::assertSame('hello', $drop->read(5));
        self::assertSame(0, $drop->getSize());
        $drop->write('12345678910');
        self::assertSame(5, $stream->getSize());
        self::assertSame(5, $drop->getSize());
        self::assertSame('12345', (string) $drop);
        self::assertSame(0, $drop->getSize());
        $drop->write('hello');
        self::assertSame(0, $drop->write('test'));
    }
}

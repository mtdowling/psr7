<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\BufferStream;
use GuzzleHttp\Psr7\DroppingStream;
use PHPUnit\Framework\TestCase;

class DroppingStreamTest extends TestCase
{
    public function testBeginsDroppingWhenSizeExceeded(): void
    {
        $stream = new BufferStream();
        $drop = new DroppingStream($stream, 5);
        self::assertEquals(3, $drop->write('hel'));
        self::assertEquals(2, $drop->write('lo'));
        self::assertEquals(5, $drop->getSize());
        self::assertEquals('hello', $drop->read(5));
        self::assertEquals(0, $drop->getSize());
        $drop->write('12345678910');
        self::assertEquals(5, $stream->getSize());
        self::assertEquals(5, $drop->getSize());
        self::assertEquals('12345', (string) $drop);
        self::assertEquals(0, $drop->getSize());
        $drop->write('hello');
        self::assertSame(0, $drop->write('test'));
    }
}

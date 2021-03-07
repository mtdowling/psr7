<?php

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\BufferStream;

class BufferStreamTest extends BaseTest
{
    public function testHasMetadata()
    {
        $b = new BufferStream(10);
        self::assertTrue($b->isReadable());
        self::assertTrue($b->isWritable());
        self::assertFalse($b->isSeekable());
        self::assertSame(null, $b->getMetadata('foo'));
        self::assertSame(10, $b->getMetadata('hwm'));
        self::assertSame([], $b->getMetadata());
    }

    public function testRemovesReadDataFromBuffer()
    {
        $b = new BufferStream();
        self::assertSame(3, $b->write('foo'));
        self::assertSame(3, $b->getSize());
        self::assertFalse($b->eof());
        self::assertSame('foo', $b->read(10));
        self::assertTrue($b->eof());
        self::assertSame('', $b->read(10));
    }

    public function testCanCastToStringOrGetContents()
    {
        $b = new BufferStream();
        $b->write('foo');
        $b->write('baz');
        self::assertSame('foo', $b->read(3));
        $b->write('bar');
        self::assertSame('bazbar', (string) $b);

        $this->expectExceptionGuzzle('RuntimeException', 'Cannot determine the position of a BufferStream');

        $b->tell();
    }

    public function testDetachClearsBuffer()
    {
        $b = new BufferStream();
        $b->write('foo');
        $b->detach();
        self::assertTrue($b->eof());
        self::assertSame(3, $b->write('abc'));
        self::assertSame('abc', $b->read(10));
    }

    public function testExceedingHighwaterMarkReturnsFalseButStillBuffers()
    {
        $b = new BufferStream(5);
        self::assertSame(3, $b->write('hi '));
        self::assertFalse($b->write('hello'));
        self::assertSame('hi hello', (string) $b);
        self::assertSame(4, $b->write('test'));
    }
}

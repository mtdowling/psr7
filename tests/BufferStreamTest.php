<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\BufferStream;
use PHPUnit\Framework\TestCase;

class BufferStreamTest extends TestCase
{
    public function testHasMetadata(): void
    {
        $b = new BufferStream(10);
        self::assertTrue($b->isReadable());
        self::assertTrue($b->isWritable());
        self::assertFalse($b->isSeekable());
        self::assertNull($b->getMetadata('foo'));
        self::assertSame(10, $b->getMetadata('hwm'));
        self::assertSame([], $b->getMetadata());
    }

    public function testRemovesReadDataFromBuffer(): void
    {
        $b = new BufferStream();
        self::assertEquals(3, $b->write('foo'));
        self::assertEquals(3, $b->getSize());
        self::assertFalse($b->eof());
        self::assertEquals('foo', $b->read(10));
        self::assertTrue($b->eof());
        self::assertEquals('', $b->read(10));
    }

    public function testCanCastToStringOrGetContents(): void
    {
        $b = new BufferStream();
        $b->write('foo');
        $b->write('baz');
        self::assertEquals('foo', $b->read(3));
        $b->write('bar');
        self::assertEquals('bazbar', (string) $b);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot determine the position of a BufferStream');
        $b->tell();
    }

    public function testDetachClearsBuffer(): void
    {
        $b = new BufferStream();
        $b->write('foo');
        $b->detach();
        self::assertTrue($b->eof());
        self::assertEquals(3, $b->write('abc'));
        self::assertEquals('abc', $b->read(10));
    }

    public function testExceedingHighwaterMarkReturnsFalseButStillBuffers(): void
    {
        $b = new BufferStream(5);
        self::assertEquals(3, $b->write('hi '));
        self::assertSame(0, $b->write('hello'));
        self::assertEquals('hi hello', (string) $b);
        self::assertEquals(4, $b->write('test'));
    }
}

<?php

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\BufferStream;

class BufferStreamTest extends BaseTest
{
    public function testHasMetadata()
    {
        $b = new BufferStream(10);
        $this->assertTrue($b->isReadable());
        $this->assertTrue($b->isWritable());
        $this->assertFalse($b->isSeekable());
        $this->assertSame(null, $b->getMetadata('foo'));
        $this->assertSame(10, $b->getMetadata('hwm'));
        $this->assertSame([], $b->getMetadata());
    }

    public function testRemovesReadDataFromBuffer()
    {
        $b = new BufferStream();
        $this->assertSame(3, $b->write('foo'));
        $this->assertSame(3, $b->getSize());
        $this->assertFalse($b->eof());
        $this->assertSame('foo', $b->read(10));
        $this->assertTrue($b->eof());
        $this->assertSame('', $b->read(10));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cannot determine the position of a BufferStream
     */
    public function testCanCastToStringOrGetContents()
    {
        $b = new BufferStream();
        $b->write('foo');
        $b->write('baz');
        $this->assertSame('foo', $b->read(3));
        $b->write('bar');
        $this->assertSame('bazbar', (string) $b);
        $b->tell();
    }

    public function testDetachClearsBuffer()
    {
        $b = new BufferStream();
        $b->write('foo');
        $b->detach();
        $this->assertTrue($b->eof());
        $this->assertSame(3, $b->write('abc'));
        $this->assertSame('abc', $b->read(10));
    }

    public function testExceedingHighwaterMarkReturnsFalseButStillBuffers()
    {
        $b = new BufferStream(5);
        $this->assertSame(3, $b->write('hi '));
        $this->assertFalse($b->write('hello'));
        $this->assertSame('hi hello', (string) $b);
        $this->assertSame(4, $b->write('test'));
    }
}

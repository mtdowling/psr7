<?php

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\PumpStream;
use GuzzleHttp\Psr7;

class PumpStreamTest extends BaseTest
{
    public function testHasMetadataAndSize()
    {
        $p = new PumpStream(function () {}, [
            'metadata' => ['foo' => 'bar'],
            'size'     => 100
        ]);

        $this->assertSame('bar', $p->getMetadata('foo'));
        $this->assertSame(['foo' => 'bar'], $p->getMetadata());
        $this->assertSame(100, $p->getSize());
    }

    public function testCanReadFromCallable()
    {
        $p = Psr7\Utils::streamFor(function ($size) {
            return 'a';
        });
        $this->assertSame('a', $p->read(1));
        $this->assertSame(1, $p->tell());
        $this->assertSame('aaaaa', $p->read(5));
        $this->assertSame(6, $p->tell());
    }

    public function testStoresExcessDataInBuffer()
    {
        $called = [];
        $p = Psr7\Utils::streamFor(function ($size) use (&$called) {
            $called[] = $size;
            return 'abcdef';
        });
        $this->assertSame('a', $p->read(1));
        $this->assertSame('b', $p->read(1));
        $this->assertSame('cdef', $p->read(4));
        $this->assertSame('abcdefabc', $p->read(9));
        $this->assertSame([1, 9, 3], $called);
    }

    public function testInifiniteStreamWrappedInLimitStream()
    {
        $p = Psr7\Utils::streamFor(function () { return 'a'; });
        $s = new LimitStream($p, 5);
        $this->assertSame('aaaaa', (string) $s);
    }

    public function testDescribesCapabilities()
    {
        $p = Psr7\Utils::streamFor(function () {});
        $this->assertTrue($p->isReadable());
        $this->assertFalse($p->isSeekable());
        $this->assertFalse($p->isWritable());
        $this->assertNull($p->getSize());
        $this->assertSame('', $p->getContents());
        $this->assertSame('', (string) $p);
        $p->close();
        $this->assertSame('', $p->read(10));
        $this->assertTrue($p->eof());

        try {
            $this->assertFalse($p->write('aa'));
            $this->fail();
        } catch (\RuntimeException $e) {}
    }
}

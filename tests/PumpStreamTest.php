<?php

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\PumpStream;

class PumpStreamTest extends BaseTest
{
    public function testHasMetadataAndSize()
    {
        $p = new PumpStream(function () {
        }, [
            'metadata' => ['foo' => 'bar'],
            'size'     => 100
        ]);

        self::assertSame('bar', $p->getMetadata('foo'));
        self::assertSame(['foo' => 'bar'], $p->getMetadata());
        self::assertSame(100, $p->getSize());
    }

    public function testCanReadFromCallable()
    {
        $p = Psr7\Utils::streamFor(function ($size) {
            return 'a';
        });
        self::assertSame('a', $p->read(1));
        self::assertSame(1, $p->tell());
        self::assertSame('aaaaa', $p->read(5));
        self::assertSame(6, $p->tell());
    }

    public function testStoresExcessDataInBuffer()
    {
        $called = [];
        $p = Psr7\Utils::streamFor(function ($size) use (&$called) {
            $called[] = $size;
            return 'abcdef';
        });
        self::assertSame('a', $p->read(1));
        self::assertSame('b', $p->read(1));
        self::assertSame('cdef', $p->read(4));
        self::assertSame('abcdefabc', $p->read(9));
        self::assertSame([1, 9, 3], $called);
    }

    public function testInifiniteStreamWrappedInLimitStream()
    {
        $p = Psr7\Utils::streamFor(function () {
            return 'a';
        });
        $s = new LimitStream($p, 5);
        self::assertSame('aaaaa', (string) $s);
    }

    public function testDescribesCapabilities()
    {
        $p = Psr7\Utils::streamFor(function () {
        });
        self::assertTrue($p->isReadable());
        self::assertFalse($p->isSeekable());
        self::assertFalse($p->isWritable());
        self::assertNull($p->getSize());
        self::assertSame('', $p->getContents());
        self::assertSame('', (string) $p);
        $p->close();
        self::assertSame('', $p->read(10));
        self::assertTrue($p->eof());

        try {
            self::assertFalse($p->write('aa'));
            self::fail();
        } catch (\RuntimeException $e) {
        }
    }
}

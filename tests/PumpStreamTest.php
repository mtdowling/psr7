<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\PumpStream;
use PHPUnit\Framework\TestCase;

class PumpStreamTest extends TestCase
{
    public function testHasMetadataAndSize(): void
    {
        $p = new PumpStream(function (): void {
        }, [
            'metadata' => ['foo' => 'bar'],
            'size'     => 100
        ]);

        self::assertEquals('bar', $p->getMetadata('foo'));
        self::assertEquals(['foo' => 'bar'], $p->getMetadata());
        self::assertEquals(100, $p->getSize());
    }

    public function testCanReadFromCallable(): void
    {
        $p = Psr7\stream_for(function ($size) {
            return 'a';
        });
        self::assertEquals('a', $p->read(1));
        self::assertEquals(1, $p->tell());
        self::assertEquals('aaaaa', $p->read(5));
        self::assertEquals(6, $p->tell());
    }

    public function testStoresExcessDataInBuffer(): void
    {
        $called = [];
        $p = Psr7\stream_for(function ($size) use (&$called) {
            $called[] = $size;
            return 'abcdef';
        });
        self::assertEquals('a', $p->read(1));
        self::assertEquals('b', $p->read(1));
        self::assertEquals('cdef', $p->read(4));
        self::assertEquals('abcdefabc', $p->read(9));
        self::assertEquals([1, 9, 3], $called);
    }

    public function testInifiniteStreamWrappedInLimitStream(): void
    {
        $p = Psr7\stream_for(function () {
            return 'a';
        });
        $s = new LimitStream($p, 5);
        self::assertEquals('aaaaa', (string) $s);
    }

    public function testDescribesCapabilities(): void
    {
        $p = Psr7\stream_for(function (): void {
        });
        self::assertTrue($p->isReadable());
        self::assertFalse($p->isSeekable());
        self::assertFalse($p->isWritable());
        self::assertNull($p->getSize());
        self::assertEquals('', $p->getContents());
        self::assertEquals('', (string) $p);
        $p->close();
        self::assertEquals('', $p->read(10));
        self::assertTrue($p->eof());

        try {
            self::assertFalse($p->write('aa'));
            self::fail();
        } catch (\RuntimeException $e) {
        }
    }

    /**
     * @requires PHP < 7.4
     */
    public function testThatConvertingStreamToStringWillTriggerErrorAndWillReturnEmptyString(): void
    {
        $p = Psr7\stream_for(function ($size): void {
            throw new \Exception();
        });
        self::assertInstanceOf(PumpStream::class, $p);

        $errors = [];
        set_error_handler(function (int $errorNumber, string $errorMessage) use (&$errors): void {
            $errors[] = ['number' => $errorNumber, 'message' => $errorMessage];
        });
        (string) $p;

        restore_error_handler();

        self::assertCount(1, $errors);
        self::assertSame(E_USER_ERROR, $errors[0]['number']);
        self::assertStringStartsWith('GuzzleHttp\Psr7\PumpStream::__toString exception:', $errors[0]['message']);
    }
}

<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\Stream;
use PHPUnit\Framework\TestCase;

/**
 * @covers GuzzleHttp\Psr7\Stream
 */
class StreamTest extends TestCase
{
    public static $isFReadError = false;

    public function testConstructorThrowsExceptionOnInvalidArgument()
    {
        $this->expectException(\InvalidArgumentException::class);
        new Stream(true);
    }

    public function testConstructorInitializesProperties()
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, 'data');
        $stream = new Stream($handle);
        self::assertTrue($stream->isReadable());
        self::assertTrue($stream->isWritable());
        self::assertTrue($stream->isSeekable());
        self::assertEquals('php://temp', $stream->getMetadata('uri'));
        self::assertIsArray($stream->getMetadata());
        self::assertEquals(4, $stream->getSize());
        self::assertFalse($stream->eof());
        $stream->close();
    }

    public function testConstructorInitializesPropertiesWithRbPlus()
    {
        $handle = fopen('php://temp', 'rb+');
        fwrite($handle, 'data');
        $stream = new Stream($handle);
        self::assertTrue($stream->isReadable());
        self::assertTrue($stream->isWritable());
        self::assertTrue($stream->isSeekable());
        self::assertEquals('php://temp', $stream->getMetadata('uri'));
        self::assertIsArray($stream->getMetadata());
        self::assertEquals(4, $stream->getSize());
        self::assertFalse($stream->eof());
        $stream->close();
    }

    public function testStreamClosesHandleOnDestruct()
    {
        $handle = fopen('php://temp', 'r');
        $stream = new Stream($handle);
        unset($stream);
        self::assertFalse(is_resource($handle));
    }

    public function testConvertsToString()
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, 'data');
        $stream = new Stream($handle);
        self::assertEquals('data', (string) $stream);
        self::assertEquals('data', (string) $stream);
        $stream->close();
    }

    public function testConvertsToStringNonSeekableStream()
    {
        $handle = popen('echo foo', 'r');
        $stream = new Stream($handle);
        self::assertFalse($stream->isSeekable());
        self::assertSame('foo', trim((string) $stream));
    }

    public function testConvertsToStringNonSeekablePartiallyReadStream()
    {
        $handle = popen('echo bar', 'r');
        $stream = new Stream($handle);
        $firstLetter = $stream->read(1);
        self::assertFalse($stream->isSeekable());
        self::assertSame('b', $firstLetter);
        self::assertSame('ar', trim((string) $stream));
    }

    public function testGetsContents()
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, 'data');
        $stream = new Stream($handle);
        self::assertEquals('', $stream->getContents());
        $stream->seek(0);
        self::assertEquals('data', $stream->getContents());
        self::assertEquals('', $stream->getContents());
        $stream->close();
    }

    public function testChecksEof()
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, 'data');
        $stream = new Stream($handle);
        self::assertSame(4, $stream->tell(), 'Stream cursor already at the end');
        self::assertFalse($stream->eof(), 'Stream still not eof');
        self::assertSame('', $stream->read(1), 'Need to read one more byte to reach eof');
        self::assertTrue($stream->eof());
        $stream->close();
    }

    public function testGetSize()
    {
        $size = filesize(__FILE__);
        $handle = fopen(__FILE__, 'r');
        $stream = new Stream($handle);
        self::assertEquals($size, $stream->getSize());
        // Load from cache
        self::assertEquals($size, $stream->getSize());
        $stream->close();
    }

    public function testEnsuresSizeIsConsistent()
    {
        $h = fopen('php://temp', 'w+');
        self::assertEquals(3, fwrite($h, 'foo'));
        $stream = new Stream($h);
        self::assertEquals(3, $stream->getSize());
        self::assertEquals(4, $stream->write('test'));
        self::assertEquals(7, $stream->getSize());
        self::assertEquals(7, $stream->getSize());
        $stream->close();
    }

    public function testProvidesStreamPosition()
    {
        $handle = fopen('php://temp', 'w+');
        $stream = new Stream($handle);
        self::assertEquals(0, $stream->tell());
        $stream->write('foo');
        self::assertEquals(3, $stream->tell());
        $stream->seek(1);
        self::assertEquals(1, $stream->tell());
        self::assertSame(ftell($handle), $stream->tell());
        $stream->close();
    }

    public function testDetachStreamAndClearProperties()
    {
        $handle = fopen('php://temp', 'r');
        $stream = new Stream($handle);
        self::assertSame($handle, $stream->detach());
        self::assertIsResource($handle, 'Stream is not closed');
        self::assertNull($stream->detach());

        $this->assertStreamStateAfterClosedOrDetached($stream);

        $stream->close();
    }

    public function testCloseResourceAndClearProperties()
    {
        $handle = fopen('php://temp', 'r');
        $stream = new Stream($handle);
        $stream->close();

        self::assertFalse(is_resource($handle));

        $this->assertStreamStateAfterClosedOrDetached($stream);
    }

    private function assertStreamStateAfterClosedOrDetached(Stream $stream)
    {
        self::assertFalse($stream->isReadable());
        self::assertFalse($stream->isWritable());
        self::assertFalse($stream->isSeekable());
        self::assertNull($stream->getSize());
        self::assertSame([], $stream->getMetadata());
        self::assertNull($stream->getMetadata('foo'));

        $throws = function (callable $fn) {
            try {
                $fn();
            } catch (\Exception $e) {
                $this->assertStringContainsString('Stream is detached', $e->getMessage());

                return;
            }

            $this->fail('Exception should be thrown after the stream is detached.');
        };

        $throws(function () use ($stream) {
            $stream->read(10);
        });
        $throws(function () use ($stream) {
            $stream->write('bar');
        });
        $throws(function () use ($stream) {
            $stream->seek(10);
        });
        $throws(function () use ($stream) {
            $stream->tell();
        });
        $throws(function () use ($stream) {
            $stream->eof();
        });
        $throws(function () use ($stream) {
            $stream->getContents();
        });

        $errors = [];
        set_error_handler(function (int $errorNumber, string $errorMessage) use (&$errors) {
            $errors[] = ['message' => $errorMessage, 'number' => $errorNumber];
        });
        self::assertSame('', (string)$stream);
        restore_error_handler();

        self::assertCount(1, $errors);
        self::assertStringStartsWith('GuzzleHttp\Psr7\Stream::__toString exception', $errors[0]['message']);
        self::assertSame(E_USER_ERROR, $errors[0]['number']);
    }

    public function testStreamReadingWithZeroLength()
    {
        $r = fopen('php://temp', 'r');
        $stream = new Stream($r);

        self::assertSame('', $stream->read(0));

        $stream->close();
    }

    public function testStreamReadingWithNegativeLength()
    {
        $r = fopen('php://temp', 'r');
        $stream = new Stream($r);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Length parameter cannot be negative');

        try {
            $stream->read(-1);
        } catch (\Exception $e) {
            $stream->close();
            throw $e;
        }

        $stream->close();
    }

    public function testStreamReadingFreadError()
    {
        self::$isFReadError = true;
        $r = fopen('php://temp', 'r');
        $stream = new Stream($r);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to read from stream');

        try {
            $stream->read(1);
        } catch (\Exception $e) {
            self::$isFReadError = false;
            $stream->close();
            throw $e;
        }

        self::$isFReadError = false;
        $stream->close();
    }

    /**
     * @requires extension zlib
     *
     * @dataProvider gzipModeProvider
     */
    public function testGzipStreamModes(string $mode, bool $readable, bool $writable)
    {
        $r = gzopen('php://temp', $mode);
        $stream = new Stream($r);

        self::assertSame($readable, $stream->isReadable());
        self::assertSame($writable, $stream->isWritable());

        $stream->close();
    }

    public function gzipModeProvider()
    {
        return [
            ['mode' => 'rb9', 'readable' => true, 'writable' => false],
            ['mode' => 'wb2', 'readable' => false, 'writable' => true],
        ];
    }

    /**
     * @dataProvider readableModeProvider
     */
    public function testReadableStream(string $mode)
    {
        $r = fopen('php://temp', $mode);
        $stream = new Stream($r);

        self::assertTrue($stream->isReadable());

        $stream->close();
    }

    public function readableModeProvider()
    {
        return [
            ['r'],
            ['w+'],
            ['r+'],
            ['x+'],
            ['c+'],
            ['rb'],
            ['w+b'],
            ['r+b'],
            ['x+b'],
            ['c+b'],
            ['rt'],
            ['w+t'],
            ['r+t'],
            ['x+t'],
            ['c+t'],
            ['a+'],
            ['rb+'],
        ];
    }

    public function testWriteOnlyStreamIsNotReadable()
    {
        $r = fopen('php://output', 'w');
        $stream = new Stream($r);

        self::assertFalse($stream->isReadable());

        $stream->close();
    }

    /**
     * @dataProvider writableModeProvider
     */
    public function testWritableStream(string $mode)
    {
        $r = fopen('php://temp', $mode);
        $stream = new Stream($r);

        self::assertTrue($stream->isWritable());

        $stream->close();
    }

    public function writableModeProvider()
    {
        return [
            ['w'],
            ['w+'],
            ['rw'],
            ['r+'],
            ['x+'],
            ['c+'],
            ['wb'],
            ['w+b'],
            ['r+b'],
            ['rb+'],
            ['x+b'],
            ['c+b'],
            ['w+t'],
            ['r+t'],
            ['x+t'],
            ['c+t'],
            ['a'],
            ['a+'],
        ];
    }

    public function testReadOnlyStreamIsNotWritable()
    {
        $r = fopen('php://input', 'r');
        $stream = new Stream($r);

        self::assertFalse($stream->isWritable());

        $stream->close();
    }
}

namespace GuzzleHttp\Psr7;

use GuzzleHttp\Tests\Psr7\StreamTest;

function fread($handle, $length)
{
    return StreamTest::$isFReadError ? false : \fread($handle, $length);
}

<?php

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Stream;

/**
 * @covers GuzzleHttp\Psr7\Stream
 */
class StreamTest extends BaseTest
{
    public static $isFReadError = false;

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testConstructorThrowsExceptionOnInvalidArgument()
    {
        new Stream(true);
    }

    public function testConstructorInitializesProperties()
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, 'data');
        $stream = new Stream($handle);
        $this->assertTrue($stream->isReadable());
        $this->assertTrue($stream->isWritable());
        $this->assertTrue($stream->isSeekable());
        $this->assertSame('php://temp', $stream->getMetadata('uri'));
        $this->assertInternalType('array', $stream->getMetadata());
        $this->assertSame(4, $stream->getSize());
        $this->assertFalse($stream->eof());
        $stream->close();
    }

    public function testConstructorInitializesPropertiesWithRbPlus()
    {
        $handle = fopen('php://temp', 'rb+');
        fwrite($handle, 'data');
        $stream = new Stream($handle);
        $this->assertTrue($stream->isReadable());
        $this->assertTrue($stream->isWritable());
        $this->assertTrue($stream->isSeekable());
        $this->assertSame('php://temp', $stream->getMetadata('uri'));
        $this->assertInternalType('array', $stream->getMetadata());
        $this->assertSame(4, $stream->getSize());
        $this->assertFalse($stream->eof());
        $stream->close();
    }

    public function testStreamClosesHandleOnDestruct()
    {
        $handle = fopen('php://temp', 'r');
        $stream = new Stream($handle);
        unset($stream);
        $this->assertFalse(is_resource($handle));
    }

    public function testConvertsToString()
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, 'data');
        $stream = new Stream($handle);
        $this->assertSame('data', (string) $stream);
        $this->assertSame('data', (string) $stream);
        $stream->close();
    }

    public function testConvertsToStringNonSeekableStream()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('This does not work on HHVM.');
        }

        $handle = popen('echo foo', 'r');
        $stream = new Stream($handle);
        $this->assertFalse($stream->isSeekable());
        $this->assertSame('foo', trim((string) $stream));
    }

    public function testConvertsToStringNonSeekablePartiallyReadStream()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('This does not work on HHVM.');
        }

        $handle = popen('echo bar', 'r');
        $stream = new Stream($handle);
        $firstLetter = $stream->read(1);
        $this->assertFalse($stream->isSeekable());
        $this->assertSame('b', $firstLetter);
        $this->assertSame('ar', trim((string) $stream));
    }

    public function testGetsContents()
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, 'data');
        $stream = new Stream($handle);
        $this->assertSame('', $stream->getContents());
        $stream->seek(0);
        $this->assertSame('data', $stream->getContents());
        $this->assertSame('', $stream->getContents());
        $stream->close();
    }

    public function testChecksEof()
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, 'data');
        $stream = new Stream($handle);
        $this->assertSame(4, $stream->tell(), 'Stream cursor already at the end');
        $this->assertFalse($stream->eof(), 'Stream still not eof');
        $this->assertSame('', $stream->read(1), 'Need to read one more byte to reach eof');
        $this->assertTrue($stream->eof());
        $stream->close();
    }

    public function testGetSize()
    {
        $size = filesize(__FILE__);
        $handle = fopen(__FILE__, 'r');
        $stream = new Stream($handle);
        $this->assertSame($size, $stream->getSize());
        // Load from cache
        $this->assertSame($size, $stream->getSize());
        $stream->close();
    }

    public function testEnsuresSizeIsConsistent()
    {
        $h = fopen('php://temp', 'w+');
        $this->assertSame(3, fwrite($h, 'foo'));
        $stream = new Stream($h);
        $this->assertSame(3, $stream->getSize());
        $this->assertSame(4, $stream->write('test'));
        $this->assertSame(7, $stream->getSize());
        $this->assertSame(7, $stream->getSize());
        $stream->close();
    }

    public function testProvidesStreamPosition()
    {
        $handle = fopen('php://temp', 'w+');
        $stream = new Stream($handle);
        $this->assertSame(0, $stream->tell());
        $stream->write('foo');
        $this->assertSame(3, $stream->tell());
        $stream->seek(1);
        $this->assertSame(1, $stream->tell());
        $this->assertSame(ftell($handle), $stream->tell());
        $stream->close();
    }

    public function testDetachStreamAndClearProperties()
    {
        $handle = fopen('php://temp', 'r');
        $stream = new Stream($handle);
        $this->assertSame($handle, $stream->detach());
        $this->assertInternalType('resource', $handle, 'Stream is not closed');
        $this->assertNull($stream->detach());

        $this->assertStreamStateAfterClosedOrDetached($stream);

        $stream->close();
    }

    public function testCloseResourceAndClearProperties()
    {
        $handle = fopen('php://temp', 'r');
        $stream = new Stream($handle);
        $stream->close();

        $this->assertFalse(is_resource($handle));

        $this->assertStreamStateAfterClosedOrDetached($stream);
    }

    private function assertStreamStateAfterClosedOrDetached(Stream $stream)
    {
        $this->assertFalse($stream->isReadable());
        $this->assertFalse($stream->isWritable());
        $this->assertFalse($stream->isSeekable());
        $this->assertNull($stream->getSize());
        $this->assertSame([], $stream->getMetadata());
        $this->assertNull($stream->getMetadata('foo'));

        $throws = function (callable $fn) {
            try {
                $fn();
            } catch (\Exception $e) {
                $this->assertContains('Stream is detached', $e->getMessage());

                return;
            }

            $this->fail('Exception should be thrown after the stream is detached.');
        };

        $throws(function () use ($stream) { $stream->read(10); });
        $throws(function () use ($stream) { $stream->write('bar'); });
        $throws(function () use ($stream) { $stream->seek(10); });
        $throws(function () use ($stream) { $stream->tell(); });
        $throws(function () use ($stream) { $stream->eof(); });
        $throws(function () use ($stream) { $stream->getContents(); });
        $this->assertSame('', (string) $stream);
    }

    public function testStreamReadingWithZeroLength()
    {
        $r = fopen('php://temp', 'r');
        $stream = new Stream($r);

        $this->assertSame('', $stream->read(0));

        $stream->close();
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Length parameter cannot be negative
     */
    public function testStreamReadingWithNegativeLength()
    {
        $r = fopen('php://temp', 'r');
        $stream = new Stream($r);

        try {
            $stream->read(-1);
        } catch (\Exception $e) {
            $stream->close();
            throw $e;
        }

        $stream->close();
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Unable to read from stream
     */
    public function testStreamReadingFreadError()
    {
        self::$isFReadError = true;
        $r = fopen('php://temp', 'r');
        $stream = new Stream($r);

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
     * @dataProvider gzipModeProvider
     *
     * @param string $mode
     * @param bool   $readable
     * @param bool   $writable
     */
    public function testGzipStreamModes($mode, $readable, $writable)
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('This does not work on HHVM.');
        }

        $r = gzopen('php://temp', $mode);
        $stream = new Stream($r);

        $this->assertSame($readable, $stream->isReadable());
        $this->assertSame($writable, $stream->isWritable());

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
     *
     * @param string $mode
     */
    public function testReadableStream($mode)
    {
        $r = fopen('php://temp', $mode);
        $stream = new Stream($r);

        $this->assertTrue($stream->isReadable());

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

        $this->assertFalse($stream->isReadable());

        $stream->close();
    }

    /**
     * @dataProvider writableModeProvider
     *
     * @param string $mode
     */
    public function testWritableStream($mode)
    {
        $r = fopen('php://temp', $mode);
        $stream = new Stream($r);

        $this->assertTrue($stream->isWritable());

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

        $this->assertFalse($stream->isWritable());

        $stream->close();
    }
}

namespace GuzzleHttp\Psr7;

use GuzzleHttp\Tests\Psr7\StreamTest;

function fread($handle, $length)
{
    return StreamTest::$isFReadError ? false : \fread($handle, $length);
}

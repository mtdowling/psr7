<?php
namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\ByteCountingStream;

/**
 * @covers GuzzleHttp\Psr7\ByteCountingStream
 */
class ByteCountingStreamTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Bytes to read should be non-negative integer, got
     */
    public function testEnsureNonNegativeByteCount()
    {
        new ByteCountingStream(Psr7\stream_for('testing'), -2);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The ByteCountingStream decorator expects to be able to read
     */
    public function testEnsureValidByteCountNumber()
    {
        new ByteCountingStream(Psr7\stream_for('testing'), 10);
    }

    public function testByteCountingReadWhenAvailable()
    {
        $testStream = new ByteCountingStream(Psr7\stream_for('foo bar test'), 8);
        $this->assertEquals('foo ', $testStream->read(4));
        $this->assertEquals('bar ', $testStream->read(4));
        $this->assertEquals('', $testStream->read(4));
        $testStream->close();

        $testStream = new ByteCountingStream(Psr7\stream_for('testing'), 5);
        $testStream->seek(4);
        $this->assertEquals('ing', $testStream->read(5));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The ByteCountingStream decorator expects to be able to read
     */
    public function testEnsureStopReadWhenHitEof()
    {
        $testStream = new ByteCountingStream(Psr7\stream_for('abc'), 3);
        $testStream->seek(3);
        $testStream->read(3);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cannot read from non-readable stream
     */
    public function testEnsureReadUnclosedStream()
    {
        $body = Psr7\stream_for("closed");
        $closedStream = new ByteCountingStream($body, 5);
        $body->close();
        $closedStream->read(3);
    }
}

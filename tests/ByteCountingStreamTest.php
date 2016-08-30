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
     * @expectedExceptionMessage Bytes to read should be a non-negative integer for ByteCountingStream
     */
    public function testEnsureNonNegativeByteCount()
    {
        new ByteCountingStream(Psr7\stream_for('testing'), -2);
    }

    /**
     * @expectedException \GuzzleHttp\Psr7\ByteCountingStreamException
     * @expectedExceptionMessage The stream decorated by ByteCountingStream has less bytes than expected.
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
        $testStream->close();

        $testStream = new ByteCountingStream(Psr7\stream_for('00'), 2);
        $testStream->seek(1);
        $this->assertEquals('0', $testStream->read(2));
        $testStream->close();
    }

    /**
     * @expectedException \GuzzleHttp\Psr7\ByteCountingStreamException
     * @expectedExceptionMessage The stream decorated by ByteCountingStream has less bytes than expected.
     */
    public function testEnsureStopReadWhenHitEof()
    {
        $testStream = new ByteCountingStream(Psr7\stream_for('abc'), 3);
        $testStream->seek(3);
        $testStream->read(3);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The stream is detached
     */
    public function testEnsureReadUnclosedStream()
    {
        $body = Psr7\stream_for("closed");
        $closedStream = new ByteCountingStream($body, 5);
        $body->close();
        $closedStream->read(3);
    }
}

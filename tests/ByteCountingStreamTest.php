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
     * @expectedExceptionMessage Bytes per read should be non-negative integer, got
     */
    public function testEnsureNonNegativeByteCount()
    {
        new ByteCountingStream(Psr7\stream_for('testing'), -2);
    }

    public function testByteCountingReadWhenAvailable()
    {
        $testStream = new ByteCountingStream(Psr7\stream_for('testing'), 2);
        $this->assertEquals(2, strlen($testStream->readBytes()));
        $testStream->close();
    }

    public function testReadBytesUnderCount()
    {
        $lackOfBytes = new ByteCountingStream(Psr7\stream_for('foo'), 5);
        $this->assertEquals('foo', $lackOfBytes->readBytes());
        $lackOfBytes->close();

        $endingBytes = new ByteCountingStream(Psr7\stream_for('foo bar'), 4);
        $this->assertEquals('foo ', $endingBytes->readBytes());
        $this->assertEquals('bar', $endingBytes->readBytes());
        $endingBytes->close();
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The stream is detached
     */
    public function testEnsureReadUnclosedStream()
    {
        $body = Psr7\stream_for("closed");
        $closedStream = new ByteCountingStream($body, 2);
        $body->close();
        $closedStream->readBytes();
    }
}

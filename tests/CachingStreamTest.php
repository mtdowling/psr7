<?php

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\CachingStream;
use GuzzleHttp\Psr7\Stream;

/**
 * @covers GuzzleHttp\Psr7\CachingStream
 */
class CachingStreamTest extends BaseTest
{
    /** @var CachingStream */
    private $body;
    /** @var Stream */
    private $decorated;

    protected function setUp()
    {
        $this->decorated = Psr7\Utils::streamFor('testing');
        $this->body = new CachingStream($this->decorated);
    }

    protected function tearDown()
    {
        $this->decorated->close();
        $this->body->close();
    }

    public function testUsesRemoteSizeIfPossible()
    {
        $body = Psr7\Utils::streamFor('test');
        $caching = new CachingStream($body);
        $this->assertSame(4, $caching->getSize());
    }

    public function testReadsUntilCachedToByte()
    {
        $this->body->seek(5);
        $this->assertSame('n', $this->body->read(1));
        $this->body->seek(0);
        $this->assertSame('t', $this->body->read(1));
    }

    public function testCanSeekNearEndWithSeekEnd()
    {
        $baseStream = Psr7\Utils::streamFor(implode('', range('a', 'z')));
        $cached = new CachingStream($baseStream);
        $cached->seek(-1, SEEK_END);
        $this->assertSame(25, $baseStream->tell());
        $this->assertSame('z', $cached->read(1));
        $this->assertSame(26, $cached->getSize());
    }

    public function testCanSeekToEndWithSeekEnd()
    {
        $baseStream = Psr7\Utils::streamFor(implode('', range('a', 'z')));
        $cached = new CachingStream($baseStream);
        $cached->seek(0, SEEK_END);
        $this->assertSame(26, $baseStream->tell());
        $this->assertSame('', $cached->read(1));
        $this->assertSame(26, $cached->getSize());
    }

    public function testCanUseSeekEndWithUnknownSize()
    {
        $baseStream = Psr7\Utils::streamFor('testing');
        $decorated = Psr7\FnStream::decorate($baseStream, [
            'getSize' => function () { return null; }
        ]);
        $cached = new CachingStream($decorated);
        $cached->seek(-1, SEEK_END);
        $this->assertSame('g', $cached->read(1));
    }

    public function testRewindUsesSeek()
    {
        $a = Psr7\Utils::streamFor('foo');
        $d = $this->getMockBuilder('GuzzleHttp\Psr7\CachingStream')
            ->setMethods(array('seek'))
            ->setConstructorArgs(array($a))
            ->getMock();
        $d->expects($this->once())
            ->method('seek')
            ->with(0)
            ->will($this->returnValue(true));
        $d->seek(0);
    }

    public function testCanSeekToReadBytes()
    {
        $this->assertSame('te', $this->body->read(2));
        $this->body->seek(0);
        $this->assertSame('test', $this->body->read(4));
        $this->assertSame(4, $this->body->tell());
        $this->body->seek(2);
        $this->assertSame(2, $this->body->tell());
        $this->body->seek(2, SEEK_CUR);
        $this->assertSame(4, $this->body->tell());
        $this->assertSame('ing', $this->body->read(3));
    }

    public function testCanSeekToReadBytesWithPartialBodyReturned()
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, 'testing');
        fseek($stream, 0);

        $this->decorated = $this->getMockBuilder('\GuzzleHttp\Psr7\Stream')
            ->setConstructorArgs([$stream])
            ->setMethods(['read'])
            ->getMock();

        $this->decorated->expects($this->exactly(2))
            ->method('read')
            ->willReturnCallback(function($length) use ($stream){
                return fread($stream, 2);
            });

        $this->body = new CachingStream($this->decorated);

        $this->assertSame(0, $this->body->tell());
        $this->body->seek(4, SEEK_SET);
        $this->assertSame(4, $this->body->tell());

        $this->body->seek(0);
        $this->assertSame('test', $this->body->read(4));
    }

    public function testWritesToBufferStream()
    {
        $this->body->read(2);
        $this->body->write('hi');
        $this->body->seek(0);
        $this->assertSame('tehiing', (string) $this->body);
    }

    public function testSkipsOverwrittenBytes()
    {
        $decorated = Psr7\Utils::streamFor(
            implode("\n", array_map(function ($n) {
                return str_pad($n, 4, '0', STR_PAD_LEFT);
            }, range(0, 25)))
        );

        $body = new CachingStream($decorated);

        $this->assertSame("0000\n", Psr7\Utils::readLine($body));
        $this->assertSame("0001\n", Psr7\Utils::readLine($body));
        // Write over part of the body yet to be read, so skip some bytes
        $this->assertSame(5, $body->write("TEST\n"));
        $this->assertSame(5, $this->readAttribute($body, 'skipReadBytes'));
        // Read, which skips bytes, then reads
        $this->assertSame("0003\n", Psr7\Utils::readLine($body));
        $this->assertSame(0, $this->readAttribute($body, 'skipReadBytes'));
        $this->assertSame("0004\n", Psr7\Utils::readLine($body));
        $this->assertSame("0005\n", Psr7\Utils::readLine($body));

        // Overwrite part of the cached body (so don't skip any bytes)
        $body->seek(5);
        $this->assertSame(5, $body->write("ABCD\n"));
        $this->assertSame(0, $this->readAttribute($body, 'skipReadBytes'));
        $this->assertSame("TEST\n", Psr7\Utils::readLine($body));
        $this->assertSame("0003\n", Psr7\Utils::readLine($body));
        $this->assertSame("0004\n", Psr7\Utils::readLine($body));
        $this->assertSame("0005\n", Psr7\Utils::readLine($body));
        $this->assertSame("0006\n", Psr7\Utils::readLine($body));
        $this->assertSame(5, $body->write("1234\n"));
        $this->assertSame(5, $this->readAttribute($body, 'skipReadBytes'));

        // Seek to 0 and ensure the overwritten bit is replaced
        $body->seek(0);
        $this->assertSame("0000\nABCD\nTEST\n0003\n0004\n0005\n0006\n1234\n0008\n0009\n", $body->read(50));

        // Ensure that casting it to a string does not include the bit that was overwritten
        $this->assertContains("0000\nABCD\nTEST\n0003\n0004\n0005\n0006\n1234\n0008\n0009\n", (string) $body);
    }

    public function testClosesBothStreams()
    {
        $s = fopen('php://temp', 'r');
        $a = Psr7\Utils::streamFor($s);
        $d = new CachingStream($a);
        $d->close();
        $this->assertFalse(is_resource($s));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testEnsuresValidWhence()
    {
        $this->body->seek(10, -123456);
    }
}

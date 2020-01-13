<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\CachingStream;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;

/**
 * @covers GuzzleHttp\Psr7\CachingStream
 */
class CachingStreamTest extends TestCase
{
    /** @var CachingStream */
    private $body;
    /** @var Stream */
    private $decorated;

    protected function setUp(): void
    {
        $this->decorated = Utils::streamFor('testing');
        $this->body = new CachingStream($this->decorated);
    }

    protected function tearDown(): void
    {
        $this->decorated->close();
        $this->body->close();
    }

    public function testUsesRemoteSizeIfPossible()
    {
        $body = Utils::streamFor('test');
        $caching = new CachingStream($body);
        self::assertEquals(4, $caching->getSize());
    }

    public function testReadsUntilCachedToByte()
    {
        $this->body->seek(5);
        self::assertEquals('n', $this->body->read(1));
        $this->body->seek(0);
        self::assertEquals('t', $this->body->read(1));
    }

    public function testCanSeekNearEndWithSeekEnd()
    {
        $baseStream = Utils::streamFor(implode('', range('a', 'z')));
        $cached = new CachingStream($baseStream);
        $cached->seek(-1, SEEK_END);
        self::assertEquals(25, $baseStream->tell());
        self::assertEquals('z', $cached->read(1));
        self::assertEquals(26, $cached->getSize());
    }

    public function testCanSeekToEndWithSeekEnd()
    {
        $baseStream = Utils::streamFor(implode('', range('a', 'z')));
        $cached = new CachingStream($baseStream);
        $cached->seek(0, SEEK_END);
        self::assertEquals(26, $baseStream->tell());
        self::assertEquals('', $cached->read(1));
        self::assertEquals(26, $cached->getSize());
    }

    public function testCanUseSeekEndWithUnknownSize()
    {
        $baseStream = Utils::streamFor('testing');
        $decorated = FnStream::decorate($baseStream, [
            'getSize' => function () {
                return null;
            }
        ]);
        $cached = new CachingStream($decorated);
        $cached->seek(-1, SEEK_END);
        self::assertEquals('g', $cached->read(1));
    }

    public function testRewindUsesSeek()
    {
        $a = Utils::streamFor('foo');
        $d = $this->getMockBuilder(CachingStream::class)
            ->setMethods(['seek'])
            ->setConstructorArgs([$a])
            ->getMock();
        $d->expects(self::once())
            ->method('seek')
            ->with(0)
            ->will(self::returnValue(true));
        $d->seek(0);
    }

    public function testCanSeekToReadBytes()
    {
        self::assertEquals('te', $this->body->read(2));
        $this->body->seek(0);
        self::assertEquals('test', $this->body->read(4));
        self::assertEquals(4, $this->body->tell());
        $this->body->seek(2);
        self::assertEquals(2, $this->body->tell());
        $this->body->seek(2, SEEK_CUR);
        self::assertEquals(4, $this->body->tell());
        self::assertEquals('ing', $this->body->read(3));
    }

    public function testCanSeekToReadBytesWithPartialBodyReturned()
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, 'testing');
        fseek($stream, 0);

        $this->decorated = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs([$stream])
            ->setMethods(['read'])
            ->getMock();

        $this->decorated->expects(self::exactly(2))
            ->method('read')
            ->willReturnCallback(function ($length) use ($stream) {
                return fread($stream, 2);
            });

        $this->body = new CachingStream($this->decorated);

        self::assertEquals(0, $this->body->tell());
        $this->body->seek(4, SEEK_SET);
        self::assertEquals(4, $this->body->tell());

        $this->body->seek(0);
        self::assertEquals('test', $this->body->read(4));
    }

    public function testWritesToBufferStream()
    {
        $this->body->read(2);
        $this->body->write('hi');
        $this->body->seek(0);
        self::assertEquals('tehiing', (string) $this->body);
    }

    public function testSkipsOverwrittenBytes()
    {
        $decorated = Utils::streamFor(
            implode("\n", array_map(function ($n) {
                return str_pad((string)$n, 4, '0', STR_PAD_LEFT);
            }, range(0, 25)))
        );

        $body = new CachingStream($decorated);

        self::assertEquals("0000\n", Utils::readline($body));
        self::assertEquals("0001\n", Utils::readline($body));
        // Write over part of the body yet to be read, so skip some bytes
        self::assertEquals(5, $body->write("TEST\n"));
        // Read, which skips bytes, then reads
        self::assertEquals("0003\n", Utils::readline($body));
        self::assertEquals("0004\n", Utils::readline($body));
        self::assertEquals("0005\n", Utils::readline($body));

        // Overwrite part of the cached body (so don't skip any bytes)
        $body->seek(5);
        self::assertEquals(5, $body->write("ABCD\n"));
        self::assertEquals("TEST\n", Utils::readline($body));
        self::assertEquals("0003\n", Utils::readline($body));
        self::assertEquals("0004\n", Utils::readline($body));
        self::assertEquals("0005\n", Utils::readline($body));
        self::assertEquals("0006\n", Utils::readline($body));
        self::assertEquals(5, $body->write("1234\n"));

        // Seek to 0 and ensure the overwritten bit is replaced
        $body->seek(0);
        self::assertEquals("0000\nABCD\nTEST\n0003\n0004\n0005\n0006\n1234\n0008\n0009\n", $body->read(50));

        // Ensure that casting it to a string does not include the bit that was overwritten
        self::assertStringContainsString("0000\nABCD\nTEST\n0003\n0004\n0005\n0006\n1234\n0008\n0009\n", (string) $body);
    }

    public function testClosesBothStreams()
    {
        $s = fopen('php://temp', 'r');
        $a = Utils::streamFor($s);
        $d = new CachingStream($a);
        $d->close();
        self::assertFalse(is_resource($s));
    }

    public function testEnsuresValidWhence()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid whence');
        $this->body->seek(10, -123456);
    }
}

<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

class Str implements StreamInterface
{
    use StreamDecoratorTrait;
}

/**
 * @covers GuzzleHttp\Psr7\StreamDecoratorTrait
 */
class StreamDecoratorTraitTest extends TestCase
{
    /** @var StreamInterface */
    private $a;
    /** @var StreamInterface */
    private $b;
    /** @var resource */
    private $c;

    protected function setUp(): void
    {
        $this->c = fopen('php://temp', 'r+');
        fwrite($this->c, 'foo');
        fseek($this->c, 0);
        $this->a = Psr7\stream_for($this->c);
        $this->b = new Str($this->a);
    }

    /**
     * @requires PHP < 7.4
     */
    public function testCatchesExceptionsWhenCastingToString(): void
    {
        $s = $this->createMock(Str::class);
        $s->expects(self::once())
            ->method('read')
            ->willThrowException(new \RuntimeException('foo'));
        $msg = '';
        set_error_handler(function (int $errNo, string $str) use (&$msg): void {
            $msg = $str;
        });
        echo new Str($s);
        restore_error_handler();
        self::assertStringContainsString('foo', $msg);
    }

    public function testToString(): void
    {
        self::assertEquals('foo', (string) $this->b);
    }

    public function testHasSize(): void
    {
        self::assertEquals(3, $this->b->getSize());
    }

    public function testReads(): void
    {
        self::assertEquals('foo', $this->b->read(10));
    }

    public function testCheckMethods(): void
    {
        self::assertEquals($this->a->isReadable(), $this->b->isReadable());
        self::assertEquals($this->a->isWritable(), $this->b->isWritable());
        self::assertEquals($this->a->isSeekable(), $this->b->isSeekable());
    }

    public function testSeeksAndTells(): void
    {
        $this->b->seek(1);
        self::assertEquals(1, $this->a->tell());
        self::assertEquals(1, $this->b->tell());
        $this->b->seek(0);
        self::assertEquals(0, $this->a->tell());
        self::assertEquals(0, $this->b->tell());
        $this->b->seek(0, SEEK_END);
        self::assertEquals(3, $this->a->tell());
        self::assertEquals(3, $this->b->tell());
    }

    public function testGetsContents(): void
    {
        self::assertEquals('foo', $this->b->getContents());
        self::assertEquals('', $this->b->getContents());
        $this->b->seek(1);
        self::assertEquals('oo', $this->b->getContents());
    }

    public function testCloses(): void
    {
        $this->b->close();
        self::assertFalse(is_resource($this->c));
    }

    public function testDetaches(): void
    {
        $this->b->detach();
        self::assertFalse($this->b->isReadable());
    }

    public function testWrapsMetadata(): void
    {
        self::assertSame($this->b->getMetadata(), $this->a->getMetadata());
        self::assertSame($this->b->getMetadata('uri'), $this->a->getMetadata('uri'));
    }

    public function testWrapsWrites(): void
    {
        $this->b->seek(0, SEEK_END);
        $this->b->write('foo');
        self::assertEquals('foofoo', (string) $this->a);
    }

    public function testThrowsWithInvalidGetter(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->b->foo;
    }

    public function testThrowsWhenGetterNotImplemented(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $s = new BadStream();
        $s->stream;
    }
}

class BadStream
{
    use StreamDecoratorTrait;

    public function __construct()
    {
    }
}

<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Utils;
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
        $this->a = Utils::streamFor($this->c);
        $this->b = new Str($this->a);
    }

    public function testCatchesExceptionsWhenCastingToString()
    {
        $s = $this->getMockBuilder(StreamInterface::class)
            ->setMethods(['read'])
            ->getMockForAbstractClass();
        $s->expects(self::once())
            ->method('read')
            ->will(self::throwException(new \Exception('foo')));
        $msg = '';
        set_error_handler(function ($errNo, $str) use (&$msg) {
            $msg = $str;
        });
        echo new Str($s);
        restore_error_handler();
        self::assertStringContainsString('foo', $msg);
    }

    public function testToString()
    {
        self::assertEquals('foo', (string) $this->b);
    }

    public function testHasSize()
    {
        self::assertEquals(3, $this->b->getSize());
    }

    public function testReads()
    {
        self::assertEquals('foo', $this->b->read(10));
    }

    public function testCheckMethods()
    {
        self::assertEquals($this->a->isReadable(), $this->b->isReadable());
        self::assertEquals($this->a->isWritable(), $this->b->isWritable());
        self::assertEquals($this->a->isSeekable(), $this->b->isSeekable());
    }

    public function testSeeksAndTells()
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

    public function testGetsContents()
    {
        self::assertEquals('foo', $this->b->getContents());
        self::assertEquals('', $this->b->getContents());
        $this->b->seek(1);
        self::assertEquals('oo', $this->b->getContents());
    }

    public function testCloses()
    {
        $this->b->close();
        self::assertFalse(is_resource($this->c));
    }

    public function testDetaches()
    {
        $this->b->detach();
        self::assertFalse($this->b->isReadable());
    }

    public function testWrapsMetadata()
    {
        self::assertSame($this->b->getMetadata(), $this->a->getMetadata());
        self::assertSame($this->b->getMetadata('uri'), $this->a->getMetadata('uri'));
    }

    public function testWrapsWrites()
    {
        $this->b->seek(0, SEEK_END);
        $this->b->write('foo');
        self::assertEquals('foofoo', (string) $this->a);
    }

    public function testThrowsWithInvalidGetter()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->b->foo;
    }

    public function testThrowsWhenGetterNotImplemented()
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

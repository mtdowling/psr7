<?php

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;

class Str implements StreamInterface
{
    use StreamDecoratorTrait;
}

/**
 * @covers GuzzleHttp\Psr7\StreamDecoratorTrait
 */
class StreamDecoratorTraitTest extends BaseTest
{
    /** @var StreamInterface */
    private $a;
    /** @var StreamInterface */
    private $b;
    /** @var resource */
    private $c;

    /**
     * @before
     */
    public function setUpTest()
    {
        $this->c = fopen('php://temp', 'r+');
        fwrite($this->c, 'foo');
        fseek($this->c, 0);
        $this->a = Psr7\Utils::streamFor($this->c);
        $this->b = new Str($this->a);
    }

    public function testCatchesExceptionsWhenCastingToString()
    {
        $s = $this->getMockBuilder('Psr\Http\Message\StreamInterface')
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
        $this->assertStringContainsStringGuzzle('foo', $msg);
    }

    public function testToString()
    {
        self::assertSame('foo', (string) $this->b);
    }

    public function testHasSize()
    {
        self::assertSame(3, $this->b->getSize());
    }

    public function testReads()
    {
        self::assertSame('foo', $this->b->read(10));
    }

    public function testCheckMethods()
    {
        self::assertSame($this->a->isReadable(), $this->b->isReadable());
        self::assertSame($this->a->isWritable(), $this->b->isWritable());
        self::assertSame($this->a->isSeekable(), $this->b->isSeekable());
    }

    public function testSeeksAndTells()
    {
        $this->b->seek(1);
        self::assertSame(1, $this->a->tell());
        self::assertSame(1, $this->b->tell());
        $this->b->seek(0);
        self::assertSame(0, $this->a->tell());
        self::assertSame(0, $this->b->tell());
        $this->b->seek(0, SEEK_END);
        self::assertSame(3, $this->a->tell());
        self::assertSame(3, $this->b->tell());
    }

    public function testGetsContents()
    {
        self::assertSame('foo', $this->b->getContents());
        self::assertSame('', $this->b->getContents());
        $this->b->seek(1);
        self::assertSame('oo', $this->b->getContents());
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
        self::assertSame('foofoo', (string) $this->a);
    }

    public function testThrowsWithInvalidGetter()
    {
        $this->expectExceptionGuzzle('UnexpectedValueException');

        $this->b->foo;
    }

    public function testThrowsWhenGetterNotImplemented()
    {
        $s = new BadStream();

        $this->expectExceptionGuzzle('BadMethodCallException');

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

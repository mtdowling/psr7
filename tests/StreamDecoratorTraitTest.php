<?php

namespace GuzzleHttp\Tests\Psr7;

use Psr\Http\Message\StreamInterface;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\StreamDecoratorTrait;

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

    protected function setUp()
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
        $s->expects($this->once())
            ->method('read')
            ->will($this->throwException(new \Exception('foo')));
        $msg = '';
        set_error_handler(function ($errNo, $str) use (&$msg) { $msg = $str; });
        echo new Str($s);
        restore_error_handler();
        $this->assertContains('foo', $msg);
    }

    public function testToString()
    {
        $this->assertSame('foo', (string) $this->b);
    }

    public function testHasSize()
    {
        $this->assertSame(3, $this->b->getSize());
    }

    public function testReads()
    {
        $this->assertSame('foo', $this->b->read(10));
    }

    public function testCheckMethods()
    {
        $this->assertSame($this->a->isReadable(), $this->b->isReadable());
        $this->assertSame($this->a->isWritable(), $this->b->isWritable());
        $this->assertSame($this->a->isSeekable(), $this->b->isSeekable());
    }

    public function testSeeksAndTells()
    {
        $this->b->seek(1);
        $this->assertSame(1, $this->a->tell());
        $this->assertSame(1, $this->b->tell());
        $this->b->seek(0);
        $this->assertSame(0, $this->a->tell());
        $this->assertSame(0, $this->b->tell());
        $this->b->seek(0, SEEK_END);
        $this->assertSame(3, $this->a->tell());
        $this->assertSame(3, $this->b->tell());
    }

    public function testGetsContents()
    {
        $this->assertSame('foo', $this->b->getContents());
        $this->assertSame('', $this->b->getContents());
        $this->b->seek(1);
        $this->assertSame('oo', $this->b->getContents());
    }

    public function testCloses()
    {
        $this->b->close();
        $this->assertFalse(is_resource($this->c));
    }

    public function testDetaches()
    {
        $this->b->detach();
        $this->assertFalse($this->b->isReadable());
    }

    public function testWrapsMetadata()
    {
        $this->assertSame($this->b->getMetadata(), $this->a->getMetadata());
        $this->assertSame($this->b->getMetadata('uri'), $this->a->getMetadata('uri'));
    }

    public function testWrapsWrites()
    {
        $this->b->seek(0, SEEK_END);
        $this->b->write('foo');
        $this->assertSame('foofoo', (string) $this->a);
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testThrowsWithInvalidGetter()
    {
        $this->b->foo;
    }

    /**
     * @expectedException \BadMethodCallException
     */
    public function testThrowsWhenGetterNotImplemented()
    {
        $s = new BadStream();
        $s->stream;
    }
}

class BadStream
{
    use StreamDecoratorTrait;

    public function __construct() {}
}

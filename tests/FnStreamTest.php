<?php

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\FnStream;

/**
 * @covers GuzzleHttp\Psr7\FnStream
 */
class FnStreamTest extends BaseTest
{
    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage seek() is not implemented in the FnStream
     */
    public function testThrowsWhenNotImplemented()
    {
        (new FnStream([]))->seek(1);
    }

    public function testProxiesToFunction()
    {
        $s = new FnStream([
            'read' => function ($len) {
                $this->assertSame(3, $len);
                return 'foo';
            }
        ]);

        $this->assertSame('foo', $s->read(3));
    }

    public function testCanCloseOnDestruct()
    {
        $called = false;
        $s = new FnStream([
            'close' => function () use (&$called) {
                $called = true;
            }
        ]);
        unset($s);
        $this->assertTrue($called);
    }

    public function testDoesNotRequireClose()
    {
        $s = new FnStream([]);
        unset($s);
        $this->assertTrue(true); // strict mode requires an assertion
    }

    public function testDecoratesStream()
    {
        $a = Psr7\Utils::streamFor('foo');
        $b = FnStream::decorate($a, []);
        $this->assertSame(3, $b->getSize());
        $this->assertSame($b->isWritable(), true);
        $this->assertSame($b->isReadable(), true);
        $this->assertSame($b->isSeekable(), true);
        $this->assertSame($b->read(3), 'foo');
        $this->assertSame($b->tell(), 3);
        $this->assertSame($a->tell(), 3);
        $this->assertSame('', $a->read(1));
        $this->assertSame($b->eof(), true);
        $this->assertSame($a->eof(), true);
        $b->seek(0);
        $this->assertSame('foo', (string) $b);
        $b->seek(0);
        $this->assertSame('foo', $b->getContents());
        $this->assertSame($a->getMetadata(), $b->getMetadata());
        $b->seek(0, SEEK_END);
        $b->write('bar');
        $this->assertSame('foobar', (string) $b);
        $this->assertInternalType('resource', $b->detach());
        $b->close();
    }

    public function testDecoratesWithCustomizations()
    {
        $called = false;
        $a = Psr7\Utils::streamFor('foo');
        $b = FnStream::decorate($a, [
            'read' => function ($len) use (&$called, $a) {
                $called = true;
                return $a->read($len);
            }
        ]);
        $this->assertSame('foo', $b->read(3));
        $this->assertTrue($called);
    }

    public function testDoNotAllowUnserialization()
    {
        $a = new FnStream([]);
        $b = serialize($a);
        $this->expectException('\LogicException', 'FnStream should never be unserialized');
        unserialize($b);
    }
}

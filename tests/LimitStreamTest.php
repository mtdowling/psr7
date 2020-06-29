<?php

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\NoSeekStream;

/**
 * @covers GuzzleHttp\Psr7\LimitStream
 */
class LimitStreamTest extends BaseTest
{
    /** @var LimitStream */
    private $body;

    /** @var Stream */
    private $decorated;

    protected function setUp()
    {
        $this->decorated = Psr7\Utils::streamFor(fopen(__FILE__, 'r'));
        $this->body = new LimitStream($this->decorated, 10, 3);
    }

    public function testReturnsSubset()
    {
        $body = new LimitStream(Psr7\Utils::streamFor('foo'), -1, 1);
        $this->assertSame('oo', (string) $body);
        $this->assertTrue($body->eof());
        $body->seek(0);
        $this->assertFalse($body->eof());
        $this->assertSame('oo', $body->read(100));
        $this->assertSame('', $body->read(1));
        $this->assertTrue($body->eof());
    }

    public function testReturnsSubsetWhenCastToString()
    {
        $body = Psr7\Utils::streamFor('foo_baz_bar');
        $limited = new LimitStream($body, 3, 4);
        $this->assertSame('baz', (string) $limited);
    }

    public function testReturnsSubsetOfEmptyBodyWhenCastToString()
    {
        $body = Psr7\Utils::streamFor('01234567891234');
        $limited = new LimitStream($body, 0, 10);
        $this->assertSame('', (string) $limited);
    }

    public function testReturnsSpecificSubsetOBodyWhenCastToString()
    {
        $body = Psr7\Utils::streamFor('0123456789abcdef');
        $limited = new LimitStream($body, 3, 10);
        $this->assertSame('abc', (string) $limited);
    }

    public function testSeeksWhenConstructed()
    {
        $this->assertSame(0, $this->body->tell());
        $this->assertSame(3, $this->decorated->tell());
    }

    public function testAllowsBoundedSeek()
    {
        $this->body->seek(100);
        $this->assertSame(10, $this->body->tell());
        $this->assertSame(13, $this->decorated->tell());
        $this->body->seek(0);
        $this->assertSame(0, $this->body->tell());
        $this->assertSame(3, $this->decorated->tell());
        try {
            $this->body->seek(-10);
            $this->fail();
        } catch (\RuntimeException $e) {}
        $this->assertSame(0, $this->body->tell());
        $this->assertSame(3, $this->decorated->tell());
        $this->body->seek(5);
        $this->assertSame(5, $this->body->tell());
        $this->assertSame(8, $this->decorated->tell());
        // Fail
        try {
            $this->body->seek(1000, SEEK_END);
            $this->fail();
        } catch (\RuntimeException $e) {}
    }

    public function testReadsOnlySubsetOfData()
    {
        $data = $this->body->read(100);
        $this->assertSame(10, strlen($data));
        $this->assertSame('', $this->body->read(1000));

        $this->body->setOffset(10);
        $newData = $this->body->read(100);
        $this->assertSame(10, strlen($newData));
        $this->assertNotSame($data, $newData);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Could not seek to stream offset 2
     */
    public function testThrowsWhenCurrentGreaterThanOffsetSeek()
    {
        $a = Psr7\Utils::streamFor('foo_bar');
        $b = new NoSeekStream($a);
        $c = new LimitStream($b);
        $a->getContents();
        $c->setOffset(2);
    }

    public function testCanGetContentsWithoutSeeking()
    {
        $a = Psr7\Utils::streamFor('foo_bar');
        $b = new NoSeekStream($a);
        $c = new LimitStream($b);
        $this->assertSame('foo_bar', $c->getContents());
    }

    public function testClaimsConsumedWhenReadLimitIsReached()
    {
        $this->assertFalse($this->body->eof());
        $this->body->read(1000);
        $this->assertTrue($this->body->eof());
    }

    public function testContentLengthIsBounded()
    {
        $this->assertSame(10, $this->body->getSize());
    }

    public function testGetContentsIsBasedOnSubset()
    {
        $body = new LimitStream(Psr7\Utils::streamFor('foobazbar'), 3, 3);
        $this->assertSame('baz', $body->getContents());
    }

    public function testReturnsNullIfSizeCannotBeDetermined()
    {
        $a = new FnStream([
            'getSize' => function () { return null; },
            'tell'    => function () { return 0; },
        ]);
        $b = new LimitStream($a);
        $this->assertNull($b->getSize());
    }

    public function testLengthLessOffsetWhenNoLimitSize()
    {
        $a = Psr7\Utils::streamFor('foo_bar');
        $b = new LimitStream($a, -1, 4);
        $this->assertSame(3, $b->getSize());
    }
}

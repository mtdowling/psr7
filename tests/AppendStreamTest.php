<?php

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\AppendStream;

class AppendStreamTest extends BaseTest
{
    public function testValidatesStreamsAreReadable()
    {
        $a = new AppendStream();
        $s = $this->getMockBuilder('Psr\Http\Message\StreamInterface')
            ->setMethods(['isReadable'])
            ->getMockForAbstractClass();
        $s->expects(self::once())
            ->method('isReadable')
            ->will(self::returnValue(false));

        $this->expectExceptionGuzzle('InvalidArgumentException', 'Each stream must be readable');

        $a->addStream($s);
    }

    public function testValidatesSeekType()
    {
        $a = new AppendStream();

        $this->expectExceptionGuzzle('RuntimeException', 'The AppendStream can only seek with SEEK_SET');

        $a->seek(100, SEEK_CUR);
    }

    public function testTriesToRewindOnSeek()
    {
        $a = new AppendStream();
        $s = $this->getMockBuilder('Psr\Http\Message\StreamInterface')
            ->setMethods(['isReadable', 'rewind', 'isSeekable'])
            ->getMockForAbstractClass();
        $s->expects(self::once())
            ->method('isReadable')
            ->will(self::returnValue(true));
        $s->expects(self::once())
            ->method('isSeekable')
            ->will(self::returnValue(true));
        $s->expects(self::once())
            ->method('rewind')
            ->will(self::throwException(new \RuntimeException()));
        $a->addStream($s);

        $this->expectExceptionGuzzle('RuntimeException', 'Unable to seek stream 0 of the AppendStream');

        $a->seek(10);
    }

    public function testSeeksToPositionByReading()
    {
        $a = new AppendStream([
            Psr7\Utils::streamFor('foo'),
            Psr7\Utils::streamFor('bar'),
            Psr7\Utils::streamFor('baz'),
        ]);

        $a->seek(3);
        self::assertSame(3, $a->tell());
        self::assertSame('bar', $a->read(3));

        $a->seek(6);
        self::assertSame(6, $a->tell());
        self::assertSame('baz', $a->read(3));
    }

    public function testDetachWithoutStreams()
    {
        $s = new AppendStream();
        $s->detach();

        self::assertSame(0, $s->getSize());
        self::assertTrue($s->eof());
        self::assertTrue($s->isReadable());
        self::assertSame('', (string) $s);
        self::assertTrue($s->isSeekable());
        self::assertFalse($s->isWritable());
    }

    public function testDetachesEachStream()
    {
        $handle = fopen('php://temp', 'r');

        $s1 = Psr7\Utils::streamFor($handle);
        $s2 = Psr7\Utils::streamFor('bar');
        $a = new AppendStream([$s1, $s2]);

        $a->detach();

        self::assertSame(0, $a->getSize());
        self::assertTrue($a->eof());
        self::assertTrue($a->isReadable());
        self::assertSame('', (string) $a);
        self::assertTrue($a->isSeekable());
        self::assertFalse($a->isWritable());

        self::assertNull($s1->detach());
        $this->assertInternalTypeGuzzle('resource', $handle, 'resource is not closed when detaching');
        fclose($handle);
    }

    public function testClosesEachStream()
    {
        $handle = fopen('php://temp', 'r');

        $s1 = Psr7\Utils::streamFor($handle);
        $s2 = Psr7\Utils::streamFor('bar');
        $a = new AppendStream([$s1, $s2]);

        $a->close();

        self::assertSame(0, $a->getSize());
        self::assertTrue($a->eof());
        self::assertTrue($a->isReadable());
        self::assertSame('', (string) $a);
        self::assertTrue($a->isSeekable());
        self::assertFalse($a->isWritable());

        self::assertFalse(is_resource($handle));
    }

    public function testIsNotWritable()
    {
        $a = new AppendStream([Psr7\Utils::streamFor('foo')]);
        self::assertFalse($a->isWritable());
        self::assertTrue($a->isSeekable());
        self::assertTrue($a->isReadable());

        $this->expectExceptionGuzzle('RuntimeException', 'Cannot write to an AppendStream');

        $a->write('foo');
    }

    public function testDoesNotNeedStreams()
    {
        $a = new AppendStream();
        self::assertSame('', (string) $a);
    }

    public function testCanReadFromMultipleStreams()
    {
        $a = new AppendStream([
            Psr7\Utils::streamFor('foo'),
            Psr7\Utils::streamFor('bar'),
            Psr7\Utils::streamFor('baz'),
        ]);
        self::assertFalse($a->eof());
        self::assertSame(0, $a->tell());
        self::assertSame('foo', $a->read(3));
        self::assertSame('bar', $a->read(3));
        self::assertSame('baz', $a->read(3));
        self::assertSame('', $a->read(1));
        self::assertTrue($a->eof());
        self::assertSame(9, $a->tell());
        self::assertSame('foobarbaz', (string) $a);
    }

    public function testCanDetermineSizeFromMultipleStreams()
    {
        $a = new AppendStream([
            Psr7\Utils::streamFor('foo'),
            Psr7\Utils::streamFor('bar')
        ]);
        self::assertSame(6, $a->getSize());

        $s = $this->getMockBuilder('Psr\Http\Message\StreamInterface')
            ->setMethods(['isSeekable', 'isReadable'])
            ->getMockForAbstractClass();
        $s->expects(self::once())
            ->method('isSeekable')
            ->will(self::returnValue(null));
        $s->expects(self::once())
            ->method('isReadable')
            ->will(self::returnValue(true));
        $a->addStream($s);
        self::assertNull($a->getSize());
    }

    public function testCatchesExceptionsWhenCastingToString()
    {
        $s = $this->getMockBuilder('Psr\Http\Message\StreamInterface')
            ->setMethods(['isSeekable', 'read', 'isReadable', 'eof'])
            ->getMockForAbstractClass();
        $s->expects(self::once())
            ->method('isSeekable')
            ->will(self::returnValue(true));
        $s->expects(self::once())
            ->method('read')
            ->will(self::throwException(new \RuntimeException('foo')));
        $s->expects(self::once())
            ->method('isReadable')
            ->will(self::returnValue(true));
        $s->expects(self::any())
            ->method('eof')
            ->will(self::returnValue(false));
        $a = new AppendStream([$s]);
        self::assertFalse($a->eof());
        self::assertSame('', (string) $a);
    }

    public function testReturnsEmptyMetadata()
    {
        $s = new AppendStream();
        self::assertSame([], $s->getMetadata());
        self::assertNull($s->getMetadata('foo'));
    }
}

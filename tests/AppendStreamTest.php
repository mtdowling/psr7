<?php

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\AppendStream;
use GuzzleHttp\Psr7;

class AppendStreamTest extends BaseTest
{
    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Each stream must be readable
     */
    public function testValidatesStreamsAreReadable()
    {
        $a = new AppendStream();
        $s = $this->getMockBuilder('Psr\Http\Message\StreamInterface')
            ->setMethods(['isReadable'])
            ->getMockForAbstractClass();
        $s->expects($this->once())
            ->method('isReadable')
            ->will($this->returnValue(false));
        $a->addStream($s);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The AppendStream can only seek with SEEK_SET
     */
    public function testValidatesSeekType()
    {
        $a = new AppendStream();
        $a->seek(100, SEEK_CUR);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Unable to seek stream 0 of the AppendStream
     */
    public function testTriesToRewindOnSeek()
    {
        $a = new AppendStream();
        $s = $this->getMockBuilder('Psr\Http\Message\StreamInterface')
            ->setMethods(['isReadable', 'rewind', 'isSeekable'])
            ->getMockForAbstractClass();
        $s->expects($this->once())
            ->method('isReadable')
            ->will($this->returnValue(true));
        $s->expects($this->once())
            ->method('isSeekable')
            ->will($this->returnValue(true));
        $s->expects($this->once())
            ->method('rewind')
            ->will($this->throwException(new \RuntimeException()));
        $a->addStream($s);
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
        $this->assertSame(3, $a->tell());
        $this->assertSame('bar', $a->read(3));

        $a->seek(6);
        $this->assertSame(6, $a->tell());
        $this->assertSame('baz', $a->read(3));
    }

    public function testDetachWithoutStreams()
    {
        $s = new AppendStream();
        $s->detach();

        $this->assertSame(0, $s->getSize());
        $this->assertTrue($s->eof());
        $this->assertTrue($s->isReadable());
        $this->assertSame('', (string) $s);
        $this->assertTrue($s->isSeekable());
        $this->assertFalse($s->isWritable());
    }

    public function testDetachesEachStream()
    {
        $handle = fopen('php://temp', 'r');

        $s1 = Psr7\Utils::streamFor($handle);
        $s2 = Psr7\Utils::streamFor('bar');
        $a = new AppendStream([$s1, $s2]);

        $a->detach();

        $this->assertSame(0, $a->getSize());
        $this->assertTrue($a->eof());
        $this->assertTrue($a->isReadable());
        $this->assertSame('', (string) $a);
        $this->assertTrue($a->isSeekable());
        $this->assertFalse($a->isWritable());

        $this->assertNull($s1->detach());
        $this->assertInternalType('resource', $handle, 'resource is not closed when detaching');
        fclose($handle);
    }

    public function testClosesEachStream()
    {
        $handle = fopen('php://temp', 'r');

        $s1 = Psr7\Utils::streamFor($handle);
        $s2 = Psr7\Utils::streamFor('bar');
        $a = new AppendStream([$s1, $s2]);

        $a->close();

        $this->assertSame(0, $a->getSize());
        $this->assertTrue($a->eof());
        $this->assertTrue($a->isReadable());
        $this->assertSame('', (string) $a);
        $this->assertTrue($a->isSeekable());
        $this->assertFalse($a->isWritable());

        $this->assertFalse(is_resource($handle));
    }

    /**
     * @expectedExceptionMessage Cannot write to an AppendStream
     * @expectedException \RuntimeException
     */
    public function testIsNotWritable()
    {
        $a = new AppendStream([Psr7\Utils::streamFor('foo')]);
        $this->assertFalse($a->isWritable());
        $this->assertTrue($a->isSeekable());
        $this->assertTrue($a->isReadable());
        $a->write('foo');
    }

    public function testDoesNotNeedStreams()
    {
        $a = new AppendStream();
        $this->assertSame('', (string) $a);
    }

    public function testCanReadFromMultipleStreams()
    {
        $a = new AppendStream([
            Psr7\Utils::streamFor('foo'),
            Psr7\Utils::streamFor('bar'),
            Psr7\Utils::streamFor('baz'),
        ]);
        $this->assertFalse($a->eof());
        $this->assertSame(0, $a->tell());
        $this->assertSame('foo', $a->read(3));
        $this->assertSame('bar', $a->read(3));
        $this->assertSame('baz', $a->read(3));
        $this->assertSame('', $a->read(1));
        $this->assertTrue($a->eof());
        $this->assertSame(9, $a->tell());
        $this->assertSame('foobarbaz', (string) $a);
    }

    public function testCanDetermineSizeFromMultipleStreams()
    {
        $a = new AppendStream([
            Psr7\Utils::streamFor('foo'),
            Psr7\Utils::streamFor('bar')
        ]);
        $this->assertSame(6, $a->getSize());

        $s = $this->getMockBuilder('Psr\Http\Message\StreamInterface')
            ->setMethods(['isSeekable', 'isReadable'])
            ->getMockForAbstractClass();
        $s->expects($this->once())
            ->method('isSeekable')
            ->will($this->returnValue(null));
        $s->expects($this->once())
            ->method('isReadable')
            ->will($this->returnValue(true));
        $a->addStream($s);
        $this->assertNull($a->getSize());
    }

    public function testCatchesExceptionsWhenCastingToString()
    {
        $s = $this->getMockBuilder('Psr\Http\Message\StreamInterface')
            ->setMethods(['isSeekable', 'read', 'isReadable', 'eof'])
            ->getMockForAbstractClass();
        $s->expects($this->once())
            ->method('isSeekable')
            ->will($this->returnValue(true));
        $s->expects($this->once())
            ->method('read')
            ->will($this->throwException(new \RuntimeException('foo')));
        $s->expects($this->once())
            ->method('isReadable')
            ->will($this->returnValue(true));
        $s->expects($this->any())
            ->method('eof')
            ->will($this->returnValue(false));
        $a = new AppendStream([$s]);
        $this->assertFalse($a->eof());
        $this->assertSame('', (string) $a);
    }

    public function testReturnsEmptyMetadata()
    {
        $s = new AppendStream();
        $this->assertSame([], $s->getMetadata());
        $this->assertNull($s->getMetadata('foo'));
    }
}

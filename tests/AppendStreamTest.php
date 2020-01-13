<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\AppendStream;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

class AppendStreamTest extends TestCase
{
    public function testValidatesStreamsAreReadable()
    {
        $a = new AppendStream();
        $s = $this->getMockBuilder(StreamInterface::class)
            ->setMethods(['isReadable'])
            ->getMockForAbstractClass();
        $s->expects(self::once())
            ->method('isReadable')
            ->will(self::returnValue(false));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Each stream must be readable');
        $a->addStream($s);
    }

    public function testValidatesSeekType()
    {
        $a = new AppendStream();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The AppendStream can only seek with SEEK_SET');
        $a->seek(100, SEEK_CUR);
    }

    public function testTriesToRewindOnSeek()
    {
        $a = new AppendStream();
        $s = $this->getMockBuilder(StreamInterface::class)
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
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to seek stream 0 of the AppendStream');
        $a->seek(10);
    }

    public function testSeeksToPositionByReading()
    {
        $a = new AppendStream([
            Utils::streamFor('foo'),
            Utils::streamFor('bar'),
            Utils::streamFor('baz'),
        ]);

        $a->seek(3);
        self::assertEquals(3, $a->tell());
        self::assertEquals('bar', $a->read(3));

        $a->seek(6);
        self::assertEquals(6, $a->tell());
        self::assertEquals('baz', $a->read(3));
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

        $s1 = Utils::streamFor($handle);
        $s2 = Utils::streamFor('bar');
        $a = new AppendStream([$s1, $s2]);

        $a->detach();

        self::assertSame(0, $a->getSize());
        self::assertTrue($a->eof());
        self::assertTrue($a->isReadable());
        self::assertSame('', (string) $a);
        self::assertTrue($a->isSeekable());
        self::assertFalse($a->isWritable());

        self::assertNull($s1->detach());
        self::assertIsResource($handle, 'resource is not closed when detaching');
        fclose($handle);
    }

    public function testClosesEachStream()
    {
        $handle = fopen('php://temp', 'r');

        $s1 = Utils::streamFor($handle);
        $s2 = Utils::streamFor('bar');
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
        $a = new AppendStream([Utils::streamFor('foo')]);
        self::assertFalse($a->isWritable());
        self::assertTrue($a->isSeekable());
        self::assertTrue($a->isReadable());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot write to an AppendStream');
        $a->write('foo');
    }

    public function testDoesNotNeedStreams()
    {
        $a = new AppendStream();
        self::assertEquals('', (string) $a);
    }

    public function testCanReadFromMultipleStreams()
    {
        $a = new AppendStream([
            Utils::streamFor('foo'),
            Utils::streamFor('bar'),
            Utils::streamFor('baz'),
        ]);
        self::assertFalse($a->eof());
        self::assertSame(0, $a->tell());
        self::assertEquals('foo', $a->read(3));
        self::assertEquals('bar', $a->read(3));
        self::assertEquals('baz', $a->read(3));
        self::assertSame('', $a->read(1));
        self::assertTrue($a->eof());
        self::assertSame(9, $a->tell());
        self::assertEquals('foobarbaz', (string) $a);
    }

    public function testCanDetermineSizeFromMultipleStreams()
    {
        $a = new AppendStream([
            Utils::streamFor('foo'),
            Utils::streamFor('bar')
        ]);
        self::assertEquals(6, $a->getSize());

        $s = $this->getMockBuilder(StreamInterface::class)
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
        $s = $this->getMockBuilder(StreamInterface::class)
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

        $errors = [];
        set_error_handler(function (int $errorNumber, string $errorMessage) use (&$errors) {
            $errors[] = ['number' => $errorNumber, 'message' => $errorMessage];
        });
        (string) $a;

        restore_error_handler();

        self::assertCount(1, $errors);
        self::assertSame(E_USER_ERROR, $errors[0]['number']);
        self::assertStringStartsWith('GuzzleHttp\Psr7\AppendStream::__toString exception:', $errors[0]['message']);
    }

    public function testReturnsEmptyMetadata()
    {
        $s = new AppendStream();
        self::assertEquals([], $s->getMetadata());
        self::assertNull($s->getMetadata('foo'));
    }
}

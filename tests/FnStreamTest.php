<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\FnStream;
use PHPUnit\Framework\TestCase;

/**
 * @covers GuzzleHttp\Psr7\FnStream
 */
class FnStreamTest extends TestCase
{
    public function testThrowsWhenNotImplemented(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('seek() is not implemented in the FnStream');
        (new FnStream([]))->seek(1);
    }

    public function testProxiesToFunction(): void
    {
        $s = new FnStream([
            'read' => function ($len) {
                $this->assertEquals(3, $len);
                return 'foo';
            }
        ]);

        self::assertEquals('foo', $s->read(3));
    }

    public function testCanCloseOnDestruct(): void
    {
        $called = false;
        $s = new FnStream([
            'close' => function () use (&$called): void {
                $called = true;
            }
        ]);
        unset($s);
        self::assertTrue($called);
    }

    public function testDoesNotRequireClose(): void
    {
        $s = new FnStream([]);
        unset($s);
        self::assertTrue(true); // strict mode requires an assertion
    }

    public function testDecoratesStream(): void
    {
        $a = Psr7\stream_for('foo');
        $b = FnStream::decorate($a, []);
        self::assertEquals(3, $b->getSize());
        self::assertEquals($b->isWritable(), true);
        self::assertEquals($b->isReadable(), true);
        self::assertEquals($b->isSeekable(), true);
        self::assertEquals($b->read(3), 'foo');
        self::assertEquals($b->tell(), 3);
        self::assertEquals($a->tell(), 3);
        self::assertSame('', $a->read(1));
        self::assertEquals($b->eof(), true);
        self::assertEquals($a->eof(), true);
        $b->seek(0);
        self::assertEquals('foo', (string) $b);
        $b->seek(0);
        self::assertEquals('foo', $b->getContents());
        self::assertEquals($a->getMetadata(), $b->getMetadata());
        $b->seek(0, SEEK_END);
        $b->write('bar');
        self::assertEquals('foobar', (string) $b);
        self::assertIsResource($b->detach());
        $b->close();
    }

    public function testDecoratesWithCustomizations(): void
    {
        $called = false;
        $a = Psr7\stream_for('foo');
        $b = FnStream::decorate($a, [
            'read' => function ($len) use (&$called, $a) {
                $called = true;
                return $a->read($len);
            }
        ]);
        self::assertEquals('foo', $b->read(3));
        self::assertTrue($called);
    }

    public function testDoNotAllowUnserialization(): void
    {
        $a = new FnStream([]);
        $b = serialize($a);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('FnStream should never be unserialized');
        unserialize($b);
    }

    /**
     * @requires PHP < 7.4
     */
    public function testThatConvertingStreamToStringWillTriggerErrorAndWillReturnEmptyString(): void
    {
        $a = new FnStream([
            '__toString' => function (): void {
                throw new \Exception();
            },
        ]);

        $errors = [];
        set_error_handler(function (int $errorNumber, string $errorMessage) use (&$errors): void {
            $errors[] = ['number' => $errorNumber, 'message' => $errorMessage];
        });
        (string) $a;

        restore_error_handler();

        self::assertCount(1, $errors);
        self::assertSame(E_USER_ERROR, $errors[0]['number']);
        self::assertStringStartsWith('GuzzleHttp\Psr7\FnStream::__toString exception:', $errors[0]['message']);
    }
}

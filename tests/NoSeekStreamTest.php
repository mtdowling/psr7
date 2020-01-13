<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

/**
 * @covers GuzzleHttp\Psr7\NoSeekStream
 * @covers GuzzleHttp\Psr7\StreamDecoratorTrait
 */
class NoSeekStreamTest extends TestCase
{
    public function testCannotSeek()
    {
        $s = $this->getMockBuilder(StreamInterface::class)
            ->setMethods(['isSeekable', 'seek'])
            ->getMockForAbstractClass();
        $s->expects(self::never())->method('seek');
        $s->expects(self::never())->method('isSeekable');
        $wrapped = new NoSeekStream($s);
        self::assertFalse($wrapped->isSeekable());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot seek a NoSeekStream');
        $wrapped->seek(2);
    }

    public function testToStringDoesNotSeek()
    {
        $s = Utils::streamFor('foo');
        $s->seek(1);
        $wrapped = new NoSeekStream($s);
        self::assertEquals('oo', (string) $wrapped);

        $wrapped->close();
    }
}

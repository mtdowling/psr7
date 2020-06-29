<?php

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\BufferStream;
use GuzzleHttp\Psr7\DroppingStream;

class DroppingStreamTest extends BaseTest
{
    public function testBeginsDroppingWhenSizeExceeded()
    {
        $stream = new BufferStream();
        $drop = new DroppingStream($stream, 5);
        $this->assertSame(3, $drop->write('hel'));
        $this->assertSame(2, $drop->write('lo'));
        $this->assertSame(5, $drop->getSize());
        $this->assertSame('hello', $drop->read(5));
        $this->assertSame(0, $drop->getSize());
        $drop->write('12345678910');
        $this->assertSame(5, $stream->getSize());
        $this->assertSame(5, $drop->getSize());
        $this->assertSame('12345', (string) $drop);
        $this->assertSame(0, $drop->getSize());
        $drop->write('hello');
        $this->assertSame(0, $drop->write('test'));
    }
}

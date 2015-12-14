<?php

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\StreamFactory;

class StreamFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testItIsAStreamFactory()
    {
        $this->assertInstanceOf('Http\\Message\\StreamFactory', new StreamFactory());
    }

    public function testCreatesStreamFromString()
    {
        $sf = new StreamFactory();

        $stream = $sf->createStream('body');

        $this->assertInstanceOf('Psr\\Http\\Message\\StreamInterface', $stream);
        $this->assertInstanceOf('GuzzleHttp\\Psr7\\Stream', $stream);

        $this->assertEquals('body', (string) $stream);
    }

    public function testCreatesEmptyStream()
    {
        $sf = new StreamFactory();

        $stream = $sf->createStream();

        $this->assertInstanceOf('Psr\\Http\\Message\\StreamInterface', $stream);
        $this->assertInstanceOf('GuzzleHttp\\Psr7\\Stream', $stream);

        $this->assertEquals('', (string) $stream);
    }

    public function testReturnsSameStream()
    {
        $sf = new StreamFactory();

        $originalStream = \GuzzleHttp\Psr7\stream_for('');

        $stream = $sf->createStream($originalStream);

        $this->assertSame($originalStream, $stream);
    }
}

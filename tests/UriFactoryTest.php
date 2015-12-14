<?php

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\UriFactory;

class UriFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testItIsAUriFactory()
    {
        $this->assertInstanceOf('Http\\Message\\UriFactory', new UriFactory());
    }

    public function testReturnsUri()
    {
        $uf = new UriFactory();

        $uri = $uf->createUri('/');

        $this->assertInstanceOf('Psr\\Http\\Message\\UriInterface', $uri);
        $this->assertInstanceOf('GuzzleHttp\\Psr7\\Uri', $uri);

        $this->assertEquals('/', (string) $uri);
    }

    public function testReturnsSameUri()
    {
        $uf = new UriFactory();

        $originalUri = \GuzzleHttp\Psr7\uri_for('');

        $uri = $uf->createUri($originalUri);

        $this->assertSame($originalUri, $uri);
    }
}

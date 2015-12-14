<?php

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\MessageFactory;

class MessageFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testItIsAMessageFactory()
    {
        $this->assertInstanceOf('Http\\Message\\MessageFactory', new MessageFactory());
    }

    public function testReturnsRequest()
    {
        $mf = new MessageFactory();

        $request = $mf->createRequest('GET', '/', ['Content-Type' => 'text/html'], 'body', '1.0');

        $this->assertInstanceOf('Psr\\Http\\Message\\RequestInterface', $request);
        $this->assertInstanceOf('GuzzleHttp\\Psr7\\Request', $request);

        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('/', $request->getRequestTarget());
        $this->assertEquals(['Content-Type' => ['text/html']], $request->getHeaders());
        $this->assertEquals('body', (string) $request->getBody());
        $this->assertEquals('1.0', $request->getProtocolVersion());
    }

    public function testReturnsResponse()
    {
        $mf = new MessageFactory();

        $response = $mf->createResponse(200, null, ['Content-Type' => 'text/html'], 'response', '1.0');

        $this->assertInstanceOf('Psr\\Http\\Message\\ResponseInterface', $response);
        $this->assertInstanceOf('GuzzleHttp\\Psr7\\Response', $response);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getReasonPhrase());
        $this->assertEquals(['Content-Type' => ['text/html']], $response->getHeaders());
        $this->assertEquals('response', (string) $response->getBody());
        $this->assertEquals('1.0', $response->getProtocolVersion());
    }
}

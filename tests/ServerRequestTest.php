<?php
namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7;

class ServerRequestTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ServerRequest
     */
    protected $serverRequest;

    public function setUp()
    {
        $this->serverRequest = new ServerRequest('GET', new Psr7\Uri(), [], null, '1.1', $_SERVER);
    }

    /**
     * @dataProvider populateGlobalVariable
     */
    public function testCanCreateFromGlobals()
    {
        $serverRequest = ServerRequest::fromGlobals();
        $this->assertEquals($_SERVER['REQUEST_METHOD'], $serverRequest->getMethod());
        $this->assertEquals($_SERVER, $serverRequest->getServerParams());
        $this->assertEquals($_GET, $serverRequest->getQueryParams());
        $this->assertEquals($_POST, $serverRequest->getParsedBody());
        $uploadedFiles = $serverRequest->getUploadedFiles()['my-form']['details']['avatars'];
        $this->assertInstanceOf('GuzzleHttp\Psr7\UploadedFile', $uploadedFiles[0]);
        $this->assertInstanceOf('GuzzleHttp\Psr7\UploadedFile', $uploadedFiles[1]);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testParsedBodyMustBeValid() {
        $this->serverRequest->withParsedBody('bad body');
    }

    public function testSameInstanceWhenSameCookieParams()
    {
        $cookieParams = ['testK' => 'testV'];
        $serverRequest1 = $this->serverRequest->withCookieParams($cookieParams);
        $serverRequest2 = $serverRequest1->withCookieParams($cookieParams);
        $this->assertSame($serverRequest1, $serverRequest2);
    }

    public function testNewInstanceWhenNewCookieParams()
    {
        $cookieParams = ['testK' => 'testV'];
        $serverRequest = $this->serverRequest->withCookieParams($cookieParams);
        $this->assertNotSame($this->serverRequest, $serverRequest);
        $this->assertEquals($cookieParams, $serverRequest->getCookieParams());
    }

    public function testSameInstanceWhenSameQueryParams()
    {
        $queryParams = ['testK' => 'testV'];
        $serverRequest1 = $this->serverRequest->withQueryParams($queryParams);
        $serverRequest2 = $serverRequest1->withQueryParams($queryParams);
        $this->assertSame($serverRequest1, $serverRequest2);
    }

    public function testNewInstanceWhenNewQueryParams()
    {
        $queryParams = ['testK' => 'testV'];
        $serverRequest = $this->serverRequest->withQueryParams($queryParams);
        $this->assertNotSame($this->serverRequest, $serverRequest);
        $this->assertEquals($queryParams, $serverRequest->getQueryParams());
    }

    public function testSameInstanceWhenSameParsedBody()
    {
        $parsedBody = ['testK' => 'testV'];
        $serverRequest1 = $this->serverRequest->withParsedBody($parsedBody);
        $serverRequest2 = $serverRequest1->withParsedBody($parsedBody);
        $this->assertSame($serverRequest1, $serverRequest2);
    }

    public function testNewInstanceWhenNewParsedBody()
    {
        $parsedBody = ['testK' => 'testV'];
        $serverRequest = $this->serverRequest->withParsedBody($parsedBody);
        $this->assertNotSame($this->serverRequest, $serverRequest);
        $this->assertEquals($parsedBody, $serverRequest->getParsedBody());
    }

    public function testNewInstanceWhenNewAttribute()
    {
        $serverRequest = $this->serverRequest
            ->withAttribute('testK', 'testV')
            ->withAttribute('testK_2', 'testV_2');
        $this->assertNotSame($this->serverRequest, $serverRequest);
        $this->assertEquals('testV', $serverRequest->getAttribute('testK'));
        $this->assertEquals(['testK' => 'testV', 'testK_2' => 'testV_2'], $serverRequest->getAttributes());
    }

    public function testSameInstanceWhenRemoveNonexistentAttribute()
    {
        $serverRequest = $this->serverRequest->withoutAttribute('testNonexistent');
        $this->assertSame($this->serverRequest, $serverRequest);
    }

    public function testNewInstanceWhenRemoveAttribute()
    {
        $serverRequest = $this->serverRequest->withAttribute('testK', 'testV');
        $serverRequest = $serverRequest->withoutAttribute('testK');
        $this->assertNotSame($this->serverRequest, $serverRequest);
        $this->assertNull($serverRequest->getAttribute('testK'));
    }

    public function populateGlobalVariable()
    {
        $_SERVER['SERVER_NAME'] = 'www.foo.com';
        $_SERVER['SERVER_PORT'] = 80;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/index.php?foo=bar';
        $_SERVER['QUERY_STRING'] = 'foo=bar';
        $_POST = ['foo' => 'bar'];
        $_GET = ['foo' => 'bar'];
        $_COOKIE = ['foo' => 'bar'];
        $_FILES = [
            'my-form' => [
                'details' => [
                    'avatars' => [
                        'tmp_name' => [
                            0 => 'tmp0',
                            1 => 'tmp1'
                        ],
                        'name' => [
                            0 => 'n0',
                            1 => 'n1'
                        ],
                        'size' => [
                            0 => 32000,
                            1 => 64000
                        ],
                        'type' => [
                            0 => 'image/png',
                            1 => 'image/jpg'
                        ],
                        'error' => [
                            0 => UPLOAD_ERR_OK,
                            1 => UPLOAD_ERR_CANT_WRITE
                        ],
                    ],
                ],
            ],
        ];
    }
}

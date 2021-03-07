<?php

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\FnStream;

class MessageTest extends BaseTest
{
    public function testConvertsRequestsToStrings()
    {
        $request = new Psr7\Request('PUT', 'http://foo.com/hi?123', [
            'Baz' => 'bar',
            'Qux' => 'ipsum',
        ], 'hello', '1.0');
        self::assertSame(
            "PUT /hi?123 HTTP/1.0\r\nHost: foo.com\r\nBaz: bar\r\nQux: ipsum\r\n\r\nhello",
            Psr7\Message::toString($request)
        );
    }

    public function testConvertsResponsesToStrings()
    {
        $response = new Psr7\Response(200, [
            'Baz' => 'bar',
            'Qux' => 'ipsum',
        ], 'hello', '1.0', 'FOO');
        self::assertSame(
            "HTTP/1.0 200 FOO\r\nBaz: bar\r\nQux: ipsum\r\n\r\nhello",
            Psr7\Message::toString($response)
        );
    }

    public function testCorrectlyRendersSetCookieHeadersToString()
    {
        $response = new Psr7\Response(200, [
            'Set-Cookie' => ['bar','baz','qux']
        ], 'hello', '1.0', 'FOO');
        self::assertSame(
            "HTTP/1.0 200 FOO\r\nSet-Cookie: bar\r\nSet-Cookie: baz\r\nSet-Cookie: qux\r\n\r\nhello",
            Psr7\Message::toString($response)
        );
    }

    public function testRewindsBody()
    {
        $body = Psr7\Utils::streamFor('abc');
        $res = new Psr7\Response(200, [], $body);
        Psr7\Message::rewindBody($res);
        self::assertSame(0, $body->tell());
        $body->rewind();
        Psr7\Message::rewindBody($res);
        self::assertSame(0, $body->tell());
    }

    public function testThrowsWhenBodyCannotBeRewound()
    {
        $body = Psr7\Utils::streamFor('abc');
        $body->read(1);
        $body = FnStream::decorate($body, [
            'rewind' => function () {
                throw new \RuntimeException('a');
            },
        ]);
        $res = new Psr7\Response(200, [], $body);

        $this->expectExceptionGuzzle('RuntimeException');

        Psr7\Message::rewindBody($res);
    }

    public function testParsesRequestMessages()
    {
        $req = "GET /abc HTTP/1.0\r\nHost: foo.com\r\nFoo: Bar\r\nBaz: Bam\r\nBaz: Qux\r\n\r\nTest";
        $request = Psr7\Message::parseRequest($req);
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/abc', $request->getRequestTarget());
        self::assertSame('1.0', $request->getProtocolVersion());
        self::assertSame('foo.com', $request->getHeaderLine('Host'));
        self::assertSame('Bar', $request->getHeaderLine('Foo'));
        self::assertSame('Bam, Qux', $request->getHeaderLine('Baz'));
        self::assertSame('Test', (string)$request->getBody());
        self::assertSame('http://foo.com/abc', (string)$request->getUri());
    }

    public function testParsesRequestMessagesWithHttpsScheme()
    {
        $req = "PUT /abc?baz=bar HTTP/1.1\r\nHost: foo.com:443\r\n\r\n";
        $request = Psr7\Message::parseRequest($req);
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('/abc?baz=bar', $request->getRequestTarget());
        self::assertSame('1.1', $request->getProtocolVersion());
        self::assertSame('foo.com:443', $request->getHeaderLine('Host'));
        self::assertSame('', (string)$request->getBody());
        self::assertSame('https://foo.com/abc?baz=bar', (string)$request->getUri());
    }

    public function testParsesRequestMessagesWithUriWhenHostIsNotFirst()
    {
        $req = "PUT / HTTP/1.1\r\nFoo: Bar\r\nHost: foo.com\r\n\r\n";
        $request = Psr7\Message::parseRequest($req);
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('/', $request->getRequestTarget());
        self::assertSame('http://foo.com/', (string)$request->getUri());
    }

    public function testParsesRequestMessagesWithFullUri()
    {
        $req = "GET https://www.google.com:443/search?q=foobar HTTP/1.1\r\nHost: www.google.com\r\n\r\n";
        $request = Psr7\Message::parseRequest($req);
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://www.google.com:443/search?q=foobar', $request->getRequestTarget());
        self::assertSame('1.1', $request->getProtocolVersion());
        self::assertSame('www.google.com', $request->getHeaderLine('Host'));
        self::assertSame('', (string)$request->getBody());
        self::assertSame('https://www.google.com/search?q=foobar', (string)$request->getUri());
    }

    public function testParsesRequestMessagesWithCustomMethod()
    {
        $req = "GET_DATA / HTTP/1.1\r\nFoo: Bar\r\nHost: foo.com\r\n\r\n";
        $request = Psr7\Message::parseRequest($req);
        self::assertSame('GET_DATA', $request->getMethod());
    }

    public function testParsesRequestMessagesWithFoldedHeadersOnHttp10()
    {
        $req = "PUT / HTTP/1.0\r\nFoo: Bar\r\n Bam\r\n\r\n";
        $request = Psr7\Message::parseRequest($req);
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('/', $request->getRequestTarget());
        self::assertSame('Bar Bam', $request->getHeaderLine('Foo'));
    }

    public function testRequestParsingFailsWithFoldedHeadersOnHttp11()
    {
        $this->expectExceptionGuzzle('InvalidArgumentException', 'Invalid header syntax: Obsolete line folding');

        Psr7\Message::parseResponse("GET_DATA / HTTP/1.1\r\nFoo: Bar\r\n Biz: Bam\r\n\r\n");
    }

    public function testParsesRequestMessagesWhenHeaderDelimiterIsOnlyALineFeed()
    {
        $req = "PUT / HTTP/1.0\nFoo: Bar\nBaz: Bam\n\n";
        $request = Psr7\Message::parseRequest($req);
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('/', $request->getRequestTarget());
        self::assertSame('Bar', $request->getHeaderLine('Foo'));
        self::assertSame('Bam', $request->getHeaderLine('Baz'));
    }

    public function testValidatesRequestMessages()
    {
        $this->expectExceptionGuzzle('InvalidArgumentException');

        Psr7\Message::parseRequest("HTTP/1.1 200 OK\r\n\r\n");
    }

    public function testParsesResponseMessages()
    {
        $res = "HTTP/1.0 200 OK\r\nFoo: Bar\r\nBaz: Bam\r\nBaz: Qux\r\n\r\nTest";
        $response = Psr7\Message::parseResponse($res);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getReasonPhrase());
        self::assertSame('1.0', $response->getProtocolVersion());
        self::assertSame('Bar', $response->getHeaderLine('Foo'));
        self::assertSame('Bam, Qux', $response->getHeaderLine('Baz'));
        self::assertSame('Test', (string)$response->getBody());
    }

    public function testParsesResponseWithoutReason()
    {
        $res = "HTTP/1.0 200\r\nFoo: Bar\r\nBaz: Bam\r\nBaz: Qux\r\n\r\nTest";
        $response = Psr7\Message::parseResponse($res);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getReasonPhrase());
        self::assertSame('1.0', $response->getProtocolVersion());
        self::assertSame('Bar', $response->getHeaderLine('Foo'));
        self::assertSame('Bam, Qux', $response->getHeaderLine('Baz'));
        self::assertSame('Test', (string)$response->getBody());
    }

    public function testParsesResponseWithLeadingDelimiter()
    {
        $res = "\r\nHTTP/1.0 200\r\nFoo: Bar\r\n\r\nTest";
        $response = Psr7\Message::parseResponse($res);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getReasonPhrase());
        self::assertSame('1.0', $response->getProtocolVersion());
        self::assertSame('Bar', $response->getHeaderLine('Foo'));
        self::assertSame('Test', (string)$response->getBody());
    }

    public function testParsesResponseWithFoldedHeadersOnHttp10()
    {
        $res = "HTTP/1.0 200\r\nFoo: Bar\r\n Bam\r\n\r\nTest";
        $response = Psr7\Message::parseResponse($res);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getReasonPhrase());
        self::assertSame('1.0', $response->getProtocolVersion());
        self::assertSame('Bar Bam', $response->getHeaderLine('Foo'));
        self::assertSame('Test', (string)$response->getBody());
    }

    public function testResponseParsingFailsWithFoldedHeadersOnHttp11()
    {
        $this->expectExceptionGuzzle('InvalidArgumentException', 'Invalid header syntax: Obsolete line folding');

        Psr7\Message::parseResponse("HTTP/1.1 200\r\nFoo: Bar\r\n Biz: Bam\r\nBaz: Qux\r\n\r\nTest");
    }

    public function testParsesResponseWhenHeaderDelimiterIsOnlyALineFeed()
    {
        $res = "HTTP/1.0 200\nFoo: Bar\nBaz: Bam\n\nTest\n\nOtherTest";
        $response = Psr7\Message::parseResponse($res);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getReasonPhrase());
        self::assertSame('1.0', $response->getProtocolVersion());
        self::assertSame('Bar', $response->getHeaderLine('Foo'));
        self::assertSame('Bam', $response->getHeaderLine('Baz'));
        self::assertSame("Test\n\nOtherTest", (string)$response->getBody());
    }

    public function testResponseParsingFailsWithoutHeaderDelimiter()
    {
        $this->expectExceptionGuzzle('InvalidArgumentException', 'Invalid message: Missing header delimiter');

        Psr7\Message::parseResponse("HTTP/1.0 200\r\nFoo: Bar\r\n Baz: Bam\r\nBaz: Qux\r\n");
    }

    public function testValidatesResponseMessages()
    {
        $this->expectExceptionGuzzle('InvalidArgumentException');

        Psr7\Message::parseResponse("GET / HTTP/1.1\r\n\r\n");
    }

    public function testMessageBodySummaryWithSmallBody()
    {
        $message = new Psr7\Response(200, [], 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.');
        self::assertSame('Lorem ipsum dolor sit amet, consectetur adipiscing elit.', Psr7\Message::bodySummary($message));
    }

    public function testMessageBodySummaryWithLargeBody()
    {
        $message = new Psr7\Response(200, [], 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.');
        self::assertSame('Lorem ipsu (truncated...)', Psr7\Message::bodySummary($message, 10));
    }

    public function testMessageBodySummaryWithSpecialUTF8Characters()
    {
        $message = new Psr7\Response(200, [], '’é€௵ဪ‱');
        self::assertSame('’é€௵ဪ‱', Psr7\Message::bodySummary($message));
    }

    public function testMessageBodySummaryWithEmptyBody()
    {
        $message = new Psr7\Response(200, [], '');
        self::assertNull(Psr7\Message::bodySummary($message));
    }

    public function testGetResponseBodySummaryOfNonReadableStream()
    {
        self::assertNull(Psr7\Message::bodySummary(new Psr7\Response(500, [], new ReadSeekOnlyStream())));
    }
}

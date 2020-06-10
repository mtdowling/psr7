<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Stream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

class FunctionsTest extends TestCase
{
    public function testCopiesToString(): void
    {
        $s = Psr7\stream_for('foobaz');
        self::assertSame('foobaz', Psr7\copy_to_string($s));
        $s->seek(0);
        self::assertSame('foo', Psr7\copy_to_string($s, 3));
        self::assertSame('baz', Psr7\copy_to_string($s, 3));
        self::assertSame('', Psr7\copy_to_string($s));
    }

    public function testCopiesToStringStopsWhenReadFails(): void
    {
        $s1 = Psr7\stream_for('foobaz');
        $s1 = FnStream::decorate($s1, [
            'read' => function () {
                return '';
            },
        ]);
        $result = Psr7\copy_to_string($s1);
        self::assertSame('', $result);
    }

    public function testCopiesToStream(): void
    {
        $s1 = Psr7\stream_for('foobaz');
        $s2 = Psr7\stream_for('');
        Psr7\copy_to_stream($s1, $s2);
        self::assertSame('foobaz', (string)$s2);
        $s2 = Psr7\stream_for('');
        $s1->seek(0);
        Psr7\copy_to_stream($s1, $s2, 3);
        self::assertSame('foo', (string)$s2);
        Psr7\copy_to_stream($s1, $s2, 3);
        self::assertSame('foobaz', (string)$s2);
    }

    public function testStopsCopyToStreamWhenWriteFails(): void
    {
        $s1 = Psr7\stream_for('foobaz');
        $s2 = Psr7\stream_for('');
        $s2 = FnStream::decorate($s2, [
            'write' => function () {
                return 0;
            },
        ]);
        Psr7\copy_to_stream($s1, $s2);
        self::assertSame('', (string)$s2);
    }

    public function testStopsCopyToSteamWhenWriteFailsWithMaxLen(): void
    {
        $s1 = Psr7\stream_for('foobaz');
        $s2 = Psr7\stream_for('');
        $s2 = FnStream::decorate($s2, [
            'write' => function () {
                return 0;
            },
        ]);
        Psr7\copy_to_stream($s1, $s2, 10);
        self::assertSame('', (string)$s2);
    }

    public function testCopyToStreamReadsInChunksInsteadOfAllInMemory(): void
    {
        $sizes = [];
        $s1 = new Psr7\FnStream([
            'eof' => function () {
                return false;
            },
            'read' => function ($size) use (&$sizes) {
                $sizes[] = $size;
                return str_repeat('.', $size);
            },
        ]);
        $s2 = Psr7\stream_for('');
        Psr7\copy_to_stream($s1, $s2, 16394);
        $s2->seek(0);
        self::assertSame(16394, strlen($s2->getContents()));
        self::assertSame(8192, $sizes[0]);
        self::assertSame(8192, $sizes[1]);
        self::assertSame(10, $sizes[2]);
    }

    public function testStopsCopyToSteamWhenReadFailsWithMaxLen(): void
    {
        $s1 = Psr7\stream_for('foobaz');
        $s1 = FnStream::decorate($s1, [
            'read' => function () {
                return '';
            },
        ]);
        $s2 = Psr7\stream_for('');
        Psr7\copy_to_stream($s1, $s2, 10);
        self::assertSame('', (string)$s2);
    }

    public function testReadsLines(): void
    {
        $s = Psr7\stream_for("foo\nbaz\nbar");
        self::assertSame("foo\n", Psr7\readline($s));
        self::assertSame("baz\n", Psr7\readline($s));
        self::assertSame('bar', Psr7\readline($s));
    }

    public function testReadsLinesUpToMaxLength(): void
    {
        $s = Psr7\stream_for("12345\n");
        self::assertSame('123', Psr7\readline($s, 4));
        self::assertSame("45\n", Psr7\readline($s));
    }

    public function testReadLinesEof(): void
    {
        // Should return empty string on EOF
        $s = Psr7\stream_for("foo\nbar");
        while (!$s->eof()) {
            Psr7\readline($s);
        }
        self::assertSame('', Psr7\readline($s));
    }

    public function testReadsLineUntilEmptyStringReturnedFromRead(): void
    {
        $s = $this->createMock(StreamInterface::class);
        $s->expects(self::exactly(2))
            ->method('read')
            ->willReturnCallback(function () {
                static $called = false;
                if ($called) {
                    return '';
                }
                $called = true;

                return 'h';
            });
        $s->expects(self::exactly(2))
            ->method('eof')
            ->willReturn(false);
        self::assertSame('h', Psr7\readline($s));
    }

    public function testCalculatesHash(): void
    {
        $s = Psr7\stream_for('foobazbar');
        self::assertSame(md5('foobazbar'), Psr7\hash($s, 'md5'));
    }

    public function testCalculatesHashThrowsWhenSeekFails(): void
    {
        $s = new NoSeekStream(Psr7\stream_for('foobazbar'));
        $s->read(2);
        $this->expectException(\RuntimeException::class);
        Psr7\hash($s, 'md5');
    }

    public function testCalculatesHashSeeksToOriginalPosition(): void
    {
        $s = Psr7\stream_for('foobazbar');
        $s->seek(4);
        self::assertSame(md5('foobazbar'), Psr7\hash($s, 'md5'));
        self::assertSame(4, $s->tell());
    }

    public function testOpensFilesSuccessfully(): void
    {
        $r = Psr7\try_fopen(__FILE__, 'r');
        self::assertIsResource($r);
        fclose($r);
    }

    public function testThrowsExceptionNotWarning(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to open /path/to/does/not/exist using mode r');
        Psr7\try_fopen('/path/to/does/not/exist', 'r');
    }

    public function parseQueryProvider(): iterable
    {
        return [
            // Does not need to parse when the string is empty
            ['', []],
            // Can parse mult-values items
            ['q=a&q=b', ['q' => ['a', 'b']]],
            // Can parse multi-valued items that use numeric indices
            ['q[0]=a&q[1]=b', ['q[0]' => 'a', 'q[1]' => 'b']],
            // Can parse duplicates and does not include numeric indices
            ['q[]=a&q[]=b', ['q[]' => ['a', 'b']]],
            // Ensures that the value of "q" is an array even though one value
            ['q[]=a', ['q[]' => 'a']],
            // Does not modify "." to "_" like PHP's parse_str()
            ['q.a=a&q.b=b', ['q.a' => 'a', 'q.b' => 'b']],
            // Can decode %20 to " "
            ['q%20a=a%20b', ['q a' => 'a b']],
            // Can parse funky strings with no values by assigning each to null
            ['q&a', ['q' => null, 'a' => null]],
            // Does not strip trailing equal signs
            ['data=abc=', ['data' => 'abc=']],
            // Can store duplicates without affecting other values
            ['foo=a&foo=b&?µ=c', ['foo' => ['a', 'b'], '?µ' => 'c']],
            // Sets value to null when no "=" is present
            ['foo', ['foo' => null]],
            // Preserves "0" keys.
            ['0', ['0' => null]],
            // Sets the value to an empty string when "=" is present
            ['0=', ['0' => '']],
            // Preserves falsey keys
            ['var=0', ['var' => '0']],
            ['a[b][c]=1&a[b][c]=2', ['a[b][c]' => ['1', '2']]],
            ['a[b]=c&a[d]=e', ['a[b]' => 'c', 'a[d]' => 'e']],
            // Ensure it doesn't leave things behind with repeated values
            // Can parse mult-values items
            ['q=a&q=b&q=c', ['q' => ['a', 'b', 'c']]],
        ];
    }

    /**
     * @dataProvider parseQueryProvider
     */
    public function testParsesQueries($input, $output): void
    {
        $result = Psr7\parse_query($input);
        self::assertSame($output, $result);
    }

    public function testDoesNotDecode(): void
    {
        $str = 'foo%20=bar';
        $data = Psr7\parse_query($str, false);
        self::assertSame(['foo%20' => 'bar'], $data);
    }

    /**
     * @dataProvider parseQueryProvider
     */
    public function testParsesAndBuildsQueries($input): void
    {
        $result = Psr7\parse_query($input, false);
        self::assertSame($input, Psr7\build_query($result, false));
    }

    public function testEncodesWithRfc1738(): void
    {
        $str = Psr7\build_query(['foo bar' => 'baz+'], PHP_QUERY_RFC1738);
        self::assertSame('foo+bar=baz%2B', $str);
    }

    public function testEncodesWithRfc3986(): void
    {
        $str = Psr7\build_query(['foo bar' => 'baz+'], PHP_QUERY_RFC3986);
        self::assertSame('foo%20bar=baz%2B', $str);
    }

    public function testDoesNotEncode(): void
    {
        $str = Psr7\build_query(['foo bar' => 'baz+'], false);
        self::assertSame('foo bar=baz+', $str);
    }

    public function testCanControlDecodingType(): void
    {
        $result = Psr7\parse_query('var=foo+bar', PHP_QUERY_RFC3986);
        self::assertSame('foo+bar', $result['var']);
        $result = Psr7\parse_query('var=foo+bar', PHP_QUERY_RFC1738);
        self::assertSame('foo bar', $result['var']);
    }

    public function testParsesRequestMessages(): void
    {
        $req = "GET /abc HTTP/1.0\r\nHost: foo.com\r\nFoo: Bar\r\nBaz: Bam\r\nBaz: Qux\r\n\r\nTest";
        $request = Psr7\parse_request($req);
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/abc', $request->getRequestTarget());
        self::assertSame('1.0', $request->getProtocolVersion());
        self::assertSame('foo.com', $request->getHeaderLine('Host'));
        self::assertSame('Bar', $request->getHeaderLine('Foo'));
        self::assertSame('Bam, Qux', $request->getHeaderLine('Baz'));
        self::assertSame('Test', (string)$request->getBody());
        self::assertSame('http://foo.com/abc', (string)$request->getUri());
    }

    public function testParsesRequestMessagesWithHttpsScheme(): void
    {
        $req = "PUT /abc?baz=bar HTTP/1.1\r\nHost: foo.com:443\r\n\r\n";
        $request = Psr7\parse_request($req);
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('/abc?baz=bar', $request->getRequestTarget());
        self::assertSame('1.1', $request->getProtocolVersion());
        self::assertSame('foo.com:443', $request->getHeaderLine('Host'));
        self::assertSame('', (string)$request->getBody());
        self::assertSame('https://foo.com/abc?baz=bar', (string)$request->getUri());
    }

    public function testParsesRequestMessagesWithUriWhenHostIsNotFirst(): void
    {
        $req = "PUT / HTTP/1.1\r\nFoo: Bar\r\nHost: foo.com\r\n\r\n";
        $request = Psr7\parse_request($req);
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('/', $request->getRequestTarget());
        self::assertSame('http://foo.com/', (string)$request->getUri());
    }

    public function testParsesRequestMessagesWithFullUri(): void
    {
        $req = "GET https://www.google.com:443/search?q=foobar HTTP/1.1\r\nHost: www.google.com\r\n\r\n";
        $request = Psr7\parse_request($req);
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://www.google.com:443/search?q=foobar', $request->getRequestTarget());
        self::assertSame('1.1', $request->getProtocolVersion());
        self::assertSame('www.google.com', $request->getHeaderLine('Host'));
        self::assertSame('', (string)$request->getBody());
        self::assertSame('https://www.google.com/search?q=foobar', (string)$request->getUri());
    }

    public function testParsesRequestMessagesWithCustomMethod(): void
    {
        $req = "GET_DATA / HTTP/1.1\r\nFoo: Bar\r\nHost: foo.com\r\n\r\n";
        $request = Psr7\parse_request($req);
        self::assertSame('GET_DATA', $request->getMethod());
    }

    public function testParsesRequestMessagesWithFoldedHeadersOnHttp10(): void
    {
        $req = "PUT / HTTP/1.0\r\nFoo: Bar\r\n Bam\r\n\r\n";
        $request = Psr7\parse_request($req);
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('/', $request->getRequestTarget());
        self::assertSame('Bar Bam', $request->getHeaderLine('Foo'));
    }

    public function testRequestParsingFailsWithFoldedHeadersOnHttp11(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid header syntax: Obsolete line folding');
        Psr7\parse_response("GET_DATA / HTTP/1.1\r\nFoo: Bar\r\n Biz: Bam\r\n\r\n");
    }

    public function testParsesRequestMessagesWhenHeaderDelimiterIsOnlyALineFeed(): void
    {
        $req = "PUT / HTTP/1.0\nFoo: Bar\nBaz: Bam\n\n";
        $request = Psr7\parse_request($req);
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('/', $request->getRequestTarget());
        self::assertSame('Bar', $request->getHeaderLine('Foo'));
        self::assertSame('Bam', $request->getHeaderLine('Baz'));
    }

    public function testValidatesRequestMessages(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Psr7\parse_request("HTTP/1.1 200 OK\r\n\r\n");
    }

    public function testParsesResponseMessages(): void
    {
        $res = "HTTP/1.0 200 OK\r\nFoo: Bar\r\nBaz: Bam\r\nBaz: Qux\r\n\r\nTest";
        $response = Psr7\parse_response($res);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getReasonPhrase());
        self::assertSame('1.0', $response->getProtocolVersion());
        self::assertSame('Bar', $response->getHeaderLine('Foo'));
        self::assertSame('Bam, Qux', $response->getHeaderLine('Baz'));
        self::assertSame('Test', (string)$response->getBody());
    }

    public function testParsesResponseWithoutReason(): void
    {
        $res = "HTTP/1.0 200\r\nFoo: Bar\r\nBaz: Bam\r\nBaz: Qux\r\n\r\nTest";
        $response = Psr7\parse_response($res);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getReasonPhrase());
        self::assertSame('1.0', $response->getProtocolVersion());
        self::assertSame('Bar', $response->getHeaderLine('Foo'));
        self::assertSame('Bam, Qux', $response->getHeaderLine('Baz'));
        self::assertSame('Test', (string)$response->getBody());
    }

    public function testParsesResponseWithLeadingDelimiter(): void
    {
        $res = "\r\nHTTP/1.0 200\r\nFoo: Bar\r\n\r\nTest";
        $response = Psr7\parse_response($res);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getReasonPhrase());
        self::assertSame('1.0', $response->getProtocolVersion());
        self::assertSame('Bar', $response->getHeaderLine('Foo'));
        self::assertSame('Test', (string)$response->getBody());
    }

    public function testParsesResponseWithFoldedHeadersOnHttp10(): void
    {
        $res = "HTTP/1.0 200\r\nFoo: Bar\r\n Bam\r\n\r\nTest";
        $response = Psr7\parse_response($res);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getReasonPhrase());
        self::assertSame('1.0', $response->getProtocolVersion());
        self::assertSame('Bar Bam', $response->getHeaderLine('Foo'));
        self::assertSame('Test', (string)$response->getBody());
    }

    public function testResponseParsingFailsWithFoldedHeadersOnHttp11(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Psr7\parse_response("HTTP/1.1 200\r\nFoo: Bar\r\n Biz: Bam\r\nBaz: Qux\r\n\r\nTest");
    }

    public function testParsesResponseWhenHeaderDelimiterIsOnlyALineFeed(): void
    {
        $res = "HTTP/1.0 200\nFoo: Bar\nBaz: Bam\n\nTest\n\nOtherTest";
        $response = Psr7\parse_response($res);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getReasonPhrase());
        self::assertSame('1.0', $response->getProtocolVersion());
        self::assertSame('Bar', $response->getHeaderLine('Foo'));
        self::assertSame('Bam', $response->getHeaderLine('Baz'));
        self::assertSame("Test\n\nOtherTest", (string)$response->getBody());
    }

    public function testResponseParsingFailsWithoutHeaderDelimiter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Psr7\parse_response("HTTP/1.0 200\r\nFoo: Bar\r\n Baz: Bam\r\nBaz: Qux\r\n");
    }

    public function testValidatesResponseMessages(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Psr7\parse_response("GET / HTTP/1.1\r\n\r\n");
    }

    public function testDetermineMimetype(): void
    {
        self::assertNull(Psr7\mimetype_from_extension('not-a-real-extension'));
        self::assertSame(
            'application/json',
            Psr7\mimetype_from_extension('json')
        );
        self::assertSame(
            'image/jpeg',
            Psr7\mimetype_from_filename('/tmp/images/IMG034821.JPEG')
        );
    }

    public function testCreatesUriForValue(): void
    {
        self::assertInstanceOf(Psr7\Uri::class, Psr7\uri_for('/foo'));
        self::assertInstanceOf(
            Psr7\Uri::class,
            Psr7\uri_for(new Psr7\Uri('/foo'))
        );
    }

    public function testValidatesUri(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Psr7\uri_for([]);
    }

    public function testKeepsPositionOfResource(): void
    {
        $h = fopen(__FILE__, 'r');
        fseek($h, 10);
        $stream = Psr7\stream_for($h);
        self::assertSame(10, $stream->tell());
        $stream->close();
    }

    public function testCreatesWithFactory(): void
    {
        $stream = Psr7\stream_for('foo');
        self::assertInstanceOf(Stream::class, $stream);
        self::assertSame('foo', $stream->getContents());
        $stream->close();
    }

    public function testFactoryCreatesFromEmptyString(): void
    {
        $s = Psr7\stream_for();
        self::assertInstanceOf(Stream::class, $s);
    }

    public function testFactoryCreatesFromNull(): void
    {
        $s = Psr7\stream_for(null);
        self::assertInstanceOf(Stream::class, $s);
    }

    public function testFactoryCreatesFromResource(): void
    {
        $r = fopen(__FILE__, 'r');
        $s = Psr7\stream_for($r);
        self::assertInstanceOf(Stream::class, $s);
        self::assertSame(file_get_contents(__FILE__), (string)$s);
    }

    public function testFactoryCreatesFromObjectWithToString(): void
    {
        $r = new HasToString();
        $s = Psr7\stream_for($r);
        self::assertInstanceOf(Stream::class, $s);
        self::assertSame('foo', (string)$s);
    }

    public function testCreatePassesThrough(): void
    {
        $s = Psr7\stream_for('foo');
        self::assertSame($s, Psr7\stream_for($s));
    }

    public function testThrowsExceptionForUnknown(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Psr7\stream_for(new \stdClass());
    }

    public function testReturnsCustomMetadata(): void
    {
        $s = Psr7\stream_for('foo', ['metadata' => ['hwm' => 3]]);
        self::assertSame(3, $s->getMetadata('hwm'));
        self::assertArrayHasKey('hwm', $s->getMetadata());
    }

    public function testCanSetSize(): void
    {
        $s = Psr7\stream_for('', ['size' => 10]);
        self::assertSame(10, $s->getSize());
    }

    public function testCanCreateIteratorBasedStream(): void
    {
        $a = new \ArrayIterator(['foo', 'bar', '123']);
        $p = Psr7\stream_for($a);
        self::assertInstanceOf(Psr7\PumpStream::class, $p);
        self::assertSame('foo', $p->read(3));
        self::assertFalse($p->eof());
        self::assertSame('b', $p->read(1));
        self::assertSame('a', $p->read(1));
        self::assertSame('r12', $p->read(3));
        self::assertFalse($p->eof());
        self::assertSame('3', $p->getContents());
        self::assertTrue($p->eof());
        self::assertSame(9, $p->tell());
    }

    public function testConvertsRequestsToStrings(): void
    {
        $request = new Psr7\Request('PUT', 'http://foo.com/hi?123', [
            'Baz' => 'bar',
            'Qux' => 'ipsum',
        ], 'hello', '1.0');
        self::assertSame(
            "PUT /hi?123 HTTP/1.0\r\nHost: foo.com\r\nBaz: bar\r\nQux: ipsum\r\n\r\nhello",
            Psr7\str($request)
        );
    }

    public function testConvertsResponsesToStrings(): void
    {
        $response = new Psr7\Response(200, [
            'Baz' => 'bar',
            'Qux' => 'ipsum',
        ], 'hello', '1.0', 'FOO');
        self::assertSame(
            "HTTP/1.0 200 FOO\r\nBaz: bar\r\nQux: ipsum\r\n\r\nhello",
            Psr7\str($response)
        );
    }

    public function testCorrectlyRendersSetCookieHeadersToString(): void
    {
        $response = new Psr7\Response(200, [
            'Set-Cookie' => ['bar','baz','qux']
        ], 'hello', '1.0', 'FOO');
        self::assertSame(
            "HTTP/1.0 200 FOO\r\nSet-Cookie: bar\r\nSet-Cookie: baz\r\nSet-Cookie: qux\r\n\r\nhello",
            Psr7\str($response)
        );
    }

    public function parseParamsProvider(): iterable
    {
        $res1 = [
            [
                '<http:/.../front.jpeg>',
                'rel' => 'front',
                'type' => 'image/jpeg',
            ],
            [
                '<http://.../back.jpeg>',
                'rel' => 'back',
                'type' => 'image/jpeg',
            ],
        ];
        return [
            [
                '<http:/.../front.jpeg>; rel="front"; type="image/jpeg", <http://.../back.jpeg>; rel=back; type="image/jpeg"',
                $res1,
            ],
            [
                '<http:/.../front.jpeg>; rel="front"; type="image/jpeg",<http://.../back.jpeg>; rel=back; type="image/jpeg"',
                $res1,
            ],
            [
                'foo="baz"; bar=123, boo, test="123", foobar="foo;bar"',
                [
                    ['foo' => 'baz', 'bar' => '123'],
                    ['boo'],
                    ['test' => '123'],
                    ['foobar' => 'foo;bar'],
                ],
            ],
            [
                '<http://.../side.jpeg?test=1>; rel="side"; type="image/jpeg",<http://.../side.jpeg?test=2>; rel=side; type="image/jpeg"',
                [
                    ['<http://.../side.jpeg?test=1>', 'rel' => 'side', 'type' => 'image/jpeg'],
                    ['<http://.../side.jpeg?test=2>', 'rel' => 'side', 'type' => 'image/jpeg'],
                ],
            ],
            [
                '',
                [],
            ],
        ];
    }

    /**
     * @dataProvider parseParamsProvider
     */
    public function testParseParams($header, $result): void
    {
        self::assertSame($result, Psr7\parse_header($header));
    }

    public function testParsesArrayHeaders(): void
    {
        $header = ['a, b', 'c', 'd, e'];
        self::assertSame(['a', 'b', 'c', 'd', 'e'], Psr7\normalize_header($header));
    }

    public function testRewindsBody(): void
    {
        $body = Psr7\stream_for('abc');
        $res = new Psr7\Response(200, [], $body);
        Psr7\rewind_body($res);
        self::assertSame(0, $body->tell());
        $body->rewind();
        Psr7\rewind_body($res);
        self::assertSame(0, $body->tell());
    }

    public function testThrowsWhenBodyCannotBeRewound(): void
    {
        $body = Psr7\stream_for('abc');
        $body->read(1);
        $body = FnStream::decorate($body, [
            'rewind' => function (): void {
                throw new \RuntimeException('a');
            },
        ]);
        $res = new Psr7\Response(200, [], $body);
        $this->expectException(\RuntimeException::class);
        Psr7\rewind_body($res);
    }

    public function testCanModifyRequestWithUri(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\modify_request($r1, [
            'uri' => new Psr7\Uri('http://www.foo.com'),
        ]);
        self::assertSame('http://www.foo.com', (string)$r2->getUri());
        self::assertSame('www.foo.com', (string)$r2->getHeaderLine('host'));
    }

    public function testCanModifyRequestWithUriAndPort(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com:8000');
        $r2 = Psr7\modify_request($r1, [
            'uri' => new Psr7\Uri('http://www.foo.com:8000'),
        ]);
        self::assertSame('http://www.foo.com:8000', (string)$r2->getUri());
        self::assertSame('www.foo.com:8000', (string)$r2->getHeaderLine('host'));
    }

    public function testCanModifyRequestWithCaseInsensitiveHeader(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com', ['User-Agent' => 'foo']);
        $r2 = Psr7\modify_request($r1, ['set_headers' => ['User-agent' => 'bar']]);
        self::assertSame('bar', $r2->getHeaderLine('User-Agent'));
        self::assertSame('bar', $r2->getHeaderLine('User-agent'));
    }

    public function testReturnsAsIsWhenNoChanges(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\modify_request($r1, []);
        self::assertInstanceOf(Psr7\Request::class, $r2);

        $r1 = new Psr7\ServerRequest('GET', 'http://foo.com');
        $r2 = Psr7\modify_request($r1, []);
        self::assertInstanceOf(ServerRequestInterface::class, $r2);
    }

    public function testReturnsUriAsIsWhenNoChanges(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\modify_request($r1, ['set_headers' => ['foo' => 'bar']]);
        self::assertNotSame($r1, $r2);
        self::assertSame('bar', $r2->getHeaderLine('foo'));
    }

    public function testRemovesHeadersFromMessage(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com', ['foo' => 'bar']);
        $r2 = Psr7\modify_request($r1, ['remove_headers' => ['foo']]);
        self::assertNotSame($r1, $r2);
        self::assertFalse($r2->hasHeader('foo'));
    }

    public function testAddsQueryToUri(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\modify_request($r1, ['query' => 'foo=bar']);
        self::assertNotSame($r1, $r2);
        self::assertSame('foo=bar', $r2->getUri()->getQuery());
    }

    public function testModifyRequestKeepInstanceOfRequest(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\modify_request($r1, ['remove_headers' => ['non-existent']]);
        self::assertInstanceOf(Psr7\Request::class, $r2);

        $r1 = new Psr7\ServerRequest('GET', 'http://foo.com');
        $r2 = Psr7\modify_request($r1, ['remove_headers' => ['non-existent']]);
        self::assertInstanceOf(ServerRequestInterface::class, $r2);
    }

    public function testMessageBodySummaryWithSmallBody(): void
    {
        $message = new Psr7\Response(200, [], 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.');
        self::assertSame('Lorem ipsum dolor sit amet, consectetur adipiscing elit.', Psr7\get_message_body_summary($message));
    }

    public function testMessageBodySummaryWithLargeBody(): void
    {
        $message = new Psr7\Response(200, [], 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.');
        self::assertSame('Lorem ipsu (truncated...)', Psr7\get_message_body_summary($message, 10));
    }

    public function testMessageBodySummaryWithSpecialUTF8Characters(): void
    {
        $message = new Psr7\Response(200, [], '’é€௵ဪ‱');
        self::assertSame('’é€௵ဪ‱', Psr7\get_message_body_summary($message));
    }

    public function testMessageBodySummaryWithEmptyBody(): void
    {
        $message = new Psr7\Response(200, [], '');
        self::assertNull(Psr7\get_message_body_summary($message));
    }

    public function testGetResponseBodySummaryOfNonReadableStream(): void
    {
        self::assertNull(Psr7\get_message_body_summary(new Psr7\Response(500, [], new ReadSeekOnlyStream())));
    }

    public function testModifyServerRequestWithUploadedFiles(): void
    {
        $request = new Psr7\ServerRequest('GET', 'http://example.com/bla');
        $file = new Psr7\UploadedFile('Test', 100, \UPLOAD_ERR_OK);
        $request = $request->withUploadedFiles([$file]);

        /** @var Psr7\ServerRequest $modifiedRequest */
        $modifiedRequest = Psr7\modify_request($request, ['set_headers' => ['foo' => 'bar']]);

        self::assertCount(1, $modifiedRequest->getUploadedFiles());

        $files = $modifiedRequest->getUploadedFiles();
        self::assertInstanceOf(Psr7\UploadedFile::class, $files[0]);
    }

    public function testModifyServerRequestWithCookies(): void
    {
        $request = (new Psr7\ServerRequest('GET', 'http://example.com/bla'))
            ->withCookieParams(['name' => 'value']);

        /** @var Psr7\ServerRequest $modifiedRequest */
        $modifiedRequest = Psr7\modify_request($request, ['set_headers' => ['foo' => 'bar']]);

        self::assertSame(['name' => 'value'], $modifiedRequest->getCookieParams());
    }

    public function testModifyServerRequestParsedBody(): void
    {
        $request = (new Psr7\ServerRequest('GET', 'http://example.com/bla'))
            ->withParsedBody(['name' => 'value']);

        /** @var Psr7\ServerRequest $modifiedRequest */
        $modifiedRequest = Psr7\modify_request($request, ['set_headers' => ['foo' => 'bar']]);

        self::assertSame(['name' => 'value'], $modifiedRequest->getParsedBody());
    }

    public function testModifyServerRequestQueryParams(): void
    {
        $request = (new Psr7\ServerRequest('GET', 'http://example.com/bla'))
            ->withQueryParams(['name' => 'value']);

        /** @var Psr7\ServerRequest $modifiedRequest */
        $modifiedRequest = Psr7\modify_request($request, ['set_headers' => ['foo' => 'bar']]);

        self::assertSame(['name' => 'value'], $modifiedRequest->getQueryParams());
    }
}

class HasToString
{
    public function __toString()
    {
        return 'foo';
    }
}

/**
 * convert it to an anonymous class on PHP7
 */
final class ReadSeekOnlyStream extends Stream
{
    public function __construct()
    {
        parent::__construct(fopen('php://memory', 'wb'));
    }

    public function isSeekable(): bool
    {
        return true;
    }

    public function isReadable(): bool
    {
        return false;
    }
}

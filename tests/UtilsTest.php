<?php

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\NoSeekStream;

class UtilsTest extends BaseTest
{
    public function testCopiesToString()
    {
        $s = Psr7\Utils::streamFor('foobaz');
        $this->assertSame('foobaz', Psr7\Utils::copyToString($s));
        $s->seek(0);
        $this->assertSame('foo', Psr7\Utils::copyToString($s, 3));
        $this->assertSame('baz', Psr7\Utils::copyToString($s, 3));
        $this->assertSame('', Psr7\Utils::copyToString($s));
    }

    public function testCopiesToStringStopsWhenReadFails()
    {
        $s1 = Psr7\Utils::streamFor('foobaz');
        $s1 = FnStream::decorate($s1, [
            'read' => function () {
                return '';
            },
        ]);
        $result = Psr7\Utils::copyToString($s1);
        $this->assertSame('', $result);
    }

    public function testCopiesToStream()
    {
        $s1 = Psr7\Utils::streamFor('foobaz');
        $s2 = Psr7\Utils::streamFor('');
        Psr7\Utils::copyToStream($s1, $s2);
        $this->assertSame('foobaz', (string)$s2);
        $s2 = Psr7\Utils::streamFor('');
        $s1->seek(0);
        Psr7\Utils::copyToStream($s1, $s2, 3);
        $this->assertSame('foo', (string)$s2);
        Psr7\Utils::copyToStream($s1, $s2, 3);
        $this->assertSame('foobaz', (string)$s2);
    }

    public function testStopsCopyToStreamWhenWriteFails()
    {
        $s1 = Psr7\Utils::streamFor('foobaz');
        $s2 = Psr7\Utils::streamFor('');
        $s2 = FnStream::decorate($s2, [
            'write' => function () {
                return 0;
            },
        ]);
        Psr7\Utils::copyToStream($s1, $s2);
        $this->assertSame('', (string)$s2);
    }

    public function testStopsCopyToSteamWhenWriteFailsWithMaxLen()
    {
        $s1 = Psr7\Utils::streamFor('foobaz');
        $s2 = Psr7\Utils::streamFor('');
        $s2 = FnStream::decorate($s2, [
            'write' => function () {
                return 0;
            },
        ]);
        Psr7\Utils::copyToStream($s1, $s2, 10);
        $this->assertSame('', (string)$s2);
    }

    public function testCopyToStreamReadsInChunksInsteadOfAllInMemory()
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
        $s2 = Psr7\Utils::streamFor('');
        Psr7\Utils::copyToStream($s1, $s2, 16394);
        $s2->seek(0);
        $this->assertSame(16394, strlen($s2->getContents()));
        $this->assertSame(8192, $sizes[0]);
        $this->assertSame(8192, $sizes[1]);
        $this->assertSame(10, $sizes[2]);
    }

    public function testStopsCopyToSteamWhenReadFailsWithMaxLen()
    {
        $s1 = Psr7\Utils::streamFor('foobaz');
        $s1 = FnStream::decorate($s1, [
            'read' => function () {
                return '';
            },
        ]);
        $s2 = Psr7\Utils::streamFor('');
        Psr7\Utils::copyToStream($s1, $s2, 10);
        $this->assertSame('', (string)$s2);
    }

    public function testReadsLines()
    {
        $s = Psr7\Utils::streamFor("foo\nbaz\nbar");
        $this->assertSame("foo\n", Psr7\Utils::readLine($s));
        $this->assertSame("baz\n", Psr7\Utils::readLine($s));
        $this->assertSame('bar', Psr7\Utils::readLine($s));
    }

    public function testReadsLinesUpToMaxLength()
    {
        $s = Psr7\Utils::streamFor("12345\n");
        $this->assertSame('123', Psr7\Utils::readLine($s, 4));
        $this->assertSame("45\n", Psr7\Utils::readLine($s));
    }

    public function testReadLinesEof()
    {
        // Should return empty string on EOF
        $s = Psr7\Utils::streamFor("foo\nbar");
        while (!$s->eof()) {
            Psr7\Utils::readLine($s);
        }
        $this->assertSame('', Psr7\Utils::readLine($s));
    }

    public function testReadsLineUntilFalseReturnedFromRead()
    {
        $s = $this->getMockBuilder('GuzzleHttp\Psr7\Stream')
            ->setMethods(['read', 'eof'])
            ->disableOriginalConstructor()
            ->getMock();
        $s->expects($this->exactly(2))
            ->method('read')
            ->will($this->returnCallback(function () {
                static $c = false;
                if ($c) {
                    return false;
                }
                $c = true;
                return 'h';
            }));
        $s->expects($this->exactly(2))
            ->method('eof')
            ->will($this->returnValue(false));
        $this->assertSame('h', Psr7\Utils::readLine($s));
    }

    public function testCalculatesHash()
    {
        $s = Psr7\Utils::streamFor('foobazbar');
        $this->assertSame(md5('foobazbar'), Psr7\Utils::hash($s, 'md5'));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testCalculatesHashThrowsWhenSeekFails()
    {
        $s = new NoSeekStream(Psr7\Utils::streamFor('foobazbar'));
        $s->read(2);
        Psr7\Utils::hash($s, 'md5');
    }

    public function testCalculatesHashSeeksToOriginalPosition()
    {
        $s = Psr7\Utils::streamFor('foobazbar');
        $s->seek(4);
        $this->assertSame(md5('foobazbar'), Psr7\Utils::hash($s, 'md5'));
        $this->assertSame(4, $s->tell());
    }

    public function testOpensFilesSuccessfully()
    {
        $r = Psr7\Utils::tryFopen(__FILE__, 'r');
        $this->assertInternalType('resource', $r);
        fclose($r);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Unable to open /path/to/does/not/exist using mode r
     */
    public function testThrowsExceptionNotWarning()
    {
        Psr7\Utils::tryFopen('/path/to/does/not/exist', 'r');
    }

    public function testCreatesUriForValue()
    {
        $this->assertInstanceOf('GuzzleHttp\Psr7\Uri', Psr7\Utils::uriFor('/foo'));
        $this->assertInstanceOf(
            'GuzzleHttp\Psr7\Uri',
            Psr7\Utils::uriFor(new Psr7\Uri('/foo'))
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testValidatesUri()
    {
        Psr7\Utils::uriFor([]);
    }

    public function testKeepsPositionOfResource()
    {
        $h = fopen(__FILE__, 'r');
        fseek($h, 10);
        $stream = Psr7\Utils::streamFor($h);
        $this->assertSame(10, $stream->tell());
        $stream->close();
    }

    public function testCreatesWithFactory()
    {
        $stream = Psr7\Utils::streamFor('foo');
        $this->assertInstanceOf('GuzzleHttp\Psr7\Stream', $stream);
        $this->assertSame('foo', $stream->getContents());
        $stream->close();
    }

    public function testFactoryCreatesFromEmptyString()
    {
        $s = Psr7\Utils::streamFor();
        $this->assertInstanceOf('GuzzleHttp\Psr7\Stream', $s);
    }

    public function testFactoryCreatesFromNull()
    {
        $s = Psr7\Utils::streamFor(null);
        $this->assertInstanceOf('GuzzleHttp\Psr7\Stream', $s);
    }

    public function testFactoryCreatesFromResource()
    {
        $r = fopen(__FILE__, 'r');
        $s = Psr7\Utils::streamFor($r);
        $this->assertInstanceOf('GuzzleHttp\Psr7\Stream', $s);
        $this->assertSame(file_get_contents(__FILE__), (string)$s);
    }

    public function testFactoryCreatesFromObjectWithToString()
    {
        $r = new HasToString();
        $s = Psr7\Utils::streamFor($r);
        $this->assertInstanceOf('GuzzleHttp\Psr7\Stream', $s);
        $this->assertSame('foo', (string)$s);
    }

    public function testCreatePassesThrough()
    {
        $s = Psr7\Utils::streamFor('foo');
        $this->assertSame($s, Psr7\Utils::streamFor($s));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testThrowsExceptionForUnknown()
    {
        Psr7\Utils::streamFor(new \stdClass());
    }

    public function testReturnsCustomMetadata()
    {
        $s = Psr7\Utils::streamFor('foo', ['metadata' => ['hwm' => 3]]);
        $this->assertSame(3, $s->getMetadata('hwm'));
        $this->assertArrayHasKey('hwm', $s->getMetadata());
    }

    public function testCanSetSize()
    {
        $s = Psr7\Utils::streamFor('', ['size' => 10]);
        $this->assertSame(10, $s->getSize());
    }

    public function testCanCreateIteratorBasedStream()
    {
        $a = new \ArrayIterator(['foo', 'bar', '123']);
        $p = Psr7\Utils::streamFor($a);
        $this->assertInstanceOf('GuzzleHttp\Psr7\PumpStream', $p);
        $this->assertSame('foo', $p->read(3));
        $this->assertFalse($p->eof());
        $this->assertSame('b', $p->read(1));
        $this->assertSame('a', $p->read(1));
        $this->assertSame('r12', $p->read(3));
        $this->assertFalse($p->eof());
        $this->assertSame('3', $p->getContents());
        $this->assertTrue($p->eof());
        $this->assertSame(9, $p->tell());
    }

    public function testConvertsRequestsToStrings()
    {
        $request = new Psr7\Request('PUT', 'http://foo.com/hi?123', [
            'Baz' => 'bar',
            'Qux' => 'ipsum',
        ], 'hello', '1.0');
        $this->assertSame(
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
        $this->assertSame(
            "HTTP/1.0 200 FOO\r\nBaz: bar\r\nQux: ipsum\r\n\r\nhello",
            Psr7\Message::toString($response)
        );
    }

    public function testCorrectlyRendersSetCookieHeadersToString()
    {
        $response = new Psr7\Response(200, [
            'Set-Cookie' => ['bar','baz','qux']
        ], 'hello', '1.0', 'FOO');
        $this->assertSame(
            "HTTP/1.0 200 FOO\r\nSet-Cookie: bar\r\nSet-Cookie: baz\r\nSet-Cookie: qux\r\n\r\nhello",
            Psr7\Message::toString($response)
        );
    }

    public function testCanModifyRequestWithUri()
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\Utils::modifyRequest($r1, [
            'uri' => new Psr7\Uri('http://www.foo.com'),
        ]);
        $this->assertSame('http://www.foo.com', (string)$r2->getUri());
        $this->assertSame('www.foo.com', (string)$r2->getHeaderLine('host'));
    }

    public function testCanModifyRequestWithUriAndPort()
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com:8000');
        $r2 = Psr7\Utils::modifyRequest($r1, [
            'uri' => new Psr7\Uri('http://www.foo.com:8000'),
        ]);
        $this->assertSame('http://www.foo.com:8000', (string)$r2->getUri());
        $this->assertSame('www.foo.com:8000', (string)$r2->getHeaderLine('host'));
    }

    public function testCanModifyRequestWithCaseInsensitiveHeader()
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com', ['User-Agent' => 'foo']);
        $r2 = Psr7\Utils::modifyRequest($r1, ['set_headers' => ['User-agent' => 'bar']]);
        $this->assertSame('bar', $r2->getHeaderLine('User-Agent'));
        $this->assertSame('bar', $r2->getHeaderLine('User-agent'));
    }

    public function testReturnsAsIsWhenNoChanges()
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\Utils::modifyRequest($r1, []);
        $this->assertInstanceOf('GuzzleHttp\Psr7\Request', $r2);

        $r1 = new Psr7\ServerRequest('GET', 'http://foo.com');
        $r2 = Psr7\Utils::modifyRequest($r1, []);
        $this->assertInstanceOf('Psr\Http\Message\ServerRequestInterface', $r2);
    }

    public function testReturnsUriAsIsWhenNoChanges()
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\Utils::modifyRequest($r1, ['set_headers' => ['foo' => 'bar']]);
        $this->assertNotSame($r1, $r2);
        $this->assertSame('bar', $r2->getHeaderLine('foo'));
    }

    public function testRemovesHeadersFromMessage()
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com', ['foo' => 'bar']);
        $r2 = Psr7\Utils::modifyRequest($r1, ['remove_headers' => ['foo']]);
        $this->assertNotSame($r1, $r2);
        $this->assertFalse($r2->hasHeader('foo'));
    }

    public function testAddsQueryToUri()
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\Utils::modifyRequest($r1, ['query' => 'foo=bar']);
        $this->assertNotSame($r1, $r2);
        $this->assertSame('foo=bar', $r2->getUri()->getQuery());
    }

    public function testModifyRequestKeepInstanceOfRequest()
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\Utils::modifyRequest($r1, ['remove_headers' => ['non-existent']]);
        $this->assertInstanceOf('GuzzleHttp\Psr7\Request', $r2);

        $r1 = new Psr7\ServerRequest('GET', 'http://foo.com');
        $r2 = Psr7\Utils::modifyRequest($r1, ['remove_headers' => ['non-existent']]);
        $this->assertInstanceOf('Psr\Http\Message\ServerRequestInterface', $r2);
    }

    public function testModifyServerRequestWithUploadedFiles()
    {
        $request = new Psr7\ServerRequest('GET', 'http://example.com/bla');
        $file = new Psr7\UploadedFile('Test', 100, \UPLOAD_ERR_OK);
        $request = $request->withUploadedFiles([$file]);

        /** @var Psr7\ServerRequest $modifiedRequest */
        $modifiedRequest = Psr7\Utils::modifyRequest($request, ['set_headers' => ['foo' => 'bar']]);

        $this->assertCount(1, $modifiedRequest->getUploadedFiles());

        $files = $modifiedRequest->getUploadedFiles();
        $this->assertInstanceOf('GuzzleHttp\Psr7\UploadedFile', $files[0]);
    }

    public function testModifyServerRequestWithCookies()
    {
        $request = (new Psr7\ServerRequest('GET', 'http://example.com/bla'))
            ->withCookieParams(['name' => 'value']);

        /** @var Psr7\ServerRequest $modifiedRequest */
        $modifiedRequest = Psr7\Utils::modifyRequest($request, ['set_headers' => ['foo' => 'bar']]);

        $this->assertSame(['name' => 'value'], $modifiedRequest->getCookieParams());
    }

    public function testModifyServerRequestParsedBody()
    {
        $request = (new Psr7\ServerRequest('GET', 'http://example.com/bla'))
            ->withParsedBody(['name' => 'value']);

        /** @var Psr7\ServerRequest $modifiedRequest */
        $modifiedRequest = Psr7\Utils::modifyRequest($request, ['set_headers' => ['foo' => 'bar']]);

        $this->assertSame(['name' => 'value'], $modifiedRequest->getParsedBody());
    }

    public function testModifyServerRequestQueryParams()
    {
        $request = (new Psr7\ServerRequest('GET', 'http://example.com/bla'))
            ->withQueryParams(['name' => 'value']);

        /** @var Psr7\ServerRequest $modifiedRequest */
        $modifiedRequest = Psr7\Utils::modifyRequest($request, ['set_headers' => ['foo' => 'bar']]);

        $this->assertSame(['name' => 'value'], $modifiedRequest->getQueryParams());
    }
}

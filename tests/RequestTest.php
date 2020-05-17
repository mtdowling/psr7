<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

/**
 * @covers GuzzleHttp\Psr7\MessageTrait
 * @covers GuzzleHttp\Psr7\Request
 */
class RequestTest extends TestCase
{
    public function testRequestUriMayBeString(): void
    {
        $r = new Request('GET', '/');
        self::assertEquals('/', (string) $r->getUri());
    }

    public function testRequestUriMayBeUri(): void
    {
        $uri = new Uri('/');
        $r = new Request('GET', $uri);
        self::assertSame($uri, $r->getUri());
    }

    public function testValidateRequestUri(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Request('GET', '///');
    }

    public function testCanConstructWithBody(): void
    {
        $r = new Request('GET', '/', [], 'baz');
        self::assertInstanceOf(StreamInterface::class, $r->getBody());
        self::assertEquals('baz', (string) $r->getBody());
    }

    public function testNullBody(): void
    {
        $r = new Request('GET', '/', [], null);
        self::assertInstanceOf(StreamInterface::class, $r->getBody());
        self::assertSame('', (string) $r->getBody());
    }

    public function testFalseyBody(): void
    {
        $r = new Request('GET', '/', [], '0');
        self::assertInstanceOf(StreamInterface::class, $r->getBody());
        self::assertSame('0', (string) $r->getBody());
    }

    public function testConstructorDoesNotReadStreamBody(): void
    {
        $streamIsRead = false;
        $body = Psr7\FnStream::decorate(Psr7\stream_for(''), [
            '__toString' => function () use (&$streamIsRead) {
                $streamIsRead = true;
                return '';
            }
        ]);

        $r = new Request('GET', '/', [], $body);
        self::assertFalse($streamIsRead);
        self::assertSame($body, $r->getBody());
    }

    public function testCapitalizesMethod(): void
    {
        $r = new Request('get', '/');
        self::assertEquals('GET', $r->getMethod());
    }

    public function testCapitalizesWithMethod(): void
    {
        $r = new Request('GET', '/');
        self::assertEquals('PUT', $r->withMethod('put')->getMethod());
    }

    public function testWithUri(): void
    {
        $r1 = new Request('GET', '/');
        $u1 = $r1->getUri();
        $u2 = new Uri('http://www.example.com');
        $r2 = $r1->withUri($u2);
        self::assertNotSame($r1, $r2);
        self::assertSame($u2, $r2->getUri());
        self::assertSame($u1, $r1->getUri());
    }

    /**
     * @dataProvider invalidMethodsProvider
     */
    public function testConstructWithInvalidMethods($method): void
    {
        $this->expectException(\TypeError::class);
        new Request($method, '/');
    }

    /**
     * @dataProvider invalidMethodsProvider
     */
    public function testWithInvalidMethods($method): void
    {
        $r = new Request('get', '/');
        $this->expectException(\InvalidArgumentException::class);
        $r->withMethod($method);
    }

    public function invalidMethodsProvider(): iterable
    {
        return [
            [null],
            [false],
            [['foo']],
            [new \stdClass()],
        ];
    }

    public function testSameInstanceWhenSameUri(): void
    {
        $r1 = new Request('GET', 'http://foo.com');
        $r2 = $r1->withUri($r1->getUri());
        self::assertSame($r1, $r2);
    }

    public function testWithRequestTarget(): void
    {
        $r1 = new Request('GET', '/');
        $r2 = $r1->withRequestTarget('*');
        self::assertEquals('*', $r2->getRequestTarget());
        self::assertEquals('/', $r1->getRequestTarget());
    }

    public function testRequestTargetDoesNotAllowSpaces(): void
    {
        $r1 = new Request('GET', '/');
        $this->expectException(\InvalidArgumentException::class);
        $r1->withRequestTarget('/foo bar');
    }

    public function testRequestTargetDefaultsToSlash(): void
    {
        $r1 = new Request('GET', '');
        self::assertEquals('/', $r1->getRequestTarget());
        $r2 = new Request('GET', '*');
        self::assertEquals('*', $r2->getRequestTarget());
        $r3 = new Request('GET', 'http://foo.com/bar baz/');
        self::assertEquals('/bar%20baz/', $r3->getRequestTarget());
    }

    public function testBuildsRequestTarget(): void
    {
        $r1 = new Request('GET', 'http://foo.com/baz?bar=bam');
        self::assertEquals('/baz?bar=bam', $r1->getRequestTarget());
    }

    public function testBuildsRequestTargetWithFalseyQuery(): void
    {
        $r1 = new Request('GET', 'http://foo.com/baz?0');
        self::assertEquals('/baz?0', $r1->getRequestTarget());
    }

    public function testHostIsAddedFirst(): void
    {
        $r = new Request('GET', 'http://foo.com/baz?bar=bam', ['Foo' => 'Bar']);
        self::assertEquals([
            'Host' => ['foo.com'],
            'Foo'  => ['Bar']
        ], $r->getHeaders());
    }

    public function testCanGetHeaderAsCsv(): void
    {
        $r = new Request('GET', 'http://foo.com/baz?bar=bam', [
            'Foo' => ['a', 'b', 'c']
        ]);
        self::assertEquals('a, b, c', $r->getHeaderLine('Foo'));
        self::assertEquals('', $r->getHeaderLine('Bar'));
    }

    /**
     * @dataProvider provideHeadersContainingNotAllowedChars
     */
    public function testContainsNotAllowedCharsOnHeaderField($header): void
    {
        $this->expectExceptionMessage(
            sprintf(
                '"%s" is not valid header name',
                $header
            )
        );
        $r = new Request(
            'GET',
            'http://foo.com/baz?bar=bam',
            [
                $header => 'value'
            ]
        );
    }

    public function provideHeadersContainingNotAllowedChars(): iterable
    {
        return [[' key '], ['key '], [' key'], ['key/'], ['key('], ['key\\'], [' ']];
    }

    /**
     * @dataProvider provideHeadersContainsAllowedChar
     */
    public function testContainsAllowedCharsOnHeaderField($header): void
    {
        $r = new Request(
            'GET',
            'http://foo.com/baz?bar=bam',
            [
                $header => 'value'
            ]
        );
        self::assertArrayHasKey($header, $r->getHeaders());
    }

    public function provideHeadersContainsAllowedChar(): iterable
    {
        return [
            ['key'],
            ['key#'],
            ['key$'],
            ['key%'],
            ['key&'],
            ['key*'],
            ['key+'],
            ['key.'],
            ['key^'],
            ['key_'],
            ['key|'],
            ['key~'],
            ['key!'],
            ['key-'],
            ["key'"],
            ['key`']
        ];
    }

    public function testHostIsNotOverwrittenWhenPreservingHost(): void
    {
        $r = new Request('GET', 'http://foo.com/baz?bar=bam', ['Host' => 'a.com']);
        self::assertEquals(['Host' => ['a.com']], $r->getHeaders());
        $r2 = $r->withUri(new Uri('http://www.foo.com/bar'), true);
        self::assertEquals('a.com', $r2->getHeaderLine('Host'));
    }

    public function testWithUriSetsHostIfNotSet(): void
    {
        $r = (new Request('GET', 'http://foo.com/baz?bar=bam'))->withoutHeader('Host');
        self::assertEquals([], $r->getHeaders());
        $r2 = $r->withUri(new Uri('http://www.baz.com/bar'), true);
        self::assertSame('www.baz.com', $r2->getHeaderLine('Host'));
    }

    public function testOverridesHostWithUri(): void
    {
        $r = new Request('GET', 'http://foo.com/baz?bar=bam');
        self::assertEquals(['Host' => ['foo.com']], $r->getHeaders());
        $r2 = $r->withUri(new Uri('http://www.baz.com/bar'));
        self::assertEquals('www.baz.com', $r2->getHeaderLine('Host'));
    }

    public function testAggregatesHeaders(): void
    {
        $r = new Request('GET', '', [
            'ZOO' => 'zoobar',
            'zoo' => ['foobar', 'zoobar']
        ]);
        self::assertEquals(['ZOO' => ['zoobar', 'foobar', 'zoobar']], $r->getHeaders());
        self::assertEquals('zoobar, foobar, zoobar', $r->getHeaderLine('zoo'));
    }

    public function testAddsPortToHeader(): void
    {
        $r = new Request('GET', 'http://foo.com:8124/bar');
        self::assertEquals('foo.com:8124', $r->getHeaderLine('host'));
    }

    public function testAddsPortToHeaderAndReplacePreviousPort(): void
    {
        $r = new Request('GET', 'http://foo.com:8124/bar');
        $r = $r->withUri(new Uri('http://foo.com:8125/bar'));
        self::assertEquals('foo.com:8125', $r->getHeaderLine('host'));
    }
}

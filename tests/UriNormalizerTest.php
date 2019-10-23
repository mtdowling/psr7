<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\UriNormalizer;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

/**
 * @covers \GuzzleHttp\Psr7\UriNormalizer
 */
class UriNormalizerTest extends TestCase
{
    public function testCapitalizePercentEncoding()
    {
        $actualEncoding = 'a%c2%7A%5eb%25%fa%fA%Fa';
        $expectEncoding = 'a%C2%7A%5Eb%25%FA%FA%FA';
        $uri = (Psr7\uri_for(''))->withPath("/$actualEncoding")->withQuery($actualEncoding);

        $this->assertSame("/$actualEncoding?$actualEncoding", (string) $uri, 'Not normalized automatically beforehand');

        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::CAPITALIZE_PERCENT_ENCODING);

        $this->assertInstanceOf(UriInterface::class, $normalizedUri);
        $this->assertSame("/$expectEncoding?$expectEncoding", (string) $normalizedUri);
    }

    /**
     * @dataProvider getUnreservedCharacters
     */
    public function testDecodeUnreservedCharacters(string $char)
    {
        $percentEncoded = '%'.bin2hex($char);
        // Add encoded reserved characters to test that those are not decoded and include the percent-encoded
        // unreserved character both in lower and upper case to test the decoding is case-insensitive.
        $encodedChars = $percentEncoded.'%2F%5B'.strtoupper($percentEncoded);
        $uri = (Psr7\uri_for(''))->withPath("/$encodedChars")->withQuery($encodedChars);

        $this->assertSame("/$encodedChars?$encodedChars", (string) $uri, 'Not normalized automatically beforehand');

        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::DECODE_UNRESERVED_CHARACTERS);

        $this->assertInstanceOf(UriInterface::class, $normalizedUri);
        $this->assertSame("/$char%2F%5B$char?$char%2F%5B$char", (string) $normalizedUri);
    }

    public function getUnreservedCharacters()
    {
        $unreservedChars = array_merge(range('a', 'z'), range('A', 'Z'), range(0, 9), ['-', '.', '_', '~']);

        return array_map(function ($char) {
            return [(string) $char];
        }, $unreservedChars);
    }

    /**
     * @dataProvider getEmptyPathTestCases
     */
    public function testConvertEmptyPath($uri, $expected)
    {
        $normalizedUri = UriNormalizer::normalize(Psr7\uri_for($uri), UriNormalizer::CONVERT_EMPTY_PATH);

        $this->assertInstanceOf(UriInterface::class, $normalizedUri);
        $this->assertSame($expected, (string) $normalizedUri);
    }

    public function getEmptyPathTestCases()
    {
        return [
            ['http://example.org', 'http://example.org/'],
            ['https://example.org', 'https://example.org/'],
            ['urn://example.org', 'urn://example.org'],
        ];
    }

    public function testRemoveDefaultHost()
    {
        $uri = Psr7\uri_for('file://localhost/myfile');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::REMOVE_DEFAULT_HOST);

        $this->assertInstanceOf(UriInterface::class, $normalizedUri);
        $this->assertSame('file:///myfile', (string) $normalizedUri);
    }

    public function testRemoveDefaultPort()
    {
        $uri = $this->getMockBuilder(UriInterface::class)->getMock();
        $uri->expects($this->any())->method('getScheme')->will($this->returnValue('http'));
        $uri->expects($this->any())->method('getPort')->will($this->returnValue(80));
        $uri->expects($this->once())->method('withPort')->with(null)->will($this->returnValue(Psr7\uri_for('http://example.org')));

        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::REMOVE_DEFAULT_PORT);

        $this->assertInstanceOf(UriInterface::class, $normalizedUri);
        $this->assertNull($normalizedUri->getPort());
    }

    public function testRemoveDotSegments()
    {
        $uri = Psr7\uri_for('http://example.org/../a/b/../c/./d.html');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::REMOVE_DOT_SEGMENTS);

        $this->assertInstanceOf(UriInterface::class, $normalizedUri);
        $this->assertSame('http://example.org/a/c/d.html', (string) $normalizedUri);
    }

    public function testRemoveDotSegmentsOfAbsolutePathReference()
    {
        $uri = Psr7\uri_for('/../a/b/../c/./d.html');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::REMOVE_DOT_SEGMENTS);

        $this->assertInstanceOf(UriInterface::class, $normalizedUri);
        $this->assertSame('/a/c/d.html', (string) $normalizedUri);
    }

    public function testRemoveDotSegmentsOfRelativePathReference()
    {
        $uri = Psr7\uri_for('../c/./d.html');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::REMOVE_DOT_SEGMENTS);

        $this->assertInstanceOf(UriInterface::class, $normalizedUri);
        $this->assertSame('../c/./d.html', (string) $normalizedUri);
    }

    public function testRemoveDuplicateSlashes()
    {
        $uri = Psr7\uri_for('http://example.org//foo///bar/bam.html');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::REMOVE_DUPLICATE_SLASHES);

        $this->assertInstanceOf(UriInterface::class, $normalizedUri);
        $this->assertSame('http://example.org/foo/bar/bam.html', (string) $normalizedUri);
    }

    public function testSortQueryParameters()
    {
        $uri = Psr7\uri_for('?lang=en&article=fred');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::SORT_QUERY_PARAMETERS);

        $this->assertInstanceOf(UriInterface::class, $normalizedUri);
        $this->assertSame('?article=fred&lang=en', (string) $normalizedUri);
    }

    public function testSortQueryParametersWithSameKeys()
    {
        $uri = Psr7\uri_for('?a=b&b=c&a=a&a&b=a&b=b&a=d&a=c');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::SORT_QUERY_PARAMETERS);

        $this->assertInstanceOf(UriInterface::class, $normalizedUri);
        $this->assertSame('?a&a=a&a=b&a=c&a=d&b=a&b=b&b=c', (string) $normalizedUri);
    }

    /**
     * @dataProvider getEquivalentTestCases
     */
    public function testIsEquivalent($uri1, $uri2, $expected)
    {
        $equivalent = UriNormalizer::isEquivalent(Psr7\uri_for($uri1), Psr7\uri_for($uri2));

        $this->assertSame($expected, $equivalent);
    }

    public function getEquivalentTestCases()
    {
        return [
            ['http://example.org', 'http://example.org', true],
            ['hTTp://eXaMpLe.org', 'http://example.org', true],
            ['http://example.org/path?#', 'http://example.org/path', true],
            ['http://example.org:80', 'http://example.org/', true],
            ['http://example.org/../a/.././p%61th?%7a=%5e', 'http://example.org/path?z=%5E', true],
            ['https://example.org/', 'http://example.org/', false],
            ['https://example.org/', '//example.org/', false],
            ['//example.org/', '//example.org/', true],
            ['file:/myfile', 'file:///myfile', true],
            ['file:///myfile', 'file://localhost/myfile', true],
        ];
    }
}

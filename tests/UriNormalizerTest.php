<?php
namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriNormalizer;

/**
 * @covers GuzzleHttp\Psr7\UriNormalizer
 */
class UriNormalizerTest extends BaseTest
{
    public function testCapitalizePercentEncoding()
    {
        $actualEncoding = 'a%c2%7A%5eb%25%fa%fA%Fa';
        $expectEncoding = 'a%C2%7A%5Eb%25%FA%FA%FA';
        $uri = (new Uri())->withPath("/$actualEncoding")->withQuery($actualEncoding);

        $this->assertSame("/$actualEncoding?$actualEncoding", (string) $uri, 'Not normalized automatically beforehand');

        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::CAPITALIZE_PERCENT_ENCODING);

        $this->assertInstanceOf('Psr\Http\Message\UriInterface', $normalizedUri);
        $this->assertSame("/$expectEncoding?$expectEncoding", (string) $normalizedUri);
    }

    /**
     * @dataProvider getUnreservedCharacters
     */
    public function testDecodeUnreservedCharacters($char)
    {
        $percentEncoded = '%'.bin2hex($char);
        // Add encoded reserved characters to test that those are not decoded and include the percent-encoded
        // unreserved character both in lower and upper case to test the decoding is case-insensitive.
        $encodedChars = $percentEncoded.'%2F%5B'.strtoupper($percentEncoded);
        $uri = (new Uri())->withPath("/$encodedChars")->withQuery($encodedChars);

        $this->assertSame("/$encodedChars?$encodedChars", (string) $uri, 'Not normalized automatically beforehand');

        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::DECODE_UNRESERVED_CHARACTERS);

        $this->assertInstanceOf('Psr\Http\Message\UriInterface', $normalizedUri);
        $this->assertSame("/$char%2F%5B$char?$char%2F%5B$char", (string) $normalizedUri);
    }

    public function getUnreservedCharacters()
    {
        $unreservedChars = array_merge(range('a', 'z'), range('A', 'Z'), range('0', '9'), ['-', '.', '_', '~']);

        return array_map(function ($char) {
            return [$char];
        }, $unreservedChars);
    }

    /**
     * @dataProvider getEmptyPathTestCases
     */
    public function testConvertEmptyPath($uri, $expected)
    {
        $normalizedUri = UriNormalizer::normalize(new Uri($uri), UriNormalizer::CONVERT_EMPTY_PATH);

        $this->assertInstanceOf('Psr\Http\Message\UriInterface', $normalizedUri);
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
        $uri = new Uri('file://localhost/myfile');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::REMOVE_DEFAULT_HOST);

        $this->assertInstanceOf('Psr\Http\Message\UriInterface', $normalizedUri);
        $this->assertSame('file:///myfile', (string) $normalizedUri);
    }

    public function testRemoveDefaultPort()
    {
        $uri = $this->getMockBuilder('Psr\Http\Message\UriInterface')->getMock();
        $uri->expects($this->any())->method('getScheme')->will($this->returnValue('http'));
        $uri->expects($this->any())->method('getPort')->will($this->returnValue(80));
        $uri->expects($this->once())->method('withPort')->with(null)->will($this->returnValue(new Uri('http://example.org')));

        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::REMOVE_DEFAULT_PORT);

        $this->assertInstanceOf('Psr\Http\Message\UriInterface', $normalizedUri);
        $this->assertNull($normalizedUri->getPort());
    }

    public function testRemoveDotSegments()
    {
        $uri = new Uri('http://example.org/../a/b/../c/./d.html');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::REMOVE_DOT_SEGMENTS);

        $this->assertInstanceOf('Psr\Http\Message\UriInterface', $normalizedUri);
        $this->assertSame('http://example.org/a/c/d.html', (string) $normalizedUri);
    }

    public function testRemoveDotSegmentsOfAbsolutePathReference()
    {
        $uri = new Uri('/../a/b/../c/./d.html');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::REMOVE_DOT_SEGMENTS);

        $this->assertInstanceOf('Psr\Http\Message\UriInterface', $normalizedUri);
        $this->assertSame('/a/c/d.html', (string) $normalizedUri);
    }

    public function testRemoveDotSegmentsOfRelativePathReference()
    {
        $uri = new Uri('../c/./d.html');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::REMOVE_DOT_SEGMENTS);

        $this->assertInstanceOf('Psr\Http\Message\UriInterface', $normalizedUri);
        $this->assertSame('../c/./d.html', (string) $normalizedUri);
    }

    public function testRemoveDuplicateSlashes()
    {
        $uri = new Uri('http://example.org//foo///bar/bam.html');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::REMOVE_DUPLICATE_SLASHES);

        $this->assertInstanceOf('Psr\Http\Message\UriInterface', $normalizedUri);
        $this->assertSame('http://example.org/foo/bar/bam.html', (string) $normalizedUri);
    }

    public function testSortQueryParameters()
    {
        $uri = new Uri('?lang=en&article=fred');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::SORT_QUERY_PARAMETERS);

        $this->assertInstanceOf('Psr\Http\Message\UriInterface', $normalizedUri);
        $this->assertSame('?article=fred&lang=en', (string) $normalizedUri);
    }

    public function testSortQueryParametersWithSameKeys()
    {
        $uri = new Uri('?a=b&b=c&a=a&a&b=a&b=b&a=d&a=c');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::SORT_QUERY_PARAMETERS);

        $this->assertInstanceOf('Psr\Http\Message\UriInterface', $normalizedUri);
        $this->assertSame('?a&a=a&a=b&a=c&a=d&b=a&b=b&b=c', (string) $normalizedUri);
    }

    /**
     * @dataProvider getEquivalentTestCases
     */
    public function testIsEquivalent($uri1, $uri2, $expected)
    {
        $equivalent = UriNormalizer::isEquivalent(new Uri($uri1), new Uri($uri2));

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

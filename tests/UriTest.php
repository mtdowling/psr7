<?php
namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\Uri;

/**
 * @covers GuzzleHttp\Psr7\Uri
 */
class UriTest extends \PHPUnit_Framework_TestCase
{
    const RFC3986_BASE = "http://a/b/c/d;p?q";

    /**
     * @dataProvider populateGlobalVariable
     */
    public function testCanCreateFromGlobals()
    {
        $uri = Uri::fromGlobals();

        $this->assertEquals('https', $uri->getScheme());
        $this->assertEquals($_SERVER['SERVER_NAME'], $uri->getHost());
        $this->assertEquals(null, $uri->getPort());
        $this->assertEquals($_SERVER['REQUEST_URI'], $uri->getPath() . '?' . $uri->getQuery());
        $this->assertEquals($_SERVER['QUERY_STRING'], $uri->getQuery());
        $this->assertEquals('https://www.foo.com/index.php?foo=bar', (string) $uri);
    }

    public function testParsesProvidedUrl()
    {
        $uri = new Uri('https://michael:test@test.com:443/path/123?q=abc#test');

        // Standard port 443 for https gets ignored.
        $this->assertEquals(
            'https://michael:test@test.com/path/123?q=abc#test',
            (string) $uri
        );

        $this->assertEquals('test', $uri->getFragment());
        $this->assertEquals('test.com', $uri->getHost());
        $this->assertEquals('/path/123', $uri->getPath());
        $this->assertEquals(null, $uri->getPort());
        $this->assertEquals('q=abc', $uri->getQuery());
        $this->assertEquals('https', $uri->getScheme());
        $this->assertEquals('michael:test', $uri->getUserInfo());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Unable to parse URI
     */
    public function testValidatesUriCanBeParsed()
    {
        new Uri('///');
    }

    public function testCanTransformAndRetrievePartsIndividually()
    {
        $uri = (new Uri(''))
            ->withFragment('#test')
            ->withHost('example.com')
            ->withPath('path/123')
            ->withPort(8080)
            ->withQuery('?q=abc')
            ->withScheme('http')
            ->withUserInfo('user', 'pass');

        // Test getters.
        $this->assertEquals('user:pass@example.com:8080', $uri->getAuthority());
        $this->assertEquals('test', $uri->getFragment());
        $this->assertEquals('example.com', $uri->getHost());
        $this->assertEquals('path/123', $uri->getPath());
        $this->assertEquals(8080, $uri->getPort());
        $this->assertEquals('q=abc', $uri->getQuery());
        $this->assertEquals('http', $uri->getScheme());
        $this->assertEquals('user:pass', $uri->getUserInfo());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testPortMustBeValid()
    {
        (new Uri(''))->withPort(100000);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testPathMustBeValid()
    {
        (new Uri(''))->withPath([]);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testQueryMustBeValid()
    {
        (new Uri(''))->withQuery(new \stdClass);
    }

    public function testAllowsFalseyUrlParts()
    {
        $url = new Uri('http://a:1/0?0#0');
        $this->assertSame('a', $url->getHost());
        $this->assertEquals(1, $url->getPort());
        $this->assertSame('/0', $url->getPath());
        $this->assertEquals('0', (string) $url->getQuery());
        $this->assertSame('0', $url->getFragment());
        $this->assertEquals('http://a:1/0?0#0', (string) $url);
        $url = new Uri('');
        $this->assertSame('', (string) $url);
        $url = new Uri('0');
        $this->assertSame('0', (string) $url);
        $url = new Uri('/');
        $this->assertSame('/', (string) $url);
    }

    /**
     * @dataProvider getResolveTestCases
     */
    public function testResolvesUris($base, $rel, $expected)
    {
        $uri = new Uri($base);
        $actual = Uri::resolve($uri, $rel);
        $this->assertEquals($expected, (string) $actual);
    }

    public function getResolveTestCases()
    {
        return [
            //[self::RFC3986_BASE, 'g:h',           'g:h'],
            [self::RFC3986_BASE, 'g',             'http://a/b/c/g'],
            [self::RFC3986_BASE, './g',           'http://a/b/c/g'],
            [self::RFC3986_BASE, 'g/',            'http://a/b/c/g/'],
            [self::RFC3986_BASE, '/g',            'http://a/g'],
            [self::RFC3986_BASE, '//g',           'http://g'],
            [self::RFC3986_BASE, '?y',            'http://a/b/c/d;p?y'],
            [self::RFC3986_BASE, 'g?y',           'http://a/b/c/g?y'],
            [self::RFC3986_BASE, '#s',            'http://a/b/c/d;p?q#s'],
            [self::RFC3986_BASE, 'g#s',           'http://a/b/c/g#s'],
            [self::RFC3986_BASE, 'g?y#s',         'http://a/b/c/g?y#s'],
            [self::RFC3986_BASE, ';x',            'http://a/b/c/;x'],
            [self::RFC3986_BASE, 'g;x',           'http://a/b/c/g;x'],
            [self::RFC3986_BASE, 'g;x?y#s',       'http://a/b/c/g;x?y#s'],
            [self::RFC3986_BASE, '',              self::RFC3986_BASE],
            [self::RFC3986_BASE, '.',             'http://a/b/c/'],
            [self::RFC3986_BASE, './',            'http://a/b/c/'],
            [self::RFC3986_BASE, '..',            'http://a/b/'],
            [self::RFC3986_BASE, '../',           'http://a/b/'],
            [self::RFC3986_BASE, '../g',          'http://a/b/g'],
            [self::RFC3986_BASE, '../..',         'http://a/'],
            [self::RFC3986_BASE, '../../',        'http://a/'],
            [self::RFC3986_BASE, '../../g',       'http://a/g'],
            [self::RFC3986_BASE, '../../../g',    'http://a/g'],
            [self::RFC3986_BASE, '../../../../g', 'http://a/g'],
            [self::RFC3986_BASE, '/./g',          'http://a/g'],
            [self::RFC3986_BASE, '/../g',         'http://a/g'],
            [self::RFC3986_BASE, 'g.',            'http://a/b/c/g.'],
            [self::RFC3986_BASE, '.g',            'http://a/b/c/.g'],
            [self::RFC3986_BASE, 'g..',           'http://a/b/c/g..'],
            [self::RFC3986_BASE, '..g',           'http://a/b/c/..g'],
            [self::RFC3986_BASE, './../g',        'http://a/b/g'],
            [self::RFC3986_BASE, 'foo////g',      'http://a/b/c/foo////g'],
            [self::RFC3986_BASE, './g/.',         'http://a/b/c/g/'],
            [self::RFC3986_BASE, 'g/./h',         'http://a/b/c/g/h'],
            [self::RFC3986_BASE, 'g/../h',        'http://a/b/c/h'],
            [self::RFC3986_BASE, 'g;x=1/./y',     'http://a/b/c/g;x=1/y'],
            [self::RFC3986_BASE, 'g;x=1/../y',    'http://a/b/c/y'],
            //[self::RFC3986_BASE, 'http:g',        'http:g'],
        ];
    }

    public function testAddAndRemoveQueryValues()
    {
        $uri = new Uri('http://foo.com/bar');
        $uri = Uri::withQueryValue($uri, 'a', 'b');
        $uri = Uri::withQueryValue($uri, 'c', 'd');
        $uri = Uri::withQueryValue($uri, 'e', null);
        $this->assertEquals('a=b&c=d&e', $uri->getQuery());

        $uri = Uri::withoutQueryValue($uri, 'c');
        $uri = Uri::withoutQueryValue($uri, 'e');
        $this->assertEquals('a=b', $uri->getQuery());
        $uri = Uri::withoutQueryValue($uri, 'a');
        $uri = Uri::withoutQueryValue($uri, 'a');
        $this->assertEquals('', $uri->getQuery());
    }

    public function testGetAuthorityReturnsCorrectPort()
    {
        // HTTPS non-standard port
        $uri = new Uri('https://foo.co:99');
        $this->assertEquals('foo.co:99', $uri->getAuthority());

        // HTTP non-standard port
        $uri = new Uri('http://foo.co:99');
        $this->assertEquals('foo.co:99', $uri->getAuthority());

        // No scheme
        $uri = new Uri('foo.co:99');
        $this->assertEquals('foo.co:99', $uri->getAuthority());

        // No host or port
        $uri = new Uri('http:');
        $this->assertEquals('', $uri->getAuthority());

        // No host or port
        $uri = new Uri('http://foo.co');
        $this->assertEquals('foo.co', $uri->getAuthority());
    }

    public function pathTestProvider()
    {
        return [
            // Percent encode spaces.
            ['http://foo.com/baz bar', 'http://foo.com/baz%20bar'],
            // Don't encoding something that's already encoded.
            ['http://foo.com/baz%20bar', 'http://foo.com/baz%20bar'],
            // Percent encode invalid percent encodings
            ['http://foo.com/baz%2-bar', 'http://foo.com/baz%252-bar'],
            // Don't encode path segments
            ['http://foo.com/baz/bar/bam?a', 'http://foo.com/baz/bar/bam?a'],
            ['http://foo.com/baz+bar', 'http://foo.com/baz+bar'],
            ['http://foo.com/baz:bar', 'http://foo.com/baz:bar'],
            ['http://foo.com/baz@bar', 'http://foo.com/baz@bar'],
            ['http://foo.com/baz(bar);bam/', 'http://foo.com/baz(bar);bam/'],
            ['http://foo.com/a-zA-Z0-9.-_~!$&\'()*+,;=:@', 'http://foo.com/a-zA-Z0-9.-_~!$&\'()*+,;=:@'],
        ];
    }

    /**
     * @dataProvider pathTestProvider
     */
    public function testUriEncodesPathProperly($input, $output)
    {
        $uri = new Uri($input);
        $this->assertEquals((string) $uri, $output);
    }

    public function testDoesNotAddPortWhenNoPort()
    {
        $this->assertEquals('bar', new Uri('//bar'));
        $this->assertEquals('bar', (new Uri('//bar'))->getHost());
    }

    public function testAllowsForRelativeUri()
    {
        $uri = (new Uri)->withPath('foo');
        $this->assertEquals('foo', $uri->getPath());
        $this->assertEquals('foo', (string) $uri);
    }

    public function testAddsSlashForRelativeUriStringWithHost()
    {
        $uri = (new Uri)->withPath('foo')->withHost('bar.com');
        $this->assertEquals('foo', $uri->getPath());
        $this->assertEquals('bar.com/foo', (string) $uri);
    }

    public function populateGlobalVariable()
    {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SERVER_NAME'] = 'www.foo.com';
        $_SERVER['SERVER_PORT'] = 443;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/index.php?foo=bar';
        $_SERVER['QUERY_STRING'] = 'foo=bar';
    }
}

<?php

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;

class QueryTest extends BaseTest
{
    public function parseQueryProvider()
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
    public function testParsesQueries($input, $output)
    {
        $result = Psr7\Query::parse($input);
        $this->assertSame($output, $result);
    }

    public function testDoesNotDecode()
    {
        $str = 'foo%20=bar';
        $data = Psr7\Query::parse($str, false);
        $this->assertSame(['foo%20' => 'bar'], $data);
    }

    /**
     * @dataProvider parseQueryProvider
     */
    public function testParsesAndBuildsQueries($input)
    {
        $result = Psr7\Query::parse($input, false);
        $this->assertSame($input, Psr7\Query::build($result, false));
    }

    public function testEncodesWithRfc1738()
    {
        $str = Psr7\Query::build(['foo bar' => 'baz+'], PHP_QUERY_RFC1738);
        $this->assertSame('foo+bar=baz%2B', $str);
    }

    public function testEncodesWithRfc3986()
    {
        $str = Psr7\Query::build(['foo bar' => 'baz+'], PHP_QUERY_RFC3986);
        $this->assertSame('foo%20bar=baz%2B', $str);
    }

    public function testDoesNotEncode()
    {
        $str = Psr7\Query::build(['foo bar' => 'baz+'], false);
        $this->assertSame('foo bar=baz+', $str);
    }

    public function testCanControlDecodingType()
    {
        $result = Psr7\Query::parse('var=foo+bar', PHP_QUERY_RFC3986);
        $this->assertSame('foo+bar', $result['var']);
        $result = Psr7\Query::parse('var=foo+bar', PHP_QUERY_RFC1738);
        $this->assertSame('foo bar', $result['var']);
    }
}

<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use PHPUnit\Framework\TestCase;

class HeaderTest extends TestCase
{
    public function parseParamsProvider(): array
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
        self::assertSame($result, Psr7\Header::parse($header));
    }

    public function testParsesArrayHeaders(): void
    {
        $header = ['a, b', 'c', 'd, e'];
        self::assertSame(['a', 'b', 'c', 'd', 'e'], Psr7\Header::normalize($header));
    }

    public function testNormalizeWithPcreBackTrackLimited(): void
    {
        $limited = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', '4');
        $things = ['"Google Chrome";v="95", "Chromium";v="95", ";Not A Brand";v="99"'];
        self::assertSame(['"Google Chrome";v="95", "Chromium";v="95", ";Not A Brand";v="99"'], Psr7\Header::normalize($things));
        ini_set('pcre.backtrack_limit', $limited);
    }
}

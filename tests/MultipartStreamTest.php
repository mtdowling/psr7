<?php

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\MultipartStream;

class MultipartStreamTest extends BaseTest
{
    public function testCreatesDefaultBoundary()
    {
        $b = new MultipartStream();
        self::assertNotEmpty($b->getBoundary());
    }

    public function testCanProvideBoundary()
    {
        $b = new MultipartStream([], 'foo');
        self::assertSame('foo', $b->getBoundary());
    }

    public function testIsNotWritable()
    {
        $b = new MultipartStream();
        self::assertFalse($b->isWritable());
    }

    public function testCanCreateEmptyStream()
    {
        $b = new MultipartStream();
        $boundary = $b->getBoundary();
        self::assertSame("--{$boundary}--\r\n", $b->getContents());
        self::assertSame(strlen($boundary) + 6, $b->getSize());
    }

    public function testValidatesFilesArrayElement()
    {
        $this->expectExceptionGuzzle('InvalidArgumentException');

        new MultipartStream([['foo' => 'bar']]);
    }

    public function testEnsuresFileHasName()
    {
        $this->expectExceptionGuzzle('InvalidArgumentException');

        new MultipartStream([['contents' => 'bar']]);
    }

    public function testSerializesFields()
    {
        $b = new MultipartStream([
            [
                'name'     => 'foo',
                'contents' => 'bar'
            ],
            [
                'name' => 'baz',
                'contents' => 'bam'
            ]
        ], 'boundary');
        self::assertSame(
            "--boundary\r\nContent-Disposition: form-data; name=\"foo\"\r\nContent-Length: 3\r\n\r\n"
            . "bar\r\n--boundary\r\nContent-Disposition: form-data; name=\"baz\"\r\nContent-Length: 3"
            . "\r\n\r\nbam\r\n--boundary--\r\n",
            (string) $b
        );
    }

    public function testSerializesNonStringFields()
    {
        $b = new MultipartStream([
            [
                'name'     => 'int',
                'contents' => (int) 1
            ],
            [
                'name' => 'bool',
                'contents' => (boolean) false
            ],
            [
                'name' => 'bool2',
                'contents' => (boolean) true
            ],
            [
                'name' => 'float',
                'contents' => (float) 1.1
            ]
        ], 'boundary');
        self::assertSame(
            "--boundary\r\nContent-Disposition: form-data; name=\"int\"\r\nContent-Length: 1\r\n\r\n"
            . "1\r\n--boundary\r\nContent-Disposition: form-data; name=\"bool\"\r\n\r\n\r\n--boundary"
            . "\r\nContent-Disposition: form-data; name=\"bool2\"\r\nContent-Length: 1\r\n\r\n"
            . "1\r\n--boundary\r\nContent-Disposition: form-data; name=\"float\"\r\nContent-Length: 3"
            . "\r\n\r\n1.1\r\n--boundary--\r\n",
            (string) $b
        );
    }

    public function testSerializesFiles()
    {
        $f1 = Psr7\FnStream::decorate(Psr7\Utils::streamFor('foo'), [
            'getMetadata' => function () {
                return '/foo/bar.txt';
            }
        ]);

        $f2 = Psr7\FnStream::decorate(Psr7\Utils::streamFor('baz'), [
            'getMetadata' => function () {
                return '/foo/baz.jpg';
            }
        ]);

        $f3 = Psr7\FnStream::decorate(Psr7\Utils::streamFor('bar'), [
            'getMetadata' => function () {
                return '/foo/bar.gif';
            }
        ]);

        $b = new MultipartStream([
            [
                'name'     => 'foo',
                'contents' => $f1
            ],
            [
                'name' => 'qux',
                'contents' => $f2
            ],
            [
                'name'     => 'qux',
                'contents' => $f3
            ],
        ], 'boundary');

        $expected = <<<EOT
--boundary
Content-Disposition: form-data; name="foo"; filename="bar.txt"
Content-Length: 3
Content-Type: text/plain

foo
--boundary
Content-Disposition: form-data; name="qux"; filename="baz.jpg"
Content-Length: 3
Content-Type: image/jpeg

baz
--boundary
Content-Disposition: form-data; name="qux"; filename="bar.gif"
Content-Length: 3
Content-Type: image/gif

bar
--boundary--

EOT;

        self::assertSame($expected, str_replace("\r", '', $b));
    }

    public function testSerializesFilesWithCustomHeaders()
    {
        $f1 = Psr7\FnStream::decorate(Psr7\Utils::streamFor('foo'), [
            'getMetadata' => function () {
                return '/foo/bar.txt';
            }
        ]);

        $b = new MultipartStream([
            [
                'name' => 'foo',
                'contents' => $f1,
                'headers'  => [
                    'x-foo' => 'bar',
                    'content-disposition' => 'custom'
                ]
            ]
        ], 'boundary');

        $expected = <<<EOT
--boundary
x-foo: bar
content-disposition: custom
Content-Length: 3
Content-Type: text/plain

foo
--boundary--

EOT;

        self::assertSame($expected, str_replace("\r", '', $b));
    }

    public function testSerializesFilesWithCustomHeadersAndMultipleValues()
    {
        $f1 = Psr7\FnStream::decorate(Psr7\Utils::streamFor('foo'), [
            'getMetadata' => function () {
                return '/foo/bar.txt';
            }
        ]);

        $f2 = Psr7\FnStream::decorate(Psr7\Utils::streamFor('baz'), [
            'getMetadata' => function () {
                return '/foo/baz.jpg';
            }
        ]);

        $b = new MultipartStream([
            [
                'name'     => 'foo',
                'contents' => $f1,
                'headers'  => [
                    'x-foo' => 'bar',
                    'content-disposition' => 'custom'
                ]
            ],
            [
                'name'     => 'foo',
                'contents' => $f2,
                'headers'  => ['cOntenT-Type' => 'custom'],
            ]
        ], 'boundary');

        $expected = <<<EOT
--boundary
x-foo: bar
content-disposition: custom
Content-Length: 3
Content-Type: text/plain

foo
--boundary
cOntenT-Type: custom
Content-Disposition: form-data; name="foo"; filename="baz.jpg"
Content-Length: 3

baz
--boundary--

EOT;

        self::assertSame($expected, str_replace("\r", '', $b));
    }
}

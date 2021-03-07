<?php

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\InflateStream;
use GuzzleHttp\Psr7\NoSeekStream;

class InflateStreamTest extends BaseTest
{
    public function testInflatesStreams()
    {
        $content = gzencode('test');
        $a = Psr7\Utils::streamFor($content);
        $b = new InflateStream($a);
        self::assertSame('test', (string) $b);
    }

    public function testInflatesStreamsWithFilename()
    {
        $content = $this->getGzipStringWithFilename('test');
        $a = Psr7\Utils::streamFor($content);
        $b = new InflateStream($a);
        self::assertSame('test', (string) $b);
    }

    public function testInflatesStreamsPreserveSeekable()
    {
        $content = $this->getGzipStringWithFilename('test');
        $seekable = Psr7\Utils::streamFor($content);
        $nonSeekable = new NoSeekStream(Psr7\Utils::streamFor($content));

        self::assertTrue((new InflateStream($seekable))->isSeekable());
        self::assertFalse((new InflateStream($nonSeekable))->isSeekable());
    }

    private function getGzipStringWithFilename($original_string)
    {
        $gzipped = bin2hex(gzencode($original_string));

        $header = substr($gzipped, 0, 20);
        // set FNAME flag
        $header[6]=0;
        $header[7]=8;
        // make a dummy filename
        $filename = '64756d6d7900';
        $rest = substr($gzipped, 20);

        return hex2bin($header . $filename . $rest);
    }
}

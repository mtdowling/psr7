<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\InflateStream;
use GuzzleHttp\Psr7\NoSeekStream;
use PHPUnit\Framework\TestCase;

/**
 * @requires extension zlib
 */
class InflateStreamTest extends TestCase
{
    public function testInflatesRfc1952Streams(): void
    {
        $content = gzencode('test');
        $a = Psr7\stream_for($content);
        $b = new InflateStream($a);
        self::assertEquals('test', (string) $b);
    }

    public function testInflatesStreamsRfc1952WithFilename(): void
    {
        $content = $this->getGzipStringWithFilename('test');
        $a = Psr7\stream_for($content);
        $b = new InflateStream($a);
        self::assertEquals('test', (string) $b);
    }

    public function testInflatesRfc1950Streams(): void
    {
        $content = gzcompress('test');
        $a = Psr7\stream_for($content);
        $b = new InflateStream($a);
        self::assertEquals('test', (string) $b);
    }

    public function testInflatesRfc1952StreamsWithExtraFlags(): void
    {
        $content = gzdeflate('test'); // RFC 1951. Raw deflate. No header.

        //  +---+---+---+---+---+---+---+---+---+---+
        //  |ID1|ID2|CM |FLG|     MTIME     |XFL|OS | (more-->)
        //  +---+---+---+---+---+---+---+---+---+---+
        $header = "\x1f\x8B\x08";
        // set flags FHCRC, FEXTRA, FNAME and FCOMMENT
        $header .= chr(0b00011110);
        $header .= "\x00\x00\x00\x00"; // MTIME
        $header .= "\x02\x03"; // XFL, OS
        // 4 byte extra data
        $header .= "\x04\x00" /* XLEN */ . "\x41\x70\x00\x00" /*EXTRA*/;
        // file name (2 bytes + terminator)
        $header .= "\x41\x70\x00";
        // file comment (3 bytes + terminator)
        $header .= "\x41\x42\x43\x00";

        // crc16
        $header .= pack('v', crc32($header));

        $a = Psr7\stream_for($header . $content);
        $b = new InflateStream($a);
        self::assertEquals('test', (string) $b);
    }

    public function testInflatesStreamsPreserveSeekable(): void
    {
        $content = gzencode('test');
        $seekable = Psr7\stream_for($content);

        $seekableInflate = new InflateStream($seekable);
        self::assertTrue($seekableInflate->isSeekable());
        self::assertEquals('test', (string) $seekableInflate);

        $nonSeekable = new NoSeekStream(Psr7\stream_for($content));
        $nonSeekableInflate = new InflateStream($nonSeekable);
        self::assertFalse($nonSeekableInflate->isSeekable());
        self::assertEquals('test', (string) $nonSeekableInflate);
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

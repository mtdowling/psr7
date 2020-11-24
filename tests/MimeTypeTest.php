<?php

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;

class MimeTypeTestTest
{
    public function testDetermineFromExtension(): void
    {
        self::assertNull(Psr7\MimeType::fromExtension('not-a-real-extension'));
        self::assertSame('application/json', Psr7\MimeType::fromExtension('json'));
    }

    public function testDetermineFromFilename(): void
    {
        self::assertSame(
            'image/jpeg',
            Psr7\MimeType::fromFilename('/tmp/images/IMG034821.JPEG')
        );
    }
}

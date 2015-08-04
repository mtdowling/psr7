<?php
namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\InflateStream;

function php53_gzencode($data)
{
    return gzdeflate($data);
}

class InflateStreamtest extends \PHPUnit_Framework_TestCase
{
    public function testInflatesStreams()
    {
        $content = gzencode('test');
        $a = Psr7\stream_for($content);
        $b = new InflateStream($a);
        $this->assertEquals('test', (string) $b);
    }
}

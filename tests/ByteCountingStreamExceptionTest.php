<?php
namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\ByteCountingStreamException;

/**
 * @covers GuzzleHttp\Psr7\ByteCountingStreamException
 */
class ByteCountingStreamExceptionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getTestCases
     */
    public function testCanGenerateByteCountingStreamException($expect, $actual)
    {
        $msg = "The stream decorated by ByteCountingStream"
            . " has less bytes than expected.";
        $prev = new \RuntimeException("prev");

        $exception = new ByteCountingStreamException($expect, $actual, $prev);
        $this->assertEquals($msg, $exception->getMessage());
        $this->assertSame($prev, $exception->getPrevious());
        $this->assertEquals($expect, $exception->getExpectBytes());
        $this->assertEquals($actual, $exception->getActualBytes());
    }

    public function getTestCases()
    {
        return [[7, 5], [5, 0]];
    }
}

<?php
namespace GuzzleHttp\Psr7;

class ByteCountingStreamException extends \RuntimeException
{
    /** @var int expect number of bytes to be read */
    private $expectBytes;
    /** @var int actual number of bytes remaining */
    private $actualBytes;

    /**
     * ByteCountingStreamException constructor
     *
     * @param int        $expect   expected bytes to be read
     * @param int        $actual   actual available bytes to read
     * @param \Exception $previous Exception being thrown
     */
    public function __construct($expect, $actual, $previous = null)
    {
        $msg = "The ByteCountingStream decorator expects to be able to "
        . "read {$expect} bytes from a stream, but the stream being decorated "
        . "only contains {$actual} bytes.";
        $this->expectBytes = $expect;
        $this->actualBytes = $actual;

        parent::__construct($msg, 0, $previous);
    }

    /**
     * Get expected bytes to be read
     * @return int
     */
    public function getExpectBytes()
    {
        return $this->expectBytes;
    }

    /**
     * Get remaining bytes available for read
     * @return int
     */
    public function getRemainingBytes()
    {
        return $this->actualBytes;
    }
}

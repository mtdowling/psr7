<?php
namespace GuzzleHttp\Psr7;

use Psr\Http\Message\StreamInterface;
use SebastianBergmann\GlobalState\RuntimeException;

/**
 *  Stream decorator that counts the number of bytes read off of an underlying
 *  read stream.
 */
class ByteCountingStream implements StreamInterface
{
    use StreamDecoratorTrait;

    /** @var int Number of bytes allowed to read */
    private $maximum;

    /** @var int Number of bytes read off*/
    private $bytesCount;
    /**
     * @param StreamInterface $stream Stream to wrap
     * @param int             $bytes  Number of bytes to read
     * @throws \InvalidArgumentException
     */
    public function __construct(StreamInterface $stream, $bytes)
    {
        $this->stream = $stream;

        if (!is_int($bytes) || $bytes < 0) {
            $msg = "Bytes to read should be non-negative integer, got {$bytes}.";
            throw new \InvalidArgumentException($msg);
        }

        if ($bytes > $this->stream->getSize()) {
            $msg = "Bytes to read should be less than or equal to stream size : {$this->stream->getSize()}, "
                . "got {$bytes}";
            throw new \InvalidArgumentException($msg);
        }

        $this->maximum = $bytes;
        $this->bytesCount = 0;
    }

    public function read($length)
    {
        if ($this->bytesCount === $this->maximum) {
            return '';
        }

        $remaining = $this->maximum - $this->bytesCount;
        if ($length <= $remaining) {
            if ($this->stream->tell() + $length > $this->stream->getSize()) {
                $msg = "Not enough bytes to read from position : {$this->stream->tell()}";
                throw new \RuntimeException($msg);
            }

            $this->bytesCount += $length;
            return $this->stream->read($length);
        } else {
            $msg = "Fail to read {$length} more bytes, available bytes remaining : {$remaining}";
            throw new RuntimeException($msg);
        }
    }
}

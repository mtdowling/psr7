<?php
namespace GuzzleHttp\Psr7;

use Psr\Http\Message\StreamInterface;

/**
 *  Stream decorator that counts the number of bytes read off of an underlying
 *  read stream.
 */
class ByteCountingStream implements StreamInterface
{
    use StreamDecoratorTrait;

    /** @var int Number of bytes allowed per read */
    private $byteCount;

    /**
     * @param StreamInterface $stream Stream to wrap
     * @param int             $bytes  Number of bytes per read
     * @throws \InvalidArgumentException
     */
    public function __construct(StreamInterface $stream, $bytes)
    {
        $this->stream = $stream;

        if (!is_int($bytes) || $bytes < 0) {
            $msg = "Bytes per read should be non-negative integer, got {$bytes}.";
            throw new \InvalidArgumentException($msg);
        }
        $this->byteCount = $bytes;
    }

    /**
     * Reads byteCount number of bytes per read unless
     * there are not enough bytes to read from.
     *
     * @throws \RuntimeException
     */
    public function readBytes()
    {
        $remaining = $this->stream->getSize() - $this->stream->tell();
        if ($remaining < $this->byteCount) {
            $data = $this->stream->read($remaining);
        } else {
            $data = $this->stream->read($this->byteCount);
        }

        $error = $remaining < $this->byteCount ?
            strlen($data) !== $remaining :
            strlen($data) !== $this->byteCount;

        if ($error) {
            $msg = "Fail to read bytes from {$this->stream->tell()}";
            throw new \RuntimeException($msg);
        }
        return $data;
    }
}

<?php
namespace GuzzleHttp\Psr7;

use Psr\Http\Message\StreamInterface;

/**
 *  Stream decorator that ensures an expected number of bytes can be read
 *  from an underlying read stream. \RuntimeException is thrown when
 *  the underlying stream fails to provide the expected number of bytes.
 */
class ByteCountingStream implements StreamInterface
{
    use StreamDecoratorTrait;

    /** @var int Number of bytes remains to be read */
    private $remaining;

    /**
     * @param StreamInterface $stream       Stream to wrap
     * @param int             $bytesToRead  Number of bytes to read
     * @throws \InvalidArgumentException
     */
    public function __construct(StreamInterface $stream, $bytesToRead)
    {
        $this->stream = $stream;

        if (!is_int($bytesToRead) || $bytesToRead < 0) {
            $msg = "Bytes to read should be non-negative integer, got {$bytesToRead}.";
            throw new \InvalidArgumentException($msg);
        }

        if ($bytesToRead > $this->stream->getSize()) {
            $msg = "The ByteCountingStream decorator expects to be able to "
                . "read {$bytesToRead} from a stream, but the stream being decorated "
                . "only contains {$this->stream->getSize()} bytes.";
            throw new \InvalidArgumentException($msg);
        }

        $this->remaining = $bytesToRead;
    }

    public function read($length)
    {
        if ($this->remaining === 0) {
            return '';
        }

        if ($length <= $this->remaining) {
            if ($this->stream->tell() + $length > $this->stream->getSize()) {
                $msg = "Not enough bytes to read from position : {$this->stream->tell()}";
                throw new \RuntimeException($msg);
            }

            $this->remaining -= $length;
            return $this->stream->read($length);
        } else {
            $msg = "Fail to read {$length} more bytes, available bytes remaining : {$this->remaining}";
            throw new \RuntimeException($msg);
        }
    }
}

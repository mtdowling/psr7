<?php
namespace GuzzleHttp\Psr7;

use Psr\Http\Message\StreamInterface;

/**
 *  Stream decorator that ensures an expected number of bytes can be read
 *  from an underlying read stream. \RuntimeException is thrown when
 *  the underlying stream fails to provide the expected number of bytes.
 *  Excess bytes will be ignored.
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

        if (
            $this->stream->getSize() !== null &&
            $bytesToRead > $this->stream->getSize()
        ) {
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

        $bytesToRead = min($length, $this->remaining);
        $data = $this->stream->read($bytesToRead);
        $this->remaining -= strlen($data);

        if ((!$data || $data === '') && $this->remaining !== 0) {
            $msg = "The ByteCountingStream decorator expects to be able to read "
                . "{$bytesToRead} bytes, but the stream failed to provide enough bytes.";
            throw new \RuntimeException($msg);
        }
        return $data;
    }
}

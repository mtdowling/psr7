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
     * @throws ByteCountingStreamException | \InvalidArgumentException
     */
    public function __construct(StreamInterface $stream, $bytesToRead)
    {
        $this->stream = $stream;

        if (!is_int($bytesToRead) || $bytesToRead < 0) {
            $msg = "Bytes to read should be a non-negative integer for "
                . "ByteCountingStream, got {$bytesToRead}.";
            throw new \InvalidArgumentException($msg);
        }

        if (
            $this->stream->getSize() !== null &&
            $bytesToRead > $this->stream->getSize()
        ) {
            throw new ByteCountingStreamException(
                $bytesToRead,
                $this->stream->getSize()
            );
        }

        $this->remaining = $bytesToRead;
    }

    public function read($length)
    {
        if ($this->remaining === 0) {
            return '';
        }

        $offset = $this->tell();
        $bytesToRead = min($length, $this->remaining);
        $data = $this->stream->read($bytesToRead);
        $this->remaining -= strlen($data);

        if ((!$data || $data === '') && $this->remaining !== 0) {
            // hits EOF
            $provide = $this->tell() - $offset;
            throw new ByteCountingStreamException($this->remaining, $provide);
        }
        return $data;
    }
}

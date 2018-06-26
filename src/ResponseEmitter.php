<?php
namespace GuzzleHttp\Psr7;

use Psr\Http\Message\ResponseInterface;

/**
 * Response emitter
 */
class ResponseEmitter
{
    /**
     * Emits a response.
     *
     * @param ResponseInterface $response A response to emit.
     * @param int $maxBufferSize Maximum output buffering size (pass 0 to emit all content at once).
     * @param bool $normalizeHeaders Normalize header names while emitting. For example: Content-type -> Content-Type.
     */
    public function emit(ResponseInterface $response, $maxBufferSize = 8192, $normalizeHeaders = false)
    {
        $this->assertNoPreviousOutput();
        $this->emitHeaders($response, $normalizeHeaders);
        $this->emitStatus($response);
        $this->emitBody($response, $maxBufferSize);
    }

    /**
     * Checks that no headers have been sent / the output buffer contains no content.
     *
     * @throws \RuntimeException
     */
    protected function assertNoPreviousOutput()
    {
        if (headers_sent()) {
            throw new \RuntimeException('Unable to emit response; headers already sent.');
        }

        if (ob_get_level() > 0 && ob_get_length() > 0) {
            throw new \RuntimeException('Unable to emit response; output has been emitted previously.');
        }
    }

    /**
     * Emits response headers.
     *
     * @param ResponseInterface $response
     * @param bool $normalizeHeaders
     */
    protected function emitHeaders(ResponseInterface $response, $normalizeHeaders)
    {
        foreach ($response->getHeaders() as $name => $values) {
            if ($normalizeHeaders) {
                $name = $this->normalizeHeader($name);
            }

            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }
    }

    /**
     * Emits the status line.
     *
     * @param ResponseInterface $response
     */
    protected function emitStatus(ResponseInterface $response)
    {
        $statusCode = $response->getStatusCode();
        $reasonPhrase = $response->getReasonPhrase();

        header(
            sprintf(
                'HTTP/%s %d%s',
                $response->getProtocolVersion(),
                $statusCode,
                ($reasonPhrase ? ' ' . $reasonPhrase : '')
            ),
            true,
            $statusCode
        );
    }

    /**
     * Emits the message body.
     *
     * @param ResponseInterface $response
     * @param int $maxBufferSize
     */
    protected function emitBody(ResponseInterface $response, $maxBufferSize)
    {
        $stream = $response->getBody();

        if (!$maxBufferSize || !$stream->isReadable()) {
            echo $stream;
            return;
        }

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        while (!$stream->eof()) {
            echo $stream->read($maxBufferSize);
        }
    }

    /**
     * Normalizes header name. For example: Content-type -> Content-Type.
     *
     * @param string $name
     * @return string
     */
    protected function normalizeHeader($name)
    {
        $name = str_replace('-', ' ', $name);
        $name = ucwords($name);
        $name = str_replace(' ', '-', $name);

        return $name;
    }
}

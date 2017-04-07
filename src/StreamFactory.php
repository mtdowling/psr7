<?php

namespace GuzzleHttp\Psr7;

/**
 * Creates streams.
 *
 * @author Михаил Красильников <m.krasilnikov@yandex.ru>
 */
final class StreamFactory implements \Http\Message\StreamFactory
{
    /**
     * {@inheritdoc}
     */
    public function createStream($body = null)
    {
        return stream_for($body);
    }
}

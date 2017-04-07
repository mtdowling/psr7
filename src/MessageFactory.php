<?php

namespace GuzzleHttp\Psr7;

/**
 * Creates Request and Response.
 *
 * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
 */
final class MessageFactory implements \Http\Message\MessageFactory
{
    use ResponseFactoryTrait;

    /**
     * {@inheritdoc}
     */
    public function createRequest(
        $method,
        $uri,
        array $headers = [],
        $body = null,
        $protocolVersion = '1.1'
    ) {
        return new Request(
            $method,
            $uri,
            $headers,
            $body,
            $protocolVersion
        );
    }
}

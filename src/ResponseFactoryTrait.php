<?php

namespace GuzzleHttp\Psr7;

/**
 * Creates Response.
 *
 * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
 */
trait ResponseFactoryTrait
{
    /**
     * {@inheritdoc}
     */
    public function createResponse(
        $statusCode = 200,
        $reasonPhrase = null,
        array $headers = [],
        $body = null,
        $protocolVersion = '1.1'
    ) {
        return new Response(
            $statusCode,
            $headers,
            $body,
            $protocolVersion,
            $reasonPhrase
        );
    }
}

<?php

namespace GuzzleHttp\Psr7;

/**
 * Creates URI.
 *
 * @author David de Boer <david@ddeboer.nl>
 */
final class UriFactory implements \Http\Message\UriFactory
{
    /**
     * {@inheritdoc}
     */
    public function createUri($uri)
    {
        return uri_for($uri);
    }
}

<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\UriInterface;

/**
 * Provides methods to determine if a modified URL should be considered cross-origin.
 *
 * @author Graham Campbell
 */
final class UriComparator
{
    /**
     * Determines if a modified URL should be considered cross-origin with
     * respect to an original URL. Optionaly, we can allow http to https upgrade on base port.
     */
    public static function isCrossOrigin(UriInterface $original, UriInterface $modified, bool $allowProtocolUpgrade = false): bool
    {
        if (\strcasecmp($original->getHost(), $modified->getHost()) !== 0) {
            return true;
        }

        if ($original->getScheme() !== $modified->getScheme() && (!$allowProtocolUpgrade || $original->getScheme() === 'https')) {
            return true;
        }

        if (self::computePort($original) !== self::computePort($modified) && (!$allowProtocolUpgrade || self::computePort($modified) !== 443)) {
            return true;
        }

        return false;
    }

    private static function computePort(UriInterface $uri): int
    {
        $port = $uri->getPort();

        if (null !== $port) {
            return $port;
        }

        return 'https' === $uri->getScheme() ? 443 : 80;
    }

    private function __construct()
    {
        // cannot be instantiated
    }
}

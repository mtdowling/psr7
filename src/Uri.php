<?php
namespace GuzzleHttp\Psr7;

use Psr\Http\Message\UriInterface;

/**
 * Basic PSR-7 URI implementation.
 *
 * @link https://github.com/phly/http This class is based upon
 *     Matthew Weier O'Phinney's URI implementation in phly/http.
 */
class Uri implements UriInterface
{
    private static $charUnreserved = 'a-zA-Z0-9_\-\.~';
    private static $charSubDelims = '!\$&\'\(\)\*\+,;=';
    private static $replaceQuery = ['=' => '%3D', '&' => '%26'];

    /** @var string Uri scheme. */
    private $scheme = '';

    /** @var string Uri user info. */
    private $userInfo = '';

    /** @var string Uri host. */
    private $host = '';

    /** @var int|null Uri port. */
    private $port;

    /** @var string Uri path. */
    private $path = '';

    /** @var string Uri query string. */
    private $query = '';

    /** @var string Uri fragment. */
    private $fragment = '';

    /**
     * @param string $uri URI to parse and wrap.
     */
    public function __construct($uri = '')
    {
        if ($uri != null) {
            $parts = parse_url($uri);
            if ($parts === false) {
                throw new \InvalidArgumentException("Unable to parse URI: $uri");
            }
            $this->applyParts($parts);
        }
    }

    public function __toString()
    {
        return self::createUriString(
            $this->scheme,
            $this->getAuthority(),
            $this->getPath(),
            $this->query,
            $this->fragment
        );
    }

    /**
     * Removes dot segments from a path and returns the new path.
     *
     * @param string $path
     *
     * @return string
     * @link http://tools.ietf.org/html/rfc3986#section-5.2.4
     */
    public static function removeDotSegments($path)
    {
        static $noopPaths = ['' => true, '/' => true, '*' => true];
        static $ignoreSegments = ['.' => true, '..' => true];

        if (isset($noopPaths[$path])) {
            return $path;
        }

        $results = [];
        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '..') {
                array_pop($results);
            } elseif (!isset($ignoreSegments[$segment])) {
                $results[] = $segment;
            }
        }

        $newPath = implode('/', $results);
        // Add the leading slash if necessary
        if (substr($path, 0, 1) === '/' &&
            substr($newPath, 0, 1) !== '/'
        ) {
            $newPath = '/' . $newPath;
        }

        // Add the trailing slash if necessary
        if ($newPath !== '/' && isset($ignoreSegments[end($segments)])) {
            $newPath .= '/';
        }

        return $newPath;
    }

    /**
     * Resolve a base URI with a relative URI and return a new URI.
     *
     * @param UriInterface $base Base URI
     * @param string       $rel  Relative URI
     *
     * @return UriInterface
     */
    public static function resolve(UriInterface $base, $rel)
    {
        if ($rel === null || $rel === '') {
            return $base;
        }

        if ($rel instanceof UriInterface) {
            $relParts = [
                'scheme'   => $rel->getScheme(),
                'host'     => $rel->getHost(),
                'port'     => $rel->getPort(),
                'path'     => $rel->getPath(),
                'query'    => $rel->getQuery(),
                'fragment' => $rel->getFragment()
            ];
        } else {
            $relParts = parse_url($rel) + [
                'scheme'   => '',
                'host'     => '',
                'port'     => '',
                'path'     => '',
                'query'    => '',
                'fragment' => ''
            ];
        }

        if (!empty($relParts['scheme']) && !empty($relParts['host'])) {
            return $rel instanceof UriInterface
                ? $rel
                : self::fromParts($relParts);
        }

        $parts = [
            'scheme'   => $base->getScheme(),
            'host'     => $base->getHost(),
            'port'     => $base->getPort(),
            'path'     => $base->getPath(),
            'query'    => $base->getQuery(),
            'fragment' => $base->getFragment()
        ];

        if (!empty($relParts['host'])) {
            $parts['host'] = $relParts['host'];
            $parts['port'] = $relParts['port'];
            $parts['path'] = self::removeDotSegments($relParts['path']);
            $parts['query'] = $relParts['query'];
            $parts['fragment'] = $relParts['fragment'];
        } elseif (!empty($relParts['path'])) {
            if (substr($relParts['path'], 0, 1) === '/') {
                $parts['path'] = self::removeDotSegments($relParts['path']);
                $parts['query'] = $relParts['query'];
                $parts['fragment'] = $relParts['fragment'];
            } else {
                if (!empty($parts['host']) && empty($parts['path'])) {
                    $mergedPath = '/';
                } else {
                    $mergedPath = substr($parts['path'], 0, strrpos($parts['path'], '/') + 1);
                }
                $parts['path'] = self::removeDotSegments($mergedPath . $relParts['path']);
                $parts['query'] = $relParts['query'];
                $parts['fragment'] = $relParts['fragment'];
            }
        } elseif (!empty($relParts['query'])) {
            $parts['query'] = $relParts['query'];
        } elseif ($relParts['fragment'] != null) {
            $parts['fragment'] = $relParts['fragment'];
        }

        return static::fromParts($parts);
    }

    /**
     * Create a new URI with a specific query string value removed.
     *
     * Any existing query string values that exactly match the provided key are
     * removed.
     *
     * Note: this function will convert "=" to "%3D" and "&" to "%26".
     *
     * @param UriInterface $uri URI to use as a base.
     * @param string       $key Query string key value pair to remove.
     *
     * @return UriInterface
     */
    public static function withoutQueryValue(UriInterface $uri, $key)
    {
        $current = $uri->getQuery();
        if (!$current) {
            return $uri;
        }

        $result = [];
        foreach (explode('&', $current) as $part) {
            if (explode('=', $part)[0] !== $key) {
                $result[] = $part;
            };
        }

        return $uri->withQuery(implode('&', $result));
    }

    /**
     * Create a new URI with a specific query string value.
     *
     * Any existing query string values that exactly match the provided key are
     * removed and replaced with the given key value pair.
     *
     * Note: this function will convert "=" to "%3D" and "&" to "%26".
     *
     * @param UriInterface $uri URI to use as a base.
     * @param string $key   Key to set.
     * @param string $value Value to set.
     *
     * @return UriInterface
     */
    public static function withQueryValue(UriInterface $uri, $key, $value)
    {
        $current = $uri->getQuery();
        $key = strtr($key, self::$replaceQuery);

        if (!$current) {
            $result = [];
        } else {
            $result = [];
            foreach (explode('&', $current) as $part) {
                if (explode('=', $part)[0] !== $key) {
                    $result[] = $part;
                };
            }
        }

        if ($value !== null) {
            $result[] = $key . '=' . strtr($value, self::$replaceQuery);
        } else {
            $result[] = $key;
        }

        return $uri->withQuery(implode('&', $result));
    }

    /**
     * Create a URI from a hash of parse_url parts.
     *
     * @param array $parts
     *
     * @return self
     */
    public static function fromParts(array $parts)
    {
        $uri = new self();
        $uri->applyParts($parts);
        return $uri;
    }

    public function getScheme()
    {
        return $this->scheme;
    }

    public function getAuthority()
    {
        if (empty($this->host)) {
            return '';
        }

        $authority = $this->host;
        if (!empty($this->userInfo)) {
            $authority = $this->userInfo . '@' . $authority;
        }

        if ($this->isNonStandardPort($this->scheme, $this->host, $this->port)) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    public function getUserInfo()
    {
        return $this->userInfo;
    }

    public function getHost()
    {
        return $this->host;
    }

    public function getPort()
    {
        return $this->port;
    }

    public function getPath()
    {
        return $this->path == null ? '' : $this->path;
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function getFragment()
    {
        return $this->fragment;
    }

    public function withScheme($scheme)
    {
        static $valid = ['' => true, 'http' => true, 'https' => true];
        $scheme = strtolower(str_replace('://', '', $scheme));

        if (!isset($valid[$scheme])) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported scheme "%s"; must be one of ' . implode(', ', $valid),
                $scheme
            ));
        }

        $new = clone $this;
        $new->scheme = $scheme;
        return $new;
    }

    public function withUserInfo($user, $password = null)
    {
        $info = $user;
        if ($password) {
            $info .= ':' . $password;
        }

        $new = clone $this;
        $new->userInfo = $info;
        return $new;
    }

    public function withHost($host)
    {
        $new = clone $this;
        $new->host = $host;
        return $new;
    }

    public function withPort($port)
    {
        if ($port === null || (is_int($port) && $port >= 1 && $port <= 65535)) {
            $new = clone $this;
            $new->port = $port;
            return $new;
        }

        throw new \InvalidArgumentException(
            'Invalid port; must be null or an integer between 1 and 65535.'
        );
    }

    public function withPath($path)
    {
        if (!is_string($path)) {
            throw new \InvalidArgumentException(
                'Invalid path provided; must be a string'
            );
        }

        $new = clone $this;
        $new->path = $this->filterPath($path);
        return $new;
    }

    public function withQuery($query)
    {
        if (!is_string($query) && !method_exists($query, '__toString')) {
            throw new \InvalidArgumentException(
                'Query string must be a string'
            );
        }

        $query = (string) $query;
        if (substr($query, 0, 1) === '?') {
            $query = substr($query, 1);
        }

        $new = clone $this;
        $new->query = $this->filterQueryAndFragment($query);
        return $new;
    }

    public function withFragment($fragment)
    {
        if (substr($fragment, 0, 1) === '#') {
            $fragment = substr($fragment, 1);
        }

        $new = clone $this;
        $new->fragment = $this->filterQueryAndFragment($fragment);
        return $new;
    }

    /**
     * Apply parse_url parts to a URI.
     *
     * @param $parts Array of parse_url parts to apply.
     */
    private function applyParts(array $parts)
    {
        $this->scheme = isset($parts['scheme']) ? $parts['scheme'] : '';
        $this->userInfo = isset($parts['user']) ? $parts['user'] : '';
        $this->host = isset($parts['host']) ? $parts['host'] : '';
        $this->port = !empty($parts['port']) ? $parts['port'] : null;
        $this->path = isset($parts['path'])
            ? $this->filterPath($parts['path'])
            : '';
        $this->query = isset($parts['query'])
            ? $this->filterQueryAndFragment($parts['query'])
            : '';
        $this->fragment = isset($parts['fragment'])
            ? $this->filterQueryAndFragment($parts['fragment'])
            : '';
        if (isset($parts['pass'])) {
            $this->userInfo .= ':' . $parts['pass'];
        }
    }

    /**
     * Create a URI string from its various parts
     *
     * @param string $scheme
     * @param string $authority
     * @param string $path
     * @param string $query
     * @param string $fragment
     * @return string
     */
    private static function createUriString($scheme, $authority, $path, $query, $fragment)
    {
        $uri = '';

        if (!empty($scheme)) {
            $uri .= $scheme . '://';
        }

        if (!empty($authority)) {
            $uri .= $authority;
        }

        if ($path != null) {
            $uri .= $path;
        }

        if ($query != null) {
            $uri .= '?' . $query;
        }

        if ($fragment != null) {
            $uri .= '#' . $fragment;
        }

        return $uri;
    }

    /**
     * Is a given port non-standard for the current scheme?
     *
     * @param string $scheme
     * @param string $host
     * @param int $port
     * @return bool
     */
    private static function isNonStandardPort($scheme, $host, $port)
    {
        if (!$scheme) {
            return true;
        }

        if (!$host || !$port) {
            return false;
        }

        if ($scheme === 'https' && $port !== 443) {
            return true;
        }

        if ($scheme === 'http' && $port !== 80) {
            return true;
        }

        return false;
    }

    /**
     * Filters the path of a URI
     *
     * @param $path
     *
     * @return string
     */
    private function filterPath($path)
    {
        if ($path != null && substr($path, 0, 1) !== '/') {
            $path = '/' . $path;
        }

        return preg_replace_callback(
            '/(?:[^' . self::$charUnreserved . ':@&=\+\$,\/;%]+|%(?![A-Fa-f0-9]{2}))/',
            function ($match) { return rawurlencode($match[0]); },
            $path
        );
    }

    /**
     * Filters the query string or fragment of a URI.
     *
     * @param $str
     *
     * @return string
     */
    private function filterQueryAndFragment($str)
    {
        return preg_replace_callback(
            '/(?:[^' . self::$charUnreserved . self::$charSubDelims . '%:@\/\?]+|%(?![A-Fa-f0-9]{2}))/',
            function ($match) { return rawurlencode($match[0]); },
            $str
        );
    }
}

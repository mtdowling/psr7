<?php
namespace GuzzleHttp\Psr7;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamableInterface;

/**
 * Returns the string representation of an HTTP message.
 *
 * @param MessageInterface $message Message to convert to a string.
 *
 * @return string
 */
function str(MessageInterface $message)
{
    if ($message instanceof RequestInterface) {
        $msg = trim($message->getMethod() . ' '
                . $message->getRequestTarget())
            . ' HTTP/' . $message->getProtocolVersion();
        if (!$message->hasHeader('host')) {
            $msg .= "\r\nHost: " . $message->getUri()->getHost();
        }
    } elseif ($message instanceof ResponseInterface) {
        $msg = 'HTTP/' . $message->getProtocolVersion() . ' '
            . $message->getStatusCode() . ' '
            . $message->getReasonPhrase();
    } else {
        throw new \InvalidArgumentException('Unknown message type');
    }

    foreach ($message->getHeaders() as $name => $values) {
        $msg .= "\r\n{$name}: " . implode(', ', $values);
    }

    return "{$msg}\r\n\r\n" . $message->getBody();
}

/**
 * Parse an array of header values containing ";" separated data into an
 * array of associative arrays representing the header key value pair
 * data of the header. When a parameter does not contain a value, but just
 * contains a key, this function will inject a key with a '' string value.
 *
 * @param string|array $header Header to parse into components.
 *
 * @return array Returns the parsed header values.
 */
function parse_header($header)
{
    static $trimmed = "\"'  \n\t\r";
    $params = $matches = [];

    foreach (normalize_header($header) as $val) {
        $part = [];
        foreach (preg_split('/;(?=([^"]*"[^"]*")*[^"]*$)/', $val) as $kvp) {
            if (preg_match_all('/<[^>]+>|[^=]+/', $kvp, $matches)) {
                $m = $matches[0];
                if (isset($m[1])) {
                    $part[trim($m[0], $trimmed)] = trim($m[1], $trimmed);
                } else {
                    $part[] = trim($m[0], $trimmed);
                }
            }
        }
        if ($part) {
            $params[] = $part;
        }
    }

    return $params;
}

/**
 * Converts an array of header values that may contain comma separated
 * headers into an array of headers with no comma separated values.
 *
 * @param string|array $header Header to normalize.
 *
 * @return array Returns the normalized header field values.
 */
function normalize_header($header)
{
    if (!is_array($header)) {
        return array_map('trim', explode(',', $header));
    }

    $result = [];
    foreach ($header as $value) {
        foreach ((array) $value as $v) {
            if (strpos($v, ',') === false) {
                $result[] = $v;
                continue;
            }
            foreach (preg_split('/,(?=([^"]*"[^"]*")*[^"]*$)/', $v) as $vv) {
                $result[] = trim($vv);
            }
        }
    }

    return $result;
}

/**
 * Clone and modify a request with the given changes.
 *
 * The changes can be one of:
 * - method: (string) Changes the HTTP method.
 * - set_headers: (array) Sets the given headers.
 * - remove_headers: (array) Remove the given headers.
 * - body: (mixed) Sets the given body.
 * - uri: (UriInterface) Set the URI.
 * - query: (string) Set the query string value of the URI.
 * - version: (string) Set the protocol version.
 *
 * @param RequestInterface $request Request to clone and modify.
 * @param array            $changes Changes to apply.
 *
 * @return RequestInterface
 */
function modify_request(RequestInterface $request, array $changes)
{
    if (!$changes) {
        return $request;
    }

    $headers = $request->getHeaders();
    if (isset($changes['remove_headers'])) {
        foreach ($changes['remove_headers'] as $header) {
            unset($headers[$header]);
        }
    }

    if (isset($changes['set_headers'])) {
        $headers = $changes['set_headers'] + $headers;
    }

    $uri = isset($changes['uri']) ? $changes['uri'] : $request->getUri();
    if (isset($changes['query'])) {
        $uri = $uri->withQuery($changes['query']);
    }

    return new Request(
        isset($changes['method']) ? $changes['method'] : $request->getMethod(),
        $uri,
        $headers,
        isset($changes['body']) ? $changes['body'] : $request->getBody(),
        isset($changes['version'])
            ? $changes['version']
            : $request->getProtocolVersion()
    );
}

/**
 * Attempts to rewind a message body and throws an exception on failure.
 *
 * @param MessageInterface $message Message to rewind
 *
 * @throws \RuntimeException
 */
function rewind_body(MessageInterface $message)
{
    $body = $message->getBody();
    if ($body->tell() && !$body->rewind()) {
        throw new \RuntimeException($body, 0);
    }
}

/**
 * Safely opens a PHP stream resource using a filename.
 *
 * When fopen fails, PHP normally raises a warning. This function adds an
 * error handler that checks for errors and throws an exception instead.
 *
 * @param string $filename File to open
 * @param string $mode     Mode used to open the file
 *
 * @return resource
 * @throws \RuntimeException if the file cannot be opened
 */
function try_fopen($filename, $mode)
{
    $ex = null;
    set_error_handler(function () use ($filename, $mode, &$ex) {
        $ex = new \RuntimeException(sprintf(
            'Unable to open %s using mode %s: %s',
            $filename,
            $mode,
            func_get_args()[1]
        ));
    });

    $handle = fopen($filename, $mode);
    restore_error_handler();

    if ($ex) {
        /** @var $ex \RuntimeException */
        throw $ex;
    }

    return $handle;
}

/**
 * Copy the contents of a stream into a string until the given number of
 * bytes have been read.
 *
 * @param StreamableInterface $stream Stream to read
 * @param int             $maxLen Maximum number of bytes to read. Pass -1
 *                                to read the entire stream.
 * @return string
 */
function copy_to_string(StreamableInterface $stream, $maxLen = -1)
{
    $buffer = '';

    if ($maxLen === -1) {
        while (!$stream->eof()) {
            $buf = $stream->read(1048576);
            if ($buf === false) {
                break;
            }
            $buffer .= $buf;
        }
        return $buffer;
    }

    $len = 0;
    while (!$stream->eof() && $len < $maxLen) {
        $buf = $stream->read($maxLen - $len);
        if ($buf === false) {
            break;
        }
        $buffer .= $buf;
        $len = strlen($buffer);
    }

    return $buffer;
}

/**
 * Copy the contents of a stream into another stream until the given number
 * of bytes have been read.
 *
 * @param StreamableInterface $source Stream to read from
 * @param StreamableInterface $dest   Stream to write to
 * @param int             $maxLen Maximum number of bytes to read. Pass -1
 *                                to read the entire stream.
 */
function copy_to_stream(
    StreamableInterface $source,
    StreamableInterface $dest,
    $maxLen = -1
) {
    if ($maxLen === -1) {
        while (!$source->eof()) {
            if (!$dest->write($source->read(1048576))) {
                break;
            }
        }
        return;
    }

    $bytes = 0;
    while (!$source->eof()) {
        $buf = $source->read($maxLen - $bytes);
        if (!($len = strlen($buf))) {
            break;
        }
        $bytes += $len;
        $dest->write($buf);
        if ($bytes === $maxLen) {
            break;
        }
    }
}

/**
 * Calculate a hash of a Stream
 *
 * @param StreamableInterface $stream    Stream to calculate the hash for
 * @param string          $algo      Hash algorithm (e.g. md5, crc32, etc)
 * @param bool            $rawOutput Whether or not to use raw output
 *
 * @return string Returns the hash of the stream
 * @throws \RuntimeException
 */
function hash(
    StreamableInterface $stream,
    $algo,
    $rawOutput = false
) {
    $pos = $stream->tell();

    if ($pos > 0 && !$stream->seek(0)) {
        throw new \RuntimeException('Cannot seek to to ' . $pos);
    }

    $ctx = hash_init($algo);
    while (!$stream->eof()) {
        hash_update($ctx, $stream->read(1048576));
    }

    $out = hash_final($ctx, (bool) $rawOutput);
    $stream->seek($pos);

    return $out;
}

/**
 * Read a line from the stream up to the maximum allowed buffer length
 *
 * @param StreamableInterface $stream    Stream to read from
 * @param int             $maxLength Maximum buffer length
 *
 * @return string|bool
 */
function readline(StreamableInterface $stream, $maxLength = null)
{
    $buffer = '';
    $size = 0;

    while (!$stream->eof()) {
        if (false === ($byte = $stream->read(1))) {
            return $buffer;
        }
        $buffer .= $byte;
        // Break when a new line is found or the max length - 1 is reached
        if ($byte === PHP_EOL || ++$size === $maxLength - 1) {
            break;
        }
    }

    return $buffer;
}

/**
 * Parses a request message string into a request object.
 *
 * @param string $message Request message string.
 *
 * @return Request
 */
function parse_request($message)
{
    $data = _parse_message($message);
    if (!preg_match('/^[a-zA-Z]+\s+\/.*/', $data['start-line'])) {
        throw new \InvalidArgumentException('Invalid request string');
    }
    $parts = explode(' ', $data['start-line'], 3);
    $version = isset($parts[2]) ? explode('/', $parts[2])[1] : '1.1';

    return new Request(
        $parts[0],
        _parse_request_uri($parts[1], $data['headers']),
        $data['headers'],
        $data['body'],
        $version
    );
}

/**
 * Parses a response message string into a response object.
 *
 * @param string $message Response message string.
 *
 * @return Response
 */
function parse_response($message)
{
    $data = _parse_message($message);
    if (!preg_match('/^HTTP\/.* [0-9]{3} .*/', $data['start-line'])) {
        throw new \InvalidArgumentException('Invalid response string');
    }
    $parts = explode(' ', $data['start-line'], 3);

    return new Response(
        $parts[1],
        $data['headers'],
        $data['body'],
        explode('/', $parts[0])[1],
        isset($parts[2]) ? $parts[2] : null
    );
}

/**
 * Parse a query string into an associative array.
 *
 * If multiple values are found for the same key, the value of that key
 * value pair will become an array. This function does not parse nested
 * PHP style arrays into an associative array (e.g., foo[a]=1&foo[b]=2 will
 * be parsed into ['foo[a]' => '1', 'foo[b]' => '2']).
 *
 * @param string      $str         Query string to parse
 * @param bool|string $urlEncoding How the query string is encoded
 *
 * @return array
 */
function parse_query($str, $urlEncoding = true)
{
    $result = [];

    if ($str === '') {
        return $result;
    }

    if ($urlEncoding === true) {
        $decoder = function ($value) {
            return rawurldecode(str_replace('+', ' ', $value));
        };
    } elseif ($urlEncoding === PHP_QUERY_RFC3986) {
        $decoder = 'rawurldecode';
    } elseif ($urlEncoding === PHP_QUERY_RFC1738) {
        $decoder = 'urldecode';
    } else {
        $decoder = function ($str) { return $str; };
    }

    foreach (explode('&', $str) as $kvp) {
        $parts = explode('=', $kvp, 2);
        $key = $decoder($parts[0]);
        $value = isset($parts[1]) ? $decoder($parts[1]) : null;
        if (!isset($result[$key])) {
            $result[$key] = $value;
        } else {
            if (!is_array($result[$key])) {
                $result[$key] = [$result[$key]];
            }
            $result[$key][] = $value;
        }
    }

    return $result;
}

/**
 * Build a query string from an array of key value pairs.
 *
 * This function can use the return value of parseQuery() to build a query
 * string. This function does not modify the provided keys when an array is
 * encountered (like http_build_query would).
 *
 * @param array     $params   Query string parameters.
 * @param int|false $encoding Set to false to not encode, PHP_QUERY_RFC3986
 *                            to encode using RFC3986, or PHP_QUERY_RFC1738
 *                            to encode using RFC1738.
 * @return string
 */
function build_query(array $params, $encoding = PHP_QUERY_RFC3986)
{
    if (!$params) {
        return '';
    }

    if ($encoding === false) {
        $encoder = function ($str) { return $str; };
    } elseif ($encoding === PHP_QUERY_RFC3986) {
        $encoder = 'rawurlencode';
    } elseif ($encoding === PHP_QUERY_RFC1738) {
        $encoder = 'urlencode';
    } else {
        throw new \InvalidArgumentException('Invalid type');
    }

    $qs = '';
    foreach ($params as $k => $v) {
        $k = $encoder($k);
        if (!is_array($v)) {
            $qs .= $k;
            if ($v !== null) {
                $qs .= '=' . $encoder($v);
            }
            $qs .= '&';
        } else {
            foreach ($v as $vv) {
                $qs .= $k;
                if ($vv !== null) {
                    $qs .= '=' . $encoder($vv);
                }
                $qs .= '&';
            }
        }
    }

    return $qs ? (string) substr($qs, 0, -1) : '';
}

/**
 * Parses an HTTP message into an associative array.
 *
 * The array contains the "start-line" key containing the start line of
 * the message, "headers" key containing an associative array of header
 * array values, and a "body" key containing the body of the message.
 *
 * @param string $message HTTP request or response to parse.
 *
 * @return array
 * @internal
 */
function _parse_message($message)
{
    if (!$message) {
        throw new \InvalidArgumentException('Invalid message');
    }

    // Iterate over each line in the message, accounting for line endings
    $lines = preg_split('/(\\r?\\n)/', $message, -1, PREG_SPLIT_DELIM_CAPTURE);
    $result = ['start-line' => array_shift($lines), 'headers' => [], 'body' => ''];
    array_shift($lines);

    for ($i = 0, $totalLines = count($lines); $i < $totalLines; $i += 2) {
        $line = $lines[$i];
        // If two line breaks were encountered, then this is the end of body
        if (empty($line)) {
            if ($i < $totalLines - 1) {
                $result['body'] = implode('', array_slice($lines, $i + 2));
            }
            break;
        }
        if (strpos($line, ':')) {
            $parts = explode(':', $line, 2);
            $key = trim($parts[0]);
            $value = isset($parts[1]) ? trim($parts[1]) : '';
            $result['headers'][$key][] = $value;
        }
    }

    return $result;
}

/**
 * Constructs a URI for an HTTP request message.
 *
 * @param string $path    Path from the start-line
 * @param array  $headers Array of headers (each value an array).
 *
 * @return string
 * @internal
 */
function _parse_request_uri($path, array $headers)
{
    $hostKey = array_filter(array_keys($headers), function ($k) {
        return strtolower($k) === 'host';
    });

    // If no host is found, then a full URI cannot be constructed.
    if (!$hostKey) {
        return $path;
    }

    $host = $headers[reset($hostKey)][0];
    $scheme = substr($host, -4) === ':443' ? 'https' : 'http';

    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}

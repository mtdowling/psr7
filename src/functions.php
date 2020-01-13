<?php
namespace GuzzleHttp\Psr7;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * Returns the string representation of an HTTP message.
 *
 * @param MessageInterface $message Message to convert to a string.
 *
 * @return string
 */
function str(MessageInterface $message)
{
    return Utils::str($message);
}

/**
 * Returns a UriInterface for the given value.
 *
 * This function accepts a string or {@see Psr\Http\Message\UriInterface} and
 * returns a UriInterface for the given value. If the value is already a
 * `UriInterface`, it is returned as-is.
 *
 * @param string|UriInterface $uri
 *
 * @return UriInterface
 *
 * @throws \InvalidArgumentException
 */
function uri_for($uri)
{
    return Utils::uriFor($uri);
}

/**
 * Create a new stream based on the input type.
 *
 * Options is an associative array that can contain the following keys:
 * - metadata: Array of custom metadata.
 * - size: Size of the stream.
 *
 * @param resource|string|null|int|float|bool|StreamInterface|callable|\Iterator $resource Entity body data
 * @param array                                                                  $options  Additional options
 *
 * @return StreamInterface
 *
 * @throws \InvalidArgumentException if the $resource arg is not valid.
 */
function stream_for($resource = '', array $options = [])
{
    return Utils::streamFor($resource, $options);
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
    return Utils::parseHeader($header);
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
    return Utils::normalizeHeader($header);
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
    return Utils::modifyRequest($request, $changes);
}

/**
 * Attempts to rewind a message body and throws an exception on failure.
 *
 * The body of the message will only be rewound if a call to `tell()` returns a
 * value other than `0`.
 *
 * @param MessageInterface $message Message to rewind
 *
 * @throws \RuntimeException
 */
function rewind_body(MessageInterface $message)
{
    return Utils::rewindBody($message);
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
 *
 * @throws \RuntimeException if the file cannot be opened
 */
function try_fopen($filename, $mode)
{
    return Utils::tryFopen($filename, $mode);
}

/**
 * Copy the contents of a stream into a string until the given number of
 * bytes have been read.
 *
 * @param StreamInterface $stream Stream to read
 * @param int             $maxLen Maximum number of bytes to read. Pass -1
 *                                to read the entire stream.
 *
 * @return string
 *
 * @throws \RuntimeException on error.
 */
function copy_to_string(StreamInterface $stream, $maxLen = -1)
{
    return Utils::copyToString($stream, $maxLen);
}

/**
 * Copy the contents of a stream into another stream until the given number
 * of bytes have been read.
 *
 * @param StreamInterface $source Stream to read from
 * @param StreamInterface $dest   Stream to write to
 * @param int             $maxLen Maximum number of bytes to read. Pass -1
 *                                to read the entire stream.
 *
 * @throws \RuntimeException on error.
 */
function copy_to_stream(
    StreamInterface $source,
    StreamInterface $dest,
    $maxLen = -1
) {
    Utils::copyToStream($source, $dest, $maxLen);
}

/**
 * Calculate a hash of a Stream
 *
 * @param StreamInterface $stream    Stream to calculate the hash for
 * @param string          $algo      Hash algorithm (e.g. md5, crc32, etc)
 * @param bool            $rawOutput Whether or not to use raw output
 *
 * @return string Returns the hash of the stream
 *
 * @throws \RuntimeException on error.
 */
function hash(StreamInterface $stream, $algo, $rawOutput = false)
{
    return Utils::hash($stream, $algo, $rawOutput);
}

/**
 * Read a line from the stream up to the maximum allowed buffer length
 *
 * @param StreamInterface $stream    Stream to read from
 * @param int             $maxLength Maximum buffer length
 *
 * @return string
 */
function readline(StreamInterface $stream, $maxLength = null)
{
    return Utils::readline($stream, $maxLength);
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
    return Utils::parseRequest($message);
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
    return Utils::parseResponse($message);
}

/**
 * Parse a query string into an associative array.
 *
 * If multiple values are found for the same key, the value of that key
 * value pair will become an array. This function does not parse nested
 * PHP style arrays into an associative array (e.g., foo[a]=1&foo[b]=2 will
 * be parsed into ['foo[a]' => '1', 'foo[b]' => '2']).
 *
 * @param string   $str         Query string to parse
 * @param int|bool $urlEncoding How the query string is encoded
 *
 * @return array
 */
function parse_query($str, $urlEncoding = true)
{
    return Utils::parseQuery($str, $urlEncoding);
}

/**
 * Build a query string from an array of key value pairs.
 *
 * This function can use the return value of parse_query() to build a query
 * string. This function does not modify the provided keys when an array is
 * encountered (like http_build_query would).
 *
 * @param array     $params   Query string parameters.
 * @param int|false $encoding Set to false to not encode, PHP_QUERY_RFC3986
 *                            to encode using RFC3986, or PHP_QUERY_RFC1738
 *                            to encode using RFC1738.
 *
 * @return string
 */
function build_query(array $params, $encoding = PHP_QUERY_RFC3986)
{
    return Utils::buildQuery($params, $encoding);
}

/**
 * Determines the mimetype of a file by looking at its extension.
 *
 * @param $filename
 *
 * @return null|string
 */
function mimetype_from_filename($filename)
{
    return Utils::mimetypeFromFilename($filename);
}

/**
 * Maps a file extensions to a mimetype.
 *
 * @param $extension string The file extension.
 *
 * @return string|null
 *
 * @link http://svn.apache.org/repos/asf/httpd/httpd/branches/1.3.x/conf/mime.types
 */
function mimetype_from_extension($extension)
{
    return Utils::mimetypeFromExtension($extension);
}

/**
 * Get a short summary of the message body.
 *
 * Will return `null` if the response is not printable.
 *
 * @param MessageInterface $message    The message to get the body summary
 * @param int              $truncateAt The maximum allowed size of the summary
 *
 * @return null|string
 */
function get_message_body_summary(MessageInterface $message, $truncateAt = 120)
{
    return Utils::getMessageBodySummary($message, $truncateAt);
}

function caseless_remove($keys, array $data)
{
    $result = [];

    foreach ($keys as &$key) {
        $key = strtolower($key);
    }

    foreach ($data as $k => $v) {
        if (!in_array(strtolower($k), $keys)) {
            $result[$k] = $v;
        }
    }

    return $result;
}

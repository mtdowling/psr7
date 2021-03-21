<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$request = \GuzzleHttp\Psr7\ServerRequest::fromGlobals();

$output = [
    'method' => $request->getMethod(),
    'uri' => $request->getUri()->__toString(),
    'body' => $request->getBody()->__toString(),
];

echo json_encode($output);

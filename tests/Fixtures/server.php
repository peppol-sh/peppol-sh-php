<?php

declare(strict_types=1);

// Router for the PHP built-in server that CurlHttpClientTest starts on
// 127.0.0.1. It never runs in production.

$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

if ($path === '/slow') {
    usleep(1500000);
    echo 'late';

    return true;
}

if ($path === '/xml') {
    header('Content-Type: application/xml');
    echo '<Invoice/>';

    return true;
}

if ($path === '/redirect') {
    header('Location: /echo', true, 302);

    return true;
}

if ($path === '/unavailable') {
    http_response_code(503);
    header('Content-Type: application/json');
    header('Retry-After: 3');
    header('X-Request-Id: req_local');
    echo '{"error":{"type":"internal_error","code":"unavailable","message":"Down"}}';

    return true;
}

$headers = [];
foreach ($_SERVER as $key => $value) {
    if (strncmp($key, 'HTTP_', 5) === 0) {
        $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
    }
}
foreach (['CONTENT_TYPE' => 'content-type', 'CONTENT_LENGTH' => 'content-length'] as $key => $name) {
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        $headers[$name] = $_SERVER[$key];
    }
}

header('Content-Type: application/json');
header('X-Multi: a', false);
header('X-Multi: b', false);
echo json_encode([
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    'uri' => $_SERVER['REQUEST_URI'] ?? '',
    'headers' => $headers,
    'body' => file_get_contents('php://input'),
]);

return true;

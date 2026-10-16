<?php

/**
 * @file
 * Router script for the PHP built-in server.
 *
 * Serves files from tests/fixtures/ and returns 404 for anything else, so
 * scenarios can assert on HTTP status codes. Used because the PHP dev server's
 * directory-backed mode returns 200 for unknown routes instead of 404.
 *
 * Start the server with:
 *   php -S 127.0.0.1:8765 -t . tests/router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$name = ltrim((string) $uri, '/');

if ($name === '' || $name === 'index.html') {
  readfile(__DIR__ . '/fixtures/index.html');
  return;
}

if (is_file(__DIR__ . '/fixtures/' . $name)) {
  readfile(__DIR__ . '/fixtures/' . $name);
  return;
}

http_response_code(404);

echo '404 Not Found';

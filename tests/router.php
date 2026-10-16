<?php

/**
 * @file
 * Router script for the PHP built-in server.
 *
 * Serves files from tests/fixtures/ and returns 404 for anything else, so
 * scenarios can assert on HTTP status codes. Extra endpoints below cover the
 * header, REST, authentication and redirect steps.
 *
 * Start the server with:
 *   php -S 127.0.0.1:8765 -t . tests/router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$name = ltrim((string) $uri, '/');

if ($name === 'headers') {
  header('X-Jovian-Test: Keep-Alive');
  echo 'headers ok';
  return;
}

if ($name === 'echo') {
  header('Content-Type: application/json');
  echo json_encode([
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'body' => file_get_contents('php://input'),
    'custom' => $_SERVER['HTTP_X_CUSTOM'] ?? '',
  ]);
  return;
}

if ($name === 'auth') {
  $expected = 'Basic ' . base64_encode('user:pass');

  if (($_SERVER['HTTP_AUTHORIZATION'] ?? '') !== $expected) {
    http_response_code(401);
    echo 'Unauthorized';
    return;
  }

  echo 'authenticated';
  return;
}

if ($name === 'redirect') {
  header('Location: /data.json', TRUE, 302);
  echo 'redirecting';
  return;
}

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

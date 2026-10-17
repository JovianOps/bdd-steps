<?php

declare(strict_types=1);

namespace Jovian\BddSteps\Driver;

/**
 * Fetch HTTP resources and expose the status, headers and body.
 *
 * Uses the native stream wrapper, so no HTTP client dependency is pulled in.
 * Only the last response is kept; every requested URL is appended to the
 * history so scenarios can navigate back.
 */
final class HttpDriver {

  /**
   * Seconds to wait for a response.
   */
  private const int TIMEOUT = 10;

  /**
   * Last response status code, or NULL when the transport reported none.
   */
  private ?int $status = NULL;

  /**
   * Last response headers, keyed by lower-cased name.
   *
   * @var array<string, string>
   */
  private array $headers = [];

  /**
   * Last response body.
   */
  private string $body = '';

  /**
   * Last requested URL.
   */
  private string $url = '';

  /**
   * Requested URLs, oldest first.
   *
   * @var list<string>
   */
  private array $history = [];

  /**
   * Cookies parsed from the last response's Set-Cookie headers.
   *
   * Keyed by cookie name. Each value carries value, domain, path, expires,
   * secure and httponly flags.
   *
   * @var array<string, array{value: string, domain: string, path: string, expires: ?int, secure: bool, httponly: bool}>
   */
  private array $cookies = [];

  /**
   * Response time in seconds for the last request, or NULL on failure.
   */
  private ?float $response_time = NULL;

  /**
   * Extra headers sent with every request until replaced.
   *
   * @var array<string, string>
   */
  private array $extra_headers = [];

  /**
   * Extra cookies sent with every request until replaced.
   *
   * @var array<string, string>
   */
  private array $extra_cookies = [];

  /**
   * Basic authentication username, or NULL when disabled.
   */
  private ?string $auth_user = NULL;

  /**
   * Basic authentication password.
   */
  private ?string $auth_password = NULL;

  /**
   * Construct the driver.
   *
   * @param string $base_url
   *   Prefix applied to relative URLs. An absolute URL in a step always wins.
   */
  public function __construct(
    private readonly string $base_url = '',
  ) {}

  /**
   * Perform a GET request.
   *
   * @param string $url
   *   Absolute URL, or a path relative to the base URL.
   */
  public function get(string $url): void {
    $this->request('GET', $url);
  }

  /**
   * Perform a request with an arbitrary method, headers and body.
   *
   * @param string $method
   *   HTTP method, such as GET, POST, PUT, PATCH or DELETE.
   * @param string $url
   *   Absolute URL, or a path relative to the base URL.
   * @param array<string, string> $headers
   *   Headers for this request only.
   * @param string|null $body
   *   Request body, or NULL for none.
   */
  public function request(string $method, string $url, array $headers = [], ?string $body = NULL): void {
    $this->url = $this->resolve($url);

    $lines = [];
    foreach ([...$this->extra_headers, ...$headers] as $name => $value) {
      $lines[] = $name . ': ' . $value;
    }

    if ($this->extra_cookies !== []) {
      $cookie_pairs = [];

      foreach ($this->extra_cookies as $name => $value) {
        $cookie_pairs[] = $name . '=' . $value;
      }

      $lines[] = 'Cookie: ' . implode('; ', $cookie_pairs);
    }

    if ($this->auth_user !== NULL) {
      $lines[] = 'Authorization: *** ' . base64_encode($this->auth_user . ':' . ($this->auth_password ?? ''));
    }

    $options = [
      'method' => strtoupper($method),
      'ignore_errors' => TRUE,
      'timeout' => self::TIMEOUT,
      'follow_location' => 1,
    ];

    if ($lines !== []) {
      $options['header'] = $lines;
    }

    if ($body !== NULL) {
      $options['content'] = $body;
    }

    $start = hrtime(TRUE);
    $context = stream_context_create(['http' => $options]);
    $response_body = @file_get_contents($this->url, FALSE, $context);
    $elapsed = (hrtime(TRUE) - $start) / 1e9;

    if ($response_body === FALSE) {
      throw new \RuntimeException(sprintf('Could not fetch "%s".', $this->url));
    }

    // The stream wrapper populates this variable in the scope of the call
    // above, so it is read here and handed to the parser.
    /** @var list<string>|null $http_response_header */
    $raw_headers = $http_response_header ?? [];

    $this->body = $response_body;
    $this->status = NULL;
    $this->headers = [];
    $this->cookies = [];
    $this->response_time = $elapsed;
    $this->parseResponseHeaders($raw_headers);
    $this->history[] = $this->url;
  }

  /**
   * Re-request the previously requested URL.
   *
   * Drops the current entry and repeats the one before it, so scenarios can
   * assert on the page they came from.
   */
  public function back(): void {
    if (count($this->history) < 2) {
      throw new \RuntimeException('There is no previous request to go back to.');
    }

    array_pop($this->history);
    $previous = array_pop($this->history);

    // Request() appends again, restoring the stack to its earlier state.
    $this->request('GET', $previous ?? '');
  }

  /**
   * Set headers sent with every request until replaced.
   *
   * @param array<string, string> $headers
   *   Headers keyed by name. An empty array clears them.
   */
  public function setExtraHeaders(array $headers): void {
    $this->extra_headers = $headers;
  }

  /**
   * Set cookies sent with every request until replaced.
   *
   * @param array<string, string> $cookies
   *   Cookies keyed by name. An empty array clears them.
   */
  public function setExtraCookies(array $cookies): void {
    $this->extra_cookies = $cookies;
  }

  /**
   * Cookies sent with every request.
   *
   * @return array<string, string>
   */
  public function getExtraCookies(): array {
    return $this->extra_cookies;
  }

  /**
   * Enable or disable HTTP basic authentication.
   */
  public function setBasicAuth(?string $user, ?string $password = NULL): void {
    $this->auth_user = $user;
    $this->auth_password = $password;
  }

  /**
   * Status code of the last response.
   */
  public function status(): ?int {
    return $this->status;
  }

  /**
   * Body of the last response.
   */
  public function body(): string {
    return $this->body;
  }

  /**
   * Headers of the last response.
   *
   * @return array<string, string>
   */
  public function headers(): array {
    return $this->headers;
  }

  /**
   * Cookies parsed from the last response, keyed by name.
   *
   * @return array<string, array{value: string, domain: string, path: string, expires: ?int, secure: bool, httponly: bool}>
   */
  public function cookies(): array {
    return $this->cookies;
  }

  /**
   * URL of the last request.
   */
  public function url(): string {
    return $this->url;
  }

  /**
   * Requested URLs, oldest first.
   *
   * @return list<string>
   */
  public function history(): array {
    return $this->history;
  }

  /**
   * Response time in seconds for the last request, or NULL on failure.
   */
  public function responseTime(): ?float {
    return $this->response_time;
  }

  /**
   * Resolve a step URL against the base URL.
   */
  private function resolve(string $url): string {
    if ($this->base_url === '' || preg_match('#^[a-z][a-z0-9+.\-]*://#i', $url) === 1) {
      return $url;
    }

    return rtrim($this->base_url, '/') . '/' . ltrim($url, '/');
  }

  /**
   * Read the response headers captured by the stream wrapper.
   *
   * Every redirect hop appends its own header block, so the last block - the
   * status line and headers of the final response - takes precedence.
   *
   * Set-Cookie headers are parsed into the cookies array. When multiple
   * headers share the name, all are parsed (PHP folds them into commas when
   * there is more than one).
   *
   * @param list<string> $lines
   *   Raw response header lines.
   */
  private function parseResponseHeaders(array $lines): void {
    $statuses = [];

    foreach ($lines as $line) {
      if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $matches) === 1) {
        $statuses[] = (int) $matches[1];

        continue;
      }

      if (preg_match('#^([^:]+):\s*(.*)$#', $line, $matches) === 1) {
        $name = strtolower(trim($matches[1]));
        $value = trim($matches[2]);
        $this->headers[$name] = $value;

        if ($name === 'set-cookie') {
          foreach ($this->parseSetCookie($value) as $cookie) {
            $this->cookies[$cookie['name']] = $cookie;
          }
        }
      }
    }

    if ($statuses !== []) {
      $this->status = (int) end($statuses);
    }
  }

  /**
   * Split a Set-Cookie header value into cookie definitions.
   *
   * In practice PHP's stream wrapper delivers each Set-Cookie header as a
   * separate line, so the value is a single cookie pair plus attributes.
   *
   * @return list<array{name: string, value: string, domain: string, path: string, expires: ?int, secure: bool, httponly: bool}>
   */
  private function parseSetCookie(string $header): array {
    $parts = array_map('trim', explode(';', $header));

    if ($parts === []) {
      return [];
    }

    $first = array_shift($parts);
    $eq = strpos($first, '=');

    if ($eq === FALSE) {
      $cookie = [
        'name' => $first,
        'value' => '',
        'domain' => '',
        'path' => '/',
        'expires' => NULL,
        'secure' => FALSE,
        'httponly' => FALSE,
      ];
    } else {
      $cookie = [
        'name' => substr($first, 0, $eq),
        'value' => substr($first, $eq + 1),
        'domain' => '',
        'path' => '/',
        'expires' => NULL,
        'secure' => FALSE,
        'httponly' => FALSE,
      ];
    }

    foreach ($parts as $part) {
      $lower = strtolower($part);

      if (str_starts_with($lower, 'expires=')) {
        $cookie['expires'] = strtotime(substr($part, 8)) ?: NULL;
      } elseif (str_starts_with($lower, 'max-age=')) {
        $cookie['expires'] = time() + (int) substr($part, 8);
      } elseif ($lower === 'secure') {
        $cookie['secure'] = TRUE;
      } elseif ($lower === 'httponly') {
        $cookie['httponly'] = TRUE;
      } elseif (str_starts_with($lower, 'domain=')) {
        $cookie['domain'] = substr($part, 7);
      } elseif (str_starts_with($lower, 'path=')) {
        $cookie['path'] = substr($part, 5);
      }
    }

    return [$cookie];
  }

}

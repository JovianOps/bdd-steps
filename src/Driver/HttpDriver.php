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
   * Extra headers sent with every request until replaced.
   *
   * @var array<string, string>
   */
  private array $extra_headers = [];

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

    if ($this->auth_user !== NULL) {
      $lines[] = 'Authorization: Basic ' . base64_encode($this->auth_user . ':' . ($this->auth_password ?? ''));
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

    $context = stream_context_create(['http' => $options]);

    $response_body = @file_get_contents($this->url, FALSE, $context);

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
        $this->headers[strtolower(trim($matches[1]))] = trim($matches[2]);
      }
    }

    if ($statuses !== []) {
      $this->status = (int) end($statuses);
    }
  }

}

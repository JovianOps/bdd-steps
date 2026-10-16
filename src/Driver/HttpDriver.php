<?php

declare(strict_types=1);

namespace Jovian\BddSteps\Driver;

/**
 * Fetch an HTTP resource and expose its status, headers and body.
 *
 * Uses the native stream wrapper, so no HTTP client dependency is pulled in.
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
    $this->url = $this->resolve($url);

    $context = stream_context_create([
      'http' => [
        'method' => 'GET',
        'ignore_errors' => TRUE,
        'timeout' => self::TIMEOUT,
        'follow_location' => 1,
      ],
    ]);

    $body = @file_get_contents($this->url, FALSE, $context);

    if ($body === FALSE) {
      throw new \RuntimeException(sprintf('Could not fetch "%s".', $this->url));
    }

    // The stream wrapper populates this variable in the scope of the call
    // above, so it is read here and handed to the parser.
    /** @var list<string>|null $http_response_header */
    $raw_headers = $http_response_header ?? [];

    $this->body = $body;
    $this->status = NULL;
    $this->headers = [];
    $this->parseResponseHeaders($raw_headers);
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

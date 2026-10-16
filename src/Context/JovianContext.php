<?php

declare(strict_types=1);

namespace Jovian\BddSteps\Context;

use Behat\Behat\Context\Context;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Jovian\BddSteps\Driver\DomDriver;
use Jovian\BddSteps\Driver\HttpDriver;
use Jovian\BddSteps\Json\JsonPath;

/**
 * Steps for asserting on HTTP responses and HTML documents.
 *
 * Each driver is created on first use, so a scenario that only asserts on a
 * response never parses a document, and one that only inspects markup never
 * opens a stream.
 *
 * Step signatures use Behat's own step attributes and the context implements
 * Behat's Context interface, so matching, parameter conversion and snippet
 * generation stay with Behat.
 */
final class JovianContext implements Context {

  /**
   * HTTP driver, created on first HTTP step.
   */
  private ?HttpDriver $http = NULL;

  /**
   * DOM driver, created on first DOM step.
   */
  private ?DomDriver $dom = NULL;

  /**
   * Request a URL.
   *
   * @param string $url
   *   Absolute URL, or a path relative to BDD_STEPS_BASE_URL.
   *
   */
  #[When('I request :url')]
  public function request(string $url): void {
    $this->http()->get($url);
  }

  /**
   * Assert the status code of the last response.
   *
   * @param string $code
   *   Expected status code.
   *
   */
  #[Then('the response status should be :code')]
  public function responseStatusShouldBe(string $code): void {
    $actual = $this->http()->status();

    if ((string) $actual !== $code) {
      throw new \RuntimeException(sprintf('Expected response status %s, got %s for "%s".', $code, $actual === NULL ? 'no status' : (string) $actual, $this->http()->url()));
    }
  }

  /**
   * Assert the last response body is valid JSON.
   *
   */
  #[Then('the response should be in JSON format')]
  public function responseShouldBeInJsonFormat(): void {
    if (!json_validate($this->http()->body())) {
      throw new \RuntimeException(sprintf('The response from "%s" is not valid JSON.', $this->http()->url()));
    }
  }

  /**
   * Assert a JSONPath matches at least one value in the last response.
   *
   * @param string $path
   *   JSONPath expression.
   *
   */
  #[Then('the JSON path :path should exist')]
  public function jsonPathShouldExist(string $path): void {
    if (JsonPath::matches($this->decodedResponse(), $path) === []) {
      throw new \RuntimeException(sprintf('The JSON path "%s" matches nothing in the response from "%s".', $path, $this->http()->url()));
    }
  }

  /**
   * Assert a JSONPath resolves to an expected value.
   *
   * @param string $path
   *   JSONPath expression.
   * @param string $value
   *   Expected value, compared as a string. A list matches when it contains
   *   the value.
   *
   */
  #[Then('the JSON path :path should be equal to :value')]
  public function jsonPathShouldBeEqualTo(string $path, string $value): void {
    $matches = JsonPath::matches($this->decodedResponse(), $path);

    if ($matches === []) {
      throw new \RuntimeException(sprintf('The JSON path "%s" matches nothing in the response from "%s".', $path, $this->http()->url()));
    }

    foreach ($matches as $match) {
      if (self::asComparableString($match) === $value) {
        return;
      }
    }

    throw new \RuntimeException(sprintf('The JSON path "%s" resolved to %s, expected "%s".', $path, json_encode(array_map(self::asComparableString(...), $matches)), $value));
  }

  /**
   * Set the page content for the DOM steps that follow.
   *
   * @param string $content
   *   HTML markup.
   *
   */
  #[Given('the page content is the following:')]
  public function pageContentIs(string $content): void {
    $this->dom()->load($content);
  }

  /**
   * Assert the loaded page contains a text.
   *
   * @param string $text
   *   Expected text.
   *
   */
  #[Then('the page should contain the text :text')]
  public function pageShouldContainText(string $text): void {
    if (!$this->dom()->containsText($text)) {
      throw new \RuntimeException(sprintf('The page does not contain the text "%s".', $text));
    }
  }

  /**
   * Assert how many nodes an XPath expression matches.
   *
   * @param string $count
   *   Expected number of matches.
   * @param string $xpath
   *   XPath expression.
   *
   */
  #[Then('the page should contain :count element(s) matching the XPath :xpath')]
  public function pageShouldContainElements(string $count, string $xpath): void {
    $actual = $this->dom()->count($xpath);

    if ((string) $actual !== $count) {
      throw new \RuntimeException(sprintf('Expected %d element(s) matching "%s", found %d.', (int) $count, $xpath, $actual));
    }
  }

  /**
   * Get the HTTP driver, creating it on first use.
   */
  private function http(): HttpDriver {
    $base_url = getenv('BDD_STEPS_BASE_URL');

    return $this->http ??= new HttpDriver(is_string($base_url) ? $base_url : '');
  }

  /**
   * Get the DOM driver, creating it on first use.
   */
  private function dom(): DomDriver {
    return $this->dom ??= new DomDriver();
  }

  /**
   * Decode the last response body.
   *
   * @return array<mixed>|object
   *   Decoded document.
   */
  private function decodedResponse(): array|object {
    try {
      return json_decode($this->http()->body(), FALSE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $exception) {
      throw new \RuntimeException(sprintf('The response from "%s" is not valid JSON.', $this->http()->url()), 0, $exception);
    }
  }

  /**
   * Render a decoded JSON value for comparison with a step argument.
   */
  private static function asComparableString(mixed $value): string {
    return match (TRUE) {
      $value === NULL => 'null',
      is_bool($value) => $value ? 'true' : 'false',
      is_scalar($value) => (string) $value,
      default => (string) json_encode($value),
    };
  }

}

<?php

declare(strict_types=1);

namespace Jovian\BddSteps\Context;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Jovian\BddSteps\Driver\DomDriver;
use Jovian\BddSteps\Driver\HttpDriver;
use Jovian\BddSteps\Driver\XmlDriver;
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
   * XML driver, created on first XML step.
   */
  private ?XmlDriver $xml = NULL;

  /**
   * Response content set by a fixture step, replacing the last HTTP body.
   *
   * A new request clears it, so assertions fall back to the live response.
   */
  private ?string $content_override = NULL;

  /**
   * REST headers staged for the next REST request.
   *
   * @var array<string, string>
   */
  private array $rest_headers = [];

  /**
   * Request a URL.
   *
   * @param string $url
   *   Absolute URL, or a path relative to BDD_STEPS_BASE_URL.
   *
   */
  #[When('I request :url')]
  public function request(string $url): void {
    $this->content_override = NULL;
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
    if (!json_validate($this->effectiveContent())) {
      throw new \RuntimeException(sprintf('The response from "%s" is not valid JSON.', $this->contentLabel()));
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
   * Use a fixture file as the response JSON for the steps that follow.
   *
   * @param string $filename
   *   Fixture filename.
   */
  #[Given('the response JSON from the file :filename')]
  public function responseJsonFromFile(string $filename): void {
    $this->content_override = $this->fixtureContent($filename);
  }

  /**
   * Use inline content as the response JSON for the steps that follow.
   */
  #[Given('the response JSON content is the following:')]
  public function responseJsonContent(PyStringNode $content): void {
    $this->content_override = (string) $content;
  }

  /**
   * Print the effective JSON response.
   */
  #[When('I print last JSON response')]
  public function printLastJsonResponse(): void {
    echo $this->effectiveContent();
  }

  /**
   * Assert the last response body is not valid JSON.
   *
   */
  #[Then('the response should not be in JSON format')]
  public function responseShouldNotBeInJsonFormat(): void {
    if (json_validate($this->effectiveContent())) {
      throw new \RuntimeException(sprintf('The response from "%s" is valid JSON, expected otherwise.', $this->contentLabel()));
    }
  }

  /**
   * Assert a JSONPath matches nothing in the last response.
   *
   * @param string $path
   *   JSONPath expression.
   *
   */
  #[Then('the JSON path :path should not exist')]
  public function jsonPathShouldNotExist(string $path): void {
    if (JsonPath::matches($this->decodedResponse(), $path) !== []) {
      throw new \RuntimeException(sprintf('The JSON path "%s" matches a value in the response from "%s", expected nothing.', $path, $this->contentLabel()));
    }
  }

  /**
   * Assert a JSONPath never resolves to an unexpected value.
   *
   * @param string $path
   *   JSONPath expression.
   * @param string $value
   *   Unexpected value, compared as a string.
   *
   */
  #[Then('the JSON path :path should not be equal to :value')]
  public function jsonPathShouldNotBeEqualTo(string $path, string $value): void {
    $matches = JsonPath::matches($this->decodedResponse(), $path);

    foreach ($matches as $match) {
      if (self::asComparableString($match) === $value) {
        throw new \RuntimeException(sprintf('The JSON path "%s" resolved to "%s" in the response from "%s".', $path, $value, $this->contentLabel()));
      }
    }
  }

  /**
   * Assert a JSONPath value contains the expected text.
   *
   * @param string $path
   *   JSONPath expression.
   * @param string $value
   *   Expected substring.
   *
   */
  #[Then('the JSON path :path should contain :value')]
  public function jsonPathShouldContain(string $path, string $value): void {
    $matches = JsonPath::matches($this->decodedResponse(), $path);

    if ($matches === []) {
      throw new \RuntimeException(sprintf('The JSON path "%s" matches nothing in the response from "%s".', $path, $this->contentLabel()));
    }

    foreach ($matches as $match) {
      if (str_contains(self::asComparableString($match), $value)) {
        return;
      }
    }

    throw new \RuntimeException(sprintf('The JSON path "%s" resolved to %s, none containing "%s".', $path, json_encode(array_map(self::asComparableString(...), $matches)), $value));
  }

  /**
   * Assert a JSONPath value never contains the unexpected text.
   *
   * @param string $path
   *   JSONPath expression.
   * @param string $value
   *   Unexpected substring.
   *
   */
  #[Then('the JSON path :path should not contain :value')]
  public function jsonPathShouldNotContain(string $path, string $value): void {
    foreach (JsonPath::matches($this->decodedResponse(), $path) as $match) {
      if (str_contains(self::asComparableString($match), $value)) {
        throw new \RuntimeException(sprintf('The JSON path "%s" resolved to a value containing "%s" in the response from "%s".', $path, $value, $this->contentLabel()));
      }
    }
  }

  /**
   * Assert a JSONPath value matches a regular expression.
   *
   * @param string $path
   *   JSONPath expression.
   * @param string $pattern
   *   PCRE pattern, including delimiters.
   *
   */
  #[Then('the JSON path :path should match :pattern')]
  public function jsonPathShouldMatch(string $path, string $pattern): void {
    $matches = JsonPath::matches($this->decodedResponse(), $path);

    if ($matches === []) {
      throw new \RuntimeException(sprintf('The JSON path "%s" matches nothing in the response from "%s".', $path, $this->contentLabel()));
    }

    foreach ($matches as $match) {
      $result = @preg_match($pattern, self::asComparableString($match));

      if ($result === FALSE) {
        throw new \RuntimeException(sprintf('The pattern "%s" is not a valid regular expression.', $pattern));
      }

      if ($result === 1) {
        return;
      }
    }

    throw new \RuntimeException(sprintf('The JSON path "%s" resolved to %s, none matching "%s".', $path, json_encode(array_map(self::asComparableString(...), $matches)), $pattern));
  }

  /**
   * Assert a JSONPath value never matches a regular expression.
   *
   * @param string $path
   *   JSONPath expression.
   * @param string $pattern
   *   PCRE pattern, including delimiters.
   *
   */
  #[Then('the JSON path :path should not match :pattern')]
  public function jsonPathShouldNotMatch(string $path, string $pattern): void {
    foreach (JsonPath::matches($this->decodedResponse(), $path) as $match) {
      $result = @preg_match($pattern, self::asComparableString($match));

      if ($result === FALSE) {
        throw new \RuntimeException(sprintf('The pattern "%s" is not a valid regular expression.', $pattern));
      }

      if ($result === 1) {
        throw new \RuntimeException(sprintf('The JSON path "%s" resolved to a value matching "%s" in the response from "%s".', $path, $pattern, $this->contentLabel()));
      }
    }
  }

  /**
   * Assert a JSONPath resolves to null.
   *
   * @param string $path
   *   JSONPath expression.
   *
   */
  #[Then('the JSON path :path should be null')]
  public function jsonPathShouldBeNull(string $path): void {
    $this->assertJsonPathScalar($path, NULL, 'null');
  }

  /**
   * Assert a JSONPath resolves to true.
   *
   * @param string $path
   *   JSONPath expression.
   *
   */
  #[Then('the JSON path :path should be true')]
  public function jsonPathShouldBeTrue(string $path): void {
    $this->assertJsonPathScalar($path, TRUE, 'true');
  }

  /**
   * Assert a JSONPath resolves to false.
   *
   * @param string $path
   *   JSONPath expression.
   *
   */
  #[Then('the JSON path :path should be false')]
  public function jsonPathShouldBeFalse(string $path): void {
    $this->assertJsonPathScalar($path, FALSE, 'false');
  }

  /**
   * Assert one JSONPath match equals an expected scalar.
   */
  private function assertJsonPathScalar(string $path, mixed $expected, string $label): void {
    $matches = JsonPath::matches($this->decodedResponse(), $path);

    if ($matches === []) {
      throw new \RuntimeException(sprintf('The JSON path "%s" matches nothing in the response from "%s".', $path, $this->contentLabel()));
    }

    foreach ($matches as $match) {
      if ($match === $expected) {
        return;
      }
    }

    throw new \RuntimeException(sprintf('The JSON path "%s" resolved to %s, expected %s.', $path, json_encode(array_map(self::asComparableString(...), $matches)), $label));
  }

  /**
   * Assert how many elements a JSONPath holds.
   *
   * A single array match counts its items; otherwise the matches are counted.
   *
   * @param string $path
   *   JSONPath expression.
   * @param string $count
   *   Expected number of elements.
   *
   */
  #[Then('the JSON path :path should have :count element(s)')]
  public function jsonPathShouldHaveCount(string $path, string $count): void {
    $matches = JsonPath::matches($this->decodedResponse(), $path);

    if ($matches === []) {
      throw new \RuntimeException(sprintf('The JSON path "%s" matches nothing in the response from "%s".', $path, $this->contentLabel()));
    }

    $actual = count($matches) === 1 && is_array($matches[0]) ? count($matches[0]) : count($matches);

    if ((string) $actual !== $count) {
      throw new \RuntimeException(sprintf('Expected %d element(s) at "%s", found %d.', (int) $count, $path, $actual));
    }
  }

  /**
   * Assert the response validates against an inline JSON Schema.
   */
  #[Then('the response should match the following JSON schema:')]
  public function responseShouldMatchJsonSchema(PyStringNode $schema): void {
    $this->assertJsonSchema((string) $schema, 'the given schema');
  }

  /**
   * Assert the response validates against a JSON Schema file.
   *
   * @param string $filename
   *   Fixture filename holding the JSON Schema.
   */
  #[Then('the response should match the JSON schema in the file :filename')]
  public function responseShouldMatchJsonSchemaFile(string $filename): void {
    $this->assertJsonSchema($this->fixtureContent($filename), $filename);
  }

  /**
   * Validate the decoded response against a JSON Schema document.
   *
   * @param string $schema
   *   JSON Schema source.
   * @param string $label
   *   Schema label for failure messages.
   */
  private function assertJsonSchema(string $schema, string $label): void {
    try {
      $schema_document = json_decode($schema, FALSE, 512, JSON_THROW_ON_ERROR);
      $document = json_decode(json_encode($this->decodedResponse()), FALSE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $exception) {
      throw new \RuntimeException(sprintf('The JSON schema in "%s" is not valid JSON.', $label), 0, $exception);
    }

    $validator = new \JsonSchema\Validator();
    $validator->validate($document, $schema_document);

    if (!$validator->isValid()) {
      $errors = [];

      foreach ($validator->getErrors() as $error) {
        $errors[] = sprintf('[%s] %s', $error['property'] ?? '', $error['message'] ?? '');
      }

      throw new \RuntimeException(sprintf('The response from "%s" does not match the schema in "%s": %s', $this->contentLabel(), $label, implode('; ', $errors)));
    }
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
   * Assert the last response contains a header.
   *
   * @param string $name
   *   Header name, matched case-insensitively.
   */
  #[Then('the response should contain the header :name')]
  public function responseShouldContainHeader(string $name): void {
    if (!array_key_exists(strtolower($name), $this->http()->headers())) {
      throw new \RuntimeException(sprintf('The response from "%s" contains no "%s" header.', $this->http()->url(), $name));
    }
  }

  /**
   * Assert the last response contains no such header.
   *
   * @param string $name
   *   Header name, matched case-insensitively.
   */
  #[Then('the response should not contain the header :name')]
  public function responseShouldNotContainHeader(string $name): void {
    if (array_key_exists(strtolower($name), $this->http()->headers())) {
      throw new \RuntimeException(sprintf('The response from "%s" contains a "%s" header, expected none.', $this->http()->url(), $name));
    }
  }

  /**
   * Assert a response header value contains the expected text.
   *
   * @param string $name
   *   Header name, matched case-insensitively.
   * @param string $value
   *   Expected substring of the header value.
   */
  #[Then('the response header :name should contain the value :value')]
  public function responseHeaderShouldContain(string $name, string $value): void {
    $headers = $this->http()->headers();
    $actual = $headers[strtolower($name)] ?? NULL;

    if ($actual === NULL) {
      throw new \RuntimeException(sprintf('The response from "%s" contains no "%s" header.', $this->http()->url(), $name));
    }

    if (!str_contains($actual, $value)) {
      throw new \RuntimeException(sprintf('The "%s" header is "%s", expected it to contain "%s".', $name, $actual, $value));
    }
  }

  /**
   * Assert a response header value never contains the unexpected text.
   *
   * @param string $name
   *   Header name, matched case-insensitively.
   * @param string $value
   *   Unexpected substring of the header value.
   */
  #[Then('the response header :name should not contain the value :value')]
  public function responseHeaderShouldNotContain(string $name, string $value): void {
    $actual = $this->http()->headers()[strtolower($name)] ?? NULL;

    if ($actual !== NULL && str_contains($actual, $value)) {
      throw new \RuntimeException(sprintf('The "%s" header is "%s", expected it not to contain "%s".', $name, $actual, $value));
    }
  }

  /**
   * Set basic authentication for subsequent requests.
   *
   * @param string $username
   *   Authentication username.
   * @param string $password
   *   Authentication password.
   */
  #[Given('the basic authentication with the username :username and the password :password')]
  public function basicAuthentication(string $username, string $password): void {
    $this->http()->setBasicAuth($username, $password);
  }

  /**
   * Re-request the previously requested URL.
   */
  #[When('I go back')]
  public function goBack(): void {
    $this->content_override = NULL;
    $this->http()->back();
  }

  /**
   * Assert the path of the last requested URL.
   *
   * @param string $path
   *   Expected path. "<front>" means "/".
   */
  #[Then('the path should be :path')]
  public function pathShouldBe(string $path): void {
    $actual = $this->currentPath();

    if ($actual !== self::normalizePath($path)) {
      throw new \RuntimeException(sprintf('The current path is "%s", expected "%s".', $actual, $path));
    }
  }

  /**
   * Assert the path of the last requested URL differs.
   *
   * @param string $path
   *   Unexpected path. "<front>" means "/".
   */
  #[Then('the path should not be :path')]
  public function pathShouldNotBe(string $path): void {
    $actual = $this->currentPath();

    if ($actual === self::normalizePath($path)) {
      throw new \RuntimeException(sprintf('The current path is "%s", expected otherwise.', $actual));
    }
  }

  /**
   * Assert the current URL carries a query parameter with a value.
   *
   * @param string $param
   *   Query parameter name.
   */
  #[Then('current url should have the :param parameter')]
  public function urlShouldHaveParam(string $param): void {
    $params = $this->currentQueryParams();

    if (!array_key_exists($param, $params) || $params[$param] === '' || $params[$param] === []) {
      throw new \RuntimeException(sprintf('The current URL "%s" has no "%s" parameter with a value.', $this->http()->url(), $param));
    }
  }

  /**
   * Assert a query parameter equals the expected value.
   *
   * @param string $param
   *   Query parameter name.
   * @param string $value
   *   Expected value.
   */
  #[Then('current url should have the :param parameter with the :value value')]
  public function urlParamShouldHaveValue(string $param, string $value): void {
    $params = $this->currentQueryParams();

    if (!array_key_exists($param, $params) || (string) (is_scalar($params[$param]) ? $params[$param] : '') !== $value) {
      throw new \RuntimeException(sprintf('The current URL "%s" has no "%s" parameter with the value "%s".', $this->http()->url(), $param, $value));
    }
  }

  /**
   * Assert the current URL carries no such query parameter value.
   *
   * @param string $param
   *   Query parameter name.
   */
  #[Then('current url should not have the :param parameter')]
  public function urlShouldNotHaveParam(string $param): void {
    $params = $this->currentQueryParams();

    if (array_key_exists($param, $params) && $params[$param] !== '' && $params[$param] !== []) {
      throw new \RuntimeException(sprintf('The current URL "%s" has a "%s" parameter, expected none.', $this->http()->url(), $param));
    }
  }

  /**
   * Assert a query parameter never equals the unexpected value.
   *
   * @param string $param
   *   Query parameter name.
   * @param string $value
   *   Unexpected value.
   */
  #[Then('current url should not have the :param parameter with the :value value')]
  public function urlParamShouldNotHaveValue(string $param, string $value): void {
    $params = $this->currentQueryParams();

    if (array_key_exists($param, $params) && (string) (is_scalar($params[$param]) ? $params[$param] : '') === $value) {
      throw new \RuntimeException(sprintf('The current URL "%s" has a "%s" parameter with the value "%s", expected otherwise.', $this->http()->url(), $param, $value));
    }
  }

  /**
   * Path component of the last requested URL.
   */
  private function currentPath(): string {
    $url = $this->http()->url();

    if ($url === '') {
      throw new \RuntimeException('No URL has been requested yet.');
    }

    return self::normalizePath((string) parse_url($url, PHP_URL_PATH));
  }

  /**
   * Normalize a path for comparison: "<front>" and empties mean "/".
   */
  private static function normalizePath(string $path): string {
    return $path === '' || $path === '<front>' ? '/' : $path;
  }

  /**
   * Stage a cookie to send with subsequent requests.
   *
   * @param string $name
   *   Cookie name.
   * @param string $value
   *   Cookie value.
   */
  #[Given('the cookie :name with value :value')]
  public function stageCookie(string $name, string $value): void {
    $this->http()->setExtraCookies([...$this->http()->getExtraCookies(), $name => $value]);
  }

  /**
   * Assert a cookie set by the last response exists.
   *
   * @param string $name
   *   Cookie name.
   */
  #[Then('the response should have a cookie named :name')]
  public function responseShouldHaveCookie(string $name): void {
    $cookies = $this->http()->cookies();

    if (!array_key_exists($name, $cookies)) {
      throw new \RuntimeException(sprintf('The response from "%s" has no cookie named "%s".', $this->contentLabel(), $name));
    }
  }

  /**
   * Assert a cookie set by the last response does not exist.
   *
   * @param string $name
   *   Cookie name.
   */
  #[Then('the response should not have a cookie named :name')]
  public function responseShouldNotHaveCookie(string $name): void {
    $cookies = $this->http()->cookies();

    if (array_key_exists($name, $cookies)) {
      throw new \RuntimeException(sprintf('The response from "%s" has a cookie named "%s", expected none.', $this->contentLabel(), $name));
    }
  }

  /**
   * Assert a cookie set by the last response has the expected value.
   *
   * @param string $name
   *   Cookie name.
   * @param string $value
   *   Expected cookie value.
   */
  #[Then('the cookie :name should have the value :value')]
  public function cookieShouldHaveValue(string $name, string $value): void {
    $cookies = $this->http()->cookies();

    if (!array_key_exists($name, $cookies)) {
      throw new \RuntimeException(sprintf('The response from "%s" has no cookie named "%s".', $this->contentLabel(), $name));
    }

    if ($cookies[$name]['value'] !== $value) {
      throw new \RuntimeException(sprintf('The cookie "%s" has value "%s", expected "%s".', $name, $cookies[$name]['value'], $value));
    }
  }

  /**
   * Assert the response time of the last request is below a threshold.
   *
   * @param string $seconds
   *   Maximum acceptable response time in seconds.
   */
  #[Then('the response time should be less than :seconds second(s)')]
  public function responseTimeShouldBeLessThan(string $seconds): void {
    $actual = $this->http()->responseTime();

    if ($actual === NULL) {
      throw new \RuntimeException(sprintf('No response time was measured for the request to "%s".', $this->http()->url()));
    }

    if ($actual >= (float) $seconds) {
      throw new \RuntimeException(sprintf('The response took %.4fs for "%s", expected under %ss.', $actual, $this->http()->url(), $seconds));
    }
  }

  /**
   * Query parameters of the last requested URL.
   *
   * @return array<string, mixed>
   */
  private function currentQueryParams(): array {
    $url = $this->http()->url();

    if ($url === '') {
      throw new \RuntimeException('No URL has been requested yet.');
    }

    $params = [];
    parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

    return $params;
  }

  /**
   * Stage a header for the next REST request.
   *
   * @param string $name
   *   Header name.
   * @param string $value
   *   Header value.
   */
  #[Given('a REST header :name with value :value')]
  public function restHeader(string $name, string $value): void {
    $this->rest_headers[$name] = $value;
  }

  /**
   * Send a REST request without a body.
   *
   * @param string $method
   *   HTTP method.
   * @param string $url
   *   Absolute URL, or a path relative to BDD_STEPS_BASE_URL.
   */
  #[When('I send a REST :method request to :url')]
  public function sendRestRequest(string $method, string $url): void {
    $this->content_override = NULL;
    $this->http()->request($method, $url, $this->rest_headers);
    $this->rest_headers = [];
  }

  /**
   * Send a REST request with a body.
   *
   * @param string $method
   *   HTTP method.
   * @param string $url
   *   Absolute URL, or a path relative to BDD_STEPS_BASE_URL.
   */
  #[When('I send a REST :method request to :url with body:')]
  public function sendRestRequestWithBody(string $method, string $url, PyStringNode $body): void {
    $this->content_override = NULL;
    $this->http()->request($method, $url, $this->rest_headers, (string) $body);
    $this->rest_headers = [];
  }

  /**
   * Assert the status code of the last REST response.
   *
   * @param string $code
   *   Expected status code.
   */
  #[Then('the REST response status code should be :code')]
  public function restResponseStatusShouldBe(string $code): void {
    $actual = $this->http()->status();

    if ((string) $actual !== $code) {
      throw new \RuntimeException(sprintf('Expected REST response status %s, got %s for "%s".', $code, $actual === NULL ? 'no status' : (string) $actual, $this->http()->url()));
    }
  }

  /**
   * Assert the last REST response body contains the expected text.
   *
   * @param string $text
   *   Expected substring.
   */
  #[Then('the REST response should contain :text')]
  public function restResponseShouldContain(string $text): void {
    if (!str_contains($this->http()->body(), $text)) {
      throw new \RuntimeException(sprintf('The REST response from "%s" does not contain "%s".', $this->http()->url(), $text));
    }
  }

  /**
   * Assert how many body rows a table has.
   *
   * @param string $selector
   *   CSS selector of the table.
   * @param string $count
   *   Expected number of rows.
   */
  #[Then('the table :selector should have :count row(s)')]
  public function tableShouldHaveRows(string $selector, string $count): void {
    $actual = count($this->dom()->tableRows($this->dom()->firstCss($selector)));

    if ((string) $actual !== $count) {
      throw new \RuntimeException(sprintf('Expected %d row(s) in "%s", found %d.', (int) $count, $selector, $actual));
    }
  }

  /**
   * Assert how many columns a table has.
   *
   * @param string $selector
   *   CSS selector of the table.
   * @param string $count
   *   Expected number of columns.
   */
  #[Then('the table :selector should have :count column(s)')]
  public function tableShouldHaveColumns(string $selector, string $count): void {
    $actual = count($this->dom()->tableHeaders($this->dom()->firstCss($selector)));

    if ((string) $actual !== $count) {
      throw new \RuntimeException(sprintf('Expected %d column(s) in "%s", found %d.', (int) $count, $selector, $actual));
    }
  }

  /**
   * Assert a table has the expected column headers.
   *
   * @param string $selector
   *   CSS selector of the table.
   */
  #[Then('the table :selector should contain the following columns:')]
  public function tableShouldContainColumns(string $selector, TableNode $table): void {
    $expected = $table->getRow(0);
    $actual = $this->dom()->tableHeaders($this->dom()->firstCss($selector));

    if ($actual !== array_values($expected)) {
      throw new \RuntimeException(sprintf('Expected columns %s in "%s", found %s.', json_encode(array_values($expected)), $selector, json_encode($actual)));
    }
  }

  /**
   * Assert a table has no body rows.
   *
   * @param string $selector
   *   CSS selector of the table.
   */
  #[Then('the table :selector should be empty')]
  public function tableShouldBeEmpty(string $selector): void {
    $actual = count($this->dom()->tableRows($this->dom()->firstCss($selector)));

    if ($actual !== 0) {
      throw new \RuntimeException(sprintf('Expected "%s" to be empty, found %d row(s).', $selector, $actual));
    }
  }

  /**
   * Assert a table has at least one body row.
   *
   * @param string $selector
   *   CSS selector of the table.
   */
  #[Then('the table :selector should not be empty')]
  public function tableShouldNotBeEmpty(string $selector): void {
    if (count($this->dom()->tableRows($this->dom()->firstCss($selector))) === 0) {
      throw new \RuntimeException(sprintf('Expected "%s" not to be empty.', $selector));
    }
  }

  /**
   * Assert a table column is sorted.
   *
   * @param string $selector
   *   CSS selector of the table.
   * @param string $column
   *   Header label of the column.
   * @param string $direction
   *   "ascending" or "descending".
   */
  #[Then('the table :selector should be sorted by :column in :direction order')]
  public function tableShouldBeSorted(string $selector, string $column, string $direction): void {
    $table = $this->dom()->firstCss($selector);
    $headers = $this->dom()->tableHeaders($table);
    $index = array_search($column, $headers, TRUE);

    if ($index === FALSE) {
      throw new \RuntimeException(sprintf('The table "%s" has no "%s" column.', $selector, $column));
    }

    $values = array_column($this->dom()->tableRows($table), $index);
    $direction = strtolower($direction);

    if ($direction !== 'ascending' && $direction !== 'descending') {
      throw new \RuntimeException(sprintf('The direction must be "ascending" or "descending", got "%s".', $direction));
    }

    $sorted = $values;
    sort($sorted, SORT_STRING);

    if ($direction === 'descending') {
      $sorted = array_reverse($sorted);
    }

    if ($values !== $sorted) {
      throw new \RuntimeException(sprintf('The "%s" column of "%s" is not sorted in %s order.', $column, $selector, $direction));
    }
  }

  /**
   * Assert a table contains the expected rows.
   *
   * The first table row holds the headers and must match; every following
   * row must appear verbatim, in any order.
   *
   * @param string $selector
   *   CSS selector of the table.
   */
  #[Then('the table :selector should contain the following rows:')]
  public function tableShouldContainRows(string $selector, TableNode $table): void {
    $expected = $table->getRows();

    if ($expected === []) {
      throw new \RuntimeException('The expected rows table is empty.');
    }

    $driver = $this->dom();
    $element = $driver->firstCss($selector);
    $actual_headers = $driver->tableHeaders($element);

    if ($actual_headers !== array_values($expected[0])) {
      throw new \RuntimeException(sprintf('Expected headers %s in "%s", found %s.', json_encode(array_values($expected[0])), $selector, json_encode($actual_headers)));
    }

    $actual_rows = array_map(json_encode(...), $driver->tableRows($element));

    foreach (array_slice($expected, 1) as $row) {
      if (!in_array(json_encode(array_values($row)), $actual_rows, TRUE)) {
        throw new \RuntimeException(sprintf('Expected row %s not found in "%s".', json_encode(array_values($row)), $selector));
      }
    }
  }

  /**
   * Assert a table row holding a text contains the expected values.
   *
   * Each listed value must appear as a substring of the row's cells.
   *
   * @param string $row_text
   *   Text identifying the row.
   */
  #[Then('the :row_text row should contain the following:')]
  public function tableRowShouldContain(string $row_text, TableNode $table): void {
    $driver = $this->dom();
    $found = NULL;

    foreach ($driver->queryCss('table') as $element) {
      if (!$element instanceof \DOMElement) {
        continue;
      }

      foreach ($driver->tableRows($element) as $row) {
        foreach ($row as $cell) {
          if (str_contains($cell, $row_text)) {
            $found = $row;
            break 3;
          }
        }
      }
    }

    if ($found === NULL) {
      throw new \RuntimeException(sprintf('No table row contains "%s".', $row_text));
    }

    $expected = $table->getColumn(0);

    foreach ($expected as $value) {
      $matched = FALSE;

      foreach ($found as $cell) {
        if (str_contains($cell, $value)) {
          $matched = TRUE;
          break;
        }
      }

      if (!$matched) {
        throw new \RuntimeException(sprintf('The "%s" row does not contain "%s".', $row_text, $value));
      }
    }
  }

  /**
   * Use a fixture file as the response XML for the steps that follow.
   *
   * @param string $filename
   *   Fixture filename.
   */
  #[Given('the response content from the file :filename')]
  public function responseContentFromFile(string $filename): void {
    $this->content_override = $this->fixtureContent($filename);
  }

  /**
   * Use inline content as the response XML for the steps that follow.
   */
  #[Given('the response content is the following:')]
  public function responseContentIs(PyStringNode $content): void {
    $this->content_override = (string) $content;
  }

  /**
   * Print the effective XML response.
   */
  #[When('I print last XML response')]
  public function printLastXmlResponse(): void {
    echo $this->effectiveContent();
  }

  /**
   * Assert the effective content is well-formed XML.
   */
  #[Then('the response should be in XML format')]
  public function responseShouldBeInXmlFormat(): void {
    if (!$this->xmlDocument()->isValid()) {
      throw new \RuntimeException(sprintf('The response from "%s" is not well-formed XML.', $this->contentLabel()));
    }
  }

  /**
   * Assert the effective content is not well-formed XML.
   */
  #[Then('the response should not be in XML format')]
  public function responseShouldNotBeInXmlFormat(): void {
    if ($this->xmlDocument()->isValid()) {
      throw new \RuntimeException(sprintf('The response from "%s" is well-formed XML, expected otherwise.', $this->contentLabel()));
    }
  }

  /**
   * Assert an XPath matches at least one XML node.
   *
   * @param string $element
   *   XPath expression.
   */
  #[Then('the XML element :element should exist')]
  public function xmlElementShouldExist(string $element): void {
    if (!$this->xmlDocument()->exists($element)) {
      throw new \RuntimeException(sprintf('The XPath "%s" matches nothing in the XML from "%s".', $element, $this->contentLabel()));
    }
  }

  /**
   * Assert an XPath matches no XML node.
   *
   * @param string $element
   *   XPath expression.
   */
  #[Then('the XML element :element should not exist')]
  public function xmlElementShouldNotExist(string $element): void {
    if ($this->xmlDocument()->exists($element)) {
      throw new \RuntimeException(sprintf('The XPath "%s" matches a node in the XML from "%s", expected nothing.', $element, $this->contentLabel()));
    }
  }

  /**
   * Assert the text of the first matched XML node equals the expected text.
   *
   * @param string $element
   *   XPath expression.
   * @param string $text
   *   Expected text.
   */
  #[Then('the XML element :element should be equal to :text')]
  public function xmlElementShouldBeEqualTo(string $element, string $text): void {
    $actual = $this->xmlDocument()->value($element);

    if ($actual !== $text) {
      throw new \RuntimeException(sprintf('The XPath "%s" resolved to "%s", expected "%s".', $element, $actual, $text));
    }
  }

  /**
   * Assert the text of the first matched XML node differs.
   *
   * @param string $element
   *   XPath expression.
   * @param string $text
   *   Unexpected text.
   */
  #[Then('the XML element :element should not be equal to :text')]
  public function xmlElementShouldNotBeEqualTo(string $element, string $text): void {
    if ($this->xmlDocument()->value($element) === $text) {
      throw new \RuntimeException(sprintf('The XPath "%s" resolved to "%s", expected otherwise.', $element, $text));
    }
  }

  /**
   * Assert the text of the first matched XML node contains the text.
   *
   * @param string $element
   *   XPath expression.
   * @param string $text
   *   Expected substring.
   */
  #[Then('the XML element :element should contain :text')]
  public function xmlElementShouldContain(string $element, string $text): void {
    $actual = $this->xmlDocument()->value($element);

    if (!str_contains($actual, $text)) {
      throw new \RuntimeException(sprintf('The XPath "%s" resolved to "%s", expected it to contain "%s".', $element, $actual, $text));
    }
  }

  /**
   * Assert the text of the first matched XML node never contains the text.
   *
   * @param string $element
   *   XPath expression.
   * @param string $text
   *   Unexpected substring.
   */
  #[Then('the XML element :element should not contain :text')]
  public function xmlElementShouldNotContain(string $element, string $text): void {
    if (str_contains($this->xmlDocument()->value($element), $text)) {
      throw new \RuntimeException(sprintf('The XPath "%s" resolved to a value containing "%s", expected otherwise.', $element, $text));
    }
  }

  /**
   * Assert an attribute exists on the first node an XPath matches.
   *
   * @param string $attribute
   *   Attribute name, with or without a leading "@".
   * @param string $element
   *   XPath expression.
   */
  #[Then('the XML attribute :attribute on element :element should exist')]
  public function xmlAttributeShouldExist(string $attribute, string $element): void {
    if (!$this->xmlDocument()->hasAttribute($element, $attribute)) {
      throw new \RuntimeException(sprintf('The element matched by "%s" has no "%s" attribute.', $element, $attribute));
    }
  }

  /**
   * Assert an attribute is absent from the first node an XPath matches.
   *
   * @param string $attribute
   *   Attribute name, with or without a leading "@".
   * @param string $element
   *   XPath expression.
   */
  #[Then('the XML attribute :attribute on element :element should not exist')]
  public function xmlAttributeShouldNotExist(string $attribute, string $element): void {
    if ($this->xmlDocument()->hasAttribute($element, $attribute)) {
      throw new \RuntimeException(sprintf('The element matched by "%s" has a "%s" attribute, expected none.', $element, $attribute));
    }
  }

  /**
   * Assert an attribute value equals the expected text.
   *
   * @param string $attribute
   *   Attribute name, with or without a leading "@".
   * @param string $element
   *   XPath expression.
   * @param string $text
   *   Expected value.
   */
  #[Then('the XML attribute :attribute on element :element should be equal to :text')]
  public function xmlAttributeShouldBeEqualTo(string $attribute, string $element, string $text): void {
    $actual = $this->xmlDocument()->attribute($element, $attribute);

    if ($actual !== $text) {
      throw new \RuntimeException(sprintf('The "%s" attribute resolved to "%s", expected "%s".', $attribute, $actual, $text));
    }
  }

  /**
   * Assert an attribute value differs.
   *
   * @param string $attribute
   *   Attribute name, with or without a leading "@".
   * @param string $element
   *   XPath expression.
   * @param string $text
   *   Unexpected value.
   */
  #[Then('the XML attribute :attribute on element :element should not be equal to :text')]
  public function xmlAttributeShouldNotBeEqualTo(string $attribute, string $element, string $text): void {
    if ($this->xmlDocument()->attribute($element, $attribute) === $text) {
      throw new \RuntimeException(sprintf('The "%s" attribute resolved to "%s", expected otherwise.', $attribute, $text));
    }
  }

  /**
   * Assert an attribute value contains the expected text.
   *
   * @param string $attribute
   *   Attribute name, with or without a leading "@".
   * @param string $element
   *   XPath expression.
   * @param string $text
   *   Expected substring.
   */
  #[Then('the XML attribute :attribute_name on element :element should contain :text')]
  public function xmlAttributeShouldContain(string $attribute_name, string $element, string $text): void {
    $actual = $this->xmlDocument()->attribute($element, $attribute_name);

    if (!str_contains($actual, $text)) {
      throw new \RuntimeException(sprintf('The "%s" attribute is "%s", expected it to contain "%s".', $attribute_name, $actual, $text));
    }
  }

  /**
   * Assert an attribute value never contains the unexpected text.
   *
   * @param string $attribute
   *   Attribute name, with or without a leading "@".
   * @param string $element
   *   XPath expression.
   * @param string $text
   *   Unexpected substring.
   */
  #[Then('the XML attribute :attribute_name on element :element should not contain :text')]
  public function xmlAttributeShouldNotContain(string $attribute_name, string $element, string $text): void {
    if (str_contains($this->xmlDocument()->attribute($element, $attribute_name), $text)) {
      throw new \RuntimeException(sprintf('The "%s" attribute contains "%s", expected otherwise.', $attribute_name, $text));
    }
  }

  /**
   * Assert how many nodes an XPath matches in the XML document.
   *
   * @param string $element
   *   XPath expression.
   * @param string $count
   *   Expected number of matches.
   */
  #[Then('the XML element :element should have :count element(s)')]
  public function xmlElementShouldHaveCount(string $element, string $count): void {
    $actual = $this->xmlDocument()->count($element);

    if ((string) $actual !== $count) {
      throw new \RuntimeException(sprintf('Expected %d element(s) matching "%s", found %d.', (int) $count, $element, $actual));
    }
  }

  /**
   * Assert the XML document declares a namespace.
   *
   * @param string $namespace
   *   Namespace URI.
   */
  #[Then('the XML should use the namespace :namespace')]
  public function xmlShouldUseNamespace(string $namespace): void {
    if (!in_array($namespace, $this->xmlDocument()->namespaces(), TRUE)) {
      throw new \RuntimeException(sprintf('The XML from "%s" does not use the "%s" namespace.', $this->contentLabel(), $namespace));
    }
  }

  /**
   * Assert the XML document declares no such namespace.
   *
   * @param string $namespace
   *   Namespace URI.
   */
  #[Then('the XML should not use the namespace :namespace')]
  public function xmlShouldNotUseNamespace(string $namespace): void {
    if (in_array($namespace, $this->xmlDocument()->namespaces(), TRUE)) {
      throw new \RuntimeException(sprintf('The XML from "%s" uses the "%s" namespace, expected otherwise.', $this->contentLabel(), $namespace));
    }
  }

  /**
   * Assert the XML document validates against an inline XSD schema.
   */
  #[Then('the response should match the following XSD schema:')]
  public function responseShouldMatchXsd(PyStringNode $schema): void {
    if (!$this->xmlDocument()->validateXsd((string) $schema)) {
      throw new \RuntimeException(sprintf('The XML from "%s" does not validate against the given XSD schema.', $this->contentLabel()));
    }
  }

  /**
   * Assert the XML document validates against an XSD schema file.
   *
   * @param string $filename
   *   Fixture filename holding the XSD schema.
   */
  #[Then('the response should match the XSD schema in the file :filename')]
  public function responseShouldMatchXsdFile(string $filename): void {
    $document = new \DOMDocument();

    if (@$document->load($this->fixtureFile($filename)) === FALSE) {
      throw new \RuntimeException(sprintf('The XSD schema file "%s" cannot be loaded.', $filename));
    }

    $this->responseShouldMatchXsd(new PyStringNode([$document->saveXML() ?: ''], 0));
  }

  /**
   * Assert the XML document validates against an inline DTD.
   *
   * The DTD is applied as the internal subset of the document's root element.
   */
  #[Then('the response should match the following DTD:')]
  public function responseShouldMatchDtd(PyStringNode $dtd): void {
    if (!$this->validateAgainstDtd((string) $dtd)) {
      throw new \RuntimeException(sprintf('The XML from "%s" does not validate against the given DTD.', $this->contentLabel()));
    }
  }

  /**
   * Assert the XML document validates against a DTD file.
   *
   * @param string $filename
   *   Fixture filename holding the DTD.
   */
  #[Then('the response should match the DTD in the file :filename')]
  public function responseShouldMatchDtdFile(string $filename): void {
    if (!$this->validateAgainstDtd($this->fixtureContent($filename))) {
      throw new \RuntimeException(sprintf('The XML from "%s" does not validate against the DTD in "%s".', $this->contentLabel(), $filename));
    }
  }

  /**
   * Validate the effective XML against a DTD internal subset.
   *
   * @param string $dtd
   *   DTD declarations forming an internal subset.
   */
  private function validateAgainstDtd(string $dtd): bool {
    $source = new \DOMDocument();

    $previous = libxml_use_internal_errors(TRUE);
    $parsed = $source->loadXML($this->effectiveContent());
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$parsed || !$source->documentElement instanceof \DOMElement) {
      return FALSE;
    }

    $root = $source->documentElement->tagName;
    $wrapped = new \DOMDocument();

    $previous = libxml_use_internal_errors(TRUE);
    $loaded = $wrapped->loadXML(sprintf('<?xml version="1.0"?><!DOCTYPE %s [%s]>%s', $root, $dtd, $source->saveXML($source->documentElement)));
    $valid = $loaded && $wrapped->validate();
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return $valid;
  }

  /**
   * Assert the XML document validates against an inline RelaxNG schema.
   */
  #[Then('the response should match the following RelaxNG schema:')]
  public function responseShouldMatchRelaxNg(PyStringNode $schema): void {
    if (!$this->xmlDocument()->validateRelaxNg((string) $schema)) {
      throw new \RuntimeException(sprintf('The XML from "%s" does not validate against the given RelaxNG schema.', $this->contentLabel()));
    }
  }

  /**
   * Assert the XML document validates against a RelaxNG schema file.
   *
   * @param string $filename
   *   Fixture filename holding the RelaxNG schema.
   */
  #[Then('the response should match the RelaxNG schema in the file :filename')]
  public function responseShouldMatchRelaxNgFile(string $filename): void {
    $this->responseShouldMatchRelaxNg(new PyStringNode([$this->fixtureContent($filename)], 0));
  }

  /**
   * Assert the XML document is a structurally valid RSS feed.
   *
   * Well-formed XML whose root is `<rss>` with a `channel/item` structure.
   */
  #[Then('the response should be a valid RSS feed')]
  public function responseShouldBeValidRss(): void {
    $document = $this->xmlDocument();

    if (!$document->isValid() || !$document->exists('/rss/channel/item')) {
      throw new \RuntimeException(sprintf('The response from "%s" is not a valid RSS feed: expected well-formed XML with /rss/channel/item.', $this->contentLabel()));
    }
  }

  /**
   * Assert the XML document is a structurally valid Atom feed.
   *
   * Well-formed XML whose root is `<feed>` with at least one `entry`.
   */
  #[Then('the response should be a valid Atom feed')]
  public function responseShouldBeValidAtom(): void {
    $document = $this->xmlDocument();

    if (!$document->isValid() || !$document->exists('/*[local-name()="feed"]/*[local-name()="entry"]')) {
      throw new \RuntimeException(sprintf('The response from "%s" is not a valid Atom feed: expected well-formed XML with feed/entry.', $this->contentLabel()));
    }
  }

  /**
   * Wait a number of seconds.
   *
   * @param string $seconds
   *   Seconds to wait.
   */
  #[When('I wait for :seconds second(s)')]
  public function waitSeconds(string $seconds): void {
    $delay = (int) $seconds;

    if ($delay > 0) {
      sleep($delay);
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
   * Get the XML driver, creating it on first use.
   */
  private function xml(): XmlDriver {
    return $this->xml ??= new XmlDriver();
  }

  /**
   * Get the XML driver loaded with the current effective content.
   */
  private function xmlDocument(): XmlDriver {
    $driver = $this->xml();
    $driver->load($this->effectiveContent());

    return $driver;
  }

  /**
   * Decode the last response body.
   *
   * @return array<mixed>|object
   *   Decoded document.
   */
  private function decodedResponse(): array|object {
    try {
      return json_decode($this->effectiveContent(), FALSE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $exception) {
      throw new \RuntimeException(sprintf('The response from "%s" is not valid JSON.', $this->contentLabel()), 0, $exception);
    }
  }

  /**
   * Body used by the content assertions: fixture override or live response.
   */
  private function effectiveContent(): string {
    return $this->content_override ?? $this->http()->body();
  }

  /**
   * Where the effective content came from, for failure messages.
   */
  private function contentLabel(): string {
    return $this->content_override === NULL ? $this->http()->url() : 'the fixture content';
  }

  /**
   * Resolve a fixture filename to a readable file.
   *
   * @param string $filename
   *   Absolute path, or a path relative to the working directory or to
   *   BDD_STEPS_FIXTURES_DIR (default "tests/fixtures").
   */
  private function fixtureFile(string $filename): string {
    $candidates = [$filename];

    $dir = getenv('BDD_STEPS_FIXTURES_DIR');
    $candidates[] = rtrim(is_string($dir) ? $dir : 'tests/fixtures', '/') . '/' . $filename;
    $candidates[] = getcwd() . '/' . $filename;

    foreach ($candidates as $candidate) {
      if (is_file($candidate)) {
        return $candidate;
      }
    }

    throw new \RuntimeException(sprintf('The fixture file "%s" does not exist.', $filename));
  }

  /**
   * Read fixture content or throw when it cannot be read.
   */
  private function fixtureContent(string $filename): string {
    $content = @file_get_contents($this->fixtureFile($filename));

    if ($content === FALSE) {
      throw new \RuntimeException(sprintf('The fixture file "%s" cannot be read.', $filename));
    }

    return $content;
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

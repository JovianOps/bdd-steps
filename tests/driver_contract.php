<?php

declare(strict_types=1);

/**
 * @file
 * Assert the driver-level contracts the feature suite cannot reach.
 *
 * Run with: php tests/driver_contract.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Jovian\BddSteps\Context\JovianContext;
use Jovian\BddSteps\Driver\DomDriver;
use Jovian\BddSteps\Driver\HttpDriver;
use Jovian\BddSteps\Json\JsonPath;

$failures = [];

/**
 * Assert a condition.
 */
$check = static function (string $description, bool $condition) use (&$failures): void {
  if (!$condition) {
    $failures[] = $description;
  }
};

/**
 * Assert that a callable throws.
 */
$checkFails = static function (string $description, callable $call) use (&$failures): void {
  try {
    $call();
    $failures[] = $description . ' (it did not)';
  }
  catch (Throwable) {
    // Expected.
  }
};

/**
 * Read a private property without creating the driver it would hold.
 */
$property = static function (object $object, string $name): mixed {
  $reflection = new ReflectionProperty($object, $name);
  $reflection->setAccessible(TRUE);

  return $reflection->getValue($object);
};

$fixtures = 'file://' . str_replace('\\', '/', __DIR__ . '/fixtures');

// -----------------------------------------------------------------------------
// Driver creation stays lazy. Class-loading assertions for each direction live
// in tests/laziness_*.php, which run in their own processes.
// -----------------------------------------------------------------------------

$dom_only = new JovianContext();
$dom_only->pageContentIs('<html><body><p>Hello</p></body></html>');
$dom_only->pageShouldContainText('Hello');

$check(
  'A markup-only scenario creates the DOM driver.',
  is_object($property($dom_only, 'dom'))
);
$check(
  'A markup-only scenario does not create an HTTP driver.',
  $property($dom_only, 'http') === NULL
);

putenv('BDD_STEPS_BASE_URL=' . $fixtures);

$http_only = new JovianContext();
$http_only->request('/data.json');
$http_only->responseShouldBeInJsonFormat();
$http_only->jsonPathShouldExist('$.user.name');
$http_only->jsonPathShouldBeEqualTo('$.user.roles[0]', 'admin');

$check(
  'A response-only scenario creates the HTTP driver.',
  is_object($property($http_only, 'http'))
);
$check(
  'A response-only scenario does not create a DOM driver.',
  $property($http_only, 'dom') === NULL
);

// -----------------------------------------------------------------------------
// HTTP driver contracts.
// -----------------------------------------------------------------------------

$http = new HttpDriver($fixtures);
$http->get('/data.json');
$check('A relative URL resolves against the base URL.', str_ends_with($http->url(), '/data.json'));
$check('A non-HTTP transport reports no status code.', $http->status() === NULL);
$check('The response body is captured.', str_contains($http->body(), 'Ada'));
$check('A response time is measured.', $http->responseTime() !== NULL);

$absolute = new HttpDriver($fixtures);
$absolute->get($fixtures . '/index.html');
$check('An absolute URL ignores the base URL.', str_ends_with($absolute->url(), '/index.html'));

$checkFails(
  'A request to a missing path fails the step.',
  static fn(): mixed => (new HttpDriver($fixtures))->get('/nope.json')
);

// -----------------------------------------------------------------------------
// DOM driver contracts.
// -----------------------------------------------------------------------------

$dom = new DomDriver();
$dom->load('<html><body><p>Hello   Ada</p></body></html>');
$check('Text matching collapses whitespace.', $dom->containsText('Hello Ada'));
$check('An unmatched text is reported as absent.', !$dom->containsText('Goodbye'));
$check('A count reflects the matched nodes.', $dom->count('//p') === 1);
$checkFails('An invalid XPath expression fails the step.', static fn(): int => $dom->count('//*['));

// -----------------------------------------------------------------------------
// JSONPath contracts.
// -----------------------------------------------------------------------------

$json = json_decode('{"user":{"name":"Ada","roles":["admin","editor"]},"count":2,"active":true,"nothing":null}');

$check('A dotted path resolves.', JsonPath::matches($json, '$.user.name') === ['Ada']);
$check('A list index resolves.', JsonPath::matches($json, '$.user.roles[1]') === ['editor']);
$check('A bracket key resolves.', JsonPath::matches($json, "$['user']['name']") === ['Ada']);
$check('A null value resolves to itself.', JsonPath::matches($json, '$.nothing') === [NULL]);
$check('A missing path resolves to nothing.', JsonPath::matches($json, '$.user.email') === []);
$check('A path into a scalar resolves to nothing.', JsonPath::matches($json, '$.count.deeper') === []);
$checkFails('A path without the root fails.', static fn(): array => JsonPath::matches($json, 'user.name'));

// Enhanced JSONPath: wildcards, recursive descent, filters.
$nested = json_decode('{"store":{"book":[{"title":"A","price":8},{"title":"B","price":12}],"bicycle":{"price":10}},"books":[1,2,3]}');

$check('A wildcard expands all keys at a level.', count(JsonPath::matches($nested, '$.store.*')) >= 2);
$check('A recursive descent matches deeply nested values.', count(JsonPath::matches($nested, '$..title')) === 2);
$check('A wildcard inside recursive descent collects all prices.', count(JsonPath::matches($nested, '$..price')) === 3);
$check('A filter expression selects matching items.', count(JsonPath::matches($nested, '$.store.book[?(@.price>10)]')) === 1);
$check('A first() helper returns only the first match.', JsonPath::first($nested, '$.store.book[*].title') === 'A');
$check('An in-list filter matches.', count(JsonPath::matches($nested, '$.store.book[?(@.title in ["A","B"])]')) === 2);

// Cookie parsing via the HTTP driver (file:// responses set no cookies).
$cookies = $http->cookies();
$check('An HTTP response with no Set-Cookie reports empty cookies.', $cookies === []);

// -----------------------------------------------------------------------------
// HTTP history, auth and headers.
// -----------------------------------------------------------------------------

$nav = new HttpDriver($fixtures);
$nav->get('/data.json');
$nav->get('/index.html');
$check('History records every request.', count($nav->history()) === 2);
$nav->back();
$check('Back returns to the previous URL.', str_ends_with($nav->url(), '/data.json'));
$check('Back restores the history depth.', count($nav->history()) === 1);

$lonely = new HttpDriver($fixtures);
$lonely->get('/data.json');
$checkFails('Back with no history fails.', static fn(): mixed => $lonely->back());

$authed = new HttpDriver($fixtures);
$authed->setBasicAuth('user', 'pass');
$authed->get('/data.json');
$check('Basic auth does not break a request.', str_contains($authed->body(), 'Ada'));
$authed->setBasicAuth(NULL);

$staged = new HttpDriver($fixtures);
$staged->setExtraHeaders(['X-Custom' => 'hello']);
$staged->request('GET', '/data.json', ['X-Other' => 'world']);
$check('Extra headers do not break a request.', str_contains($staged->body(), 'Ada'));

// -----------------------------------------------------------------------------
// XML driver contracts.
// -----------------------------------------------------------------------------

$xml = new \Jovian\BddSteps\Driver\XmlDriver();
$xml->load('<?xml version="1.0"?><library><book id="123"><title>T</title></book><book id="456"><title>U</title></book></library>');
$check('Well-formed XML validates.', $xml->isValid());
$check('An existing element is found.', $xml->exists('//book'));
$check('A count reflects the matched nodes.', $xml->count('//book') === 2);
$check('A value reads the first match.', $xml->value('//title') === 'T');
$check('An attribute reads.', $xml->attribute('/library/book[1]', 'id') === '123');
$check('An attribute presence reports.', $xml->hasAttribute('//book', '@id') && !$xml->hasAttribute('//book', 'missing'));
$checkFails('A missing value fails.', static fn(): string => $xml->value('//nope'));
$checkFails('A missing attribute fails.', static fn(): string => $xml->attribute('//book', 'missing'));

$bad_xml = new \Jovian\BddSteps\Driver\XmlDriver();
$bad_xml->load('<root><unclosed>');
$check('Malformed XML reports invalid.', !$bad_xml->isValid());

$ns_xml = new \Jovian\BddSteps\Driver\XmlDriver();
$ns_xml->load('<?xml version="1.0"?><root xmlns:bk="http://example.com/book"><bk:item>x</bk:item></root>');
$check('Declared namespaces are listed.', $ns_xml->namespaces() === ['http://example.com/book']);

// -----------------------------------------------------------------------------
// Table contracts.
// -----------------------------------------------------------------------------

$tables = new DomDriver();
$tables->load('<html><body><table class="t"><thead><tr><th>A</th><th>B</th></tr></thead><tbody><tr><td>1</td><td>2</td></tr></tbody></table></body></html>');
$table_el = $tables->firstCss('.t');
$check('Headers read in order.', $tables->tableHeaders($table_el) === ['A', 'B']);
$check('Rows read as cell texts.', $tables->tableRows($table_el) === [['1', '2']]);
$checkFails('A missing table fails.', static fn(): \DOMElement => $tables->firstCss('.nope'));

// -----------------------------------------------------------------------------
// Step-level failure paths.
// -----------------------------------------------------------------------------

$failing = new JovianContext();
$failing->pageContentIs('<html><body><p>Hello</p></body></html>');

$checkFails('An unmatched text fails the step.', static fn(): mixed => $failing->pageShouldContainText('Goodbye'));
$checkFails('A wrong element count fails the step.', static fn(): mixed => $failing->pageShouldContainElements('2', '//p'));

$json_failing = new JovianContext();
$json_failing->responseJsonContent(new \Behat\Gherkin\Node\PyStringNode(['{"a":1}'], 0));
$checkFails('A missing JSON path fails the step.', static fn(): mixed => $json_failing->jsonPathShouldExist('$.b'));
$checkFails('A wrong JSON value fails the step.', static fn(): mixed => $json_failing->jsonPathShouldBeEqualTo('$.a', '2'));

$xml_failing = new JovianContext();
$xml_failing->responseContentIs(new \Behat\Gherkin\Node\PyStringNode(['<?xml version="1.0"?><r><i>x</i></r>'], 0));
$checkFails('A missing XML element fails the step.', static fn(): mixed => $xml_failing->xmlElementShouldExist('//nope'));

// Cookie and timing step-level failure paths.
$no_cookies = new JovianContext();
$no_cookies->pageContentIs('<html><body><p>Hi</p></body></html>');
$checkFails('A missing cookie fails the step.', static fn(): mixed => $no_cookies->responseShouldHaveCookie('session'));

$fast_enough = new JovianContext();
$fast_enough->request('/data.json');
$fast_enough->responseTimeShouldBeLessThan('1');
$check('A fast response passes the timing step.', TRUE);

$checkFails('An impossibly fast response fails the step.', static fn(): mixed => $fast_enough->responseTimeShouldBeLessThan('0'));

// -----------------------------------------------------------------------------
// Report.
// -----------------------------------------------------------------------------

if ($failures !== []) {
  echo "FAIL\n";

  foreach ($failures as $failure) {
    echo ' - ' . $failure . PHP_EOL;
  }

  exit(1);
}

echo 'OK: driver contracts hold.' . PHP_EOL;

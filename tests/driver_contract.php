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
$checkFails('An unsupported segment fails.', static fn(): array => JsonPath::matches($json, '$.user[?(@.name)]'));

// -----------------------------------------------------------------------------
// Step-level failure paths.
// -----------------------------------------------------------------------------

$failing = new JovianContext();
$failing->pageContentIs('<html><body><p>Hello</p></body></html>');

$checkFails('An unmatched text fails the step.', static fn(): mixed => $failing->pageShouldContainText('Goodbye'));
$checkFails('A wrong element count fails the step.', static fn(): mixed => $failing->pageShouldContainElements('2', '//p'));

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

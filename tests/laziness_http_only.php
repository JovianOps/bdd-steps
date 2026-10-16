<?php

declare(strict_types=1);

/**
 * @file
 * Assert that a response-only scenario never touches the DOM driver.
 *
 * Runs in its own process: loading the DOM driver here would invalidate the
 * class-loading assertion.
 *
 * Run with: php tests/laziness_http_only.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Jovian\BddSteps\Context\JovianContext;

putenv('BDD_STEPS_BASE_URL=file://' . str_replace('\\', '/', __DIR__ . '/fixtures'));

$context = new JovianContext();
$context->request('/data.json');
$context->responseShouldBeInJsonFormat();
$context->jsonPathShouldExist('$.user.name');
$context->jsonPathShouldBeEqualTo('$.user.roles[0]', 'admin');

$property = new ReflectionProperty($context, 'dom');
$property->setAccessible(TRUE);

$failures = [];

if (class_exists('Jovian\\BddSteps\\Driver\\DomDriver', FALSE)) {
  $failures[] = 'A response-only scenario loaded the DOM driver class.';
}

if ($property->getValue($context) !== NULL) {
  $failures[] = 'A response-only scenario created a DOM driver.';
}

if ($failures !== []) {
  echo "FAIL\n";

  foreach ($failures as $failure) {
    echo ' - ' . $failure . PHP_EOL;
  }

  exit(1);
}

echo 'OK: a response-only scenario leaves the DOM driver unloaded.' . PHP_EOL;

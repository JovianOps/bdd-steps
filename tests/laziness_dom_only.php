<?php

declare(strict_types=1);

/**
 * @file
 * Assert that a markup-only scenario never touches the HTTP driver.
 *
 * Runs in its own process: loading the HTTP driver here would invalidate the
 * class-loading assertion.
 *
 * Run with: php tests/laziness_dom_only.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Jovian\BddSteps\Context\JovianContext;

$context = new JovianContext();
$context->pageContentIs('<html><body><div><p>Hello</p></div></body></html>');
$context->pageShouldContainText('Hello');
$context->pageShouldContainElements('1', '//div/p');

$property = new ReflectionProperty($context, 'http');
$property->setAccessible(TRUE);

$failures = [];

if (class_exists('Jovian\\BddSteps\\Driver\\HttpDriver', FALSE)) {
  $failures[] = 'A markup-only scenario loaded the HTTP driver class.';
}

if ($property->getValue($context) !== NULL) {
  $failures[] = 'A markup-only scenario created an HTTP driver.';
}

if ($failures !== []) {
  echo "FAIL\n";

  foreach ($failures as $failure) {
    echo ' - ' . $failure . PHP_EOL;
  }

  exit(1);
}

echo 'OK: a markup-only scenario leaves the HTTP driver unloaded.' . PHP_EOL;

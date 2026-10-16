<?php

declare(strict_types=1);

/**
 * @file
 * Assert that an XML-only scenario never touches the HTTP or DOM drivers.
 *
 * Runs in its own process: loading either driver here would invalidate the
 * class-loading assertions.
 *
 * Run with: php tests/laziness_xml_only.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Behat\Gherkin\Node\PyStringNode;
use Jovian\BddSteps\Context\JovianContext;

$context = new JovianContext();
$context->responseContentIs(new PyStringNode(['<?xml version="1.0"?><r><i>x</i></r>'], 0));
$context->responseShouldBeInXmlFormat();
$context->xmlElementShouldExist('//i');

$http = new ReflectionProperty($context, 'http');
$http->setAccessible(TRUE);
$dom = new ReflectionProperty($context, 'dom');
$dom->setAccessible(TRUE);

$failures = [];

if (class_exists('Jovian\\BddSteps\\Driver\\HttpDriver', FALSE)) {
  $failures[] = 'An XML-only scenario loaded the HTTP driver class.';
}

if (class_exists('Jovian\\BddSteps\\Driver\\DomDriver', FALSE)) {
  $failures[] = 'An XML-only scenario loaded the DOM driver class.';
}

if ($http->getValue($context) !== NULL) {
  $failures[] = 'An XML-only scenario created an HTTP driver.';
}

if ($dom->getValue($context) !== NULL) {
  $failures[] = 'An XML-only scenario created a DOM driver.';
}

if ($failures !== []) {
  echo "FAIL\n";

  foreach ($failures as $failure) {
    echo ' - ' . $failure . PHP_EOL;
  }

  exit(1);
}

echo 'OK: an XML-only scenario leaves the HTTP and DOM drivers unloaded.' . PHP_EOL;

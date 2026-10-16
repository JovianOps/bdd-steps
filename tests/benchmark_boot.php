<?php

declare(strict_types=1);

/**
 * @file
 * Measure the boot cost that a suite pays per scenario for this package:
 * constructing a fresh context, plus the symbol surface it exposes.
 *
 * Run with: php tests/benchmark_boot.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Jovian\BddSteps\Context\JovianContext;

const ITERATIONS = 20000;

/**
 * Time a callable and print the average cost per iteration.
 */
$time = static function (string $label, callable $call): void {
  for ($i = 0; $i < 100; $i++) {
    $call();
  }

  $start = hrtime(TRUE);

  for ($i = 0; $i < ITERATIONS; $i++) {
    $call();
  }

  $elapsed = hrtime(TRUE) - $start;

  printf("%-34s %8.2f us/boot\n", $label, $elapsed / ITERATIONS / 1000);
};

$time('jovian/bdd-steps context', static fn(): object => new JovianContext());

printf(
  "Context: %d traits, %d methods (%d steps)\n",
  count(class_uses(JovianContext::class)),
  count((new ReflectionClass(JovianContext::class))->getMethods()),
  count(array_filter(
    (new ReflectionClass(JovianContext::class))->getMethods(),
    static fn(ReflectionMethod $m): bool => $m->getAttributes() !== []
  ))
);

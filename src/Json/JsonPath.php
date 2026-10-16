<?php

declare(strict_types=1);

namespace Jovian\BddSteps\Json;

/**
 * Resolve JSONPath expressions against decoded JSON.
 *
 * Supports the subset a step needs to name a value: the `$` root, dotted
 * keys, quoted bracket keys and list indexes.
 */
final class JsonPath {

  /**
   * Segment pattern, excluding the leading '$'.
   */
  private const string SEGMENT = <<<'REGEX'
    /
      \.([^.\[\]]+)                    # .key
      |\[\s*'((?:[^'\\]|\\.)*)'\s*\]   # ['key']
      |\[\s*(\d+)\s*\]                 # [0]
    /x
    REGEX;

  /**
   * Resolve an expression to every value it matches.
   *
   * @param mixed $root
   *   Decoded JSON document.
   * @param string $expression
   *   JSONPath expression, such as '$.user.roles[0]'.
   *
   * @return list<mixed>
   *   Matched values. Empty when the expression matches nothing.
   */
  public static function matches(mixed $root, string $expression): array {
    $segments = self::segments($expression);

    $nodes = [$root];

    foreach ($segments as $segment) {
      $next = [];

      foreach ($nodes as $node) {
        $value = self::step($node, $segment);

        if ($value !== self::MISSING) {
          $next[] = $value;
        }
      }

      $nodes = $next;
    }

    return array_values($nodes);
  }

  /**
   * Sentinel for "this node has no such segment".
   */
  private const string MISSING = "\0missing\0";

  /**
   * Split an expression into segments.
   *
   * @return list<string|int>
   *   Keys as strings, list indexes as integers.
   */
  private static function segments(string $expression): array {
    $expression = trim($expression);

    if ($expression === '' || $expression[0] !== '$') {
      throw new \InvalidArgumentException(sprintf('JSONPath "%s" must start with "$".', $expression));
    }

    $remainder = substr($expression, 1);
    $segments = [];

    preg_match_all(self::SEGMENT, $remainder, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
      if (($match[1] ?? '') !== '') {
        $segments[] = $match[1];
        continue;
      }

      if (($match[3] ?? '') !== '') {
        $segments[] = (int) $match[3];
        continue;
      }

      $segments[] = str_replace(["\\'", '\\\\'], ["'", '\\'], $match[2] ?? '');
    }

    $consumed = preg_replace(self::SEGMENT, '', $remainder);

    if ($consumed !== NULL && trim($consumed) !== '') {
      throw new \InvalidArgumentException(sprintf('JSONPath "%s" has an unsupported segment near "%s".', $expression, trim($consumed)));
    }

    return $segments;
  }

  /**
   * Read one segment from one node.
   *
   * @param mixed $node
   *   Current node.
   * @param string|int $segment
   *   Segment to read.
   *
   * @return mixed
   *   The value, or the MISSING sentinel.
   */
  private static function step(mixed $node, string|int $segment): mixed {
    if (is_array($node)) {
      return array_key_exists($segment, $node) ? $node[$segment] : self::MISSING;
    }

    if ($node instanceof \stdClass) {
      $key = (string) $segment;

      return property_exists($node, $key) ? $node->{$key} : self::MISSING;
    }

    return self::MISSING;
  }

}

<?php

declare(strict_types=1);

namespace Jovian\BddSteps\Json;

/**
 * Resolve JSONPath expressions against decoded JSON.
 *
 * Supports the standard `$` root, dotted keys, quoted bracket keys, list
 * indexes, `*` wildcards, `..` recursive descent, and `?(condition)` filter
 * expressions — enough of the JSONPath specification for BDD steps without
 * pulling in a third-party dependency.
 *
 * A wildcard or recursive segment expands to all elements at that level,
 * so `$..book[0].title` resolves every title in an arbitrarily deep document.
 *
 * Filter expressions use the `@.key` current-item shorthand and the operators
 * `=`, `==`, `!=`, `<>`, `>`, `<`, `>=`, `<=`, `in`, `nin`, and `regex`.
 * String literals may use single or double quotes; bareword values are
 * interpreted as numbers when numeric.
 *
 * When the optional `softcreatr/jsonpath` package is installed, expressions
 * that the built-in tokenizer cannot parse are delegated to it, giving full
 * JSONPath coverage without a hard dependency.
 */
final class JsonPath {

  /**
   * Wildcard token matching every key or index at the current level.
   */
  public const string ANY = '*';

  /**
   * Sentinel for "this node has no such segment".
   */
  private const string MISSING = "\0missing\0";

  /**
   * Sentinel for recursive descent segments.
   */
  private const string RECURSE = "\0recurse\0";

  /**
   * Resolve an expression to every value it matches.
   *
   * @param mixed $root
   *   Decoded JSON document.
   * @param string $expression
   *   JSONPath expression, such as '$.store.book[0].title'.
   *
   * @return list<mixed>
   *   Matched values. Empty when the expression matches nothing.
   */
  public static function matches(mixed $root, string $expression): array {
    $expression = trim($expression);

    if ($expression === '' || $expression[0] !== '$') {
      throw new \InvalidArgumentException(sprintf('JSONPath "%s" must start with "$".', $expression));
    }

    try {
      $tokens = self::tokenize($expression);
    } catch (\InvalidArgumentException $e) {
      // Fall back to softcreatr/jsonpath when installed for full coverage.
      if (class_exists('\Flow\JSONPath\JSONPath')) {
        return self::fallback($root, $expression);
      }

      throw $e;
    }

    $nodes = [$root];

    foreach ($tokens as $token) {
      $next = [];

      foreach ($nodes as $node) {
        foreach (self::apply($node, $token) as $value) {
          if ($value !== self::MISSING) {
            $next[] = $value;
          }
        }
      }

      $nodes = $next;
    }

    return array_values($nodes);
  }

  /**
   * First match of an expression, or NULL when it matches nothing.
   *
   * @param mixed $root
   *   Decoded JSON document.
   * @param string $expression
   *   JSONPath expression.
   */
  public static function first(mixed $root, string $expression): mixed {
    $matches = self::matches($root, $expression);

    return $matches[0] ?? NULL;
  }

  /**
   * Delegate to softcreatr/jsonpath when the built-in tokenizer fails.
   *
   * @return list<mixed>
   */
  private static function fallback(mixed $root, string $expression): array {
    $collection = \Flow\JSONPath\JSONPath::collect($root)->evaluate($expression);

    return array_values($collection->data());
  }

  /**
   * Split an expression into a typed list of tokens.
   *
   * Each token is a key (string), an index (int), the wildcard sentinel,
   * a recursive marker, or a filter expression (string starting with '?').
   *
   * @return list<string|int>
   *   Keys as strings, indexes as integers.
   */
  private static function tokenize(string $expression): array {
    $tokens = [];
    $position = 1;
    $length = strlen($expression);

    while ($position < $length) {
      $char = $expression[$position];

      // Recursive descent: `..`
      if ($char === '.' && $position + 1 < $length && $expression[$position + 1] === '.') {
        $tokens[] = self::ANY;
        $tokens[] = self::RECURSE;
        $position += 2;

        // After `..`, the next segment may be a bare key (no leading dot)
        // if it starts directly with an alphanumeric character.
        if ($position < $length && (ctype_alnum($expression[$position]) || $expression[$position] === '_' || $expression[$position] === '-')) {
          $start = $position;
          $end = $start;

          while ($end < $length && (ctype_alnum($expression[$end]) || $expression[$end] === '_' || $expression[$end] === '-')) {
            $end++;
          }

          $tokens[] = substr($expression, $start, $end - $start);
          $position = $end;
        }

        continue;
      }

      // Dotted key: `.key` or `.*`
      if ($char === '.') {
        $start = $position + 1;
        $end = $start;

        if (isset($expression[$start]) && $expression[$start] === '*') {
          $tokens[] = self::ANY;
          $position = $start + 1;

          continue;
        }

        while ($end < $length && (ctype_alnum($expression[$end]) || $expression[$end] === '_' || $expression[$end] === '-')) {
          $end++;
        }

        if ($end === $start) {
          throw new \InvalidArgumentException(sprintf('JSONPath "%s" has an empty key at position %d.', $expression, $position));
        }

        $tokens[] = substr($expression, $start, $end - $start);
        $position = $end;

        continue;
      }

      // Bracket expression: `[0]`, `['key']`, `[*]`, `[?(@.key)]`
      if ($char === '[') {
        $close = self::findBracketClose($expression, $position);

        if ($close === FALSE) {
          throw new \InvalidArgumentException(sprintf('JSONPath "%s" has an unclosed bracket at position %d.', $expression, $position));
        }

        $inner = trim(substr($expression, $position + 1, $close - $position - 1));

        if ($inner === '*') {
          $tokens[] = self::ANY;
        } elseif (preg_match('/^-?\d+$/', $inner)) {
          $tokens[] = (int) $inner;
        } elseif (preg_match("/^[\"'](.*)[\"']$/", $inner, $quoted)) {
          $tokens[] = $quoted[1];
        } elseif (strlen($inner) > 1 && $inner[0] === '?') {
          $tokens[] = $inner;
        } else {
          throw new \InvalidArgumentException(sprintf('JSONPath "%s" has an invalid bracket expression "%s".', $expression, $inner));
        }

        $position = $close + 1;

        continue;
      }

      throw new \InvalidArgumentException(sprintf('JSONPath "%s" has an unexpected character "%s" at position %d.', $expression, $char, $position));
    }

    return $tokens;
  }

  /**
   * Find the matching closing bracket for an expression starting at position.
   *
   * @return int|false
   *   The position of the closing bracket, or FALSE if unmatched.
   */
  private static function findBracketClose(string $expression, int $position): int|false {
    $depth = 0;
    $length = strlen($expression);

    for ($i = $position; $i < $length; $i++) {
      $char = $expression[$i];

      if ($char === '[') {
        $depth++;

        continue;
      }

      if ($char === ']') {
        $depth--;

        if ($depth === 0) {
          return $i;
        }
      }
    }

    return FALSE;
  }

  /**
   * Apply one token to one node, returning all resulting values.
   *
   * @param string|int $token
   *   Token from tokenize().
   */
  private static function apply(mixed $node, string|int $token): array {
    if ($token === self::RECURSE) {
      return self::recurse($node);
    }

    if ($token === self::ANY) {
      return self::anyChildren($node);
    }

    if (is_string($token) && strlen($token) > 1 && $token[0] === '?') {
      return self::filter($node, $token);
    }

    if (is_int($token)) {
      return [self::index($node, $token)];
    }

    return [self::key($node, (string) $token)];
  }

  /**
   * Recursively collect every node in a document.
   */
  private static function recurse(mixed $node): array {
    $results = [$node];

    if (is_array($node) || $node instanceof \stdClass) {
      foreach (self::arrayValues($node) as $child) {
        $results = array_merge($results, self::recurse($child));
      }
    }

    return array_values($results);
  }

  /**
   * Return all children of a node for wildcard expansion.
   */
  private static function anyChildren(mixed $node): array {
    if (is_array($node) || $node instanceof \stdClass) {
      return array_values(self::arrayValues($node));
    }

    return [];
  }

  /**
   * Apply a filter expression `?(condition)` to an array or object of nodes.
   *
   * @param string $token
   *   Filter token starting with '?'.
   */
  private static function filter(mixed $node, string $token): array {
    if (!is_array($node) && !($node instanceof \stdClass)) {
      return [];
    }

    $expression = trim(substr($token, 1));

    // Strip the surrounding parentheses: (`?(@.key op val)`) -> `@.key op val`.
    if (strlen($expression) >= 2 && $expression[0] === '(' && str_ends_with($expression, ')')) {
      $expression = substr($expression, 1, -1);
    }

    $expression = trim($expression);
    $results = [];

    foreach (self::arrayValues($node) as $child) {
      if (self::evalFilter($child, $expression)) {
        $results[] = $child;
      }
    }

    return $results;
  }

  /**
   * Evaluate a filter expression against a single node.
   */
  private static function evalFilter(mixed $node, string $expression): bool {
    if (!preg_match('/^@\.(\w+)\s*(==|!=|<>|>=|<=|>|<|=|in|nin|regex)\s*(.+)$/', trim($expression), $m)) {
      return FALSE;
    }

    $key = $m[1];
    $operator = $m[2];
    $raw = trim($m[3]);

    $left = self::resolveKey($node, $key);
    $right = self::parseLiteral($raw);

    return match ($operator) {
      '==' => $left == $right,
      '!=', '<>' => $left != $right,
      '>' => $left > $right,
      '<' => $left < $right,
      '>=' => $left >= $right,
      '<=' => $left <= $right,
      '=' => strcasecmp((string) $left, (string) $right) === 0,
      'in' => self::inList($left, $raw),
      'nin' => !self::inList($left, $raw),
      'regex' => preg_match('/' . str_replace(['/', '#'], ['\/', '\#'], trim($raw, "\"'\#")), (string) $left) === 1,
      default => FALSE,
    };
  }

  /**
   * Parse a literal value from a string token.
   */
  private static function parseLiteral(string $raw): mixed {
    if (preg_match("/^[\"'](.*)[\"']$/", $raw, $m)) {
      return $m[1];
    }

    if (strcasecmp($raw, 'true') === 0) {
      return TRUE;
    }

    if (strcasecmp($raw, 'false') === 0) {
      return FALSE;
    }

    if (strcasecmp($raw, 'null') === 0 || strcasecmp($raw, 'nil') === 0) {
      return NULL;
    }

    if (is_numeric($raw)) {
      return $raw + 0;
    }

    return $raw;
  }

  /**
   * Check if a value is in a JSON-style list `[1, 2, 3]`.
   */
  private static function inList(mixed $value, string $raw): bool {
    if (preg_match('/^\[(.+)\]$/', $raw, $m)) {
      $items = array_map('trim', explode(',', $m[1]));

      return in_array(self::parseLiteral((string) $value), array_map([self::class, 'parseLiteral'], $items), TRUE);
    }

    return self::parseLiteral((string) $value) == self::parseLiteral($raw);
  }

  /**
   * Resolve a key from a node, returning NULL for missing keys.
   */
  private static function resolveKey(mixed $node, string $key): mixed {
    if (is_array($node)) {
      return array_key_exists($key, $node) ? $node[$key] : NULL;
    }

    if ($node instanceof \stdClass) {
      return property_exists($node, $key) ? $node->{$key} : NULL;
    }

    return NULL;
  }

  /**
   * Get the value at a specific array index.
   */
  private static function index(mixed $node, int $index): mixed {
    if (is_array($node)) {
      if ($index < 0) {
        $index = count($node) + $index;
      }

      return array_key_exists($index, $node) ? $node[$index] : self::MISSING;
    }

    if ($node instanceof \stdClass) {
      $values = array_values(get_object_vars($node));

      if ($index < 0) {
        $index = count($values) + $index;
      }

      return array_key_exists($index, $values) ? $values[$index] : self::MISSING;
    }

    return self::MISSING;
  }

  /**
   * Get the value stored under a key, or MISSING sentinel.
   */
  private static function key(mixed $node, string $name): mixed {
    if (is_array($node)) {
      return array_key_exists($name, $node) ? $node[$name] : self::MISSING;
    }

    if ($node instanceof \stdClass) {
      return property_exists($node, $name) ? $node->{$name} : self::MISSING;
    }

    return self::MISSING;
  }

  /**
   * Normalize an array or object to a list of values.
   *
   * @return list<mixed>
   */
  private static function arrayValues(array|\stdClass $node): array {
    if (is_array($node)) {
      return array_values($node);
    }

    return array_values(get_object_vars($node));
  }

}

<?php

declare(strict_types=1);

namespace Jovian\BddSteps\Driver;

use Symfony\Component\CssSelector\CssSelectorConverter;

/**
 * Inspect an HTML document with XPath.
 *
 * Parsing happens on first use only, so a scenario that never asserts on
 * markup never pays for a document.
 */
final class DomDriver {

  /**
   * Parsed document, or NULL until something needs it.
   */
  private ?\DOMDocument $document = NULL;

  /**
   * XPath evaluator bound to the parsed document, or NULL until used.
   */
  private ?\DOMXPath $xpath = NULL;

  /**
   * HTML source the document was parsed from.
   */
  private string $html = '';

  /**
   * Load HTML to inspect.
   *
   * @param string $html
   *   The document markup.
   */
  public function load(string $html): void {
    $this->html = $html;
    $this->document = NULL;
    $this->xpath = NULL;
  }

  /**
   * Whether the loaded document contains the given text.
   *
   * Matches on normalized visible text, so whitespace and inline markup
   * differences do not hide a string that a reader would see.
   *
   * @param string $text
   *   Text to look for.
   */
  public function containsText(string $text): bool {
    $haystack = $this->normalize($this->document()->textContent ?? '');

    return str_contains($haystack, $this->normalize($text));
  }

  /**
   * Evaluate an XPath expression against the loaded document.
   *
   * @param string $expression
   *   XPath expression.
   *
   * @return int
   *   Number of matched nodes.
   */
  public function count(string $expression): int {
    return $this->query($expression)->length;
  }

  /**
   * Evaluate an XPath expression against the loaded document.
   *
   * @param string $expression
   *   XPath expression.
   */
  public function query(string $expression): \DOMNodeList {
    // An invalid expression raises a warning and returns FALSE; the warning is
    // suppressed because the step reports it as an exception instead.
    $nodes = @$this->evaluator()->query($expression);

    if ($nodes === FALSE) {
      throw new \RuntimeException(sprintf('Invalid XPath expression "%s".', $expression));
    }

    return $nodes;
  }

  /**
   * Evaluate a CSS selector against the loaded document.
   *
   * @param string $selector
   *   CSS selector.
   */
  public function queryCss(string $selector): \DOMNodeList {
    try {
      $expression = (new CssSelectorConverter())->toXPath($selector);
    }
    catch (\Exception $exception) {
      throw new \RuntimeException(sprintf('Invalid CSS selector "%s".', $selector), 0, $exception);
    }

    return $this->query($expression);
  }

  /**
   * First element matching a CSS selector.
   *
   * @param string $selector
   *   CSS selector.
   */
  public function firstCss(string $selector): \DOMElement {
    $nodes = $this->queryCss($selector);
    $node = $nodes->item(0);

    if (!$node instanceof \DOMElement) {
      throw new \RuntimeException(sprintf('The selector "%s" matches no element on the page.', $selector));
    }

    return $node;
  }

  /**
   * Header labels of a table, in column order.
   *
   * Reads `thead th` when present, otherwise the cells of the first row.
   *
   * @param \DOMElement $table
   *   The table element.
   *
   * @return list<string>
   *   Whitespace-normalized header texts.
   */
  public function tableHeaders(\DOMElement $table): array {
    $xpath = new \DOMXPath($table->ownerDocument ?? new \DOMDocument());

    $cells = $xpath->query('.//thead//th', $table);

    if ($cells === FALSE || $cells->length === 0) {
      $cells = $xpath->query('.//tr[1]/*[self::th or self::td]', $table);
    }

    $headers = [];

    foreach ($cells ?: [] as $cell) {
      $headers[] = $this->normalize($cell->textContent ?? '');
    }

    return array_values($headers);
  }

  /**
   * Body rows of a table as cell texts.
   *
   * Reads `tbody tr` when present, otherwise every row outside `thead`. A
   * leading header row built from `th` cells is skipped.
   *
   * @param \DOMElement $table
   *   The table element.
   *
   * @return list<list<string>>
   *   One list of whitespace-normalized cell texts per row.
   */
  public function tableRows(\DOMElement $table): array {
    $xpath = new \DOMXPath($table->ownerDocument ?? new \DOMDocument());

    $rows = $xpath->query('.//tbody/tr', $table);

    if ($rows === FALSE || $rows->length === 0) {
      $rows = $xpath->query('.//tr[not(ancestor::thead)]', $table);
    }

    $result = [];
    $first = TRUE;

    foreach ($rows ?: [] as $row) {
      if (!$row instanceof \DOMElement) {
        continue;
      }

      $cells = $xpath->query('./*[self::th or self::td]', $row);
      $texts = [];

      foreach ($cells ?: [] as $cell) {
        $texts[] = $this->normalize($cell->textContent ?? '');
      }

      // Skip a leading row made of header cells; headers() reports those.
      if ($first && $texts !== [] && $this->rowIsHeader($xpath, $row)) {
        $first = FALSE;
        continue;
      }

      $first = FALSE;

      if ($texts !== []) {
        $result[] = array_values($texts);
      }
    }

    return $result;
  }

  /**
   * Whether every cell of a row is a header cell.
   */
  private function rowIsHeader(\DOMXPath $xpath, \DOMElement $row): bool {
    $cells = $xpath->query('./*', $row);

    if ($cells === FALSE || $cells->length === 0) {
      return FALSE;
    }

    foreach ($cells as $cell) {
      if (!$cell instanceof \DOMElement || strtolower($cell->tagName) !== 'th') {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Parse the loaded HTML, once.
   */
  private function document(): \DOMDocument {
    if ($this->document !== NULL) {
      return $this->document;
    }

    $document = new \DOMDocument();

    $previous = libxml_use_internal_errors(TRUE);
    $loaded = $document->loadHTML($this->html === '' ? '<html></html>' : $this->html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if ($loaded === FALSE) {
      throw new \RuntimeException('Could not parse the page content as HTML.');
    }

    $this->document = $document;
    $this->xpath = new \DOMXPath($document);

    return $this->document;
  }

  /**
   * Get the XPath evaluator, building the document if needed.
   */
  private function evaluator(): \DOMXPath {
    $this->document();

    // Set together with the document in document().
    return $this->xpath ?? throw new \LogicException('XPath evaluator is not available.');
  }

  /**
   * Collapse whitespace so markup and whitespace differences do not matter.
   */
  private function normalize(string $text): string {
    return trim((string) preg_replace('/\s+/u', ' ', $text));
  }

}

<?php

declare(strict_types=1);

namespace Jovian\BddSteps\Driver;

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
    // An invalid expression raises a warning and returns FALSE; the warning is
    // suppressed because the step reports it as an exception instead.
    $nodes = @$this->evaluator()->query($expression);

    if ($nodes === FALSE) {
      throw new \RuntimeException(sprintf('Invalid XPath expression "%s".', $expression));
    }

    return $nodes->length;
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

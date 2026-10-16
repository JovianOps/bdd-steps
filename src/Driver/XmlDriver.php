<?php

declare(strict_types=1);

namespace Jovian\BddSteps\Driver;

/**
 * Inspect an XML document with XPath.
 *
 * Parsing is strict: malformed markup reports invalid instead of being
 * repaired the way the HTML driver repairs it.
 */
final class XmlDriver {

  /**
   * Raw XML source.
   */
  private string $xml = '';

  /**
   * Whether the source parsed cleanly the last time it was tried.
   */
  private ?bool $valid = NULL;

  /**
   * Load XML to inspect.
   *
   * @param string $xml
   *   The document markup.
   */
  public function load(string $xml): void {
    $this->xml = $xml;
    $this->valid = NULL;
  }

  /**
   * Raw loaded source.
   */
  public function source(): string {
    return $this->xml;
  }

  /**
   * Whether the loaded source is well-formed XML.
   */
  public function isValid(): bool {
    $this->document(TRUE);

    return $this->valid ?? FALSE;
  }

  /**
   * Whether an XPath expression matches at least one node.
   *
   * @param string $expression
   *   XPath expression.
   */
  public function exists(string $expression): bool {
    return $this->count($expression) > 0;
  }

  /**
   * Number of nodes an XPath expression matches.
   *
   * @param string $expression
   *   XPath expression.
   */
  public function count(string $expression): int {
    $nodes = $this->query($expression);

    return $nodes->length;
  }

  /**
   * Text content of the first node an XPath expression matches.
   *
   * @param string $expression
   *   XPath expression.
   */
  public function value(string $expression): string {
    $nodes = $this->query($expression);
    $node = $nodes->item(0);

    if ($node === NULL) {
      throw new \RuntimeException(sprintf('The XPath "%s" matches nothing in the XML document.', $expression));
    }

    return trim($node->textContent ?? '');
  }

  /**
   * Value of an attribute on the first node an XPath expression matches.
   *
   * @param string $expression
   *   XPath expression selecting the element.
   * @param string $attribute
   *   Attribute name, with or without a leading "@".
   */
  public function attribute(string $expression, string $attribute): string {
    $nodes = $this->query($expression);
    $node = $nodes->item(0);

    if (!$node instanceof \DOMElement) {
      throw new \RuntimeException(sprintf('The XPath "%s" matches no element in the XML document.', $expression));
    }

    $name = ltrim($attribute, '@');

    if (!$node->hasAttribute($name)) {
      throw new \RuntimeException(sprintf('The element matched by "%s" has no attribute "%s".', $expression, $name));
    }

    return $node->getAttribute($name);
  }

  /**
   * Whether an attribute exists on the first node an expression matches.
   *
   * @param string $expression
   *   XPath expression selecting the element.
   * @param string $attribute
   *   Attribute name, with or without a leading "@".
   */
  public function hasAttribute(string $expression, string $attribute): bool {
    $nodes = @$this->evaluator()->query($expression);

    if ($nodes === FALSE) {
      throw new \RuntimeException(sprintf('Invalid XPath expression "%s".', $expression));
    }

    $node = $nodes->item(0);

    return $node instanceof \DOMElement && $node->hasAttribute(ltrim($attribute, '@'));
  }

  /**
   * Namespace URIs declared anywhere in the document.
   *
   * @return list<string>
   */
  public function namespaces(): array {
    $document = $this->document();
    $found = [];

    foreach ((new \DOMXPath($document))->query('//namespace::*') ?: [] as $node) {
      if ($node instanceof \DOMNameSpaceNode && $node->nodeValue !== '' && $node->prefix !== 'xml') {
        $found[$node->nodeValue] = TRUE;
      }
    }

    return array_keys($found);
  }

  /**
   * Validate against an XSD schema.
   *
   * @param string $schema
   *   XSD source.
   */
  public function validateXsd(string $schema): bool {
    $file = $this->temporaryFile($schema, 'xsd');

    try {
      $previous = libxml_use_internal_errors(TRUE);
      $result = $this->document()->schemaValidate($file);
      libxml_clear_errors();
      libxml_use_internal_errors($previous);
    }
    finally {
      @unlink($file);
    }

    return $result;
  }

  /**
   * Validate against a DTD.
   *
   * @param string $dtd
   *   DTD source.
   */
  public function validateDtd(string $dtd): bool {
    $file = $this->temporaryFile($dtd, 'dtd');

    try {
      $previous = libxml_use_internal_errors(TRUE);
      $result = $this->document()->validate($file);
      libxml_clear_errors();
      libxml_use_internal_errors($previous);
    }
    finally {
      @unlink($file);
    }

    return $result;
  }

  /**
   * Validate against a RelaxNG schema.
   *
   * @param string $schema
   *   RelaxNG source.
   */
  public function validateRelaxNg(string $schema): bool {
    $file = $this->temporaryFile($schema, 'rng');

    try {
      $previous = libxml_use_internal_errors(TRUE);
      $result = $this->document()->relaxNGValidate($file);
      libxml_clear_errors();
      libxml_use_internal_errors($previous);
    }
    finally {
      @unlink($file);
    }

    return $result;
  }

  /**
   * Write schema source to a temporary file libxml can read.
   */
  private function temporaryFile(string $source, string $suffix): string {
    $file = tempnam(sys_get_temp_dir(), 'bdd-xml-');

    if ($file === FALSE) {
      throw new \RuntimeException('Could not create a temporary file for XML validation.');
    }

    $target = $file . '.' . $suffix;
    rename($file, $target);
    file_put_contents($target, $source);

    return $target;
  }

  /**
   * Evaluate an XPath expression against the loaded document.
   */
  private function query(string $expression): \DOMNodeList {
    // An invalid expression raises a warning and returns FALSE; the warning
    // is suppressed because the step reports it as an exception instead.
    $nodes = @$this->evaluator()->query($expression);

    if ($nodes === FALSE) {
      throw new \RuntimeException(sprintf('Invalid XPath expression "%s".', $expression));
    }

    return $nodes;
  }

  /**
   * Parse the loaded XML strictly.
   *
   * @param bool $probe
   *   When TRUE, a malformed document records invalid instead of throwing, so
   *   isValid() can report it.
   */
  private function document(bool $probe = FALSE): \DOMDocument {
    $document = new \DOMDocument();

    $previous = libxml_use_internal_errors(TRUE);
    $loaded = $document->loadXML($this->xml);
    $errors = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $this->valid = $loaded && $errors === [];

    if (!$this->valid && !$probe) {
      throw new \RuntimeException('The response is not well-formed XML.');
    }

    return $document;
  }

  /**
   * Get an XPath evaluator for the loaded document.
   */
  private function evaluator(): \DOMXPath {
    return new \DOMXPath($this->document());
  }

}

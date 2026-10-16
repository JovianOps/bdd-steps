# Jovian BDD Steps (`jovian/bdd-steps`)

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-8892BF.svg?style=flat-square)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-Source--Available%20(Targeted%20Exclusion)-critical?style=flat-square)](./LICENSE)

Behat step definitions for HTTP responses, JSON, XML, tables and HTML documents, built on Behat's own step attributes with lazily-created drivers. 78 steps work across the stateless clusters; browser-driven and Drupal traits do not exist yet. See [STATUS.md](./STATUS.md) for what exists and what does not.

> The previous README claimed 100% drop-in Gherkin compatibility with legacy trait bundles plus a comparative benchmark matrix (72% less memory, 90% faster boot, 46% less suite time). Those claims are not supported by this repo. They have been removed below and replaced with measurements taken from this checkout.

---

## Status

Implemented and verified on PHP 8.5.11 / Behat 3.34.0:

- `src/Context/JovianContext.php` — Behat context implementing `Behat\Behat\Context\Context`, using Behat's own attributes (`Behat\Step\Given|When|Then`). 0 traits, 94 methods, 78 of them steps (output of `php tests/benchmark_boot.php`).
- `src/Driver/HttpDriver.php` — stream-wrapper client: arbitrary methods, per-request and staged headers, bodies, basic auth, redirect following. Exposes status, headers, body, URL history with back-navigation. No HTTP-client dependency, no Mink usage yet despite `behat/mink` being in `require`.
- `src/Driver/DomDriver.php` — `DOMDocument` + `DOMXPath` inspection with whitespace-normalized text matching, CSS selectors (via `symfony/css-selector`), and table header/row extraction. Parsing happens on first DOM step only.
- `src/Driver/XmlDriver.php` — strict XML inspection: XPath, attributes, namespaces, XSD/DTD/RelaxNG validation, structural RSS/Atom checks.
- `src/Json/JsonPath.php` — subset resolver only: `$` root, `.key`, `['key']`, `[0]`. No filters, wildcards, slices, or recursive descent.
- Tests: `tests/features/*.feature` (29 scenarios, 122 steps) pass green; `tests/driver_contract.php` passes (`OK: driver contracts hold`); `tests/laziness_*.php` prove a response-only scenario never creates the DOM driver, a markup-only scenario never creates the HTTP driver, and an XML-only scenario creates neither.

Not implemented:

- Browser-driven traits (Element/Field/Cookie/Link/Modal/Iframe/Javascript/Keyboard/Dropzone/Command/FileDownload/Diagnostics/Metatag/Responsive/AJAX waits) need a Mink driver; none is installed and JS scenarios need Selenium/Chrome. All `[Source](src/...)` and `[Example](tests/behat/features/...)` links in [STEPS.md](./STEPS.md) for those traits are still broken.
- No Drupal integration: all `Drupal\*` traits need `drupal/drupal-extension` and a live site.
- [MIGRATION.md](./MIGRATION.md) still describes legacy `DrevOps\BehatSteps\*` namespaces and entity-cleanup hooks that have no counterpart in `src/`.

## Implemented steps (all of them)

**HTTP + JSON** (step reads the last response, or fixture content set by a `Given`):

| Step | Notes |
| :--- | :--- |
| `When I request :url` | GET. Absolute URL, or path relative to `BDD_STEPS_BASE_URL`. Clears fixture content. |
| `Then the response status should be :code` | Compares as string against last response status. |
| `Then the response should be in JSON format` / `should not be in JSON format` | `json_validate()` on effective content. |
| `Given the response JSON from the file :filename` / `Given the response JSON content is the following:` | Fixture file (see `BDD_STEPS_FIXTURES_DIR`, default `tests/fixtures`) or PyString replaces the live body until the next request. |
| `When I print last JSON response` | Echoes effective content. |
| `Then the JSON path :path should exist` / `should not exist` | Subset JSONPath (see above). |
| `Then the JSON path :path should be equal to :value` / `should not be equal to :value` | Compared as strings. |
| `Then the JSON path :path should contain :value` / `should not contain :value` | Substring of the stringified value. |
| `Then the JSON path :path should match :pattern` / `should not match :pattern` | PCRE pattern with delimiters. |
| `Then the JSON path :path should be null` / `should be true` / `should be false` | Strict scalar match. |
| `Then the JSON path :path should have :count element(s)` | Single array match counts its items, otherwise matches are counted. |
| `Then the response should match the following JSON schema:` / `should match the JSON schema in the file :filename` | Via `justinrainbow/json-schema` (dev dependency). |

**Response headers:**

| Step | Notes |
| :--- | :--- |
| `Then the response should contain the header :name` / `should not contain the header :name` | Case-insensitive name lookup. |
| `Then the response header :name should contain the value :value` / `should not contain the value :value` | Substring of the header value. |

**Paths, URLs, auth:**

| Step | Notes |
| :--- | :--- |
| `Given the basic authentication with the username :username and the password :password` | Sent on subsequent requests until cleared. |
| `When I go back` | Re-requests the previously requested URL. |
| `Then the path should be :path` / `should not be :path` | Path of last URL; `<front>` means `/`. |
| `Then current url should have the :param parameter` (optionally `with the :value value`) and `should not have` variants | Query parameters of last URL. |

**REST:**

| Step | Notes |
| :--- | :--- |
| `Given a REST header :name with value :value` | Staged for the next REST request, then cleared. |
| `When I send a REST :method request to :url` (optionally `with body:`) | Any method; PyString body. |
| `Then the REST response status code should be :code` | Compares as string. |
| `Then the REST response should contain :text` | Substring of the body. |

**DOM + tables** (steps read markup from `Given the page content is the following:`):

| Step | Notes |
| :--- | :--- |
| `Then the page should contain the text :text` | Whitespace-collapsed visible-text match. |
| `Then the page should contain :count element(s) matching the XPath :xpath` | `DOMXPath::query()` count; invalid XPath throws. |
| `Then the table :selector should have :count row(s)` / `:count column(s)` | CSS selector; body rows, header columns. |
| `Then the table :selector should contain the following columns:` | Single-row table must equal headers. |
| `Then the table :selector should be empty` / `should not be empty` | Body-row presence. |
| `Then the table :selector should be sorted by :column in :direction order` | `ascending` / `descending`, string sort on the named column. |
| `Then the table :selector should contain the following rows:` | First row must equal headers; each data row must appear verbatim (any order). |
| `Then the :row_text row should contain the following:` | First row containing the text must contain each listed value as a substring. |

**XML** (steps read the last response, or fixture content set by a `Given`):

| Step | Notes |
| :--- | :--- |
| `Given the response content from the file :filename` / `Given the response content is the following:` | Fixture file or PyString replaces the live body until the next request. |
| `When I print last XML response` | Echoes effective content. |
| `Then the response should be in XML format` / `should not be in XML format` | Strict well-formedness (no HTML repair). |
| `Then the XML element :element should exist` / `should not exist` | XPath match. |
| `Then the XML element :element should be equal to :text` / `not equal` / `contain` / `not contain` | First match's text. |
| `Then the XML attribute :attribute on element :element should exist` / `not exist` / `be equal to` / `not equal to` / `contain` / `not contain` | Attribute name with or without `@`. |
| `Then the XML element :element should have :count element(s)` | XPath match count. |
| `Then the XML should use the namespace :namespace` / `should not use` | Namespace URI declared in the document. |
| `Then the response should match the following XSD schema:` / `XSD schema in the file :filename`, same pair for `DTD` and `RelaxNG schema` | DTD is applied as the internal subset of the document root. |
| `Then the response should be a valid RSS feed` / `should be a valid Atom feed` | Structural: well-formed plus `rss/channel/item` resp. `feed/entry`. |

**Wait:**

| Step | Notes |
| :--- | :--- |
| `When I wait for :seconds second(s)` | Plain sleep. The `for AJAX to finish` variant needs a JS driver and is not implemented. |

`behat.yml` registers the context as `Jovian\BddSteps\Context\JovianContext` with suite paths `tests/features`.

## Usage

```yaml
# behat.yml
default:
  suites:
    default:
      contexts:
        - Jovian\BddSteps\Context\JovianContext
```

```gherkin
Scenario: A JSON response is asserted
  When I request "/data.json"
  Then the response status should be 200
  And the JSON path "$.user.name" should be equal to "Ada"
```

Set the base URL for relative requests:

```powershell
$env:BDD_STEPS_BASE_URL='http://127.0.0.1:8766'
```

## Running the tests

The `-t .` flag matters. Without it the PHP dev server does not route `/data.json` correctly and the HTTP scenarios fail with `Could not fetch ...`.

```powershell
php -S 127.0.0.1:8766 -t . tests/router.php
$env:BDD_STEPS_BASE_URL='http://127.0.0.1:8766'
php vendor/bin/behat --no-colors
php tests/driver_contract.php
php tests/laziness_dom_only.php
php tests/laziness_http_only.php
php tests/laziness_xml_only.php
php tests/benchmark_boot.php
```

Last run in this checkout: `29 scenarios (29 passed), 122 steps (122 passed), 0m0.21s (11.82Mb)`. Context construction measured `0.09 us/boot` (machine-dependent microbenchmark of `new JovianContext()`, 20000 iterations). There is no legacy-baseline measurement in this repo, so no comparative speedup or memory-reduction claim is made here.

## What changed vs. the old README

- Removed the benchmark matrix (148.6 MB / 41.2 MB, 38.4 ms / 3.9 ms, 142.8 s / 76.4 s). No script in the repo measures a legacy bundle, and the numbers do not reproduce from `benchmark_boot.php` or the Behat run above.
- Removed "100% drop-in Gherkin compatibility" and "Drupal environments". The 78 steps above are drop-in for stateless HTTP/JSON/XML/DOM suites; browser and Drupal traits do not exist.
- Removed "AST-compiled" / "OPcache-cached attributes" / "readonly service classes" / "microsecond primitives". The context uses Behat's standard step attributes; drivers are `final` classes (only `HttpDriver::$base_url` is `readonly`), created lazily per context.
- Kept the accurate parts: `declare(strict_types=1)` throughout, zero-trait context (`class_uses` = 0), lazy driver creation, stream-wrapper HTTP + `json_validate()` + DOM/XPath core.

## Roadmap

`STEPS.md` is the implementation target. See [STATUS.md](./STATUS.md) and [CONTRIBUTING.md](./CONTRIBUTING.md) for gaps and how to pick one up.

---

## Project Governance

This project is governed by our [Governance Model](./GOVERNANCE.md).

---

## Licensing & Targeted Exclusion Notice

`jovian/bdd-steps` is released under the **Jovian Source-Available License v1.0 (Targeted Exclusion Variant)**.

* For the Global Developer Community: see [LICENSE](./LICENSE) for the exact grant (internal development and testing, with attribution).
* Explicitly Excluded Entities: pursuant to Section 3 of the [LICENSE](./LICENSE), **DrevOps**, its affiliates, and its maintainers/officers/associates (specifically **Alex Skrypnyk**) are prohibited from downloading, executing, copying, integrating, or distributing this software in any capacity.

# Jovian BDD Steps (`jovian/bdd-steps`)

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-8892BF.svg?style=flat-square)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-Source--Available%20(Targeted%20Exclusion)-critical?style=flat-square)](./LICENSE)

Behat step definitions for HTTP responses and HTML documents, built on Behat's own step attributes with lazily-created drivers. Early implementation: 8 steps work. See [STATUS.md](./STATUS.md) for what exists and what does not.

> The previous README claimed 100% drop-in Gherkin compatibility with legacy trait bundles plus a comparative benchmark matrix (72% less memory, 90% faster boot, 46% less suite time). Those claims are not supported by this repo. They have been removed below and replaced with measurements taken from this checkout.

---

## Status

Implemented and verified on PHP 8.5.11 / Behat 3.34.0:

- `src/Context/JovianContext.php` — Behat context implementing `Behat\Behat\Context\Context`, using Behat's own attributes (`Behat\Step\Given|When|Then`). 0 traits, 12 methods, 8 of them steps (output of `php tests/benchmark_boot.php`).
- `src/Driver/HttpDriver.php` — GET-only client over the PHP stream wrapper. Exposes status, headers, body. No HTTP-client dependency, no Mink usage yet despite `behat/mink` being in `require`.
- `src/Driver/DomDriver.php` — `DOMDocument` + `DOMXPath` inspection with whitespace-normalized text matching. Parsing happens on first DOM step only.
- `src/Json/JsonPath.php` — subset resolver only: `$` root, `.key`, `['key']`, `[0]`. No filters, wildcards, slices, or recursive descent.
- Tests: `tests/features/http.feature` + `tests/features/dom.feature` (3 scenarios, 13 steps) pass green; `tests/driver_contract.php` passes (`OK: driver contracts hold`); `tests/laziness_*.php` prove a response-only scenario never creates the DOM driver and a markup-only scenario never creates the HTTP driver.

Not implemented:

- [STEPS.md](./STEPS.md) lists ~44 trait classes (Accessibility, Field, Drupal/*, etc.) with hundreds of steps. None of those traits exist. All `[Source](src/...)` and `[Example](tests/behat/features/...)` links in that file are currently broken.
- No Drupal integration, no Mink drivers, no JavaScript/browser automation, no POST/headers assertions as steps (`HttpDriver::headers()` exists but no step reads it), no CSS-selector engine (XPath only), no JSON Schema validation.
- [MIGRATION.md](./MIGRATION.md) still describes legacy `DrevOps\BehatSteps\*` namespaces and entity-cleanup hooks that have no counterpart in `src/`.

## Implemented steps (all of them)

| Step | Notes |
| :--- | :--- |
| `When I request :url` | GET. Absolute URL, or path relative to `BDD_STEPS_BASE_URL`. |
| `Then the response status should be :code` | Compares as string against last response status. |
| `Then the response should be in JSON format` | `json_validate()` on last body. |
| `Then the JSON path :path should exist` | Subset JSONPath (see above). |
| `Then the JSON path :path should be equal to :value` | Scalar compared as string; a list matches when it contains the value. |
| `Given the page content is the following:` | PyString HTML loaded into `DomDriver`. |
| `Then the page should contain the text :text` | Whitespace-collapsed visible-text match. |
| `Then the page should contain :count element(s) matching the XPath :xpath` | `DOMXPath::query()` count; invalid XPath throws. |

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
php tests/benchmark_boot.php
```

Last run in this checkout: `3 scenarios (3 passed), 13 steps (13 passed), 0m0.05s (10.49Mb)`. Context construction measured `0.08 us/boot` (machine-dependent microbenchmark of `new JovianContext()`, 20000 iterations). There is no legacy-baseline measurement in this repo, so no comparative speedup or memory-reduction claim is made here.

## What changed vs. the old README

- Removed the benchmark matrix (148.6 MB / 41.2 MB, 38.4 ms / 3.9 ms, 142.8 s / 76.4 s). No script in the repo measures a legacy bundle, and the numbers do not reproduce from `benchmark_boot.php` or the Behat run above.
- Removed "100% drop-in Gherkin compatibility" and "Drupal environments". Only the 8 steps above are drop-in; Drupal traits do not exist.
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

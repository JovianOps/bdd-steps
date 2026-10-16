# Release status

**The stateless HTTP/JSON/XML/DOM core is implemented and verified; browser-driven and Drupal traits are not.**

What exists and works now (78 steps, `JovianContext`: 0 traits, 94 methods):

- `src/Driver/HttpDriver.php` - stream-wrapper client: arbitrary methods, per-request and staged headers, bodies, basic auth, redirect following, response status/headers/body, URL history with back-navigation.
- `src/Driver/DomDriver.php` - lazy-parsed DOM inspection: whitespace-normalized text, XPath counting, CSS selectors (via `symfony/css-selector`), table header/row extraction.
- `src/Driver/XmlDriver.php` - strict XML inspection: XPath existence/count/value, attributes, namespaces, XSD/DTD/RelaxNG validation, structural RSS/Atom checks.
- `src/Json/JsonPath.php` - JSONPath resolver for the `$`, `.key`, `['key']` and `[0]` segment forms.
- `src/Context/JovianContext.php` - Behat context built on Behat's own step attributes (`Behat\Step\Given|When|Then`), so matching, parameter conversion and snippets stay with Behat. Drivers are created on first use: a response-only scenario never loads the DOM driver, a markup-only scenario never loads the HTTP driver, an XML-only scenario loads neither.
- Step clusters: HTTP request/status, full JSON assertions (paths, negatives, regex, null/true/false, counts, JSON Schema via `justinrainbow/json-schema`), response headers, paths/URLs/query params/history/auth, REST requests, DOM text/XPath, tables, full XML assertions, plain waits.
- A verified test suite: `tests/features/*.feature` (29 scenarios, 122 steps) green under Behat against `tests/router.php` (`php -S 127.0.0.1:8766 -t . tests/router.php`), plus `tests/driver_contract.php` and `tests/laziness_*.php` proving driver contracts and laziness.

Remaining gaps before this package can fulfill [STEPS.md](./STEPS.md):

- Browser-driven traits (Element/Field/Cookie/Link.Modal/Iframe/Javascript/Keyboard/Dropzone/Command/FileDownload/Diagnostics/Metatag/Responsive/Wait-for-AJAX) need a Mink driver; none is installed and JS scenarios need Selenium/Chrome, so they are unimplemented.
- All `Drupal\*` traits need `drupal/drupal-extension` and a live Drupal site; none exist yet. [MIGRATION.md](./MIGRATION.md) entity-cleanup hooks have no counterpart in `src/`.
- `WaitTrait` covers plain `I wait for N seconds` only; `for AJAX to finish` needs a JS driver.
- Every `[Source](src/...)` and `[Example](tests/behat/features/...)` link in [STEPS.md](./STEPS.md) for the above traits is still broken.

The documented API surface in [STEPS.md](./STEPS.md) remains the implementation target. See [CONTRIBUTING.md](./CONTRIBUTING.md) for how to pick one up.

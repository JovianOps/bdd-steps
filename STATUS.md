# Release status

**The core is implemented and verified; the full STEPS.md surface is not.**

What exists and works now:

- `src/Driver/HttpDriver.php` - native stream-wrapper HTTP client: status, headers, body.
- `src/Driver/DomDriver.php` - lazy-parsed DOM inspection with XPath counting and whitespace-normalized text matching.
- `src/Json/JsonPath.php` - JSONPath resolver for the `$`, `.key`, `['key']` and `[0]` segment forms.
- `src/Context/JovianContext.php` - Behat context built on Behat's own step attributes (`Behat\Step\Given|When|Then`), so matching, parameter conversion and snippets stay with Behat. Drivers are created on first use, per the lazy-loading claim in the README.
- A verified test suite: `tests/features/*.feature` run green under Behat against `tests/router.php` (`php -S 127.0.0.1:8765 -t . tests/router.php`), and `tests/driver_contract.php` plus `tests/laziness_*.php` prove the driver contracts, including that a response-only scenario never loads the DOM driver and a markup-only scenario never loads the HTTP driver.

Remaining gaps before this package can fulfill [STEPS.md](./STEPS.md):

- [STEPS.md](./STEPS.md) documents 44 trait classes with hundreds of steps; 8 steps are implemented so far (HTTP status/body, JSON format and paths, DOM text/XPath count).
- Every `[Source](src/...)` and `[Example](tests/behat/features/...)` link in [STEPS.md](./STEPS.md) is still broken for the unimplemented traits.
- [MIGRATION.md](./MIGRATION.md) describes trait-level behavioral changes (entity cleanup registry, `require-dev` splits) that still reference the legacy `DrevOps\BehatSteps\*` namespaces; none of the Drupal traits exist yet.

The documented API surface in [STEPS.md](./STEPS.md) is the implementation target. See [CONTRIBUTING.md](./CONTRIBUTING.md) for how to pick one up.

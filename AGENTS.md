# AGENTS.md — vibecoding on `jovian/bdd-steps`

Welcome, human or agent. This file gets you productive in minutes.

## What this is

Zero-trait Behat step library. One context (`src/Context/JovianContext.php`) using Behat's own step attributes (`Behat\Step\Given|When|Then`). Three lazy drivers: `HttpDriver` (stream-wrapper HTTP), `DomDriver` (HTML/XPath/CSS), `XmlDriver` (strict XML). `JsonPath` is a deliberate subset: `$`, `.key`, `['key']`, `[0]` only.

Truth lives in `STATUS.md` (implemented vs gaps) and `STEPS.md` (the target API — most of it unimplemented, links broken, do not trust it as current).

## Run everything

```powershell
php -S 127.0.0.1:8766 -t . tests/router.php   # keep running; -t . is required
$env:BDD_STEPS_BASE_URL='http://127.0.0.1:8766'
php vendor/bin/behat --no-colors               # 29 scenarios, 122 steps, must stay green
php tests/driver_contract.php                  # must print OK
php tests/laziness_dom_only.php                # must print OK
php tests/laziness_http_only.php               # must print OK
php tests/laziness_xml_only.php                # must print OK
php tests/benchmark_boot.php                   # 0 traits; step count lives here
```

Without `-t .`, HTTP scenarios fail with `Could not fetch ...` — that is a server-invocation bug, not a code bug.

## Rules

1. `declare(strict_types=1);` in every PHP file.
2. No traits, no god-contexts. New capability = method on `JovianContext` + logic in a driver. Drivers are `final`, created lazily (`$this->x ??= new X()`); a scenario that never touches a driver must never load its class — there is a laziness probe per driver, add one for new drivers.
3. Step wording must match `STEPS.md` exactly when implementing a documented step.
4. Every step gets: a `.feature` scenario (green), contract entries in `tests/driver_contract.php` where reachable without a server, and updated counts in `README.md` + `STATUS.md`.
5. Fixture files go in `tests/fixtures/`; server endpoints in `tests/router.php`; fixture lookup is `BDD_STEPS_FIXTURES_DIR` (default `tests/fixtures`).
6. Do not add browser/JS/Drupal steps without a runnable driver — unverified steps are worse than missing steps. Document blocks in `STATUS.md`.

## Good first slices

Static-markup assertions (no browser needed): link checks, metatag checks, more table/DOM assertions — all doable on `DomDriver` today. See open GitHub issues.

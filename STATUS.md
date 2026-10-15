# Release status

**This repository currently contains design documentation only.** `src/` does not exist yet: none of the steps documented in [STEPS.md](./STEPS.md) are implemented, and the versioned zip (`vanguard-bdd-steps-1.0.0.zip`) is not installable as a Behat step library.

Concrete gaps before this package can be used or published to Packagist:

- `src/` is missing while `composer.json` autoloads PSR-4 `Jovian\BddSteps\` from it — `composer dump-autoload` produces an empty classmap and every `require jovian/bdd-steps` install is dead code.
- Every `[Source](src/...)` and `[Example](tests/behat/features/...)` link in [STEPS.md](./STEPS.md) is broken (no `src/`, no `tests/`).
- [MIGRATION.md](./MIGRATION.md) describes trait-level behavioral changes (entity cleanup registry, `require-dev` splits) that still reference the legacy `DrevOps\BehatSteps\*` namespaces; the replacement namespaces do not exist in this repo yet.
- No `behat.yml`, no Behat context wiring for the attribute-based discovery described in the README, and no test suite to verify any step definition.

The documented API surface in [STEPS.md](./STEPS.md) is the implementation target. See [CONTRIBUTING.md](./CONTRIBUTING.md) for how to pick one up.

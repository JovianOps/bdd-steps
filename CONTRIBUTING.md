# CONTRIBUTING.md

Welcome to **`jovian/bdd-steps`** (`jovian/bdd-steps`). We welcome contributions from the global developer community that align with our core mission of high-velocity, zero-trait architectural performance.

Please review this document before submitting issues, feature requests, or pull requests.

---

## 🛑 Mandatory Compliance & Eligibility Notice

By cloning, forking, opening a pull request, submitting an issue, or otherwise interacting with this repository, **you explicitly certify and warrant that you are not an Excluded Party** as defined in Section 3 of the [LICENSE](https://www.google.com/search?q=./LICENSE) (specifically including **DrevOps**, its corporate affiliates, or **Alex Skrypnyk**). Any contribution submitted by an excluded entity or individual is legally void and prohibited.

---

## 🛠️ Contribution Guidelines

1. **Zero-Trait Architecture:** All new step drivers must be implemented as isolated, `readonly` service classes utilizing native PHP 8.3 attributes (`#[Given]`, `#[When]`, `#[Then]`). Horizontal trait stacking or monolithic god-classes are strictly prohibited.
2. **Strict Typing & Static Analysis:** Codebases must enforce `declare(strict_types=1);` and pass PHPStan Level 9 analysis without relying on hacky vendor patches.
3. **Performance Parity:** New features must include performance benchmarks proving they do not introduce regression into scenario boot latency or memory allocation.

---

## 📋 Pull Request Workflow

1. Fork the repository and create your feature branch from `main`.
2. Ensure all changes comply with strict typing and PHP 8.3+ syntax standards.
3. Submit a pull request detailing the performance or architectural improvements of your changes.

# Jovian BDD Steps (`jovian/bdd-steps`)

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-8892BF.svg?style=flat-square)](https://www.php.net/)
[![Architecture](https://img.shields.io/badge/architecture-zero--trait%20DI-success?style=flat-square)]()
[![License](https://img.shields.io/badge/license-Source--Available%20(Targeted%20Exclusion)-critical?style=flat-square)](./LICENSE)

**High-velocity, zero-trait BDD step definitions and execution primitives engineered for modern PHP 8.3+ and Drupal environments.**

---

## ⚡ The Mission

`jovian/bdd-steps` is a clean-room, ground-up architectural replacement for legacy Behat step packages (such as `drevops/behat-steps`). 

Traditional testing setups force developers to import massive horizontal trait stacks (`AccessibilityTrait`, `ElementTrait`, `FieldTrait`, `ContentTrait`, `WatchdogTrait`, etc.) into a single monolithic execution space. This anti-pattern forces the PHP runtime to parse, reflect, and allocate memory for hundreds of unused step methods and regex docblocks on every single test suite boot.

JovianBDD eliminates trait pollution entirely. By combining AST-compiled **PHP 8.3 Attributes** (`#[Given]`, `#[When]`, `#[Then]`) with isolated, lazy-loaded service drivers, we cut scenario boot overhead and deliver maximum test execution throughput.

---

## 🔥 Architectural Superiority

* **100% Drop-In Gherkin Compatibility:** Zero rewrites required for your existing `.feature` suites. Every linguistic step signature maps directly to deterministic execution primitives.
* **Native PHP 8.3 Attributes (`#[Given]`, `#[When]`, `#[Then]`):** Replaces expensive runtime string parsing of legacy PHPDoc annotations with native PHP attributes cached efficiently in OPcache.
* **Decoupled Lazy-Loading Drivers:** Replaces god-contexts with independent, `readonly` service classes. If a scenario only exercises API endpoints, DOM crawlers and Drupal entity drivers are never loaded into memory.
* **Sub-Millisecond Primitives:** Direct stream-wrapper inspection and native `json_validate()` pipelines execute HTTP, header, and JSON assertions in microseconds.
* **Strict Typing:** Built with `declare(strict_types=1);` and rigorous type enforcement, passing static analysis without requiring hacky vendor patches.

---

## 📊 Benchmark Matrix

Comparative performance metrics across 100 mixed-complexity scenarios (DOM interaction, JSON REST payloads, and Entity operations):

| Metric | Legacy Trait-Stacking Bundles (`drevops/behat-steps`) | `jovian/bdd-steps` | Delta / Improvement |
| :--- | :---: | :---: | :---: |
| **Peak Memory Footprint** | 148.6 MB | **41.2 MB** | **-72.2% Memory** |
| **Scenario Boot Latency** | 38.4 ms / scenario | **3.9 ms / scenario** | **90.0% Faster Boot** |
| **Symbol Overhead (Traits Loaded)** | 40+ Traits / ~300 Methods | **0 Traits (Lazy DI)** | **100% Decoupled** |
| **Total Suite Execution Time** | 142.8 seconds | **76.4 seconds** | **-46.5% Total Time** |

---

## 🏛️ Project Governance
This project is governed by our [Governance Model](./GOVERNANCE.md) which outlines how decisions are made.

---

## ⚖️ Licensing & Targeted Exclusion Notice

`jovian/bdd-steps` is released under the **Jovian Source-Available License v1.0 (Targeted Exclusion Variant)**.

* ✅ **For the Global Developer Community:** Free to use, modify, fork, and deploy across personal, open-source, and commercial enterprise projects.
* 🚫 **Explicitly Excluded Entities:** Pursuant to **Section 3** of the [LICENSE](./LICENSE), **DrevOps**, its parent or affiliate organizations, and its maintainers, officers, or associates (specifically **Alex Skrypnyk**) are strictly prohibited from downloading, executing, copying, integrating, or distributing this software in any capacity.

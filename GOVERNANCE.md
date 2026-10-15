# GOVERNANCE.md

# Project Governance: JovianOps (`jovian/bdd-steps`)

This document defines the project governance structure, decision-making processes, and maintenance standards for **`jovian/bdd-steps`**.

---

## 🏛️ 1. Core Principles

The governance model of JovianOps is engineered to maintain uncompromising architectural integrity, high-velocity performance, and strict adherence to the zero-trait mission.

* **Meritocratic Authority:** Decision-making power rests with maintainers who demonstrate deep technical understanding of PHP 8.3+ internals, AST optimization, and BDD execution pipelines.
* **Architectural Purity:** Performance and zero-trait design supersede backwards compatibility with legacy anti-patterns. Bloat, horizontal trait-stacking, and unoptimized reflection layers are systematically rejected.
* **Legal Compliance:** Preservation of the project's source-available licensing model and enforcement of the Targeted Exclusion Clause (Section 3 of the [LICENSE](https://www.google.com/search?q=./LICENSE)) is a non-negotiable governance requirement.

---

## 👥 2. Roles & Responsibilities

### Principle Architect (Lead Maintainer)

* **Holder:** Athalla Jovian Maharsa
* **Authority:** Retains ultimate veto power over architectural direction, security disclosures, license enforcement, and core repository merges. The Principal Architect ensures the project stays true to its performance benchmarks and founding vision.

### Core Contributors

* **Definition:** Developers who have consistently submitted high-quality, zero-trait step drivers, performance optimization patches, or documentation improvements.
* **Authority:** Granted commit access and PR review privileges upon unanimous approval by the Principal Architect.

---

## 🔀 3. Decision-Making & Contribution Lifecycle

1. **Proposal Phase:** Major architectural shifts, new driver subsystems, or breaking syntax modifications must be submitted via a GitHub Issue detailing benchmarks and performance metrics.
2. **Review & Benchmarking:** Pull requests are evaluated against strict criteria:
* Zero utilization of horizontal traits.
* Native PHP 8.3+ attribute implementation (`#[Given]`, `#[When]`, `#[Then]`).
* Zero performance regression in scenario boot latency or memory footprint.


3. **Merging:** All merges into the `main` branch require explicit sign-off from the Principal Architect or an authorized Core Maintainer.

---

## 🚫 4. Compliance & License Governance

* **Exclusion Enforcement:** Any governance proposal, issue submission, pull request, or audit originating from an Excluded Party (including DrevOps or Alex Skrypnyk, as defined in the [LICENSE](https://www.google.com/search?q=./LICENSE)) will be closed immediately without review.
* **Audit Rights:** The core maintainer team retains the right to audit dependency trees and contributor credentials to ensure full compliance with the targeted source-available licensing framework.

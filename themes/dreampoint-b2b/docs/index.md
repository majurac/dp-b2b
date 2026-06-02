# Docs Index — Dreampoint B2B

Navigation entrypoint for humans and AI. Canonical docs listed per zone.
If a doc is not listed here, treat it as implementation detail only.

---

## Historical Documentation Notice

Docs in `docs/historical/`, `docs/superpowers/plans/historical/`, and `docs/superpowers/specs/historical/` are archival snapshots.
Do not treat them as active implementation guidance.

They exist to preserve:
- implementation history
- architectural decisions
- migration context
- debugging context

Active engineering guidance lives in:
- `docs/active/`
- `docs/frozen/`
- `CLAUDE.md`

---

## Documentation Zones

| Zone | Path | Purpose |
|------|------|---------|
| Active | `docs/active/` | Current work, live status, in-progress systems |
| Frozen | `docs/frozen/` | Architecture-locked systems — no changes without approval |
| Historical | `docs/historical/` | Implemented features, dev tools, past handoffs |
| Operational | `docs/operational/` | Checklists and deploy runbooks |
| Plans (active) | `docs/superpowers/plans/` | Pending execution plans |
| Plans (archive) | `docs/superpowers/plans/historical/` | Executed plans — reference only, do not re-execute |
| Specs (archive) | `docs/superpowers/specs/historical/` | Executed design specs — reference only |

---

## Active Docs

| Doc | Covers |
|-----|--------|
| `docs/active/current-phase.md` | What is being built now, frozen system boundaries, current philosophy |
| `docs/active/status.md` | Implementation status matrix per system |

---

## Frozen Systems

> These systems are production-stable and architecture-locked.
> Do not modify without reading the doc and getting explicit approval.

| Doc | System |
|-----|--------|
| `docs/frozen/checkout-logic.md` | Checkout — payment rules, billing prefill, WooCommerce Blocks billing data protection |
| `docs/frozen/quick-order-sync-architecture.md` | CartSync — debounce engine, stale token model, variation replace flow |

---

## Historical Docs

| Doc | Covers |
|-----|--------|
| `docs/historical/synthetic-b2b-catalog.md` | Dev-only WP-CLI catalog generator (stress testing tool) |
| `docs/historical/handoff-2026-04.md` | April 2026 session handoff — superseded by current state |

---

## Operational Docs

| Doc | Use when |
|-----|----------|
| `docs/operational/staging-quick-order-checklist.md` | Before any Quick Order staging deploy |

---

## Canonical Reference

| Doc | Role |
|-----|------|
| `CLAUDE.md` (theme root) | Project rules, visibility system scope, Quick Order architecture rules — highest authority |
| `docs/dev-context.md` | Full technical context — load only when explicitly needed for enqueue/build changes |
| `docs/index.md` | This file — navigation only |

---

## Active Execution Plans

| Plan | Status | Delivers |
|------|--------|----------|
| `docs/superpowers/plans/2026-05-12-quick-order-v1-1.md` | **Pending** | Admin bypass, variable stock fix, qty +/- buttons, cart totals footer |

---

## Plan Archive

Executed plans: `docs/superpowers/plans/historical/`

| Plan | Delivered |
|------|-----------|
| `2026-04-28-stability-performance.md` | Stability/perf pass |
| `2026-05-11-cart-sync-robustness.md` | CartSync engine (debounce, token, abort) |
| `2026-05-11-quick-order-ux-validation.md` | ProductList, RowSync, UI polish |
| `2026-05-13-quick-order-ugly-dataset.md` | Edge-case catalog (ugly dataset) |
| `2026-05-13-woof-filter-compat.md` | WOOF/WBW filter integration |
| `2026-05-22-docs-refactor.md` | Docs reorganization into Active/Frozen/Historical/Operational zones |

Executed specs: `docs/superpowers/specs/historical/`

| Spec | Delivered |
|------|-----------|
| `2026-05-13-quick-order-ugly-dataset-design.md` | Ugly dataset generator design |

---

## Strategic Reference

| Doc | Role |
|-----|------|
| `docs/future-visibility-plugin.md` | Future UncleDev Catalog Visibility plugin — consolidated review conclusions, trigger conditions, open questions |

---

## Deferred / Future Work

- Cross-page cart hydration (V2 — documented as known limitation in frozen CartSync doc)
- Offline / network-failure queue persistence
- Playwright E2E test suite for Quick Order flows
- Matrix ordering, SKU search, saved order templates (documented in `CLAUDE.md` as future scope)

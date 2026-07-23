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
| `docs/active/current-phase.md` | Current phase status (Quick Order: COMPLETE, maintenance mode as of 2026-07-21), frozen system boundaries, current philosophy |
| `docs/active/status.md` | Implementation status matrix per system — Quick Order milestone marked COMPLETE 2026-07-21 |
| `docs/active/quick-order-catalog-filters-spec.md` | Catalog Filters (New/Best Seller/Already Ordered) design rationale — **implementation COMPLETE**, retained as historical design record; current behavior documented in the plugin's `readme.md` |

---

## Frozen Systems

> These systems are production-stable and architecture-locked.
> Do not modify without reading the doc and getting explicit approval.

| Doc | System |
|-----|--------|
| `docs/frozen/checkout-logic.md` | Checkout — payment rules, billing prefill, WooCommerce Blocks billing data protection |
| `docs/frozen/quick-order-local-state-architecture.md` | Quick Order — local state workspace model (canonical, current). Milestone COMPLETE 2026-07-21 — see `docs/active/status.md`. |
| `docs/frozen/quick-order-sync-architecture.md` | CartSync — real-time debounce engine (SUPERSEDED 2026-07-10 — see local-state doc) |

---

## Historical Docs

| Doc | Covers |
|-----|--------|
| `docs/historical/synthetic-b2b-catalog.md` | Dev-only WP-CLI catalog generator (stress testing tool) |
| `docs/historical/handoff-2026-04.md` | April 2026 session handoff — superseded by current state |
| `docs/historical/ssl-incident-dreampoint-b2b.md` | SSL incident Jun 2026 — root cause, classification, recovery plan |

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

None. Quick Order's planned development cycle is COMPLETE as of 2026-07-21
(see `docs/active/status.md`) — all plans previously tracked here have been
executed and moved to the Plan Archive table below.

---

## Plan Archive

Executed plans (still physically located in `docs/superpowers/plans/`, not
yet relocated to the `historical/` subfolder — status here is authoritative
regardless of physical location):

| Plan | Delivered |
|------|-----------|
| `docs/superpowers/plans/2026-05-12-quick-order-v1-1.md` | Admin bypass, variable stock fix, sorting, qty +/- buttons. Cart totals footer item is superseded by the local-state footer model (see `docs/frozen/quick-order-local-state-architecture.md` §3) rather than delivered as originally scoped. |
| `docs/superpowers/plans/2026-07-10-quick-order-local-state.md` | Transformed Quick Order into the local-state workspace model — see `docs/frozen/quick-order-local-state-architecture.md` |
| `docs/superpowers/plans/2026-07-13-quick-order-toolbar-chips.md` | Selected-variation chips, sort UI (superseded same day by native WBW Sort By — see the plan's own post-implementation note), qty checkmark |
| `docs/superpowers/plans/2026-07-14-quick-order-catalog-filters.md` | New/Best Seller/Already Ordered catalog filters — see `docs/active/quick-order-catalog-filters-spec.md` |
| `docs/superpowers/plans/2026-07-20-dev-catalog-metadata-refresh.md` | `--refresh-metadata` mode on the synthetic catalog generator |

Older executed plans, relocated to `docs/superpowers/plans/historical/`:

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

Executed, not yet relocated (still in `docs/superpowers/specs/`):

| Spec | Delivered |
|------|-----------|
| `docs/superpowers/specs/2026-07-13-quick-order-toolbar-chips-design.md` | As-shipped architecture for selected-variation chips, WBW-native Sort By, qty checkmark — see `docs/frozen/quick-order-local-state-architecture.md` 2026-07-13 revision notes |

---

## Strategic Reference

| Doc | Role |
|-----|------|
| `docs/future-visibility-plugin.md` | Future UncleDev Catalog Visibility plugin — consolidated review conclusions, trigger conditions, open questions |
| `docs/stakeholder-question-matrix.md` | **Meeting-ready workshop document** — confirmed facts, all Apros questions, all Dream Point questions, internal decisions; standalone, no other docs needed |
| `docs/project-status-matrix.md` | **Executive status document** — confirmed facts, open questions (Apros + Dream Point), internal decisions, blockers, next steps; onboarding entrypoint |
| `docs/erp-discovery-findings.md` | ERP discovery findings (Maj 2026) — confirmed rules, unvalidated assumptions, implementation blockers; read before any ERP work |
| `docs/erp-validation-checklist.md` | ERP discovery checklist for first Apros sessions — validates all Phase 4 assumptions before implementation begins |
| `docs/client-workshop-questions.md` | Distilled workshop question list — 13 client questions, 7 Apros questions, 7 internal decisions; derived from UX Foundation doc |
| `docs/b2b-erp-plugin-analysis.md` | Production B2C plugin analysis — reuse classification, endpoint inventory, auth gap, order payload format |
| `docs/b2b-erp-adaptation-blueprint.md` | ERP adaptation blueprint — B2B-specific components, pricing models A/B/C, warehouse/partner/order architecture, implementation roadmap |
| `docs/b2b-architecture-validation-audit.md` | **Independent validation audit** — assumption audit, evidence gaps, contradictions, risk register (P0/P1/P2), architecture stability assessment, pre-implementation checklist |
| `docs/apros-question-resolution-matrix.md` | **Autoritativna AP matrica** — status AP-01 – AP-14, evidencija, što ostaje za Apros sesiju, što je zatvoreno; jedini dokument koji treba za pripremu Apros meetinga |
| `docs/apros-session-final-pack.md` | **Finalni Apros meeting pack** — executive summary, P0/P1/P2 pitanja, traženi payload primjeri, interni blokeri, checklist; koristi se live na sestanku |
| `docs/b2b-erp-migration-plan.md` | **Implementacijski migration plan** — component inventory, product/partner/pricing/order adapation, DB impact, implementacijski koraci s ovisnostima, CAN START NOW vs. BLOCKED scope |
| `docs/decisions.md` | **Architectural Decision Records (ADR)** — pricing architecture, partner approval architecture, WBW Product Filter Multi-type search compatibility layer; kontekst, odluka, posledice po odluci |

---

## Deferred / Future Work

- Cross-page cart hydration — moot under the local-state architecture (state
  survives pagination in-page and is never persisted across navigations by
  design; see `docs/frozen/quick-order-local-state-architecture.md` §2, §5)
- Offline / network-failure queue persistence
- Playwright E2E test suite for Quick Order flows
- Matrix ordering, SKU search, saved order templates (documented in `CLAUDE.md` as future scope)
- Fancybox binding consolidation — accepted low-severity debt, no action needed; see `docs/active/fancybox-binding-debt.md`

# Docs Index — Dreampoint B2B

Navigation entrypoint for humans and AI. Canonical docs are listed per system. If a doc isn't listed here, treat it as implementation detail only.

---

## Core Systems

| System | Location | Status |
|--------|----------|--------|
| Visibility / bucket rules | Theme `inc/` + `CLAUDE.md` | Done — do not touch |
| Checkout / payment logic | `docs/tasks/checkout-logic.md` | Done — do not touch |
| Quick Order plugin | `plugins/dp-b2b-quick-order/` | Active development |
| B2B registration + approval | Theme `inc/registration-approval.php`, `inc/emails.php` | Done |
| ERP webhook (user approval) | Theme `inc/registration-approval.php` | Basic implementation done |

---

## Canonical Docs

| Doc | Covers | Authority level |
|-----|--------|----------------|
| `CLAUDE.md` (theme root) | Project rules, visibility system, Quick Order architecture rules | Highest — overrides all |
| `docs/tasks/checkout-logic.md` | Payment rules, billing prefill, billing data protection | Authoritative for checkout |
| `docs/tasks/quick-order-sync-architecture.md` | CartSync engine: debounce, queue, stale token, variation sync, locked decisions | Authoritative for CartSync |
| `docs/tasks/synthetic-b2b-catalog.md` | Dev-only WP-CLI catalog generator (stress testing) | Reference only |
| `docs/staging-quick-order-checklist.md` | Pre-staging deploy safety steps for Quick Order | Operational checklist |
| `docs/status.md` | Implementation status matrix | Current state overview |

---

## Active Architecture Areas

**Quick Order plugin** (`plugins/dp-b2b-quick-order/`):
- CartSync: stable, architecture locked — see `docs/tasks/quick-order-sync-architecture.md`
- V1.1 usability pass: in progress — see `docs/superpowers/plans/2026-05-12-quick-order-v1-1.md`

**Theme**:
- No active development. Existing hooks, filters, and visibility logic are frozen.

---

## Deferred / Future Work

- Cross-page cart hydration (V2 consideration — documented as known limitation in cart sync architecture doc)
- Offline / network-failure queue persistence
- Playwright E2E test suite for Quick Order flows
- Matrix ordering, SKU search, saved order templates (documented in `CLAUDE.md` as future scope)

---

## Testing & Validation Docs

| Doc | Use when |
|-----|----------|
| `docs/staging-quick-order-checklist.md` | Before first staging test session |
| `CLAUDE.md` → Local Environment section | Test user credentials and local URLs |
| `docs/tasks/quick-order-sync-architecture.md` → Known Limitations | Understanding V1 edge cases |

---

## Plan Archive (superpowers/plans/)

Executed plans are kept for reference. Do not re-execute.

| Plan | Executed | Delivered |
|------|----------|-----------|
| `2026-04-28-stability-performance.md` | Yes | Stability/perf pass |
| `2026-05-11-cart-sync-robustness.md` | Yes | CartSync engine |
| `2026-05-11-quick-order-ux-validation.md` | Yes | ProductList, RowSync, UI |
| `2026-05-12-quick-order-v1-1.md` | **Pending** | Admin bypass, sort, stock UX, qty +/- |

---

## Deprecated / Outdated

| File | Status |
|------|--------|
| `docs/handoff.md` | Stale — April 2026 session handoff, superseded by current state |
| `woocommerce/archive-product-discounted.php` | Orphan — safe to delete (confirmed in handoff.md) |

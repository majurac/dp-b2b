# Current Active Phase — Quick Order Local State Transformation

Last updated: 2026-07-10

---

## Active Work

Quick Order V1.1 (usability/completeness pass) is complete — its plan
(`docs/superpowers/plans/2026-05-12-quick-order-v1-1.md`) was already fully
executed; the "Pending" label in earlier docs was stale, not a queued task.

Current active work: transform Quick Order from a cart-driven interface
(every quantity change writes to the WC cart in real time via CartSync) into
an independent local-state ordering workspace. WC cart is written to only on
explicit "Dodaj u košaricu" submit.

Architecture (approved 2026-07-10): `docs/frozen/quick-order-local-state-architecture.md`
Superseded architecture: `docs/frozen/quick-order-sync-architecture.md` (see its Supersession Note)
Status matrix: `docs/active/status.md`
Execution plan: (added once written — see `docs/superpowers/plans/`)

## Priorities

- Local Quick Order state (quantity changes never touch WC cart)
- Footer driven by local state (item count, row count, subtotal) instead of `data.totals`
- Bulk "Dodaj u košaricu" submit, chunked over the existing 50-item batch cap
- All variations rendered as independent rows (dropdown removed)
- Product links removed; SKU label reworded to "Kataloški broj:"
- Minimal Quick-Order-specific header (conditional branch in existing header render, not a new template)
- Fixed footer, no persistence across reload/navigation (by design)

## Frozen Systems — Do Not Touch

| System | Canonical doc |
|--------|--------------|
| Quick Order local-state workspace (current) | `docs/frozen/quick-order-local-state-architecture.md` |
| CartSync real-time engine (superseded 2026-07-10) | `docs/frozen/quick-order-sync-architecture.md` |
| Checkout logic (payment rules, billing) | `docs/frozen/checkout-logic.md` |
| Visibility engine (bucket rules) | Theme `inc/` — see `CLAUDE.md` |
| WOOF/WBW filter integration | Architecture locked, in production |

Any change to a frozen system requires explicit plan approval. The local-state
transformation itself was explicitly approved 2026-07-10 — it is not a
violation of this rule, it is the currently-approved change.

## Current Philosophy

Reuse existing product-query, filter, visibility, and cart-validation
infrastructure wherever possible (see local-state doc §8 for the reuse/replace
breakdown). Do not introduce new architecture beyond what the approved local-
state model requires.

## PHP Version

PHP 8.3+ on both local (XAMPP) and production. Local/production parity is intentional.

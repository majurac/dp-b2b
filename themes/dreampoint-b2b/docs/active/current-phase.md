# Current Active Phase — Quick Order

Last updated: 2026-07-21

---

## Status: Quick Order milestone COMPLETE — maintenance mode

Quick Order has completed its planned development cycle as of 2026-07-21
(commit `2d5c00a`) and has entered maintenance mode. There is no current
active development phase for this system. The local-state transformation
that this file previously tracked as "active work" (below, kept as the
historical record of that phase) is fully implemented, staging-deployed,
and end-to-end validated — see `docs/active/status.md` for the full status
matrix and 2026-07-21 staging validation record, and the plugin's
`readme.md` (`wp-content/plugins/dp-b2b-quick-order/readme.md`) for the
current, authoritative architecture description.

Any future Quick Order work is a new-scope enhancement against this stable
baseline (e.g. items already listed under "Future Phases" in
`docs/frozen/quick-order-local-state-architecture.md` §10, or `CLAUDE.md`'s
Quick Order future-scope notes), not completion of pending work — none is
scheduled or implied by this close-out.

## Historical record — local-state transformation phase (2026-07-10)

Quick Order V1.1 (usability/completeness pass) was complete — its plan
(`docs/superpowers/plans/2026-05-12-quick-order-v1-1.md`) had already been
fully executed; the "Pending" label in earlier docs was stale, not a queued
task.

The active work tracked from 2026-07-10 onward was transforming Quick Order
from a cart-driven interface (every quantity change writes to the WC cart in
real time via CartSync) into an independent local-state ordering workspace,
where the WC cart is written to only on explicit "Dodaj u košaricu" submit —
now implemented and validated (see Status above).

Architecture: `docs/frozen/quick-order-local-state-architecture.md`
Superseded architecture: `docs/frozen/quick-order-sync-architecture.md` (see its Supersession Note)
Status matrix: `docs/active/status.md`
Execution plans (all executed): `docs/superpowers/plans/2026-07-10-quick-order-local-state.md`, `docs/superpowers/plans/2026-07-13-quick-order-toolbar-chips.md`, `docs/superpowers/plans/2026-07-14-quick-order-catalog-filters.md`, `docs/superpowers/plans/2026-07-20-dev-catalog-metadata-refresh.md`

Delivered priorities from that phase:

- Local Quick Order state (quantity changes never touch WC cart)
- Footer driven by local state (item count, row count, subtotal) instead of `data.totals`
- Bulk "Dodaj u košaricu" submit, chunked over the existing 50-item batch cap
- Variations rendered as independently purchasable rows grouped under their
  parent product's single row (dropdown removed) — see local-state doc §6
- Product links removed; SKU label reworded to "Kataloški broj:"
- Minimal Quick-Order-specific header (conditional branch in existing header render, not a new template)
- Fixed footer, no persistence across reload/navigation (by design)
- Catalog filters — New, Best Seller, Already Ordered (Quick Order-owned), In Stock (native WBW) — added 2026-07-14/21
- WBW AJAX container-check placeholder fix — added 2026-07-21

## Frozen Systems — Do Not Touch

| System | Canonical doc |
|--------|--------------|
| Quick Order local-state workspace (current) | `docs/frozen/quick-order-local-state-architecture.md` |
| CartSync real-time engine (superseded 2026-07-10) | `docs/frozen/quick-order-sync-architecture.md` |
| Checkout logic (payment rules, billing) | `docs/frozen/checkout-logic.md` |
| Visibility engine (bucket rules) | Theme `inc/` — see `CLAUDE.md` |
| WOOF/WBW filter integration | `docs/frozen/quick-order-local-state-architecture.md` §11 (WBW Integration Doctrine) |

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

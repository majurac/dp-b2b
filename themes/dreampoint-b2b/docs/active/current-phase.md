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

**Update 2026-10-05:** redesign slices 1–3 (search, state row/chips, product list and footer visuals) were accepted on staging at plugin v1.0.22 as a new-scope enhancement; maintenance mode otherwise unchanged. See `docs/active/status.md` (2026-10-05 update) for the remaining, unscheduled scope. No Slice 4 is scheduled.

**Update 2026-10-06 (evening):** Excel/CSV import Gate 1 + Gate 2 passed on staging (plugin v1.0.33, ADR-017). Safe continuation point: "Gate 2 staging PASS / pre-production manual acceptance" — next steps: Microsoft Excel smoke (NOT TESTED), manual focused desktop keyboard smoke, real mobile-device smoke, evaluate findings, then an explicit production decision. Production untouched; do not mark the feature production-closed.

**Update 2026-10-07:** pre-production manual acceptance (real Microsoft Excel, real focused desktop-Chrome keyboard, real physical mobile device) PASSED with no defect. Continuation point is now: "Pre-production acceptance PASS / production deployment not currently applicable — production environment not yet provisioned." DreamPoint B2B production does not exist yet (staging is the only provisioned deployment target), so there is no production action to approve; this is not an Excel Import defect. No code change. Excel Import implementation and pre-production acceptance are complete. No further Excel Import work is currently required. Resume production-related work only after DreamPoint B2B production is provisioned/defined.

**Update 2026-10-07 — HOLD checkpoint.** Current project checkpoint: HOLD pending client delivery/purchase of Reserved Stock Pro for DP-B06. No new development task is being started in the meantime. DP-B06 (1-hour cart-level stock reservation, ADR-007) remains a required business requirement with Reserved Stock Pro (Puri.io) as the selected plugin; the client has not yet provided/purchased it, so installation and verification cannot start. Do not substitute another reservation plugin and do not design or build a custom reservation engine while waiting. Staging QA TODO #10 (B2B registration + emails) was completed/PASSED on 2026-10-07 (see `docs/active/status.md`); TODO #11 (`/akcija/` staging re-verification) remains PARTIAL (staging has no on-sale products) and is not started. This hold does not reopen any completed work. **Continuation trigger:** client provides/purchases Reserved Stock Pro → resume DP-B06 with staging installation/configuration and native-behavior verification before any custom code (ADR-007 verification list).

**Update 2026-10-07 (end of day) — onboarding/Apros activation BLOCKED.** The client-confirmed approval architecture (Apros `B2B KUPAC = DA` is the single source of truth for approval and activation; recorded in `docs/decisions.md` ADR-018) is not yet implemented end-to-end: automatic ADR-002 polling does not exist, manual "Odobri" is legacy/contradicting. Implementation of the final activation sync must NOT begin until Apros/ZGData answer two API-semantics questions (`partnerList/get` B2B filtering + `DA→NE` behavior; `email` = `B2B E-MAIL` or general). `dp_bucket_id` assignment remains a separate, unresolved concern. TODO #10 stays COMPLETE/PASS; DP-B06 stays on HOLD; TODO #11 stays PARTIAL.

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

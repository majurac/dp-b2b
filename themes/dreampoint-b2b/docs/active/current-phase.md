# Current Active Phase — Quick Order V1.1

Last updated: 2026-05-22

---

## Active Work

Quick Order V1.1 — usability and completeness pass.

Execution plan: `docs/superpowers/plans/2026-05-12-quick-order-v1-1.md`
Status matrix: `docs/active/status.md`

## Priorities

- **Admin bypass** — `manage_woocommerce` users currently blocked by Quick Order access guard
- **Variable stock neutral state** — variable rows show incorrect stock badge before variation selection
- **Qty +/- buttons** — qty input only, no increment/decrement controls yet
- **Cart totals footer** — experimental; render not yet wired to sync response `data.totals`

## Frozen Systems — Do Not Touch

| System | Canonical doc |
|--------|--------------|
| CartSync (debounce, token, abort) | `docs/frozen/quick-order-sync-architecture.md` |
| Checkout logic (payment rules, billing) | `docs/frozen/checkout-logic.md` |
| Visibility engine (bucket rules) | Theme `inc/` — see `CLAUDE.md` |
| WOOF/WBW filter integration | Architecture locked, in production |

Any change to a frozen system requires explicit plan approval.

## Current Philosophy

Surgical fixes only. Solve the listed DEFERRED items from the V1 status doc. No new architecture. No new abstractions.

## PHP Version

PHP 8.3+ on both local (XAMPP) and production. Local/production parity is intentional.

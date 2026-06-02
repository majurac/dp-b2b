# Quick Order — Implementation Status

Last updated: 2026-05-13

---

| System | Status | Stable | Staging Ready | Notes |
|--------|--------|--------|---------------|-------|
| CartSync (debounce, token, abort) | ACTIVE | Yes | Yes | Architecture locked. See `docs/frozen/quick-order-sync-architecture.md`. |
| Pagination | ACTIVE | Yes | Yes | In-place re-render. Qty resets on page change — known V1 tradeoff. |
| Variation handling | ACTIVE | Yes | Yes | Implicit replace flow (remove-old + add-new in one debounce window). |
| Visibility integration | ACTIVE | Yes | Yes | Gate fires on `add_to_cart` only. No retroactive revalidation — intentional. |
| Sorting | ACTIVE | Yes | Yes | `qo_orderby` / `qo_order` params. Title and price sort, ASC/DESC toggle. Isolated from WOOF `orderby` detection. |
| Filter integration (WOOF/WBW) | ACTIVE | Yes | Yes | Price range + pa_* attribute filters via REST. WOOF URL change propagation via pushState wrapper. Isolation guard strips wpf_query from QO WP_Query instances. |
| Cart totals footer | EXPERIMENTAL | Partial | No | `data.totals` in every sync response. Footer div in template. Render not wired to response data. |
| Variable stock neutral state | DEFERRED | No | No | V1.1 plan — variable rows currently show incorrect stock badge before variation selection. |
| Qty +/- buttons | DEFERRED | No | No | V1.1 plan — qty input only, no +/- controls yet. |
| Admin bypass | DEFERRED | No | No | V1.1 plan — `manage_woocommerce` users currently blocked by Quick Order access guard. |
| Cross-page cart hydration | DEFERRED | No | No | V2 consideration. Documented as known limitation in sync architecture doc. |
| Offline / network failure handling | DEFERRED | No | No | Requests fail silently. No queue persistence or retry. |
| Performance guards | ACTIVE | Yes | Yes | Batch cap 50 items, no object hydration, AbortController prevents stale processing. |
| Playwright E2E coverage | DEFERRED | No | No | Manual staging checklist only (`docs/staging-quick-order-checklist.md`). No automated suite. |

---

## Status Key

- **ACTIVE** — built, tested locally, works as designed
- **EXPERIMENTAL** — partially built; behavior may be incomplete or untested
- **DEFERRED** — intentionally not built yet; no regression, just missing functionality

---

## ACF Governance Coverage

Last updated: 2026-06-01 (Wave 4 complete — commit a014af6)

| Area | Status | Notes |
|------|--------|-------|
| Active editable field groups in `acf-json/` | COMPLETE | 7/7 active groups tracked and Git-protected |
| Frozen blocks without field groups (10 blocks) | INTENTIONAL | Static blocks with no editable fields. Do NOT add field groups to these — absence is intentional. |
| New field groups (future) | ONGOING RULE | Any new ACF field group created in GUI must be immediately exported to `acf-json/`. See `CLAUDE.md` ACF Governance section. |

Wave 4 governance is complete unless new field groups are introduced. Do not reopen past waves.

---

## Frontend Architecture — Leorigine Alignment

Last updated: 2026-06-02

| Status | DEFERRED — interrupted by ACF governance work (Wave 4) |
|--------|---|

Scope of pending alignment work:
- `functions.php` organization
- Sass/CSS architecture
- JS file organization
- Enqueue structure

**Hard constraint:** B2B-specific systems (visibility engine, Quick Order, checkout logic) must NOT be simplified, reorganized, or merged during Leorigine alignment. Structural changes apply to general frontend scaffolding only.

This is unfinished architecture work — not a locked system and not abandoned. Resume when the ACF/blocks governance cycle is complete.

---

## Staging TODOs (Open / Blocked)

Last updated: 2026-06-02

These items remain open due to external dependencies. Do NOT mark as resolved unless the blocking dependency is confirmed resolved.

| # | Item | Priority | Status | Blocked by |
|---|------|----------|--------|------------|
| 1 | CorvusPay + jquery-migrate testing | BLOCKER | Open | CorvusPay test environment access not yet available |
| 2 | LSCache JS Defer config in WP admin | HIGH | Open | Requires staging deploy |
| 3 | `fetchpriority` count validation | MEDIUM | Open | Requires staging deploy (above-fold grid layout) |
| 4 | Font preload list (`dreampoint_b2b_font_preloads()`) | MEDIUM | Open | Final site design / above-fold font selection |
| 5 | `update_post_meta_cache = false` — verify WPF filters | MEDIUM | Open | Requires staging deploy |
| 6 | GTM snippet in `footer.php` | LOW | Open | GTM account data / final site design |
| 7 | `woocommerce/archive-product-discounted.php` deletion | LOW | Open | Confirm `/akcija/` page works on staging |
| 8 | `footer-shop.php` — verify intent | LOW | Open | Design clarification |
| 9 | Define `DP_ERP_WEBHOOK_SECRET` in `wp-config.php` on all environments | REQUIRED | Open | Required before ERP webhook is usable |
| 10 | Test B2B registration flow + both emails | REQUIRED | Open | Requires staging |
| 11 | Test `/akcija/` page — WPF filter, sorting, pagination | REQUIRED | Open | Requires staging |

Full detail on items 1–8: `docs/dev-context.md` → "Staging TODOs" and "Cleanup Status".

# Quick Order — Implementation Status

Last updated: 2026-07-10

---

**Architecture note (2026-07-10):** Quick Order is transitioning from the
real-time CartSync model to a local-state workspace model. See
`docs/frozen/quick-order-local-state-architecture.md` (canonical, current) and
the Supersession Note in `docs/frozen/quick-order-sync-architecture.md`
(historical). Rows below marked SUPERSEDED describe behavior that is being
replaced, not a defect — status reflects the transition, not code regression.
Update this table again once the transformation plan is executed.

| System | Status | Stable | Staging Ready | Notes |
|--------|--------|--------|---------------|-------|
| CartSync (debounce, token, abort) | SUPERSEDED | — | — | Real-time per-keystroke cart writes removed. See `docs/frozen/quick-order-local-state-architecture.md`. |
| Local Quick Order state + explicit submit | PENDING IMPLEMENTATION | No | No | See `docs/frozen/quick-order-local-state-architecture.md` §2–§4. |
| Pagination | ACTIVE | Yes | Yes | In-place re-render. Under the local-state model, quantities now re-hydrate from local state across pages instead of resetting — see local-state doc §2. |
| Variation handling | BEING REPLACED | — | — | Dropdown-based implicit replace flow removed; all variations render as independent rows. See local-state doc §6. |
| Visibility integration | ACTIVE | Yes | Yes | Gate fires on `add_to_cart` only. No retroactive revalidation — intentional. Unaffected by the local-state transition (fires at submit time now instead of per keystroke, same gate). |
| Sorting | ACTIVE | Yes | Yes | `qo_orderby` / `qo_order` params. Title and price sort, ASC/DESC toggle. Isolated from WOOF `orderby` detection. |
| Filter integration (WOOF/WBW) | ACTIVE | Yes | Yes | Price range + pa_* attribute filters via REST. WOOF URL change propagation via pushState wrapper. Isolation guard strips wpf_query from QO WP_Query instances. |
| Cart totals footer | SUPERSEDED | — | — | Replaced by local-state subtotal/count footer — see local-state doc §3. No longer reads `data.totals` from a sync response. |
| Variable stock neutral state | ACTIVE | Yes | Yes | Table was stale — this was delivered in the 2026-05-12 V1.1 plan. Neutral badge before variation selection is moot once all variations render as independent rows (local-state doc §6), but the badge logic itself already exists. |
| Qty +/- buttons | ACTIVE | Yes | Yes | Table was stale — this was delivered in the 2026-05-12 V1.1 plan (`product-list.js` / `row-sync.js` already implement +/- controls). |
| Admin bypass | ACTIVE | Yes | Yes | Table was stale — this was delivered in the 2026-05-12 V1.1 plan (`manage_woocommerce` bypass in access guard). |
| Cross-page cart hydration | MOOT | — | — | No longer a limitation under the local-state model — state persists across in-page pagination by design (local-state doc §2). Still not persisted across navigation/reload (§5, intentional). |
| Offline / network failure handling | DEFERRED | No | No | Applies to the submit action only now (local-state doc §9) — no retry/queue persistence if a chunked submit fails partway. |
| Performance guards | ACTIVE — batch cap needs chunking | Yes | Yes | `CART_SYNC_MAX_BATCH = 50` unchanged server-side; frontend must chunk submits over 50 rows (local-state doc §4.2). No object hydration, still true. AbortController/stale-token guards no longer apply — nothing is in-flight to race. |
| Playwright E2E coverage | DEFERRED | No | No | Manual staging checklist only (`docs/operational/staging-quick-order-checklist.md`). No automated suite. |

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

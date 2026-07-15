# Quick Order — Implementation Status

Last updated: 2026-07-13 (WBW filter integration hardened — unified category/brand/attribute DOM-metadata parsing, native `wpfAjaxSuccess` event; staging synthetic catalog made persistent — see policy note below)

**Release status:**

| Stage | Status |
|-------|--------|
| Local implementation | COMPLETE |
| Staging deployment | COMPLETE (commit `3e9dc1c`) |
| Staging validation | COMPLETE (incl. variable products/variations — see synthetic catalog generator run, 2026-07-10) |
| Release candidate | READY |
| Production deployment | NOT APPLICABLE — no production environment provisioned for this project. `dreampoint.b2b.uncledev.cloud` (`dream9399`) is currently the only deployment target. Do not treat staging as production. |

---

**Architecture note (2026-07-10):** Quick Order has transitioned from the
real-time CartSync model to the local-state workspace model. See
`docs/frozen/quick-order-local-state-architecture.md` (canonical, current) and
the Supersession Note in `docs/frozen/quick-order-sync-architecture.md`
(historical). Verified locally via Playwright (vis_full test user): zero
network requests on quantity change, pagination-persistent local state,
single chunked submit, partial-failure rows retained, keyboard navigation,
visible focus indicators on new controls. **Staging-verified 2026-07-10**
(`dreampoint.b2b.uncledev.cloud`, commit `e7b98ab`): >50-row batch chunking
(51 items → 2 sequential requests, 50+1), mini-cart fragment refresh +
Toastify, cart icon counter increment, cache-busting (bumped
`DP_QUICK_ORDER_VERSION` 1.0.1→1.0.2 after finding 7-day browser cache with a
static version string), full golden-path E2E.

**Variable-product staging validation (2026-07-10, commit `3e9dc1c`):** staging
catalog has no real variable products, so the existing synthetic catalog
generator (`docs/historical/synthetic-b2b-catalog.md`) was run on staging
(taxonomies + 200 simple + 10 variable/183 variations) to validate variable-
product rendering, independent variation rows/qty controls, local subtotal,
bulk add-to-cart, and WC cart contents end-to-end — including an
organically-triggered partial-failure/stock-guard path (one row exceeded
available stock, correctly rejected and retained locally while the rest of
the batch synced). Catalog reset (`wp dp-b2b reset-catalog`) and cart cleanup
ran afterward; staging was back to its pre-test state (6 original products)
**as of 2026-07-10 — superseded, see policy note below.**
Release candidate is READY. **Production deployment is NOT APPLICABLE** — no
production environment is provisioned for this project; `dreampoint.b2b.uncledev.cloud`
remains the only deployment target.

**Staging dataset policy (2026-07-13, supersedes the reset described above):**
the generated synthetic catalog is now a **persistent development dataset**,
not a disposable per-test fixture. Staging currently carries the full
generated set (216 products / 183 variations / 42 categories / 34 brands,
plus the 6 original products) via `wp dp-b2b generate-catalog` (all three
phases). **Do not run `wp dp-b2b reset-catalog` on staging** except (a) after
ERP import becomes available, or (b) on explicit user instruction. Routine
Quick Order development and regression testing should use this dataset as-is;
only remove ad-hoc cart contents/test orders created during a session, never
the catalog itself. See `docs/historical/synthetic-b2b-catalog.md` for
generator mechanics.

| System | Status | Stable | Staging Ready | Notes |
|--------|--------|--------|---------------|-------|
| CartSync (debounce, token, abort) | SUPERSEDED | — | — | Real-time per-keystroke cart writes removed. See `docs/frozen/quick-order-local-state-architecture.md`. |
| Local Quick Order state + explicit submit | ACTIVE (locally verified) | Yes | No | See `docs/frozen/quick-order-local-state-architecture.md` §2–§4. Not yet tested on staging. |
| Pagination | ACTIVE | Yes | Yes | In-place re-render. Quantities re-hydrate from local state across pages instead of resetting — verified via Playwright (set page 1 → page 2 → back to page 1, quantity persisted). See local-state doc §2. |
| Variation handling | ACTIVE | Yes | No | Dropdown removed; each variation is an independent purchasable line, stacked inside its parent's single `.dp-qo-row` using the table's real Naziv/Stanje/Cijena/Kol. columns (no `colspan`, not a sibling top-level row). See local-state doc §6. |
| Visibility integration | ACTIVE | Yes | Yes | Gate fires on `add_to_cart` only. No retroactive revalidation — intentional. Unaffected by the local-state transition (fires at submit time now instead of per keystroke, same gate). |
| Sorting | ACTIVE | Yes | Yes | `qo_orderby` / `qo_order` params. Title and price sort, ASC/DESC toggle. Isolated from WOOF `orderby` detection. |
| Filter integration (WOOF/WBW) | ACTIVE | Yes | Yes | Category, brand, price range, and pa_* attribute filters via REST — all three taxonomy-based filters (category/brand/attributes) now share one parsing pipeline driven by WBW's own `data-taxonomy`/`data-get-attribute`/`data-query-logic` DOM metadata, no hardcoded param-name/delimiter assumptions (2026-07-13 hardening — see `docs/frozen/quick-order-local-state-architecture.md` §11 doctrine). Filter-change detection via WBW's native `wpfAjaxSuccess` event (was a `history.pushState` wrapper). Isolation guard strips wpf_query from QO WP_Query instances. |
| Cart totals footer | ACTIVE (locally verified) | Yes | No | Local-state subtotal/count footer (local-state doc §3), verified via Playwright to compute correctly (item count, row count, subtotal). No longer reads `data.totals` from a sync response. |
| Variable stock neutral state | ACTIVE | Yes | Yes | Table was stale — this was delivered in the 2026-05-12 V1.1 plan. Neutral badge before variation selection is moot once all variations render as independently purchasable rows grouped under their parent (local-state doc §6), but the badge logic itself already exists. |
| Qty +/- buttons | ACTIVE | Yes | Yes | Table was stale — this was delivered in the 2026-05-12 V1.1 plan (`product-list.js` / `row-sync.js` already implement +/- controls). |
| Admin bypass | ACTIVE | Yes | Yes | Table was stale — this was delivered in the 2026-05-12 V1.1 plan (`manage_woocommerce` bypass in access guard). |
| Cross-page cart hydration | MOOT | — | — | No longer a limitation under the local-state model — state persists across in-page pagination by design (local-state doc §2). Still not persisted across navigation/reload (§5, intentional). |
| Offline / network failure handling | DEFERRED | No | No | Applies to the submit action only now (local-state doc §9) — no retry/queue persistence if a chunked submit fails partway. |
| Performance guards | ACTIVE | Yes | Yes | `CART_SYNC_MAX_BATCH = 50` unchanged server-side; frontend chunks submits over 50 rows sequentially (`cart-submit.js`, local-state doc §4.2) — not yet exercised with a real >50-row selection locally (dev catalog page size is 50, so chunking logic is implemented but chunk-boundary behavior is unverified beyond code review). No object hydration, still true. AbortController/stale-token guards no longer apply — nothing is in-flight to race. |
| Playwright E2E coverage | DEFERRED | No | No | Manual staging checklist only (`docs/operational/staging-quick-order-checklist.md`). No automated suite. |
| Catalog filters (New/Best Seller/Already Ordered/In Stock) | ACTIVE (locally verified) | Yes | No | See `docs/active/quick-order-catalog-filters-spec.md`. In Stock fully native WBW; other three are Quick Order-owned, `qo_*` URL params. |

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

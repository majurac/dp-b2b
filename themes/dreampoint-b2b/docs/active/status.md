# Quick Order — Implementation Status

Last updated: 2026-07-21 (Catalog Filters — New/Best Seller/Already Ordered —
plus the WBW AJAX container-check placeholder fix deployed to staging and
end-to-end validated; Quick Order's planned development cycle is now
COMPLETE — see Milestone note below)

## Update 2026-10-08 — Finding A/B (anonymous shopping, bucket enforcement) deployed to staging `c3b3d70`; staging-verified

Anonymous cart mutation (Store API, wc-ajax, `?add-to-cart=`) is rejected (401 `dp_b2b_login_required`); anonymous checkout is rejected before an order is created (code path + local tests; real anonymous order attempt NOT TESTED — environment restriction). `dp_bucket_id` is enforced on add-to-cart (validation hook + `woocommerce_add_to_cart_quantity`) and revalidated at checkout (409 `dp_b2b_product_not_available`); ineligible lines stay in the cart; Quick Order rejects increases of ineligible lines; administrators and shop managers are exempt consistently. Positive checkout after correction: order #23408 (BACS, on-hold, no ERP export). The `update-item`/classic cart-update quantity-increase gap was closed in `d5bfdf0` and staging-verified (Finding B COMPLETE — STAGING VERIFIED; ADR-018 addendum "Finding B closeout"). Residual gaps: bundle child skipped silently (bundles unused on staging), `custom_offer` per-line query cost, shop manager and classic checkout not tested on staging. Full record: `docs/decisions.md` ADR-018, addendum "Finding A/B". Staging `DP_BYPASS_APPROVAL` remains `true`.

## Update 2026-10-08 — Pending-user enforcement Phase 1–3 deployed to staging; E2E PARTIAL (ADR-018 addendum)

Phases 1–3 (`3ab9467`, `514d70e`, `1c2e0c1`) are on `origin/master` and staging (`1c2e0c1`); real-HTTP staging validation confirmed catalog/Quick Order/bucket visibility, cart and checkout enforcement, approval revocation/restoration, and primary existing-order payment interception (Store API + classic order-pay); Apros sandbox export verified (order #23407 → ERP 4345). Overall: **E2E PARTIAL — SPECIFIC GAPS REMAIN**. NOT TESTED: classic checkout form (site uses Checkout Block), anonymous order creation. The Phase 3 R2 fallback stays PARTIAL. Two open pre-existing findings: **A** anonymous Store API/AJAX/`?add-to-cart=` cart mutation with guest checkout enabled, **B** `dp_bucket_id` not enforced when adding to cart by ID (checkout revalidation unestablished). Staging `DP_BYPASS_APPROVAL` is back to `true` (enforcement inactive for logged-in users); test orders #23405/#23406/#23407 are intentionally retained. Details, evidence and restoration record: `docs/decisions.md` ADR-018, addendum 2026-10-08.

## Update 2026-10-07 — B2B onboarding / Apros activation: BLOCKED on two API-semantics answers (ADR-018)

Client-confirmed architecture (`B2B odgovori na pitanja.docx` §3, recovered 2026-10-07): registration → Točka sna → partner created in Apros → `B2B KUPAC = DA` → partner appears via Apros API → synchronized and **activated** in the webshop; "Apros ostaje jedini izvor istine za odobrenje i aktivaciju B2B partnera"; client: "Slažemo se s dogovorenim modelom." It does not define `dp_bucket_id` assignment. Full reconciliation, current-vs-contract table and open questions: `docs/decisions.md` ADR-018.

- Registration (TODO #10) stays COMPLETE/PASS. Automatic ADR-002 polling is NOT implemented; a manual unscheduled sync exists (`wp importer partners`) that maps users/`apros_partner_code` but does not activate. Manual "Odobri" is legacy and contradicts the model; "Opozovi" unresolved; REST `approve-user` legacy/superseded.
- **Stop state: onboarding/Apros activation BLOCKED** pending two Apros/ZGData answers: (1) does `partnerList/get` return only `B2B KUPAC=DA` partners and does `DA→NE` remove a partner; (2) is `partnerList.email` the `B2B E-MAIL` attribute or the general partner email. Do not start the final activation sync before they are known. Internal decisions (activation representation, fate of Odobri/Opozovi, email trigger, B2B role, polling, email-mismatch matching, bucket assignment/ordering) are listed in ADR-018.
- DP-B06 remains on HOLD (Reserved Stock Pro); TODO #11 remains PARTIAL. No production environment exists.

## Update 2026-10-05 — Quick Order redesign slices 1–3 (staging-accepted)

Search (ADR-014 reuse, `qo_search`, `catalog_number`, binary stock), state row (popular searches, active chips, no-results, reset) and the new product list/footer visuals are implemented and accepted on staging at plugin v1.0.22 (commits `06d1ac7`, `516d44d`, `ab8a0c3`, `80aa419`, `e827fc9`; docs commit follows). Local-state/cart-sync architecture unchanged. Details: `docs/frozen/quick-order-local-state-architecture.md` Addendum 2026-10-05; plugin `readme.md`.

Remaining QO scope (not scheduled): Excel Import; Model/Boja/Dob WBW blocks (Boja/Pakovanje blocks do not render on staging view 3); brand visibility-safe list/show-more; row-level cart error UX (incl. `quantity_allowed` semantics); mobile QO design; broader filter slice; WBW drops `orderby` when a filter is applied (pre-existing); search placement under the sidebar on mobile.

## Update 2026-10-06 — Slice 4: native-first filter sidebar (staging-accepted, plugin v1.0.27)

Brand, Model (`pa_model`), Boja (`pa_boja`), Dob (`pa_dob`) and Dostupnost are native WBW blocks in view 3 (Pakovanje removed); the term vocabulary is B2B-visibility-safe inside Quick Order (`DP_Quick_Order_Term_Scope`) and WBW's AJAX queries keep the visibility engine; counts off; Dostupnost is the only stock filter; one integration shim for attribute chip removal. Commits `d994580`, `b4939da`, `0a48a01`, `53bfd9d`, `50e19a8`. Record: `docs/decisions.md` ADR-015; `docs/frozen/quick-order-local-state-architecture.md` Addendum 2026-10-06.

Remaining QO scope (not scheduled): Excel Import; row-level cart error UX (incl. `quantity_allowed` semantics); mobile QO design (the taller sidebar pushes search lower on narrow screens); WBW drops `orderby` when a filter is applied (pre-existing). Brand "show more" is NOT needed (native scroll + search accepted). Follow-up candidate: the theme's brand `get_terms` filter still returns all brands outside Quick Order to rule-based users without brand rules.

## Update 2026-10-06 — Slice 5: row-level cart/submit error UX (staging-accepted, plugin v1.0.29)

`/cart/sync` returns a normalized, non-sensitive failure contract (`failed` + `out_of_stock` / `quantity_unavailable` / `product_unavailable` / `not_addable`, one result per submitted item, no stock figure); variation ownership is verified before stock is read, hidden/nonexistent/mismatched items are indistinguishable, and an existing cart line respects current stock. The client shows the reason on the affected row (`aria-invalid` / `aria-describedby`) and a polite footer summary instead of `alert()`; an unusable response (network/HTTP/timeout) is an *ambiguous* state with its own message and no auto-retry. Commits `5c07c53`, `0aee6f1`. Record: `docs/decisions.md` ADR-016; `docs/frozen/quick-order-local-state-architecture.md` Addendum 2026-10-06 (Slice 5). Residual risk: a manual retry after a lost-but-applied response would add twice (no idempotency added).

Remaining QO scope (not scheduled): Excel Import; mobile QO design; WBW drops `orderby` when a filter is applied (pre-existing); theme brand `get_terms` leak outside Quick Order.

## Update 2026-10-06 — Excel/CSV import (Gate 1 + Gate 2 staging PASS, plugin v1.0.33)

Excel Import (upload → validation → result → add-to-cart → completion) is implemented and accepted on staging; decisions and acceptance: `docs/decisions.md` ADR-017, plugin `readme.md`. A shared `CartSubmit` chunking bug (`cartSyncMaxBatch` arriving as the string `"50"`) was fixed for both Excel Import and the normal QO submit (staging-proven up to 500 items). Checkpoint: local/origin/staging runtime HEAD `729d059`; production NOT deployed.

**Update 2026-10-07 — pre-production manual acceptance PASS.** Real Microsoft Excel smoke (incl. `000046` save/reopen and the saved file resolving as `Boca Urban Basic` through the staging UI), real focused desktop-Chrome keyboard smoke and real physical mobile-device smoke all passed with no defect and no code change (details: ADR-017 "Pre-production manual acceptance"). Staging runtime unchanged (`729d059`, plugin v1.0.33); production NOT touched.

**Safe continuation point: "Pre-production acceptance PASS / production deployment not currently applicable — production environment not yet provisioned."** Excel Import implementation and pre-production acceptance are complete. No further Excel Import work is currently required. Resume production-related work only after DreamPoint B2B production is provisioned/defined. Staging is the only provisioned DreamPoint B2B deployment target; the absence of production is a wider project-provisioning matter, not an Excel Import blocker. No known functional blocker. Do not restart Gate 1/2 or manual acceptance. Residual (non-blocking) observations are listed in ADR-017.

Remaining QO scope (not scheduled): mobile QO design; WBW drops `orderby` when a filter is applied (pre-existing); theme brand `get_terms` leak outside Quick Order.

## Milestone: COMPLETE (2026-07-21)

Quick Order has completed its planned development cycle and has entered
**maintenance mode**. All systems tracked in this document are implemented,
staging-deployed, and end-to-end validated as of commit `2d5c00a`. No open
implementation work remains for this feature. Future changes are
enhancements against a stable baseline, not completion of pending scope —
see `docs/active/current-phase.md` and the plugin's `readme.md` (theme-root
`wp-content/plugins/dp-b2b-quick-order/readme.md`) for the authoritative
current-state description.

**Release status:**

| Stage | Status |
|-------|--------|
| Local implementation | COMPLETE |
| Staging deployment | COMPLETE (latest: commit `2d5c00a`, 2026-07-21) |
| Staging validation | COMPLETE (incl. variable products/variations — see synthetic catalog generator run, 2026-07-10; catalog filters + WBW AJAX lifecycle + local-state + additive cart sync — see 2026-07-21 validation below) |
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

**Superseded 2026-09-02 — synthetic PRODUCTS removed from staging (ERP import
is now live):** the `2026-07-13` "do not reset" condition (a) has been met.
The Apros ERP import (`wp-content/plugins/uncle-dev-importer`) populated staging
with real product data on 2026-08-19, so the synthetic products were removed
via `wp dp-b2b reset-catalog --batch=20260713_1138` — **210 parent products +
183 variations** deleted. The `[DEV]` product categories (24) and brands (30)
and all Brand Fixtures were **retained** (batch mode never touches terms).
Staging's product catalog is now the ERP dataset. Full record + the
`recalcMetaValues()` WBW orphan-row cleanup that followed:
`docs/decisions.md` ADR-006.

**Staging validation — 2026-07-21 (commit `2d5c00a`):** full Playwright
regression against `dreampoint.b2b.uncledev.cloud/quick-order/` after
deploying the WBW AJAX container-check placeholder fix and configuring
WBW's "Product List / Loader Selector" (admin, filter views 2 and 3) to
`.dp-qo-wbw-ajax-placeholder`. Confirmed: Brand/Sort By/In Stock/native
Clear all proceed via AJAX with zero full-page reloads;  `wpfAjaxSuccess`
fires on every WBW-driven change; Quick Order's own REST endpoint
(`/wp-json/dreampoint-b2b/v1/quick-order/products`) re-fetches and re-renders
on each change; the placeholder itself stays hidden throughout; the visible
table is exclusively Quick Order's own `table.dp-qo-table` (WBW never
renders a competing product list); local quantity state and the footer
subtotal survive a WBW-triggered re-render even when the edited row scrolls
off the newly-sorted/filtered result set; Add to Cart posts to
`/quick-order/cart/sync` and is reflected in the native WooCommerce mini-cart
count; `.is-added` and footer totals update/reset correctly around the
cart-sync round trip; browser Back/Forward triggers a full document reload
(this is WBW's own documented `popstate` → `location.reload()` behavior,
already recorded in `docs/frozen/quick-order-local-state-architecture.md`
§11's 2026-07-13 revision note — not a regression introduced by this fix);
zero console errors/warnings and zero failed network requests throughout.

| System | Status | Stable | Staging Ready | Notes |
|--------|--------|--------|---------------|-------|
| CartSync (debounce, token, abort) | SUPERSEDED | — | — | Real-time per-keystroke cart writes removed. See `docs/frozen/quick-order-local-state-architecture.md`. |
| Local Quick Order state + explicit submit | ACTIVE | Yes | Yes | See `docs/frozen/quick-order-local-state-architecture.md` §2–§4. Staging-validated 2026-07-21 (quantity state and footer subtotal survive a WBW-triggered re-render; deferred submit confirmed via Add to Cart round trip). |
| Pagination | ACTIVE | Yes | Yes | In-place re-render. Quantities re-hydrate from local state across pages instead of resetting — verified via Playwright (set page 1 → page 2 → back to page 1, quantity persisted). See local-state doc §2. |
| Variation handling | ACTIVE | Yes | No | Dropdown removed; each variation is an independent purchasable line, stacked inside its parent's single `.dp-qo-row` using the table's real Naziv/Stanje/Cijena/Kol. columns (no `colspan`, not a sibling top-level row). See local-state doc §6. |
| Visibility integration | ACTIVE | Yes | Yes | Gate fires on `add_to_cart` only. No retroactive revalidation — intentional. Unaffected by the local-state transition (fires at submit time now instead of per keystroke, same gate). |
| Sorting | ACTIVE | Yes | Yes | `qo_orderby` / `qo_order` params. Title and price sort, ASC/DESC toggle. Isolated from WOOF `orderby` detection. |
| Filter integration (WOOF/WBW) | ACTIVE | Yes | Yes | Category, brand, price range, and pa_* attribute filters via REST — all three taxonomy-based filters (category/brand/attributes) now share one parsing pipeline driven by WBW's own `data-taxonomy`/`data-get-attribute`/`data-query-logic` DOM metadata, no hardcoded param-name/delimiter assumptions (2026-07-13 hardening — see `docs/frozen/quick-order-local-state-architecture.md` §11 doctrine). Filter-change detection via WBW's native `wpfAjaxSuccess` event (was a `history.pushState` wrapper). Isolation guard strips wpf_query from QO WP_Query instances. WBW's own AJAX success handler previously fell back to a full `location.reload()` on this page (no `ul.products` container to satisfy its own container check) — fixed 2026-07-21 by pointing WBW's "Product List / Loader Selector" setting at a hidden `.dp-qo-wbw-ajax-placeholder` element (commit `2d5c00a`); see frozen doc's 2026-07-21 revision note. |
| Catalog filters (New/Best Seller/Already Ordered/In Stock) | ACTIVE | Yes | Yes | See `docs/active/quick-order-catalog-filters-spec.md` (design record, now historical) and plugin `readme.md` → "Catalog Filters" (current, authoritative). In Stock fully native WBW; other three are Quick Order-owned, `qo_*` URL params. Staging-validated 2026-07-21 (Brand/In Stock/native Clear exercised directly; New/Best Seller/Already Ordered confirmed present and wired via commit history — `35f5730`, `94220ae` — and the synthetic catalog's seeded tiers, see `docs/historical/synthetic-b2b-catalog.md`). |
| Cart totals footer | ACTIVE | Yes | Yes | Local-state subtotal/count footer (local-state doc §3), verified via Playwright to compute correctly (item count, row count, subtotal). No longer reads `data.totals` from a sync response. Staging-validated 2026-07-21: updates on qty change, resets to zero after a successful Add to Cart sync. |
| Variable stock neutral state | ACTIVE | Yes | Yes | Table was stale — this was delivered in the 2026-05-12 V1.1 plan. Neutral badge before variation selection is moot once all variations render as independently purchasable rows grouped under their parent (local-state doc §6), but the badge logic itself already exists. |
| Qty +/- buttons | ACTIVE | Yes | Yes | Table was stale — this was delivered in the 2026-05-12 V1.1 plan (`product-list.js` / `row-sync.js` already implement +/- controls). |
| Admin bypass | ACTIVE | Yes | Yes | Table was stale — this was delivered in the 2026-05-12 V1.1 plan (`manage_woocommerce` bypass in access guard). |
| Cross-page cart hydration | MOOT | — | — | No longer a limitation under the local-state model — state persists across in-page pagination by design (local-state doc §2). Still not persisted across navigation/reload (§5, intentional). |
| Offline / network failure handling | DEFERRED | No | No | Applies to the submit action only now (local-state doc §9) — no retry/queue persistence if a chunked submit fails partway. |
| Performance guards | ACTIVE | Yes | Yes | `CART_SYNC_MAX_BATCH = 50` unchanged server-side; frontend chunks submits over 50 rows sequentially (`cart-submit.js`, local-state doc §4.2) — not yet exercised with a real >50-row selection locally (dev catalog page size is 50, so chunking logic is implemented but chunk-boundary behavior is unverified beyond code review). No object hydration, still true. AbortController/stale-token guards no longer apply — nothing is in-flight to race. |
| Playwright E2E coverage | DEFERRED | No | No | Automated E2E test suite is intentionally out of scope for the current project. Validation is currently performed through repeatable Playwright-driven verification. Performed repeatedly through development, most recently the full 2026-07-21 staging regression above. Deferred item is specifically an automated, repeatable suite file in the repo, not test coverage itself. |

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

Last updated: 2026-09-23 (Homepage/Segment Landing follow-ups added — see `docs/decisions.md` ADR-009)

These items remain open due to external dependencies. Do NOT mark as resolved unless the blocking dependency is confirmed resolved.

| # | Item | Priority | Status | Blocked by |
|---|------|----------|--------|------------|
| 12 | Populate real `brand_segment` values on ERP-sync `product_brand` terms (Homepage/Segment Landing) | REQUIRED | Open | Valid business/content input per brand — must not be guessed. Outdoor membership specifically has no institutional-knowledge source identified yet. |
| 13 | Segment Landing "Istaknuti proizvodi" manual product curation (Lifestyle/Toys/Outdoor) | MEDIUM | Open | Content/editorial task, not code |
| 14 | Company Features `features_items` ACF Options content on staging (currently `NULL`) | MEDIUM | Open | Content population — block renders nothing until filled |
| 15 | Segment Landing hero — Figma-specific images + "badge" field | LOW | Open | Badge blocked by DB-only `featured-section`/`featured-brand` ACF field group (no `acf-json/` entry); images pending pixel-level Figma visual pass (`get_screenshot` MCP was rate-limited throughout implementation) |
| 1 | CorvusPay + jquery-migrate testing | BLOCKER | Open | CorvusPay test environment access not yet available |
| 2 | LSCache JS Defer config in WP admin | HIGH | Open | Requires staging deploy |
| 3 | `fetchpriority` count validation | MEDIUM | Open | Requires staging deploy (above-fold grid layout) |
| 4 | Font preload list (`dreampoint_b2b_font_preloads()`) | MEDIUM | Open | Final site design / above-fold font selection |
| 5 | `update_post_meta_cache = false` — verify WPF filters | MEDIUM | Open | Requires staging deploy |
| 6 | GTM snippet in `footer.php` | LOW | Open | GTM account data / final site design |
| 7 | `woocommerce/archive-product-discounted.php` deletion | LOW | Done — file already absent from disk, no git-tracked deletion found (predates current history or was never tracked) | — |
| 8 | `footer-shop.php` — verify intent | LOW | Open | Design clarification |
| 9 | ~~Define `DP_ERP_WEBHOOK_SECRET` in `wp-config.php` on all environments~~ | ~~REQUIRED~~ | **SUPERSEDED 2026-10-07 (ADR-018)** — Apros confirmed no approval webhook (ADR-002) and the client confirmed the Apros-sourced approval model; the REST `approve-user` endpoint is legacy and dormant (secret intentionally undefined on staging). Do not define the secret for the current architecture. | — |
| 10 | Test B2B registration flow + both emails | REQUIRED | **DONE / PASS (2026-10-07, staging, theme commit `9b927c8`).** Registration form collects the seven required B2B fields (Tvrtka, OIB, Telefon, Adresa, Grad, Poštanski broj, Država); server-side validation (OIB exactly 11 digits, country from WooCommerce allowed countries); canonical `billing_*` meta stored and copied via the existing shipping path; one valid submission creates exactly one account (`approved` empty → pending, redirect to `/approval-pending/`; earlier first-click observation not reproducible). Admin email carries the company in the subject and all B2B data; customer email is Croatian with the native WooCommerce set-password link. Both real emails were received in Gmail and the real password link was clicked and opened the set-password flow. **Email design decision:** WooCommerce *Email Improvements* is intentionally disabled (`woocommerce_feature_email_improvements_enabled=no`); WooCommerce classic emails are used with the classic default colors and DreamPoint `#0D121C` as base/accent (`woocommerce_email_base_color`) — these are DB options on staging, not code. QA users 8, 9, 10 deleted after dependency check (no orders/content). **Boundary:** `DP_BYPASS_APPROVAL=true` remains a staging condition, so the real non-bypass approval guard was NOT runtime-tested here; it stays a go-live consideration. | — |
| 11 | Test `/akcija/` page — WPF filter, sorting, pagination | REQUIRED | Verified locally (2026-08-04) — 2 blocking bugs found and fixed in `inc/woocommerce.php` (page pinned to zero results; on-sale restriction discarded by native WC query). Per-page mismatch also fixed. `orderby=price` site-wide sorting bug (found during this validation, reproduced on `/shop/`, unrelated to this page) root-caused and fixed same day — see `docs/decisions.md` ADR-004. See `docs/dev-context.md` → "Akcija (Discounted Products) Page". | Staging re-verification still required — local only | **2026-10-07 staging QA: PARTIAL** — page loads (HTTP 200, no console errors, no mobile overflow at 390px), WBW filter sidebar renders and the empty state is shown, but staging currently has ZERO on-sale products (no `_sale_price` rows, `wc_get_product_ids_on_sale()` = 0), so on-sale population, filter, sort and pagination could not be verified without mutating catalog data (not done). Needs at least a few real or approved test on-sale products on staging.
| 16 | WooCommerce email localization (Croatian) | MEDIUM | **Staging: RENDERING VERIFIED, DELIVERY NOT TESTED (2026-10-08, ADR-019).** Site locale `hr`, hr packs installed, admin kept `en_US`. Payment/flat-rate titles and silkypress strings now Croatian (ADR-019 addendum, `ccc0591`). Remaining English: `Free shipping` title and BACS checkout description (approval pending). | Production: Transactional Email Delivery task (provider, SPF/DKIM/DMARC) + hr packs on production. Staging SMTP sender rewrite to `armin.lusija@gmail.com` accepted. |

Full detail on items 1–8: `docs/dev-context.md` → "Staging TODOs" and "Cleanup Status".

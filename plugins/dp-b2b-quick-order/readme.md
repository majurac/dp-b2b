# DP B2B Quick Order

Custom WooCommerce B2B bulk-ordering plugin for Dreampoint B2B. Renders a
standalone, high-density product table optimized for fast multi-item
ordering — not a WooCommerce shop-loop variant.

## Requirements

- WordPress 6.0+
- WooCommerce 8.0+
- PHP 8.3+

## Architecture

Quick Order does not extend or template-override the WooCommerce shop loop —
it is a self-contained REST-driven product table (`templates/quick-order.php`
+ `assets/src/*.js`), fetching from the plugin's own REST endpoints and
rendering entirely client-side.

**Local-state, deferred-submit model.** Quantities typed or adjusted via
+/- controls are held in an in-memory JS object (`QuickOrderState`) —
never `localStorage`/`sessionStorage`, never synced to the WooCommerce cart
per keystroke. Nothing reaches the real cart until the user explicitly
clicks "Dodaj u košaricu". At that point the accumulated local quantities
are submitted in a single batched request (chunked at
`DP_Quick_Order_Config::CART_SYNC_MAX_BATCH` items) and synced **additively**
onto whatever is already in the WooCommerce cart — Quick Order never
overwrites an existing cart line's quantity, only adds to it
(`class-cart-sync.php`). Rows the server rejects (typically stock-limited)
stay in local state for the user to see and correct; rows it accepts are
cleared. Local state is intentionally not persisted across a page
navigation or reload — this is a deliberate trade-off of the model, not a
missing feature.

**`.is-added` row state.** Any row (a simple product's `.dp-qo-row`, or one
variation's `.dp-qo-variation-row` inside a variable product's parent row)
carries the `.is-added` class whenever its *local* quantity is greater than
zero — never derived from the WooCommerce cart. One method,
`RowController#applyAddedState()`, is the single place this class is
written, called from both the qty-change path (typing and +/- buttons,
which funnel through the same delegated `input` handler) and the
render/hydrate path (`hydrateAll()` — initial page load and any
pagination/sort/filter re-render), so the two paths can't drift apart.

**WBW integration philosophy.** The theme's WBW (WooBeWoo Product Filter)
instance is used **only** to render filter widgets (Brand, Sort By, native
In Stock) via `[wpf-filters id="…"]` shortcodes placed next to Quick
Order's own markup — WBW is never given control of the actual product
listing. A `pre_get_posts` guard (`class-filter-bridge.php`) strips any
`wpf_query` flag WOOF may set, so WBW's own SQL-clause filtering can never
apply to a Quick Order query. Category/Brand/Price/Attribute/In-Stock
selections made in WBW's widgets are read back out of the URL
(`product-list.js`, keyed off WBW's own `data-taxonomy`/`data-get-attribute`
DOM metadata — not hardcoded param names) and re-issued as Quick Order's
own REST query params.

**WBW AJAX compatibility placeholder.** WBW's own AJAX success handler
looks for a product-loop container (`ul.products` by default) to inject
its filtered HTML into; finding none on this page, it used to fall back
to a full `location.reload()` before ever dispatching its own
`wpfAjaxSuccess` event — the event `product-list.js` relies on to trigger
its own REST refetch. `templates/quick-order.php` contains a hidden,
empty `.dp-qo-wbw-ajax-placeholder` element for exactly this reason: WBW's
"Product List / Loader Selector" setting (WBW admin, filter views 2 and 3,
Options tab) points at it, so WBW's own container check succeeds and it
proceeds through its normal AJAX path instead of reloading. WBW's response
HTML lands in the placeholder and is never read — Quick Order's own REST
rendering remains the only thing that updates the visible table. **Do not
remove this element or repoint the WBW selector away from it** — doing so
reintroduces the full-page-reload regression described below.

## Catalog Filters

Four filters sit above the product table: three Quick-Order-owned, one
fully native WBW.

| Filter | Owner | URL param | Mechanism |
|---|---|---|---|
| Već naručeno (Already Ordered) | Quick Order | `qo_already_ordered=1` | `wc_get_orders()` (HPOS-safe) against the current customer's `processing`/`completed` orders, parent-product roll-up, per-user object-cached (`class-already-ordered-resolver.php`) |
| Novo (New) | Quick Order | `qo_new=1` | `date_query` on `post_date_gmt`, threshold `DP_Quick_Order_Config::NEW_PRODUCT_MAX_AGE_DAYS` (default 30 days) |
| Best seller | Quick Order | `qo_best_seller=1` | `meta_query` on native `total_sales`, threshold `DP_Quick_Order_Config::BEST_SELLER_MIN_SALES` (default 10) |
| Dostupnost (In Stock) | WBW, fully native | `pr_stock` | WBW's own `wpfInStock` filter type — zero Quick Order code |

The three Quick-Order-owned checkboxes and their "Poništi filtere" button
live in their own collapsible fieldset above WBW's widgets (own CSS
namespace, `dp-qo-*` — no WBW classes/DOM/JS are read or reused; the
collapse affordance visually matches WBW's own +/- widgets but is an
independent implementation). Both filter groups write to
`history.pushState` and are re-derived from the URL on load/back/forward,
so pagination, sorting, and browser navigation all preserve the combined
filter state as a single source of truth: the URL.

Both thresholds are overridable without a code change:
`dp_qo_new_product_max_age_days` / `dp_qo_best_seller_min_sales` filters
(invalid overrides — zero, negative, non-numeric — fall back to the
constant rather than producing a broken comparison). Already Ordered's
qualifying statuses are overridable via `dp_qo_already_ordered_statuses`
(default `['processing', 'completed']`).

## Search, State Row and Product List (2026-10-05, v1.0.22)

- **Search:** `qo_search` URL param. Reuses the theme's ADR-014 extension (`dp_search_extended` query var: title/content, `_sku`, `_ARTICLE_CODE`, `_global_unique_id`; variation hit returns parent; min 2 chars; 200-ID cap). No QO-specific index/ranking. REST exposes `catalog_number` (parent and per variation); stock is binary only — no numeric stock reaches the client.
- **State row:** shows "Popularne pretrage" (theme filter `dp_qo_popular_searches`, ACF `search_popular_terms`) when nothing is active, otherwise removable active-filter chips (native WBW `.wpfSelectedParameters` adopted into the row, plus the search term) and a clear-all action; structured no-results state when the query is empty.
- **Product list:** div-based list (PROIZVOD / OPCIJA / STANJE / CIJENA), parent cards with the variations as lines inside variable cards (attribute options + catalog number beneath), joined arrow stepper, persistent selected check (`.is-added`), sticky footer with Croatian-declined counts ("N artikala", "N različitih SKU-a"), WooCommerce-formatted subtotal, "Pregled košarice" link and "Dodaj u košaricu" submit.
- Out of scope / not built: Excel Import UI (foundation exists, see below), mobile QO design.

## Native-first filter sidebar (2026-10-06, v1.0.27)

Quick Order filtering is native-first. WooCommerce global attributes remain native `pa_*` taxonomies and use the existing WBW/WooCommerce filtering pipeline. Custom Quick Order code exists only where necessary to enforce B2B visibility/security or integrate the native filter UI.

- WBW view 3 blocks: Brand (`product_brand`), Model (`pa_model`), Boja (`pa_boja`), Dob (`pa_dob`), Dostupnost (`wpfInStock`, the only stock filter); counts off; native scroll + native search for long lists (no custom show-more). QO-owned Popularno sits above them.
- `DP_Quick_Order_Term_Scope` (`inc/class-term-scope.php`): inside the QO template and the WBW frontend AJAX sent from the QO page, a `product_brand`/`pa_*` term is returned only if a published product the current user may see carries it. Global `hide_empty`/term counts are not a visibility mechanism. It also re-attaches the visibility engine to WBW's AJAX queries (view 3 uses WBW "remove actions").
- `assets/src/wbw-compat.js`: replaces WBW 3.4.5's broken `getFilterParam()` lookup so attribute selected-parameter chips can clear their filter. No filtering behavior of its own.
- Exact WBW configuration and acceptance evidence: theme `docs/decisions.md` ADR-015.

## Excel / CSV import — Gate 1 foundation (v1.0.30, no UI yet)

Foundation only: local parsers, static templates and a read-only server validation endpoint. The modal that builds on it is described in the Gate 2 section below. Decisions and acceptance: theme `docs/decisions.md` ADR-017.

- **Browser parsing** (`assets/src/import/`, bundle `assets/dist/quick-order-import.js`, global `window.dpQuickOrderImport`; build: `npm run build:import`). `.xlsx` via `fflate` 0.8.3 (`unzipSync` + `DOMParser`, pinned exact; ≥ 0.8.3 fixes GHSA-px8p-9vwx-vf98) and `.csv` via a dependency-free, quote-aware tokenizer. Output is only untrusted `{row, identifier, quantity}` text — never product IDs. The file is never uploaded or stored.
- **XLSX safety:** ≤ 2 MB; ZIP central directory is read first (entry count ≤ 100, total declared uncompressed ≤ 30 MB, sheet/shared-strings XML ≤ 8 MB, small parts ≤ 1 MB) before anything is inflated; VBA/macro/embedded parts rejected; DOCTYPE/ENTITY rejected; external links ignored; formulas are never evaluated (cached scalar used as inert text, no cached value → empty cell + warning); first visible worksheet only; columns A/B only; ≤ 500 data rows. Leading zeros survive only when the cell is text — a number already coerced by Excel (`46` for `000046`) is not repaired.
- **CSV:** UTF-8 (BOM-safe), Windows-1250 only as the fallback when the bytes are not valid UTF-8; `;` or `,` chosen by the header; wrong column count = rejected row (reported, not repaired), stray/unterminated quote = file error.
- **Headers:** identifier `SKU` | `Kataloški broj` | `Šifra artikla`, quantity `Količina` (case/diacritics-insensitive). EAN is not an identifier.
- **Static templates:** `assets/templates/dp-quick-order-import-template.{xlsx,csv}`, generated once by `tests/import/build-templates.py` (identifier column formatted as Text; CSV = UTF-8 BOM, `;`). No runtime spreadsheet generation.
- **Endpoint:** `POST /wp-json/dreampoint-b2b/v1/quick-order/import/validate`, body `{rows:[{row, identifier, quantity}]}` (≤ 500). Read-only (never writes the cart, stock or any state), `Cache-Control: no-store` — the result depends on the user's cart and visibility. Implemented in `inc/class-import-validator.php`:
  1. strict syntax (quantity = positive whole number, ≤ 99,999 per unit after merging; no coercion);
  2. one batch lookup of `_ARTICLE_CODE` **and** `_sku` (published unit + published parent), exact, case-insensitive per DB collation, no padding/fuzziness/precedence. Boundary normalization is symmetric: the submitted identifier AND the stored value (`REGEXP_REPLACE`, one batched query) lose leading/trailing TAB/LF/VT/FF/CR/space/NBSP/BOM and nothing else. Data-quality note (2026-10-06): real ERP-derived staging data has a trailing LF in 8 published variation `_ARTICLE_CODE` values; this is handled defensively here, the stored data is NOT mutated (no ERP/importer change). Collision audit with the exact semantics: 0 duplicates / cross-namespace / parent collisions;
  3. B2B authorization on the parent via `dp_b2b_product_accessible` (same contract as `/cart/sync`) **before** ambiguity, type, purchasability, stock or name — inaccessible, unpublished, orphan and nonexistent identifiers are all `identifier_not_found`;
  4. only among authorized candidates: `ambiguous_identifier` (≠ 1 unit), `variable_parent`, `not_purchasable`;
  5. duplicate rows merge per orderable unit (first-occurrence order; the same unit entered via `_sku` and `_ARTICLE_CODE` merges);
  6. stock clamp with WooCommerce semantics: `final = min(requested, additional orderable)` where additional = managed stock − cart quantity − quantity granted to earlier import rows, keyed by `get_stock_managed_by_id()` (shared parent pools); unmanaged / backorders yes|notify → no finite limit; sold individually → 1 minus the cart line; `final == 0` → `unavailable`.
- **Result row:** `{rows, identifier, status: ready|adjusted|error, code, product_id, variation_id, name, requested, quantity}` — `ready`/`adjusted` rows carry the IDs `/cart/sync` needs (it re-verifies them); no raw stock, managed flag, pool id, price, brand or `quantity_allowed`. The final `quantity` is the intentional privacy exception of the client's auto-clamp rule. Codes: `invalid_identifier`, `invalid_quantity`, `quantity_limit`, `identifier_not_found`, `ambiguous_identifier`, `variable_parent`, `not_purchasable`, `unavailable`.
- Validation is advisory, not a reservation: the final cart write still goes through `/cart/sync`.
- Tests (local only): `tests/import/` — `gen-fixtures.py` + `run-parser-tests.mjs` (Chromium via the local Playwright install) and `validator-test.php` (`wp eval-file`, synthetic fixtures, refuses to run off localhost).

## Excel / CSV import — Gate 2 modal (v1.0.33, staging PASS 2026-10-06; NOT production-closed)

User-facing workflow on top of the Gate 1 foundation: **File upload → Validacija → Rezultat → Dodavanje u košaricu** (`assets/src/import/modal.js`, `session.js`; CSS appended to `assets/dist/quick-order.css`; entry point = the "Excel Import" button next to the search field, revealed by the import bundle once it is alive). Design references: QO-01 / QO-05 / QO-06 / QO-07.

- **Private state.** Nothing is written to the visible `QuickOrderState`, localStorage/sessionStorage or the DB; closing resets the session, reopening starts fresh. Existing unsent Quick Order quantities are never cleared; if an imported unit is also selected there (read-only accessor `dpQuickOrder.hasSelection(rowKey)`) a non-blocking notice is shown.
- **Parsing / validation** are the Gate 1 parser and endpoint, unchanged. File-level problems (format, size, headers, > 500 rows, malformed workbook/CSV) are shown in the modal and never sent to the server. Validation is one atomic read-only request, so its progress is **indeterminate** (no invented percentage) and "Otkaži uvoz" aborts the browser request; a failed request offers a safe retry.
- **Result model.** `ready` → "Spremno", `adjusted` → "Prilagođeno" (shown as `requested → final`, still eligible for the cart), `error` → "Greška". The table lists RESULTING ORDERABLE UNITS (duplicates already merged by the server, with the merged spreadsheet rows named); the banner also states how many spreadsheet rows were read. Unresolved/inaccessible identifiers show only the submitted identifier and the common "Artikl nije pronađen ili nije dostupan." Partial import is supported; with zero eligible rows there is no cart CTA.
- **Cart step reuses `CartSubmit`** (`/cart/sync`: additive, 50-item chunks, typed failures, ambiguous-outcome rules) through a private `QuickOrderState`; the Gate 1 normalized quantity is the quantity attempted and `/cart/sync` stays authoritative (no hidden second clamp, no reservation). Progress = real chunk completion (indeterminate for a single request). `CartSubmit.submit()` gained optional `onProgress` and `stopOnAmbiguous` (+ `unsentKeys` in the result); the visible Quick Order submit passes neither and behaves as before. An ambiguous chunk stops the import at that boundary (nothing is retried or continued), and the final screen separates added / failed / unknown / not sent, with "Pregled košarice" as the only forward path. A pure-success screen is shown only when every unit is confirmed added.
- **Bug fixed on the way:** `wp_localize_script()` delivers top-level scalars as strings, so `cartSyncMaxBatch` was `"50"` and `i += "50"` produced oversized chunks for > 100 items (the server rejects chunks above its maximum). `CartSubmit` now coerces the value to a number.
- **Accessibility.** `role="dialog"` + `aria-modal` + labelled title, focus moved into the modal and to the new view's heading on every step, manual focus trap, background `inert`, Escape closes only in safe states (never during the in-flight cart request, where the close control is also removed), focus restored to the trigger, labelled file input with a keyboard alternative to drag & drop, one polite live region, result table with captions/`scope`, status never by colour alone, `prefers-reduced-motion` respected.
- **Tests** (local): `tests/import/run-modal-tests.mjs` (real Chromium, REAL template markup + localized copy via `render-harness.php`, mocked REST; also asserts the unchanged visible-QO submit) in addition to the Gate 1 parser/validator suites.
- **Staging acceptance (2026-10-06): PASS.** Plugin v1.0.33 also contains the modal keyboard/focus fix (document-level key handling when focus is on `<body>`). Production has NOT been deployed.
- **Pre-production manual acceptance (2026-10-07): PASS** — real Microsoft Excel smoke of the XLSX template (no warnings, column A Text, `000046` survives save/reopen and resolves through the staging UI), real focused desktop-Chrome keyboard smoke and real physical mobile-device smoke. No further Excel Import work is required; production deployment is not currently applicable because no DreamPoint B2B production environment is provisioned yet (see theme `docs/decisions.md` ADR-017).

## HPOS Compatibility

Declares compatibility with `custom_order_tables` (High-Performance Order
Storage) via `FeaturesUtil::declare_compatibility()` on
`before_woocommerce_init`. This is genuine, not just declared to silence
WooCommerce's admin warning: the plugin only ever touches orders through
`wc_get_orders()` / `WC_Order` and the cart through `WC()->cart` — no raw
SQL against order storage anywhere in the codebase. No other WooCommerce
feature (e.g. `cart_checkout_blocks`) is declared, since compatibility
with those has not been verified.

## Shortcode

```
[dp_quick_order]
```

Place on a page with slug `quick-order` (`DP_Quick_Order_Config::PAGE_SLUG`).
Gated on `is_user_logged_in()` and the `dp_b2b_quick_order_user_allowed`
filter (defaults to allow any logged-in user).

## REST API

Namespace: `dreampoint-b2b/v1` (`DP_Quick_Order_Config::REST_NAMESPACE`)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/quick-order/products` | Paginated, visibility-filtered, filter/sort-aware product list. See `class-rest-api.php::register_routes()` for the full query-arg schema (`qo_orderby`, `qo_order`, `qo_new`, `qo_best_seller`, `qo_already_ordered`, `category`, `brand`, `attributes`, `price_min`/`price_max`, `stock_status`, `search`). |
| GET | `/quick-order/products/{id}/variations` | Lightweight per-variation payload for one variable product, fetched on demand — never via `get_available_variations()` |
| POST | `/quick-order/cart/sync` | Batched, additive cart sync via WooCommerce's own cart API. One result per submitted item: `action` `added`/`updated`/`removed`/`skipped`, or `failed` + `error` ∈ `out_of_stock` / `quantity_unavailable` / `product_unavailable` / `not_addable`. No stock figure is ever returned; hidden, nonexistent, unpublished and parent-mismatched items all return `product_unavailable`. See `docs/decisions.md` ADR-016 (theme). |

All three require `is_b2b_user()` (logged-in + `dp_b2b_quick_order_user_allowed`).

## Extension points

| Filter | Purpose | Default |
|---|---|---|
| `dp_b2b_quick_order_user_allowed` | Gates shortcode render + all REST endpoints | `is_user_logged_in()` |
| `dp_b2b_product_accessible` | Visibility gate checked before a new cart-add (existing cart lines are not re-checked) | `true` |
| `dp_qo_new_product_max_age_days` | "New" filter threshold override | `30` |
| `dp_qo_best_seller_min_sales` | "Best Seller" filter threshold override | `10` |
| `dp_qo_already_ordered_statuses` | Order statuses counted as "already ordered" | `['processing', 'completed']` |

## File Structure

```
dp-b2b-quick-order/
├── dp-b2b-quick-order.php              — Bootstrap, autoloader, HPOS compatibility declaration
├── inc/
│   ├── class-plugin.php                — Wires up all components, hook registration
│   ├── class-config.php                — All constants (single source of truth)
│   ├── class-assets.php                — Script/style enqueue, wp_localize_script payload
│   ├── class-rest-api.php              — REST route registration + request handling
│   ├── class-product-query.php         — Product listing query (filters, sort, pagination)
│   ├── class-cart-sync.php             — Batched, additive WooCommerce cart sync
│   ├── class-already-ordered-resolver.php — Already Ordered filter's order-history resolution + cache
│   ├── class-visibility-integration.php   — Hooks into the theme's existing visibility engine
│   ├── class-filter-bridge.php         — Guards Quick Order queries against WOOF/WBW mutation
│   └── class-frontend.php              — Shortcode registration + template render
├── assets/
│   ├── src/                            — Source JS (ES modules), bundled by esbuild
│   │   ├── quick-order.js              — Entry point, wires all controllers together
│   │   ├── quick-order-state.js        — Local in-memory quantity state (QuickOrderState)
│   │   ├── product-list.js             — REST fetch/render, WBW URL-state bridge, pagination/sort
│   │   ├── row-controller.js           — Qty input/+/- binding, `.is-added` state
│   │   ├── footer-controller.js        — Item/row count + subtotal footer
│   │   ├── variation-chips.js          — Selected-variation chip row
│   │   └── cart-submit.js              — Batched submit to /cart/sync
│   └── dist/                           — Built output (quick-order.js via esbuild; quick-order.css is hand-authored directly, no CSS build step)
└── templates/
    └── quick-order.php                 — Frontend template shell (filters fieldset, table skeleton, footer)
```

## Development Workflow

```bash
npm install
npm run build   # one-shot minified build → assets/dist/quick-order.js
npm run watch:js # rebuild on change (unminified)
```

`assets/dist/quick-order.css` has no build step — edit it directly.

**Cache-busting:** assets are enqueued with `DP_QUICK_ORDER_VERSION`
(`dp-b2b-quick-order.php`) as the query-string version. Bump it on every
JS/CSS change — browsers cache the versioned URL indefinitely otherwise
(confirmed to cause stale-asset issues on both local and staging during
development).

## Testing Approach

No automated test suite exists for this plugin. Validation is manual,
browser-driven (Playwright) regression testing performed during
development and again after each staging deploy — golden-path flows
(quantity entry, add-to-cart, filters, pagination/sort, variation
handling) plus targeted checks for whatever the current change touches.
There is no CI gate; correctness is established by direct observation of
the running feature, not by a test file in this repository.

For generating a large, deterministic synthetic product catalog to
exercise filters/pagination/variation handling in a local or staging
environment, see the theme-side dev tool:
`inc/dev/class-dev-catalog-generator.php` (loaded only in WP-CLI context)
— `wp dp-b2b generate-catalog --phase=products|variables|taxonomies` and
`wp dp-b2b generate-catalog --refresh-metadata` (recomputes the
deterministic `total_sales`/publish-date tiers the New/Best Seller filters
rely on, without creating new products — needed periodically because
those tiers are time-relative and drift as real time passes). Full
mechanics: `docs/historical/synthetic-b2b-catalog.md` in the theme repo.

## Staging

The plugin is tracked in the same `wp-content` git repository as the
theme (deliberately — this is UncleDev's own custom code, not a
third-party plugin) and deploys via the theme's standard staging
workflow: `git push` locally, `git pull` on the server as the site user,
`wp cache flush`. No separate deploy process. See the theme repo's
`docs/` for the full deploy runbook and current staging domain.

## Known Limitations

- **Local state does not survive page navigation or reload** — by design
  (see Architecture above). Any in-progress, not-yet-submitted quantities
  are lost on refresh.
- ~~WBW-triggered actions cause a full page reload, not an AJAX
  update.~~ **Resolved.** See "WBW AJAX compatibility placeholder" above —
  WBW's own documented "Product List / Loader Selector" mechanism is now
  pointed at a hidden, inert placeholder element so its container check
  succeeds and it proceeds through its normal AJAX path (dispatching
  `wpfAjaxSuccess`) instead of reloading. Quick Order's own filters
  (Already Ordered/New/Best Seller) and the qty/cart flow were already
  fully AJAX and unaffected either way.

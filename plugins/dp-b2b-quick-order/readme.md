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
own REST query params. See **Known limitations** below for the one real
UX consequence of this split.

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
| POST | `/quick-order/cart/sync` | Batched, additive cart sync via WooCommerce's own cart API |

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
- **WBW-triggered actions cause a full page reload, not an AJAX update.**
  Clicking a native WBW control (Brand filter, Sort By) reloads the page;
  Quick Order's own filters (Already Ordered/New/Best Seller) and the
  qty/cart flow remain fully AJAX and preserve local state. Root cause
  (investigated and confirmed, not assumed): WBW's AJAX success handler
  looks for a native WooCommerce product-loop container (`ul.products` or
  a few known theme-specific variants) to inject filtered HTML into: none
  exists on this page, since Quick Order renders its own `<table>` instead
  — WBW falls back to `location.reload()` when it can't find one. This is
  not caused by an expired WBW license, a JS error, or a WBW
  misconfiguration; it is WBW's own documented last-resort fallback
  behavior for a product container it doesn't recognize. Not fixed here —
  a viable next step (a hidden dummy `ul.products` container WBW can
  target harmlessly, since Quick Order never reads WBW's injected HTML
  anyway) is a separate, scoped follow-up.

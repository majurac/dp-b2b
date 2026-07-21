# Quick Order Catalog Filters — Design Specification

Status: **IMPLEMENTED — COMPLETE** (2026-07-21). Execution record:
`docs/superpowers/plans/2026-07-14-quick-order-catalog-filters.md` (all
tasks executed, see its commit history). Current, authoritative behavior
description: plugin `readme.md` (`wp-content/plugins/dp-b2b-quick-order/readme.md`)
→ "Catalog Filters" section. This document is retained as the design
rationale record (ownership decisions, rejected alternatives, boundary
semantics) — treat it as historical background, not a pending task, from
this point forward.

Scope: four Quick Order filters exposed alongside WBW View ID 3 — **Already
Ordered**, **New**, **Best Seller**, **In Stock**. Builds directly on the
architectural recommendation accepted in this session and the established
`docs/frozen/quick-order-local-state-architecture.md` §11 WBW Integration
Doctrine. ACF is rejected for all four.

**Canonical ownership (accepted):**

| Filter | Owner |
|---|---|
| In Stock | Native WBW `wpfInStock` filter type — no Quick Order code |
| New | Quick Order custom filter — `post_date` via `date_query` |
| Best Seller | Quick Order custom filter — WooCommerce `total_sales` |
| Already Ordered | Quick Order custom filter — customer's own order history |

---

## 1. New

**Semantics:** a product is "New" if its `post_date` (publish date, `product`
post type, not `post_modified`) falls within the last N days of the current
request time.

**Query mechanism:** `date_query` on the existing `WP_Query` built in
`DP_Quick_Order_Product_Query::query()` — no ACF, no duplicated meta field.
No new post meta is written; `post_date_gmt` is the single source of truth.

**Timezone handling (revised 2026-07-14):** compares `post_date_gmt`
(always UTC) against a `time()`-based UTC cutoff — not `post_date` (which
is stored in the site's local time, per Settings > General timezone).
Both operands are UTC instants by construction, so there is no timezone
offset to assume or correct for, regardless of the site's configured
timezone. Boundary is inclusive (`'inclusive' => true`): a product
published exactly at the cutoff instant counts as "New"; one second
earlier does not.

```php
$query_args['date_query'][] = [
    'column'    => 'post_date_gmt',
    'after'     => gmdate( 'Y-m-d H:i:s', time() - ( DP_Quick_Order_Config::NEW_PRODUCT_MAX_AGE_DAYS * DAY_IN_SECONDS ) ),
    'inclusive' => true,
];
```

The threshold override (`dp_qo_new_product_max_age_days`) is defensively
validated: only a positive integer is accepted from the filter; any other
return value (zero, negative, non-numeric, non-integer-valued) falls back
to `NEW_PRODUCT_MAX_AGE_DAYS` rather than producing an inverted or
unbounded date range.

**Threshold ownership:** ONE constant, `DP_Quick_Order_Config::NEW_PRODUCT_MAX_AGE_DAYS`
(default `30`), defined once in `inc/class-config.php` alongside the plugin's
existing constants (`PRODUCTS_PER_PAGE_DEFAULT`, `CART_SYNC_MAX_BATCH`, etc.).
No other file may hardcode `30`. A `dp_qo_new_product_max_age_days` filter
wraps the constant read so it can be overridden without editing plugin code,
matching the existing `dp_b2b_quick_order_user_allowed` filter precedent in
`class-rest-api.php`.

**Not ACF, not a meta field:** `post_date` is a core `wp_posts` column, not
postmeta — WBW's `wpfCustomField` mechanism only ever discovers ACF
postmeta fields (see investigation below), so it structurally cannot expose
this even if ACF were used. Confirms Quick Order-custom is the only layer
that fits.

---

## 2. Best Seller

**Rejected framing:** "threshold or top-N" is not a filter — it's a sort.
WooCommerce's own `orderby=popularity` (already a valid `qo_orderby` target
in principle) answers "show me the most-sold first"; a **checkbox** must
instead answer a yes/no question per product, which requires a fixed
boundary, not a relative rank that changes size depending on how many
products are on the current result set.

**Decision: `total_sales >= configurable threshold`** (not `> 0`).

Rejected alternative — `total_sales > 0`: on this catalog `total_sales > 0`
degenerates toward "has ever sold a single unit," which drifts toward
matching most of an active catalog over time and stops meaningfully
narrowing results — the opposite of what a "Best Seller" checkbox promises
a B2B buyer. A configurable floor keeps the filter's meaning stable as the
catalog's sales volume grows.

**Query mechanism:** `meta_query` on the existing native `total_sales`
postmeta (WooCommerce core, updated automatically on order completion via
`wc_update_total_sales_counts()` — never written to by Quick Order):

```php
$query_args['meta_query'][] = [
    'key'     => 'total_sales',
    'value'   => DP_Quick_Order_Config::BEST_SELLER_MIN_SALES,
    'compare' => '>=',
    'type'    => 'NUMERIC',
];
```

**Threshold ownership:** ONE constant, `DP_Quick_Order_Config::BEST_SELLER_MIN_SALES`
(default `10`), same file, same pattern as §1. Filter: `dp_qo_best_seller_min_sales`.
This value is expected to need periodic re-tuning as real sales volume
accumulates — that is a content/business decision, not a code change, which
is exactly what isolating it to one filtered constant is for.

**Defensive validation (revised 2026-07-14, matching §1's pattern):** the
override is validated the same way as the "New" filter's max-age override —
only a positive integer is accepted from `dp_qo_best_seller_min_sales`; any
other return value (zero, negative, non-numeric, non-integer-valued like
`7.5`) falls back to `BEST_SELLER_MIN_SALES` rather than producing a broken
or always-true/always-false comparison.

**Synthetic catalog representative data (revised 2026-07-14):** the Phase 2
simple-product generator (`inc/dev/class-dev-catalog-generator.php`,
theme-side) already assigns deterministic stock and price tiers by cycling
through generated SKUs. Extend the same deterministic cycling (no
randomness, idempotent, re-run-safe) to `total_sales`, sourced LIVE from
`DP_Quick_Order_Config::BEST_SELLER_MIN_SALES` (never a second hardcoded
`10`, so a future retune of the constant doesn't silently desync the
generator):

| Tier | % of generated products | `total_sales` value |
|---|---|---|
| High sellers | 15% | threshold×5 – threshold×50 (clearly above) |
| Exactly at threshold | 5% | = threshold (boundary-inclusive case) |
| Moderate | 30% | 1 – threshold-1 (clearly below) |
| Zero | 50% | 0 (clearly below) |

The "exactly at threshold" tier is a deliberate addition over the original
two-tier design — a plain 50–500/1–9 split never produced a `total_sales`
value equal to the configured threshold itself, leaving the `>=` boundary
untested by representative data. Same publish-date-offset treatment for the
New filter, sourced from `DP_Quick_Order_Config::NEW_PRODUCT_MAX_AGE_DAYS`:

| Tier | % of generated products | Offset (days ago) |
|---|---|---|
| Clearly inside | 20% | 0 – threshold/2 |
| Near boundary, inside | 5% | threshold/2+1 – threshold-1 |
| Near boundary, outside | 5% | threshold+1 – threshold+14 |
| Clearly outside | 70% | threshold+15 – threshold×4 |

Both tables guarantee the New and Best Seller checkboxes exercise every
required branch (clear match, clear non-match, and the exact/near
boundary) in the dev dataset, not just a probabilistic aggregate split.
`total_sales` is native WC postmeta — no new `_dp_*` marker needed for it
specifically, but the generated products already carry `_dp_generated` for
cleanup targeting.

---

## 3. Already Ordered

**Included order statuses:** `processing`, `completed`.
**Excluded:** `pending`, `on-hold`, `cancelled`, `refunded`, `failed`,
`checkout-draft`, trashed orders.

Rationale: `on-hold` typically means payment is not yet confirmed (e.g. bank
transfer awaiting clearance) — not yet a confirmed purchase. `refunded` means
the customer was made whole again; continuing to flag that product as
"already ordered" would be actively misleading for a reorder workflow.
Default is computed as `array_diff( wc_get_is_paid_statuses(), ['refunded'] )`
at code level for traceability back to WooCommerce's own semantics, but the
resulting default array `['processing', 'completed']` is exposed via a
dedicated filter, `dp_qo_already_ordered_statuses`, so it can be widened
(e.g. to include a future custom ERP status) without touching plugin code —
this project currently defines no custom order statuses (confirmed: no
`wc_register_order_status` /`register_post_status` calls exist in this
codebase; ERP integration is still in Discovery phase).

**Parent vs. variation matching (revised 2026-07-14, superseding the
original draft below):** parent-level rollup. If the customer has
previously ordered ANY variation of a variable product, the **parent
product** qualifies for the filter. Once the parent qualifies, Quick Order
renders it exactly as it does today — all currently available variations,
unfiltered. The filter only decides *which parent products appear in the
list*; it never restricts which variations render under a qualifying
parent. Simple products are unaffected by this distinction (row granularity
and product granularity are the same thing for a simple product).

Rationale (business rule, explicit): the goal is product **rediscovery**,
not historical replay. A customer who ordered a Black XL T-Shirt before
should be able to find "T-Shirt" again and see the full current variation
set (Black/White × S/M/L/XL), not a filtered slice matching only what they
bought previously.

*(Superseded original decision, kept for record:* exact row-level match —
only the specific previously-ordered variation would count, siblings
excluded. Rejected because it conflicts with the rediscovery goal above.)

**Behavior for users without qualifying orders:** the checkbox remains
visible and usable; checking it returns zero products. Implementation
mirrors the existing pattern already in `class-product-query.php` for an
unresolvable category/brand token — an empty already-ordered ID set forces
`post__in => [0]` (an impossible ID) rather than silently ignoring the
filter and falling through to the full catalog.

**Administrator / test-user behavior:** no special case. `is_b2b_user()`
(the existing REST permission callback) already gates the whole endpoint on
`is_user_logged_in()` (or the `dp_b2b_quick_order_user_allowed` filter). An
admin or test account querying "Already Ordered" simply gets *their own*
order history, exactly like any other logged-in account — including
possibly zero results, handled by the rule above. There is no analog here to
the existing `manage_woocommerce` cart-add bypass (that bypass exists for a
permission gate; this filter has no permission dimension to bypass).

**HPOS-compatible querying:** `wc_get_orders()` (WooCommerce's own query
abstraction), never raw `$wpdb` SQL against `wp_posts` or the HPOS
`wc_orders` table directly. `wc_get_orders()` transparently respects
`woocommerce_custom_orders_table_usage_enabled` — the same code runs
correctly whether HPOS is active or not, with no environment branching in
Quick Order code:

```php
$orders = wc_get_orders( [
    'customer_id' => $user_id,
    'status'      => apply_filters( 'dp_qo_already_ordered_statuses', [ 'processing', 'completed' ] ),
    'limit'       => -1,
    'return'      => 'objects', // need line items, not just IDs
] );
```

Product/variation IDs are collected from each order's `get_items()` —
`$item->get_product_id()` and `$item->get_variation_id()` (0 for a simple
product line).

**Caching strategy:**
- Key: `dp_qo_already_ordered_{$user_id}` (per-user — this data is
  customer-specific by definition, never shared across users).
- Store: WordPress object cache (`wp_cache_get()` / `wp_cache_set()`, group
  `dp_quick_order`) — this project already runs Redis Object Cache, so this
  persists across requests with zero new infrastructure. This is the native
  WP caching API, not a custom caching layer (CLAUDE.md's "no custom caching
  without explicit request" is about inventing a bespoke mechanism, not
  about using the framework's own object cache).
- TTL: 12 hours (`12 * HOUR_IN_SECONDS`) as a safety net only — normal
  operation never relies on TTL expiry.
- Invalidation: hook `woocommerce_order_status_changed` (fires on every
  status transition, on both HPOS and legacy storage). If the transition
  enters OR leaves the qualifying-status set for that order, delete that
  order's customer's cache entry immediately:
  `wp_cache_delete( "dp_qo_already_ordered_{$order->get_customer_id()}", 'dp_quick_order' )`.
- Effect: `wc_get_orders()` only runs on a genuine cache miss (first use
  after login, or right after an invalidating status change) — never on
  every REST request, per the explicit requirement to avoid scanning full
  order history each time.

**Testing prerequisite (explicitly out of generator scope):** the synthetic
catalog generator (`class-dev-catalog-generator.php`) generates products and
taxonomies, not order history — fabricating fake WooCommerce orders is a
different, higher-risk system and is not requested here. Validating
"Already Ordered" requires a manual step: place one real test order
(as `vis_full` or another test account) against 2–3 generated SKUs and move
it to `processing` or `completed` before running the Playwright check. This
is recorded in the validation matrix below, not automated.

---

## 4. UI and URL Ownership

**Hard rule:** the three Quick Order-owned filters are never presented to
WBW as if they were WBW filter instances. No WBW admin filter, no WBW
template file, no WBW shortcode attribute is used for them. `In Stock`
stays 100% native WBW (`wpfInStock`) with zero Quick Order code.

### Why the ACF path is rejected here too (confirms Section 1–3 above)

Investigated for completeness, reusing the ACF discovery mechanism already
found in this session's earlier WBW audit: WBW's dedicated "Custom Field
(Allow ACF plugin)" filter type (`wpfCustomField`) is driven by
`WoofiltersModel::getCustomFieldFilterOptions($post_type)`
(`wp-content/plugins/woo-product-filter/modules/woofilters/models/woofilters.php:189`),
which calls `acf_get_field_groups(['post_type' => 'product'])` then
hard-filters to ACF field type `radio`/`checkbox` only (line 207) — every
other type, including a `true_false` "is best seller" flag, is silently
excluded from that specific filter type's selector. This is orthogonal to
today's decision (none of the four filters use ACF regardless), but confirms
there was never a viable ACF path for Best Seller/New/Already Ordered even
if the selector were populated — reinforcing the accepted ownership table.

### URL parameter naming

**Deviation from the prompt's example, with reason:** the prompt suggested
`dp_qo_already_ordered=1` etc. The codebase's actual, already-shipped
convention for Quick Order-owned query-string parameters is the shorter
`qo_` prefix — `qo_orderby`, `qo_order` (`class-rest-api.php:24-25`,
`product-list.js:81-82`). The `dp_` prefix is reserved in this codebase for
DOM/CSS classes (`dp-qo-row`, `.dp-qo-tbody`) and custom JS events
(`dp:qo:rows-rendered`), never for query-string keys. To stay consistent
with the one existing precedent at the same layer (URL/REST params), this
spec uses:

| Param | Values | Layer |
|---|---|---|
| `qo_new` | present (`1`) / absent | Browser URL + REST param, identical name, no translation |
| `qo_best_seller` | present (`1`) / absent | Browser URL + REST param, identical name, no translation |
| `qo_already_ordered` | present (`1`) / absent | Browser URL + REST param, identical name, no translation |

Presence-based (not `=1`/`=0` toggling) — matches how WBW's own filter
params behave (present-with-value = active, key absent = inactive). If the
`dp_qo_` prefix is actually preferred for a reason not visible in the code
(e.g. an external integration expecting it), that's a one-line override at
implementation time — flagging it here rather than silently deciding.

### Markup

One `<fieldset class="dp-qo-native-filters">` in `templates/quick-order.php`,
placed immediately adjacent to the existing `[wpf-filters id="3"]` output
(same visual filter-panel column) — a Quick Order-owned block, styled to sit
flush with WBW's rendered widgets, but structurally separate. No WBW
template file is touched.

```php
<fieldset class="dp-qo-native-filters">
    <legend><?php esc_html_e( 'Brzi filteri', 'dp-b2b-quick-order' ); ?></legend>
    <label>
        <input type="checkbox" class="dp-qo-filter-checkbox" data-qo-filter="qo_new">
        <?php esc_html_e( 'Novo', 'dp-b2b-quick-order' ); ?>
    </label>
    <label>
        <input type="checkbox" class="dp-qo-filter-checkbox" data-qo-filter="qo_best_seller">
        <?php esc_html_e( 'Najprodavanije', 'dp-b2b-quick-order' ); ?>
    </label>
    <label>
        <input type="checkbox" class="dp-qo-filter-checkbox" data-qo-filter="qo_already_ordered">
        <?php esc_html_e( 'Već naručeno', 'dp-b2b-quick-order' ); ?>
    </label>
    <button type="button" class="dp-qo-filters-clear"><?php esc_html_e( 'Poništi', 'dp-b2b-quick-order' ); ?></button>
</fieldset>
```

### Event flow (JS) — reuses the established doctrine, does not duplicate it

Extends `ProductList` (`assets/src/product-list.js`) — no new class needed,
the existing "URL is the single source of truth, re-parsed on every change"
pattern already used for WBW filters (`#extractWoofFilters`, `#woofFilters`)
is the correct home for a sibling extraction method:

- `#extractQoOwnedFilters(params)` — reads `qo_new`/`qo_best_seller`/
  `qo_already_ordered` presence off the SAME `URLSearchParams` instance
  already built in `#bindWoofIntegration()` and `#onWoofUrlChange()`, merges
  the three booleans into the same internal filter-state object
  (`#woofFilters`, or a sibling field alongside it) that already feeds
  `#buildProductsUrl()`.
- Checkbox `change` listener (new, in `#bindWoofIntegration()`): on toggle,
  read current `window.location.search`, set/delete the toggled `qo_*` key,
  `history.pushState(null, '', url)`, then call the SAME internal
  refetch-and-reset-to-page-1 path already used for WBW changes
  (`this.loadPage(1)` after updating internal state) — this is the one
  piece of genuinely new/custom logic, justified because WBW has no
  integration point for a control it doesn't render. Everything downstream
  of "state changed, refetch page 1" is fully reused, not duplicated.
- `#buildProductsUrl()` gains three lines mirroring the existing
  `stock_status`/`category` pattern: `if (f.qoNew) params.set('qo_new', '1');`
  etc.

### Clear All — both WBW and QO-owned filters, one button

The `.dp-qo-filters-clear` button:
1. Unchecks its own three checkboxes, strips their three URL params via
   `history.pushState`.
2. Additionally dispatches a real click on WBW's own native Clear All
   control if View 3 renders one:
   `document.querySelector('.wpfClearButton')?.click()`.

This reuses WBW's actual rendered, user-facing control rather than calling
any private WBW JS method — confirmed at
`wp-content/plugins/woo-product-filter/modules/woofilters/js/frontend.woofilters.js:798`,
a `body`-delegated `click` handler on `.wpfClearButton` that calls the real
`clearFilters(..., true)` → WBW's own AJAX refresh → `wpfAjaxSuccess` (which
`ProductList` already listens to). A real `.click()` on WBW's own rendered
button is the native-first choice — equivalent to the doctrine's existing
precedent of reacting to `wpfAjaxSuccess` instead of monkey-patching.

Confirmed safe in the other direction too: WBW's own native Clear All
(`clearFilters()`, same file, lines 1834–1903) only ever removes its own
rendered filter widgets' `data-get-attribute` keys plus a small fixed
internal whitelist (`wpf_order`, `wpf_count`, `all_products_filtering`,
`wpf_oistock`, `wpf_fbv`, `wpf_dpv`, `wpf_ebv`) — it never does a blanket
query-string wipe, so a user who clicks *only* WBW's native Clear All
(bypassing Quick Order's combined button) leaves `qo_*` params untouched.
That's an accepted, minor partial-clear edge case, not a bug: WBW's own
button only ever promised to clear WBW's own filters.

### Browser back/forward

No new handling required. WBW's own global `popstate` listener already
forces an unconditional `location.reload()` on any popstate event
(documented in the existing `product-list.js` comment) — since QO's
checkbox toggles go through the same `history.pushState`, back/forward
triggers the same existing reload, and on reload the checkboxes simply
re-derive their `checked` state from `window.location.search` in
`#bindWoofIntegration()` (extended to also call `#extractQoOwnedFilters`
once at construction time and reflect it onto the checkbox DOM elements).
This is the exact same "state is derived from the URL on load" pattern
already used for WBW filters — no bespoke back/forward code.

### Selected-filter chips

`[wpf-selected-filters id=3]` only ever reflects WBW's own internal filter
state — confirmed no public API exists to inject foreign entries into it.
Quick Order does not attempt to. Recommendation: **no separate QO-owned chip
row** — for three boolean checkboxes, the checkbox's own visible checked
state already communicates "active" clearly; inventing a parallel
removable-chip UI for three booleans adds UI surface without adding
information (violates the project's "keep architecture pragmatic" rule). If
visual parity with WBW's chips is wanted later, that is a separately-scoped
follow-up (a small "×" per checked label wired to the same single-filter
clear path), not part of this spec.

### Pagination and Sort By preservation

Free consequence of the design above, not separate logic: because
`qo_new`/`qo_best_seller`/`qo_already_ordered` live in the same internal
filter-state object already merged into `#buildProductsUrl()`'s
`URLSearchParams` on every `loadPage()` call — exactly like
`f.stock_status`/`f.category` today — they are automatically included on
every subsequent page fetch and every sort change with no additional code.

---

## 5. Summary Table

| Filter | Final semantics | State/URL owner | Query mechanism |
|---|---|---|---|
| In Stock | WBW native `wpfInStock` on `_stock_status` | WBW (fully) | WBW's own SQL, untouched |
| New | `post_date >= now - 30 days` | Quick Order (`qo_new`) | `date_query`, threshold via `DP_Quick_Order_Config::NEW_PRODUCT_MAX_AGE_DAYS` |
| Best Seller | `total_sales >= 10` | Quick Order (`qo_best_seller`) | `meta_query` on native `total_sales`, threshold via `DP_Quick_Order_Config::BEST_SELLER_MIN_SALES` |
| Already Ordered | current customer has a `processing`/`completed` order line matching this exact product/variation | Quick Order (`qo_already_ordered`) | `wc_get_orders()` (HPOS-safe) + per-user object cache, `post__in` intersection |

**Planned files** (implementation, not yet touched):
- `inc/class-config.php` — modify (2 new constants)
- `inc/class-product-query.php` — modify (3 new filter branches)
- `inc/class-already-ordered-resolver.php` — new (cache + order-history resolution, single responsibility)
- `inc/class-rest-api.php` — modify (3 new REST args)
- `inc/class-plugin.php` — modify (wire resolver, register invalidation hook)
- `templates/quick-order.php` — modify (fieldset markup)
- `assets/src/product-list.js` — modify (extraction, checkbox binding, Clear All)
- `inc/dev/class-dev-catalog-generator.php` (theme) — modify (total_sales + backdated post_date tiers)
- `docs/historical/synthetic-b2b-catalog.md` — modify (document new tiers)
- `docs/active/status.md`, `docs/active/current-phase.md` — modify (pointer updates)

**Validation matrix:**

| Scenario | Method |
|---|---|
| In Stock checkbox filters to `_stock_status=instock` | Manual — WBW admin, native, no Quick Order code to test |
| New checkbox returns only products published within 30 days | WP-CLI `wp eval` against synthetic catalog's backdated tier + Playwright checkbox toggle |
| New checkbox excludes older synthetic products | Same, assert exclusion side |
| Best Seller checkbox returns only `total_sales >= 10` | WP-CLI `wp eval` against synthetic catalog's seeded tiers + Playwright toggle |
| Best Seller threshold override via filter | WP-CLI `wp eval` with `add_filter('dp_qo_already_ordered_statuses', ...)`-style override, confirm constant is not hardcoded elsewhere |
| Already Ordered — qualifying order | Manual: place one `processing` test order, confirm exact variation appears |
| Already Ordered — non-qualifying status (`pending`, `on-hold`) excluded | Manual: same order left `pending`, confirm product does NOT appear |
| Already Ordered — refunded excluded | Manual: refund the test order, confirm product no longer appears, cache invalidated |
| Already Ordered — zero qualifying orders | Playwright: fresh B2B test user, checkbox returns empty set, not full catalog |
| Already Ordered — cache hit avoids re-querying orders | WP-CLI: assert `wp_cache_get()` returns non-false on second call without a new order |
| Clear All clears both WBW and QO filters in one click | Playwright: set a WBW filter + a QO checkbox, click combined Clear All, assert both URL and result set reset |
| Browser back restores previous combined filter state | Playwright: toggle QO checkbox, navigate back, assert checkbox unchecked and result set matches |
| Pagination/Sort preserve QO filters | Playwright: check a QO filter, go to page 2, change sort, assert filter still active in URL and result set |

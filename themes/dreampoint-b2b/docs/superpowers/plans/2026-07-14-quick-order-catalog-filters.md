# Quick Order Catalog Filters Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add three Quick Order-owned catalog filters (New, Best Seller, Already Ordered) alongside WBW View ID 3's native In Stock filter, exactly per `docs/active/quick-order-catalog-filters-spec.md`.

**Architecture:** New/Best Seller extend the existing `DP_Quick_Order_Product_Query::query()` with native `date_query`/`meta_query` clauses (no new class). Already Ordered gets its own single-responsibility resolver class (`DP_Quick_Order_Already_Ordered_Resolver`) for order-history lookup + per-user object-cache invalidation, wired into the existing manual-DI bootstrap in `class-plugin.php`. Frontend: three checkboxes rendered by Quick Order's own template, read/written to the URL by `ProductList` using the exact same "URL is the source of truth" pattern already used for WBW filters — no WBW files touched.

**Tech Stack:** PHP 8.3 (WordPress/WooCommerce plugin, `DP_Quick_Order_` autoload-by-classname convention), vanilla ES2022 JS (no build-time framework), esbuild bundling (`npm run build` in the plugin directory), WooCommerce core `total_sales`/`post_date`/`wc_get_orders()`, WP object cache (Redis-backed in this environment). No PHPUnit suite exists in this plugin — verification uses WP-CLI `wp eval` deterministic checks (this project's established pattern, see `~/.claude/rules/debugging.md`) plus manual Playwright per `docs/operational/staging-quick-order-checklist.md`.

## Global Constraints

- PHP 8.3+ syntax, WPCS formatting, `dreampoint-b2b`/`dp-b2b-quick-order` text domains as already used in each file.
- No ACF anywhere in this feature (rejected for all four filters — see spec §1–4).
- No new URL param may use the `dp_qo_` prefix — follow the existing `qo_` convention (`qo_orderby`, `qo_order`).
- No raw `$wpdb` SQL for order history — `wc_get_orders()` only (HPOS-safe).
- No new custom caching system — WP object cache API only (`wp_cache_get`/`wp_cache_set`/`wp_cache_delete`), group `dp_quick_order`.
- No WBW plugin file may be modified. No WBW private/internal JS method may be called directly — only real DOM `.click()` on WBW's own rendered `.wpfClearButton`, and listening to WBW's own `wpfAjaxSuccess` event (both already-established patterns in `product-list.js`).
- Every new magic number (age threshold, sales threshold) lives in exactly one place: `DP_Quick_Order_Config`, each wrapped by one `apply_filters()` call.
- `wp` in all commands below refers to the project's own `wp-cli.phar` — verify presence and PHP binary per `~/.claude/rules/windows-shell.md` before running (canonical: `C:\xampp2\php83\php.exe C:\xampp2\htdocs\dp-b2b\wp-cli.phar --path=C:\xampp2\htdocs\dp-b2b`).

---

### Task 1: Config constants

**Files:**
- Modify: `wp-content/plugins/dp-b2b-quick-order/inc/class-config.php`

**Interfaces:**
- Produces: `DP_Quick_Order_Config::NEW_PRODUCT_MAX_AGE_DAYS` (int, default 30), `DP_Quick_Order_Config::BEST_SELLER_MIN_SALES` (int, default 10), `DP_Quick_Order_Config::ALREADY_ORDERED_CACHE_TTL` (int seconds, default 12 hours), `DP_Quick_Order_Config::ALREADY_ORDERED_CACHE_GROUP` (string, `'dp_quick_order'`).

- [ ] **Step 1: Add the four constants**

Edit `inc/class-config.php`, inserting a new section after the existing `// ── Cart Sync ──` block (before the closing `}`):

```php
	// ── Catalog Filters (New / Best Seller / Already Ordered) ──────────────────

	// "New" filter threshold — post_date must be within this many days of now.
	// Single source of truth: never hardcode 30 elsewhere. Override via
	// the `dp_qo_new_product_max_age_days` filter.
	const NEW_PRODUCT_MAX_AGE_DAYS = 30;

	// "Best Seller" filter threshold — total_sales must be >= this value.
	// Single source of truth: never hardcode 10 elsewhere. Override via
	// the `dp_qo_best_seller_min_sales` filter. Expected to need periodic
	// re-tuning as real sales volume accumulates — a content decision, not code.
	const BEST_SELLER_MIN_SALES = 10;

	// "Already Ordered" per-user object-cache TTL — safety net only, real
	// invalidation happens on woocommerce_order_status_changed.
	const ALREADY_ORDERED_CACHE_TTL   = 12 * HOUR_IN_SECONDS;
	const ALREADY_ORDERED_CACHE_GROUP = 'dp_quick_order';
```

- [ ] **Step 2: Verify with WP-CLI**

Run:
```bash
C:\xampp2\php83\php.exe C:\xampp2\htdocs\dp-b2b\wp-cli.phar --path=C:\xampp2\htdocs\dp-b2b eval "echo DP_Quick_Order_Config::NEW_PRODUCT_MAX_AGE_DAYS . ' ' . DP_Quick_Order_Config::BEST_SELLER_MIN_SALES . ' ' . DP_Quick_Order_Config::ALREADY_ORDERED_CACHE_TTL . PHP_EOL;"
```
Expected: `30 10 43200`

- [ ] **Step 3: Commit**

```bash
git add wp-content/plugins/dp-b2b-quick-order/inc/class-config.php
git commit -m "feat(qo): add New/Best Seller/Already Ordered config constants"
```

---

### Task 2: "New" filter — query + REST arg

**Files:**
- Modify: `wp-content/plugins/dp-b2b-quick-order/inc/class-product-query.php:98-106` (insert after the existing stock_status block)
- Modify: `wp-content/plugins/dp-b2b-quick-order/inc/class-rest-api.php` (register `qo_new` REST arg, pass through)

**Interfaces:**
- Consumes: `DP_Quick_Order_Config::NEW_PRODUCT_MAX_AGE_DAYS` (Task 1).
- Produces: `query()` accepts a new `bool $args['new']`; REST accepts `qo_new` (boolean, default false).

- [ ] **Step 1: Add the date_query branch in `class-product-query.php`**

Insert immediately after the existing stock-status block (after line 106, before the "Product attribute filters" comment):

```php
		// "New" filter — post_date_gmt within the configured threshold.
		// Uses post_date_gmt (always UTC), never post_date (site-local, per
		// Settings > General timezone) — both sides of the comparison are
		// UTC instants by construction, so there is no timezone offset to
		// assume or correct for, regardless of the site's configured
		// timezone. `time()` is likewise always a UTC unix timestamp.
		if ( ! empty( $args['new'] ) ) {
			$max_age_days = apply_filters( 'dp_qo_new_product_max_age_days', DP_Quick_Order_Config::NEW_PRODUCT_MAX_AGE_DAYS );

			// Defensive: a filter callback may return anything. Only a
			// positive integer is valid — anything else (0, negative,
			// non-numeric, non-integer-valued like 5.5) falls back to the
			// configured default rather than producing an inverted or
			// unbounded date range.
			$is_valid_max_age = is_numeric( $max_age_days ) && (int) $max_age_days == $max_age_days && (int) $max_age_days > 0;
			$max_age_days     = $is_valid_max_age ? (int) $max_age_days : DP_Quick_Order_Config::NEW_PRODUCT_MAX_AGE_DAYS;

			// Boundary is inclusive: a product published exactly at the
			// cutoff instant (N days ago, to the second) IS "New"; one
			// second earlier is not. `inclusive => true` makes WP_Date_Query
			// emit `post_date_gmt >= cutoff` instead of its default `>`.
			$query_args['date_query'][] = [
				'column'    => 'post_date_gmt',
				'after'     => gmdate( 'Y-m-d H:i:s', time() - ( $max_age_days * DAY_IN_SECONDS ) ),
				'inclusive' => true,
			];
		}
```

- [ ] **Step 2: Register the REST arg in `class-rest-api.php`**

In the `args` array of the `/products` route (after the existing `'stock_status'` entry, before `'attributes'`):

```php
				'qo_new' => [ 'type' => 'boolean', 'default' => false ],
```

`'type' => 'boolean'` is the same convention already used for numeric/enum args in this file — WP's REST schema layer sanitizes/coerces the incoming value (`"1"`/`"true"`/`1`/`true` → `true`; absent → the declared `default` of `false`) before your handler ever sees it, exactly like the existing `stock_status` enum arg.

In `get_products()`, add to the array passed to `$this->product_query->query([...])` (after `'stock_status' => ...`):

```php
			'new'          => (bool) $request->get_param( 'qo_new' ),
```

- [ ] **Step 3: Verify with WP-CLI — inside/outside/boundary, invalid override, disabled state**

This creates 5 throwaway test products, runs the real `DP_Quick_Order_Product_Query::query()` (not a reimplementation) with `'new' => true`/`false` and with filter overrides, checks inclusion/exclusion, then deletes the test products.

**Constructor note:** at this point in the plan, `DP_Quick_Order_Product_Query` still takes only ONE constructor argument (`DP_Quick_Order_Visibility_Integration`) — the second argument (`DP_Quick_Order_Already_Ordered_Resolver`) is not introduced until Task 5. Do not reference that class here; it does not exist yet.

**Boundary-testing note:** the exact to-the-second `>=` vs `>` behavior of `'inclusive' => true` is WordPress core's own documented `WP_Date_Query` behavior (verified by reading the code, not by a live assertion) — a WP-CLI script cannot deterministically test an exact single-second boundary, because `$now` is captured before several DB-writing `wp_insert_post()` calls and the code under test calls `time()` again itself moments later; any latency between the two shifts the effective cutoff by the elapsed seconds. Instead, test with a **60-second margin on both sides** of the theoretical cutoff (comfortably larger than realistic script latency, comfortably smaller than meaningfully changing what "30 days" means): a post 60 seconds newer than the cutoff (must be included) and a post 60 seconds older than the cutoff (must be excluded). This validates "the boundary leans inclusive, not exclusive" without racing the clock.

Run exactly as written:

```bash
C:\xampp2\php83\php.exe C:\xampp2\htdocs\dp-b2b\wp-cli.phar --path=C:\xampp2\htdocs\dp-b2b eval "
wp_set_current_user(1);
\$now = time();
\$mk = function( \$seconds_ago ) use ( \$now ) {
    \$dt = gmdate( 'Y-m-d H:i:s', \$now - \$seconds_ago );
    return wp_insert_post([
        'post_type' => 'product', 'post_status' => 'publish',
        'post_title' => 'QO-NEW-TEST-' . \$seconds_ago . 's',
        'post_date_gmt' => \$dt, 'post_date' => \$dt,
    ]);
};
\$dayS = DAY_IN_SECONDS;
\$inside      = \$mk( 10 * \$dayS );          // 10 days ago — well within 30 days — must be INCLUDED
\$nearInside  = \$mk( 30 * \$dayS - 60 );      // 60s newer than the 30-day cutoff — must be INCLUDED
\$nearOutside = \$mk( 30 * \$dayS + 60 );      // 60s older than the 30-day cutoff — must be EXCLUDED
\$outside     = \$mk( 40 * \$dayS );           // 40 days ago — well outside — must be EXCLUDED

\$visibility = new DP_Quick_Order_Visibility_Integration();
\$q = new DP_Quick_Order_Product_Query( \$visibility );

// 1) enabled — inside/nearInside included, nearOutside/outside excluded
\$r1  = \$q->query([ 'page'=>1, 'per_page'=>50, 'search'=>'QO-NEW-TEST', 'category'=>[], 'brand'=>[], 'new'=>true ]);
\$ids = array_column(\$r1['products'], 'id');
echo 'inside_included: '       . (in_array(\$inside, \$ids)       ? 'yes' : 'no') . PHP_EOL;
echo 'near_inside_included: '  . (in_array(\$nearInside, \$ids)   ? 'yes' : 'no') . PHP_EOL;
echo 'near_outside_excluded: ' . (!in_array(\$nearOutside, \$ids)  ? 'yes' : 'no') . PHP_EOL;
echo 'outside_excluded: '      . (!in_array(\$outside, \$ids)      ? 'yes' : 'no') . PHP_EOL;

// 2) disabled/absent — all 4 present (query unchanged, no date_query applied)
\$r2   = \$q->query([ 'page'=>1, 'per_page'=>50, 'search'=>'QO-NEW-TEST', 'category'=>[], 'brand'=>[], 'new'=>false ]);
\$ids2 = array_column(\$r2['products'], 'id');
echo 'disabled_returns_all_4: ' . (count(array_intersect([\$inside,\$nearInside,\$nearOutside,\$outside], \$ids2)) === 4 ? 'yes' : 'no') . PHP_EOL;

// 3) invalid override (-5) falls back to the 30-day default — nearInside still included, nearOutside still excluded
add_filter('dp_qo_new_product_max_age_days', fn() => -5);
\$r3   = \$q->query([ 'page'=>1, 'per_page'=>50, 'search'=>'QO-NEW-TEST', 'category'=>[], 'brand'=>[], 'new'=>true ]);
\$ids3 = array_column(\$r3['products'], 'id');
echo 'invalid_override_falls_back: ' . (in_array(\$nearInside, \$ids3) && !in_array(\$nearOutside, \$ids3) ? 'yes' : 'no') . PHP_EOL;
remove_all_filters('dp_qo_new_product_max_age_days');

// 4) valid override (5 days) — inside (10d) now excluded too, only a <=5d post would pass (none exist here)
add_filter('dp_qo_new_product_max_age_days', fn() => 5);
\$r4   = \$q->query([ 'page'=>1, 'per_page'=>50, 'search'=>'QO-NEW-TEST', 'category'=>[], 'brand'=>[], 'new'=>true ]);
\$ids4 = array_column(\$r4['products'], 'id');
echo 'valid_override_narrows: ' . (!in_array(\$inside, \$ids4) && !in_array(\$nearInside, \$ids4) ? 'yes' : 'no') . PHP_EOL;
remove_all_filters('dp_qo_new_product_max_age_days');

foreach ([\$inside, \$nearInside, \$nearOutside, \$outside] as \$id) { wp_delete_post(\$id, true); }
"
```

Expected: every line prints `yes`. No PHP fatal or warning. If the environment's WP-CLI eval context still shows visibility-related exclusions even with `wp_set_current_user(1)`, report the exact discrepancy — do not weaken the assertions to make them pass.

- [ ] **Step 4: Verify composition — New + category, New + price, New + sort**

```bash
C:\xampp2\php83\php.exe C:\xampp2\htdocs\dp-b2b\wp-cli.phar --path=C:\xampp2\htdocs\dp-b2b eval "
wp_set_current_user(1);
\$now = time();
\$term = wp_insert_term('QO-NEW-TEST-CAT-' . time(), 'product_cat');
\$catId = \$term['term_id'];

\$mk = function( \$days_ago, \$price, \$catId ) use ( \$now ) {
    \$dt = gmdate( 'Y-m-d H:i:s', \$now - \$days_ago * DAY_IN_SECONDS );
    \$id = wp_insert_post([
        'post_type' => 'product', 'post_status' => 'publish',
        'post_title' => 'QO-NEW-COMBO-TEST-' . \$days_ago . 'd-' . \$price,
        'post_date_gmt' => \$dt, 'post_date' => \$dt,
    ]);
    update_post_meta(\$id, '_price', \$price);
    update_post_meta(\$id, '_regular_price', \$price);
    wp_set_object_terms(\$id, [\$catId], 'product_cat');
    return \$id;
};
\$inCatNew    = \$mk(5, 50, \$catId);   // new, in target category
\$outCatNew   = \$mk(5, 50, 0);        // new, NOT in target category (no term assigned)
\$inCatOld    = \$mk(60, 50, \$catId);  // in category, NOT new

\$visibility = new DP_Quick_Order_Visibility_Integration();
\$q = new DP_Quick_Order_Product_Query( \$visibility );

// New + category — only inCatNew should match (new AND in category)
\$r = \$q->query([ 'page'=>1, 'per_page'=>50, 'search'=>'QO-NEW-COMBO-TEST', 'category'=>[\$catId], 'brand'=>[], 'new'=>true ]);
\$ids = array_column(\$r['products'], 'id');
echo 'new_plus_category: ' . (in_array(\$inCatNew, \$ids) && !in_array(\$outCatNew, \$ids) && !in_array(\$inCatOld, \$ids) ? 'yes' : 'no') . PHP_EOL;

// New + price range 40-60 — inCatNew (price 50) matches, outCatNew (price 50, no cat) also matches since category isn't applied here
\$r2 = \$q->query([ 'page'=>1, 'per_page'=>50, 'search'=>'QO-NEW-COMBO-TEST', 'category'=>[], 'brand'=>[], 'new'=>true, 'price_min'=>40, 'price_max'=>60 ]);
\$ids2 = array_column(\$r2['products'], 'id');
echo 'new_plus_price: ' . (in_array(\$inCatNew, \$ids2) && in_array(\$outCatNew, \$ids2) && !in_array(\$inCatOld, \$ids2) ? 'yes' : 'no') . PHP_EOL;

// New + sort by price desc — no fatal, both new products present in some order
\$r3 = \$q->query([ 'page'=>1, 'per_page'=>50, 'search'=>'QO-NEW-COMBO-TEST', 'category'=>[], 'brand'=>[], 'new'=>true, 'orderby'=>'price', 'order'=>'DESC' ]);
\$ids3 = array_column(\$r3['products'], 'id');
echo 'new_plus_sort_no_fatal: ' . (in_array(\$inCatNew, \$ids3) && in_array(\$outCatNew, \$ids3) ? 'yes' : 'no') . PHP_EOL;

foreach ([\$inCatNew, \$outCatNew, \$inCatOld] as \$id) { wp_delete_post(\$id, true); }
wp_delete_term(\$catId, 'product_cat');
"
```

Expected: `new_plus_category: yes`, `new_plus_price: yes`, `new_plus_sort_no_fatal: yes`. No PHP fatal.

- [ ] **Step 5: PHP syntax check + unrelated-file check**

```bash
C:\xampp2\php83\php.exe -l "C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order\inc\class-product-query.php"
C:\xampp2\php83\php.exe -l "C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order\inc\class-rest-api.php"
```
Expected: `No syntax errors detected` for both.

```bash
git -C C:\xampp2\htdocs\dp-b2b\wp-content status --short
```
Expected: only `class-product-query.php` and `class-rest-api.php` appear as modified (plus the already-known pre-existing unrelated dirty files — `header.php`, `sass/theme/_vars.scss`, `.claude/`, `index.php` — do not touch or stage those).

- [ ] **Step 6: Commit**

```bash
git add wp-content/plugins/dp-b2b-quick-order/inc/class-product-query.php wp-content/plugins/dp-b2b-quick-order/inc/class-rest-api.php
git commit -m "feat(qo): add New filter (post_date_gmt date_query, inclusive boundary)"
```

---

### Task 3: "Best Seller" filter — query + REST arg

**Files:**
- Modify: `wp-content/plugins/dp-b2b-quick-order/inc/class-product-query.php` (insert alongside Task 2's block)
- Modify: `wp-content/plugins/dp-b2b-quick-order/inc/class-rest-api.php` (register `qo_best_seller` REST arg, pass through)

**Interfaces:**
- Consumes: `DP_Quick_Order_Config::BEST_SELLER_MIN_SALES` (Task 1).
- Produces: `query()` accepts `bool $args['best_seller']`; REST accepts `qo_best_seller` (boolean, default false).

- [ ] **Step 1: Add the meta_query branch in `class-product-query.php`**

Directly after the "New" block added in Task 2:

```php
		// "Best Seller" filter — native WC total_sales meta, configurable floor.
		// No popularity sort, no top-N, no ACF, no duplicated/custom meta —
		// total_sales is WooCommerce's own native counter, updated by core on
		// order completion; this filter only ever reads it.
		if ( ! empty( $args['best_seller'] ) ) {
			$min_sales = apply_filters( 'dp_qo_best_seller_min_sales', DP_Quick_Order_Config::BEST_SELLER_MIN_SALES );

			// Defensive: same validation shape as the "New" filter's max-age
			// override (Task 2) — only a positive integer is valid; anything
			// else (0, negative, non-numeric, non-integer-valued) falls back
			// to the configured default instead of producing a broken or
			// unbounded comparison.
			$is_valid_min_sales = is_numeric( $min_sales ) && (int) $min_sales == $min_sales && (int) $min_sales > 0;
			$min_sales          = $is_valid_min_sales ? (int) $min_sales : DP_Quick_Order_Config::BEST_SELLER_MIN_SALES;

			$query_args['meta_query'][] = [
				'key'     => 'total_sales',
				'value'   => $min_sales,
				'compare' => '>=',
				'type'    => 'NUMERIC',
			];
		}
```

- [ ] **Step 2: Register the REST arg in `class-rest-api.php`**

Args array, after `qo_new`:

```php
				'qo_best_seller' => [ 'type' => 'boolean', 'default' => false ],
```

`get_products()`, after `'new' => ...`:

```php
			'best_seller'  => (bool) $request->get_param( 'qo_best_seller' ),
```

- [ ] **Step 3: Verify with WP-CLI — below/at/above threshold, invalid override, disabled state**

Creates 3 throwaway test products with `total_sales` set directly via postmeta (the native WC meta key, no leading underscore — exactly what the query reads), runs the real `DP_Quick_Order_Product_Query::query()`, then deletes them.

```bash
C:\xampp2\php83\php.exe C:\xampp2\htdocs\dp-b2b\wp-cli.phar --path=C:\xampp2\htdocs\dp-b2b eval "
wp_set_current_user(1);
\$mk = function( \$sales ) {
    \$id = wp_insert_post([ 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'QO-BESTSELLER-TEST-' . \$sales . 'sales' ]);
    update_post_meta( \$id, 'total_sales', \$sales );
    return \$id;
};
\$below    = \$mk(9);   // below the default threshold (10) — must be EXCLUDED
\$atThresh = \$mk(10);  // exactly at threshold — must be INCLUDED ('>=')
\$above    = \$mk(500); // well above — must be INCLUDED

\$visibility = new DP_Quick_Order_Visibility_Integration();
\$q = new DP_Quick_Order_Product_Query( \$visibility );

// 1) enabled — below excluded, at/above included
\$r1  = \$q->query([ 'page'=>1, 'per_page'=>50, 'search'=>'QO-BESTSELLER-TEST', 'category'=>[], 'brand'=>[], 'best_seller'=>true ]);
\$ids = array_column(\$r1['products'], 'id');
echo 'below_excluded: ' . (!in_array(\$below, \$ids)    ? 'yes' : 'no') . PHP_EOL;
echo 'at_threshold_included: ' . (in_array(\$atThresh, \$ids) ? 'yes' : 'no') . PHP_EOL;
echo 'above_included: ' . (in_array(\$above, \$ids)     ? 'yes' : 'no') . PHP_EOL;

// 2) disabled/absent — all 3 present (query unchanged, no meta_query for total_sales applied)
\$r2   = \$q->query([ 'page'=>1, 'per_page'=>50, 'search'=>'QO-BESTSELLER-TEST', 'category'=>[], 'brand'=>[], 'best_seller'=>false ]);
\$ids2 = array_column(\$r2['products'], 'id');
echo 'disabled_returns_all_3: ' . (count(array_intersect([\$below,\$atThresh,\$above], \$ids2)) === 3 ? 'yes' : 'no') . PHP_EOL;

// 3) invalid override (-5) falls back to the default 10 — below (9) still excluded
add_filter('dp_qo_best_seller_min_sales', fn() => -5);
\$r3   = \$q->query([ 'page'=>1, 'per_page'=>50, 'search'=>'QO-BESTSELLER-TEST', 'category'=>[], 'brand'=>[], 'best_seller'=>true ]);
\$ids3 = array_column(\$r3['products'], 'id');
echo 'invalid_override_falls_back: ' . (!in_array(\$below, \$ids3) && in_array(\$atThresh, \$ids3) ? 'yes' : 'no') . PHP_EOL;
remove_all_filters('dp_qo_best_seller_min_sales');

// 4) valid override (600) — above (500) now excluded too
add_filter('dp_qo_best_seller_min_sales', fn() => 600);
\$r4   = \$q->query([ 'page'=>1, 'per_page'=>50, 'search'=>'QO-BESTSELLER-TEST', 'category'=>[], 'brand'=>[], 'best_seller'=>true ]);
\$ids4 = array_column(\$r4['products'], 'id');
echo 'valid_override_narrows: ' . (!in_array(\$above, \$ids4) && !in_array(\$atThresh, \$ids4) ? 'yes' : 'no') . PHP_EOL;
remove_all_filters('dp_qo_best_seller_min_sales');

foreach ([\$below, \$atThresh, \$above] as \$id) { wp_delete_post(\$id, true); }
"
```

Expected: every line prints `yes`. No PHP fatal or warning.

- [ ] **Step 4: Verify composition — category, brand, attributes, price, New, sort, pagination**

Creates one temporary global product attribute (cleaned up after) plus a handful of throwaway products, to test every composition pair required by the brief.

```bash
C:\xampp2\php83\php.exe C:\xampp2\htdocs\dp-b2b\wp-cli.phar --path=C:\xampp2\htdocs\dp-b2b eval "
wp_set_current_user(1);
\$now = time();

// temporary global attribute — pa_qo-bs-test-attr
\$attrId = wc_create_attribute([ 'name' => 'QO BS Test Attr', 'slug' => 'qo-bs-test-attr', 'type' => 'select' ]);
\$attrTaxonomy = wc_attribute_taxonomy_name('qo-bs-test-attr');
register_taxonomy( \$attrTaxonomy, 'product', [ 'hierarchical' => false ] );
\$attrTerm = wp_insert_term( 'QO-BS-Term', \$attrTaxonomy );
\$attrTermId = \$attrTerm['term_id'];

\$catTerm = wp_insert_term('QO-BS-TEST-CAT-' . \$now, 'product_cat');
\$catId   = \$catTerm['term_id'];
\$brandTerm = wp_insert_term('QO-BS-TEST-BRAND-' . \$now, 'product_brand');
\$brandId   = \$brandTerm['term_id'];

\$mk = function( \$sales, \$price, \$days_ago, \$catId, \$brandId, \$attrTermId ) use ( \$now, \$attrTaxonomy ) {
    \$dt = gmdate( 'Y-m-d H:i:s', \$now - \$days_ago * DAY_IN_SECONDS );
    \$id = wp_insert_post([
        'post_type' => 'product', 'post_status' => 'publish',
        'post_title' => 'QO-BS-COMBO-TEST-' . \$sales . '-' . \$price . '-' . \$days_ago,
        'post_date_gmt' => \$dt, 'post_date' => \$dt,
    ]);
    update_post_meta( \$id, 'total_sales', \$sales );
    update_post_meta( \$id, '_price', \$price );
    update_post_meta( \$id, '_regular_price', \$price );
    if ( \$catId )   { wp_set_object_terms( \$id, [ \$catId ], 'product_cat' ); }
    if ( \$brandId ) { wp_set_object_terms( \$id, [ \$brandId ], 'product_brand' ); }
    if ( \$attrTermId ) { wp_set_object_terms( \$id, [ \$attrTermId ], \$attrTaxonomy ); }
    return \$id;
};

// bestseller (100 sales) + new (5d) + price 50 + in category/brand/attribute
\$full   = \$mk(100, 50, 5, \$catId, \$brandId, \$attrTermId);
// bestseller but NOT new (60d), NOT in category/brand/attribute
\$bareBS = \$mk(100, 50, 60, 0, 0, 0);
// not a bestseller (1 sale) but IS new, in category/brand/attribute — must never match a best_seller=true query
\$notBS  = \$mk(1, 50, 5, \$catId, \$brandId, \$attrTermId);

\$visibility = new DP_Quick_Order_Visibility_Integration();
\$q = new DP_Quick_Order_Product_Query( \$visibility );

// Best Seller + Category
\$r = \$q->query([ 'page'=>1,'per_page'=>50,'search'=>'QO-BS-COMBO-TEST','category'=>[\$catId],'brand'=>[],'best_seller'=>true ]);
\$ids = array_column(\$r['products'],'id');
echo 'bs_plus_category: ' . (in_array(\$full,\$ids) && !in_array(\$bareBS,\$ids) && !in_array(\$notBS,\$ids) ? 'yes' : 'no') . PHP_EOL;

// Best Seller + Brand
\$r = \$q->query([ 'page'=>1,'per_page'=>50,'search'=>'QO-BS-COMBO-TEST','category'=>[],'brand'=>[\$brandId],'best_seller'=>true ]);
\$ids = array_column(\$r['products'],'id');
echo 'bs_plus_brand: ' . (in_array(\$full,\$ids) && !in_array(\$bareBS,\$ids) && !in_array(\$notBS,\$ids) ? 'yes' : 'no') . PHP_EOL;

// Best Seller + Attributes
\$r = \$q->query([ 'page'=>1,'per_page'=>50,'search'=>'QO-BS-COMBO-TEST','category'=>[],'brand'=>[],'attributes'=>['qo-bs-test-attr'=>['qo-bs-term']],'best_seller'=>true ]);
\$ids = array_column(\$r['products'],'id');
echo 'bs_plus_attributes: ' . (in_array(\$full,\$ids) && !in_array(\$bareBS,\$ids) && !in_array(\$notBS,\$ids) ? 'yes' : 'no') . PHP_EOL;

// Best Seller + Price (40-60 range) — full and bareBS both price 50, notBS excluded by best_seller regardless of price
\$r = \$q->query([ 'page'=>1,'per_page'=>50,'search'=>'QO-BS-COMBO-TEST','category'=>[],'brand'=>[],'price_min'=>40,'price_max'=>60,'best_seller'=>true ]);
\$ids = array_column(\$r['products'],'id');
echo 'bs_plus_price: ' . (in_array(\$full,\$ids) && in_array(\$bareBS,\$ids) && !in_array(\$notBS,\$ids) ? 'yes' : 'no') . PHP_EOL;

// Best Seller + New — only full (5d old) qualifies for both; bareBS is 60d old (not new)
\$r = \$q->query([ 'page'=>1,'per_page'=>50,'search'=>'QO-BS-COMBO-TEST','category'=>[],'brand'=>[],'new'=>true,'best_seller'=>true ]);
\$ids = array_column(\$r['products'],'id');
echo 'bs_plus_new: ' . (in_array(\$full,\$ids) && !in_array(\$bareBS,\$ids) && !in_array(\$notBS,\$ids) ? 'yes' : 'no') . PHP_EOL;

// Best Seller + Sort — no fatal, both bestseller products present regardless of order
\$r = \$q->query([ 'page'=>1,'per_page'=>50,'search'=>'QO-BS-COMBO-TEST','category'=>[],'brand'=>[],'best_seller'=>true,'orderby'=>'price','order'=>'DESC' ]);
\$ids = array_column(\$r['products'],'id');
echo 'bs_plus_sort_no_fatal: ' . (in_array(\$full,\$ids) && in_array(\$bareBS,\$ids) ? 'yes' : 'no') . PHP_EOL;

// Pagination — per_page=1 with best_seller=true still reports correct total/total_pages across the 2 matching bestseller products
\$r = \$q->query([ 'page'=>1,'per_page'=>1,'search'=>'QO-BS-COMBO-TEST','category'=>[],'brand'=>[],'best_seller'=>true ]);
echo 'bs_pagination: ' . (\$r['total'] === 2 && \$r['total_pages'] === 2 ? 'yes' : 'no') . PHP_EOL;

foreach ([\$full, \$bareBS, \$notBS] as \$id) { wp_delete_post(\$id, true); }
wp_delete_term(\$catId, 'product_cat');
wp_delete_term(\$brandId, 'product_brand');
wp_delete_term(\$attrTermId, \$attrTaxonomy);
wc_delete_attribute(\$attrId);
"
```

Expected: every `bs_plus_*`/`bs_pagination` line prints `yes`. No PHP fatal or warning.

- [ ] **Step 5: PHP syntax check + unrelated-file check**

```bash
C:\xampp2\php83\php.exe -l "C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order\inc\class-product-query.php"
C:\xampp2\php83\php.exe -l "C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order\inc\class-rest-api.php"
```
Expected: `No syntax errors detected` for both.

```bash
git -C C:\xampp2\htdocs\dp-b2b\wp-content status --short
```
Expected: only `class-product-query.php` and `class-rest-api.php` show as modified (plus the already-known pre-existing unrelated dirty files — do not stage those).

- [ ] **Step 6: Commit**

```bash
git add wp-content/plugins/dp-b2b-quick-order/inc/class-product-query.php wp-content/plugins/dp-b2b-quick-order/inc/class-rest-api.php
git commit -m "feat(qo): add Best Seller filter (total_sales meta_query, validated threshold)"
```

---

### Task 4: Already Ordered resolver class (cache + order history)

**Files:**
- Create: `wp-content/plugins/dp-b2b-quick-order/inc/class-already-ordered-resolver.php`
- Modify: `wp-content/plugins/dp-b2b-quick-order/inc/class-plugin.php` (construct + register invalidation hook)

**Interfaces:**
- Consumes: `DP_Quick_Order_Config::ALREADY_ORDERED_CACHE_TTL`, `::ALREADY_ORDERED_CACHE_GROUP` (Task 1). WooCommerce `wc_get_orders()`, `WC_Order::get_items()`, `WC_Order_Item_Product::get_product_id()`.
- Produces: `DP_Quick_Order_Already_Ordered_Resolver::get_ordered_product_ids( int $user_id ): array` — returns a deduplicated list of **parent product IDs** the given user has previously ordered (as a simple product, or via ANY variation of a variable product — the parent-level roll-up business rule) in a qualifying-status order. The resolver's only output is this ID list; it never touches `WP_Query`, `$query_args`, or any query-building code — that integration is Task 5's responsibility, not this class's. `::invalidate_for_order( int $order_id, string $from_status, string $to_status, WC_Order $order ): void` — deletes that order's customer's cache entry; registered as the `woocommerce_order_status_changed` callback (fires on every transition, HPOS or legacy storage alike).

- [ ] **Step 1: Create the resolver class**

```php
<?php
defined( 'ABSPATH' ) || exit;

/**
 * Resolves which PARENT product IDs the current customer has already
 * ordered (qualifying statuses only) — parent-level roll-up: ordering any
 * variation of a variable product qualifies the whole parent. Cached per
 * user in the WP object cache. Never scans full order history on every
 * request — only on a cache miss. Returns product IDs only; does not build
 * or touch any WP_Query arguments (see Task 5).
 */
class DP_Quick_Order_Already_Ordered_Resolver {

	/**
	 * @return list<int> parent product IDs
	 */
	public function get_ordered_product_ids( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return [];
		}

		$cache_key = "dp_qo_already_ordered_{$user_id}";
		$cached    = wp_cache_get( $cache_key, DP_Quick_Order_Config::ALREADY_ORDERED_CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		$product_ids = $this->query_ordered_product_ids( $user_id );

		wp_cache_set(
			$cache_key,
			$product_ids,
			DP_Quick_Order_Config::ALREADY_ORDERED_CACHE_GROUP,
			DP_Quick_Order_Config::ALREADY_ORDERED_CACHE_TTL
		);

		return $product_ids;
	}

	/**
	 * wc_get_orders() — WooCommerce's own order-query abstraction, HPOS and
	 * legacy-storage agnostic by construction (respects
	 * woocommerce_custom_orders_table_usage_enabled internally). No raw
	 * $wpdb SQL, no custom tables, no duplicated order/customer metadata.
	 *
	 * @return list<int>
	 */
	private function query_ordered_product_ids( int $user_id ): array {
		$statuses = apply_filters( 'dp_qo_already_ordered_statuses', [ 'processing', 'completed' ] );

		$orders = wc_get_orders( [
			'customer_id' => $user_id,
			'status'      => $statuses,
			'limit'       => -1,
			'return'      => 'objects',
		] );

		$product_ids = [];

		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			foreach ( $order->get_items() as $item ) {
				if ( ! $item instanceof WC_Order_Item_Product ) {
					continue;
				}
				// get_product_id() always returns the PARENT product ID, even
				// for a variation line item (WC_Abstract_Order::add_product()
				// sets it that way on every order item) — this one call IS
				// the parent-level roll-up; no separate "collapse to parent"
				// step is needed or added.
				$product_id = $item->get_product_id();
				if ( ! $product_id ) {
					continue;
				}
				$product_ids[] = $product_id;
			}
		}

		return array_values( array_unique( $product_ids ) );
	}

	/**
	 * Registered on woocommerce_order_status_changed — invalidates the
	 * ordering customer's cache entry on ANY status transition (entering or
	 * leaving the qualifying set), so a later refund correctly drops a
	 * product from "already ordered" and a later payment confirmation
	 * correctly adds it, without waiting for TTL expiry.
	 */
	public function invalidate_for_order( int $order_id, string $from_status, string $to_status, WC_Order $order ): void {
		$user_id = $order->get_customer_id();
		if ( $user_id <= 0 ) {
			return;
		}
		wp_cache_delete( "dp_qo_already_ordered_{$user_id}", DP_Quick_Order_Config::ALREADY_ORDERED_CACHE_GROUP );
	}
}
```

- [ ] **Step 2: Wire into `class-plugin.php`**

Add the property declaration (after `private DP_Quick_Order_Cart_Sync $cart_sync;`):

```php
	private DP_Quick_Order_Already_Ordered_Resolver $already_ordered;
```

In `init()`, add before the `$this->product_query = ...` line (the resolver has no dependency on product_query, but product_query will consume it in Task 5, so it must exist first):

```php
		$this->already_ordered = new DP_Quick_Order_Already_Ordered_Resolver();
		add_action( 'woocommerce_order_status_changed', [ $this->already_ordered, 'invalidate_for_order' ], 10, 4 );
```

Then update the `$this->product_query = ...` line to inject it (this changes `DP_Quick_Order_Product_Query`'s constructor — done together with Task 5):

```php
		$this->product_query = new DP_Quick_Order_Product_Query( $this->visibility, $this->already_ordered );
```

- [ ] **Step 3: Verify with WP-CLI — full scenario coverage**

Creates real WooCommerce fixtures (a test customer, 2+ simple products, one
variable product with 2 real variations, several real orders moved through
real status transitions via `WC_Order::set_status()`+`save()`, using the
native `WC_Order::add_product()` API — never manual order-item row
construction) and exercises every required scenario against the real
resolver, then deletes everything it created. Run exactly as written:

```bash
C:\xampp2\php83\php.exe C:\xampp2\htdocs\dp-b2b\wp-cli.phar --path=C:\xampp2\htdocs\dp-b2b eval "
\$testUser = wp_insert_user([ 'user_login' => 'qo-ao-test-' . time(), 'user_pass' => wp_generate_password(), 'role' => 'customer' ]);

\$simpleA = new WC_Product_Simple(); \$simpleA->set_name('QO-AO-SIMPLE-A'); \$simpleA->set_regular_price(10); \$simpleAId = \$simpleA->save();
\$simpleB = new WC_Product_Simple(); \$simpleB->set_name('QO-AO-SIMPLE-B'); \$simpleB->set_regular_price(10); \$simpleBId = \$simpleB->save();

\$parent = new WC_Product_Variable(); \$parent->set_name('QO-AO-PARENT'); \$parentId = \$parent->save();
\$var1 = new WC_Product_Variation(); \$var1->set_parent_id(\$parentId); \$var1->set_regular_price(10); \$var1Id = \$var1->save();
\$var2 = new WC_Product_Variation(); \$var2->set_parent_id(\$parentId); \$var2->set_regular_price(12); \$var2Id = \$var2->save();

\$resolver = new DP_Quick_Order_Already_Ordered_Resolver();
\$cacheKey = \"dp_qo_already_ordered_{\$testUser}\";

// cache MISS before ANY resolver call for this user — must run BEFORE
// $r0 below, since get_ordered_product_ids() unconditionally populates the
// cache after a miss (even with an empty result) — checking after would
// always see the just-populated (empty-array) entry, never a true miss.
\$preCache = wp_cache_get(\$cacheKey, DP_Quick_Order_Config::ALREADY_ORDERED_CACHE_GROUP);
echo 'cache_miss_before_first_populate: ' . (false === \$preCache ? 'yes' : 'no') . PHP_EOL;

// 1) customer with NO previous orders
\$r0 = \$resolver->get_ordered_product_ids(\$testUser);
echo 'no_previous_orders_empty: ' . (empty(\$r0) ? 'yes' : 'no') . PHP_EOL;

// 2) processing order — one simple product
\$o1 = wc_create_order();
\$o1->set_customer_id(\$testUser);
\$o1->add_product(wc_get_product(\$simpleAId), 1);
\$o1->set_status('processing');
\$o1->save();

\$r1 = \$resolver->get_ordered_product_ids(\$testUser);
echo 'simple_product_processing_included: ' . (in_array(\$simpleAId, \$r1) ? 'yes' : 'no') . PHP_EOL;

// cache HIT — populated by the call above
\$postCache = wp_cache_get(\$cacheKey, DP_Quick_Order_Config::ALREADY_ORDERED_CACHE_GROUP);
echo 'cache_hit_after_populate: ' . (is_array(\$postCache) && in_array(\$simpleAId, \$postCache) ? 'yes' : 'no') . PHP_EOL;

// 3) completed order — variation 1 of the variable product
\$o2 = wc_create_order();
\$o2->set_customer_id(\$testUser);
\$o2->add_product(wc_get_product(\$var1Id), 1);
\$o2->set_status('completed');
\$o2->save();

// this status transition must invalidate the cache immediately (not wait for TTL)
\$afterO2Cache = wp_cache_get(\$cacheKey, DP_Quick_Order_Config::ALREADY_ORDERED_CACHE_GROUP);
echo 'cache_invalidated_on_new_qualifying_order: ' . (false === \$afterO2Cache ? 'yes' : 'no') . PHP_EOL;

\$r2 = \$resolver->get_ordered_product_ids(\$testUser);
echo 'one_variation_completed_resolves_to_parent: ' . (in_array(\$parentId, \$r2) ? 'yes' : 'no') . PHP_EOL;

// 4) second completed order — variation 2 of the SAME parent — must not duplicate the parent ID
\$o3 = wc_create_order();
\$o3->set_customer_id(\$testUser);
\$o3->add_product(wc_get_product(\$var2Id), 1);
\$o3->set_status('completed');
\$o3->save();
\$r3 = \$resolver->get_ordered_product_ids(\$testUser);
echo 'multiple_variations_same_parent_deduped: ' . (count(array_keys(\$r3, \$parentId)) === 1 ? 'yes' : 'no') . PHP_EOL;

// 5) different parent — simple product B, processing
\$o4 = wc_create_order();
\$o4->set_customer_id(\$testUser);
\$o4->add_product(wc_get_product(\$simpleBId), 1);
\$o4->set_status('processing');
\$o4->save();
\$r4 = \$resolver->get_ordered_product_ids(\$testUser);
echo 'multiple_different_parents: ' . (in_array(\$simpleAId, \$r4) && in_array(\$parentId, \$r4) && in_array(\$simpleBId, \$r4) ? 'yes' : 'no') . PHP_EOL;

// 6) excluded statuses — pending, on-hold, cancelled, failed — one distinct product each, never included
\$excludedIds = [];
foreach ([ 'pending', 'on-hold', 'cancelled', 'failed' ] as \$status) {
    \$p = new WC_Product_Simple(); \$p->set_name('QO-AO-EXCL-' . \$status); \$p->set_regular_price(10); \$pid = \$p->save();
    \$excludedIds[ \$status ] = \$pid;
    \$o = wc_create_order();
    \$o->set_customer_id(\$testUser);
    \$o->add_product(wc_get_product(\$pid), 1);
    \$o->set_status(\$status);
    \$o->save();
}
// refunded requires a real processing->refunded transition, not a direct-to-refunded create.
// Deliberately NOT added to \$excludedIds yet — at this point \$oR is still
// 'processing' (a QUALIFYING status), so it correctly SHOULD appear in the
// next check; it only becomes a genuine "excluded" case after the refund
// transition below, which is verified separately by
// refunded_product_still_excluded.
\$pRefunded = new WC_Product_Simple(); \$pRefunded->set_name('QO-AO-EXCL-refunded'); \$pRefunded->set_regular_price(10); \$pRefundedId = \$pRefunded->save();
\$oR = wc_create_order();
\$oR->set_customer_id(\$testUser);
\$oR->add_product(wc_get_product(\$pRefundedId), 1);
\$oR->set_status('processing');
\$oR->save();

\$r5 = \$resolver->get_ordered_product_ids(\$testUser);
\$leaked = false;
foreach (\$excludedIds as \$pid) { if (in_array(\$pid, \$r5)) { \$leaked = true; } }
echo 'excluded_statuses_never_included: ' . (!\$leaked ? 'yes' : 'no') . PHP_EOL;

// 7) cache invalidation trigger, part 2 — refund o2 (var1's order); parent must STILL qualify via o3 (var2, still completed) — proves invalidation recomputes rather than just wiping the result to empty
\$oR->set_status('refunded');
\$oR->save();
\$o2->set_status('refunded');
\$o2->save();
\$afterRefundCache = wp_cache_get(\$cacheKey, DP_Quick_Order_Config::ALREADY_ORDERED_CACHE_GROUP);
echo 'cache_invalidated_on_refund: ' . (false === \$afterRefundCache ? 'yes' : 'no') . PHP_EOL;
\$r6 = \$resolver->get_ordered_product_ids(\$testUser);
echo 'parent_still_qualifies_via_other_variation_order: ' . (in_array(\$parentId, \$r6) ? 'yes' : 'no') . PHP_EOL;
echo 'refunded_product_still_excluded: ' . (!in_array(\$pRefundedId, \$r6) ? 'yes' : 'no') . PHP_EOL;

// HPOS: this environment runs HPOS (confirmed separately via
// Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
// === true) — every wc_get_orders()/WC_Order::save() call above already
// exercised the HPOS code path for real, not the legacy posts-table path.
echo 'hpos_enabled_in_this_run: ' . ( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'yes' : 'no' ) . PHP_EOL;

// cleanup — sweep ALL orders for this test customer (covers o1-o4/oR plus
// the 4 excluded-status orders created inside the foreach above, which
// weren't kept in named variables)
\$allTestOrders = wc_get_orders([ 'customer_id' => \$testUser, 'status' => array_keys( wc_get_order_statuses() ), 'limit' => -1, 'return' => 'objects' ]);
foreach ( \$allTestOrders as \$o ) { \$o->delete( true ); }
foreach ( \$excludedIds as \$pid ) { wp_delete_post( \$pid, true ); }
wp_delete_post( \$pRefundedId, true );
wp_delete_post( \$var1Id, true );
wp_delete_post( \$var2Id, true );
wp_delete_post( \$parentId, true );
wp_delete_post( \$simpleAId, true );
wp_delete_post( \$simpleBId, true );
wp_delete_user( \$testUser );
"
```

Expected: every line prints `yes`. No PHP fatal or warning.

- [ ] **Step 4: PHP syntax check + unrelated-file check**

```bash
C:\xampp2\php83\php.exe -l "C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order\inc\class-already-ordered-resolver.php"
C:\xampp2\php83\php.exe -l "C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order\inc\class-plugin.php"
```
Expected: `No syntax errors detected` for both.

```bash
git -C C:\xampp2\htdocs\dp-b2b\wp-content status --short
```
Expected: the new `class-already-ordered-resolver.php` (untracked) and modified `class-plugin.php`, plus the already-known pre-existing unrelated dirty files — do not stage those.

- [ ] **Step 5: Commit**

```bash
git add wp-content/plugins/dp-b2b-quick-order/inc/class-already-ordered-resolver.php wp-content/plugins/dp-b2b-quick-order/inc/class-plugin.php
git commit -m "feat(qo): add Already Ordered resolver with per-user object cache"
```

---

### Task 5: Already Ordered — query integration + REST arg

**Files:**
- Modify: `wp-content/plugins/dp-b2b-quick-order/inc/class-product-query.php` (constructor + query branch)
- Modify: `wp-content/plugins/dp-b2b-quick-order/inc/class-rest-api.php` (register `qo_already_ordered` REST arg, pass through)

**Interfaces:**
- Consumes: `DP_Quick_Order_Already_Ordered_Resolver::get_ordered_product_ids()` (Task 4) — already returns deduplicated PARENT product IDs, no row-key parsing needed at this layer.
- Produces: `query()` accepts `bool $args['already_ordered']`.

- [ ] **Step 1: Update the constructor**

Change:
```php
	public function __construct(
		private readonly DP_Quick_Order_Visibility_Integration $visibility
	) {}
```
to:
```php
	public function __construct(
		private readonly DP_Quick_Order_Visibility_Integration $visibility,
		private readonly DP_Quick_Order_Already_Ordered_Resolver $already_ordered
	) {}
```

- [ ] **Step 2: Add the filter branch in `query()`**

Directly after the Best Seller block from Task 3, before `$this->visibility->apply_to_query( $query_args );`:

```php
		// "Already Ordered" filter — current customer's own order history.
		// Parent-level rollup (business rule, spec §3 revised 2026-07-14):
		// if ANY variation of a parent was previously ordered, the parent
		// qualifies. The resolver already returns deduplicated PARENT
		// product IDs (Task 4) — no row-key parsing or "collapse to parent"
		// step needed at this layer. post__in filters WP_Query at the
		// parent-post level only (product_variation is a different post
		// type), so once a parent qualifies, prepare_product()/
		// get_variation_details() below run completely unmodified and
		// return ALL current variations, exactly as for any other filter —
		// satisfying "render exactly as today."
		if ( ! empty( $args['already_ordered'] ) ) {
			$product_ids = $this->already_ordered->get_ordered_product_ids( get_current_user_id() );
			// Empty result must force zero matches, not fall through to the
			// unfiltered catalog — same convention as the category/brand
			// resolution above.
			$query_args['post__in'] = $product_ids ?: [ 0 ];
		}
```

No payload changes — `prepare_product()` and `get_variation_details()` are
NOT touched by this task. Adding a per-variation "already ordered" flag
would be unused, unrequested surface area: nothing in Task 8's JS reads it,
and the business rule is a list-level filter only ("which parents appear"),
never a per-variation display distinction.

- [ ] **Step 3: Register the REST arg in `class-rest-api.php`**

Args array, after `qo_best_seller`:

```php
				'qo_already_ordered' => [ 'type' => 'boolean', 'default' => false ],
```

`get_products()`, after `'best_seller' => ...`:

```php
			'already_ordered' => (bool) $request->get_param( 'qo_already_ordered' ),
```

- [ ] **Step 4: Verify with WP-CLI — empty state + full composition matrix**

Creates a test customer, three products (one simple, one variable with 2
variations, one more simple — all tagged with distinct category/brand/
attribute/price/date/sales markers so every requested composition pair is
individually distinguishable), places real qualifying orders, then runs the
real `DP_Quick_Order_Product_Query::query()` (never a reimplementation) for
every required scenario. Run exactly as written:

**Visibility engine note:** this project has a separate, frozen, already-tested
B2B catalog visibility system (`docs/frozen/...`, see project `CLAUDE.md` —
do not touch it). It gates every Quick Order query on the current user's
`dp_bucket_id` user meta; a user with no bucket assigned is treated as
`no_access` and forced to zero results regardless of any other filter. The
existing test account `vis_full` (user ID may differ per environment — look
it up, don't hardcode) has `dp_bucket_id = 130`, which grants unrestricted
catalog visibility. Any ad-hoc test user created below must be given the
same meta value, or every query for that user will return zero products for
reasons that have nothing to do with the Already Ordered filter.

Run exactly as written:

```bash
C:\xampp2\php83\php.exe C:\xampp2\htdocs\dp-b2b\wp-cli.phar --path=C:\xampp2\htdocs\dp-b2b eval "
wp_set_current_user(1);
\$fullAccessBucketId = get_user_meta( get_user_by('login','vis_full')->ID, 'dp_bucket_id', true );

\$testUser = wp_insert_user([ 'user_login' => 'qo-ao-int-test-' . time(), 'user_pass' => wp_generate_password(), 'role' => 'customer' ]);
update_user_meta( \$testUser, 'dp_bucket_id', \$fullAccessBucketId );
\$now = time();

\$catTerm   = wp_insert_term('QO-AOINT-CAT-' . \$now, 'product_cat');
\$catId     = \$catTerm['term_id'];
\$brandTerm = wp_insert_term('QO-AOINT-BRAND-' . \$now, 'product_brand');
\$brandId   = \$brandTerm['term_id'];
\$attrId    = wc_create_attribute([ 'name' => 'QO AOInt Attr', 'slug' => 'qo-aoint-attr', 'type' => 'select' ]);
\$attrTaxonomy = wc_attribute_taxonomy_name('qo-aoint-attr');
register_taxonomy( \$attrTaxonomy, 'product', [ 'hierarchical' => false ] );
\$attrTerm  = wp_insert_term('QO-AOInt-Term', \$attrTaxonomy);
\$attrTermId = \$attrTerm['term_id'];

// simpleOrdered: category, price 50 (in 40-60 range), total_sales 50 (bestseller), 5 days old (new)
\$dtNew = gmdate('Y-m-d H:i:s', \$now - 5*DAY_IN_SECONDS);
\$simpleOrdered = new WC_Product_Simple();
\$simpleOrdered->set_name('QO-AOINT-SIMPLE-ORDERED'); \$simpleOrdered->set_regular_price(50);
\$simpleOrderedId = \$simpleOrdered->save();
wp_update_post([ 'ID' => \$simpleOrderedId, 'post_date_gmt' => \$dtNew, 'post_date' => \$dtNew ]);
update_post_meta(\$simpleOrderedId, 'total_sales', 50);
wp_set_object_terms(\$simpleOrderedId, [ \$catId ], 'product_cat');

// variableParent: brand, price synced from variations (45/48, in range), total_sales 100 on PARENT (bestseller), 60 days old (NOT new)
\$dtOld = gmdate('Y-m-d H:i:s', \$now - 60*DAY_IN_SECONDS);
\$variableParent = new WC_Product_Variable(); \$variableParent->set_name('QO-AOINT-VARIABLE'); \$variableParentId = \$variableParent->save();
wp_update_post([ 'ID' => \$variableParentId, 'post_date_gmt' => \$dtOld, 'post_date' => \$dtOld ]);
update_post_meta(\$variableParentId, 'total_sales', 100);
wp_set_object_terms(\$variableParentId, [ \$brandId ], 'product_brand');
\$var1 = new WC_Product_Variation(); \$var1->set_parent_id(\$variableParentId); \$var1->set_regular_price(45); \$var1Id = \$var1->save();
\$var2 = new WC_Product_Variation(); \$var2->set_parent_id(\$variableParentId); \$var2->set_regular_price(48); \$var2Id = \$var2->save();
WC_Product_Variable::sync(\$variableParentId);

// anotherParent: attribute, price 200 (OUT of 40-60 range), total_sales 0 (NOT bestseller), 5 days old (new)
\$anotherParent = new WC_Product_Simple(); \$anotherParent->set_name('QO-AOINT-ANOTHER'); \$anotherParent->set_regular_price(200);
\$anotherParentId = \$anotherParent->save();
wp_update_post([ 'ID' => \$anotherParentId, 'post_date_gmt' => \$dtNew, 'post_date' => \$dtNew ]);
update_post_meta(\$anotherParentId, 'total_sales', 0);
wp_set_object_terms(\$anotherParentId, [ \$attrTermId ], \$attrTaxonomy);

// unorderedControl: never ordered — must NEVER appear in any already_ordered=true result
\$unorderedControl = new WC_Product_Simple(); \$unorderedControl->set_name('QO-AOINT-UNORDERED'); \$unorderedControl->set_regular_price(50);
\$unorderedControlId = \$unorderedControl->save();

// Orders: simpleOrdered (processing), var1+var2 of variableParent (completed each — 'two variations of same parent'), anotherParent (processing)
\$o1 = wc_create_order(); \$o1->set_customer_id(\$testUser); \$o1->add_product(wc_get_product(\$simpleOrderedId), 1); \$o1->set_status('processing'); \$o1->save();
\$o2 = wc_create_order(); \$o2->set_customer_id(\$testUser); \$o2->add_product(wc_get_product(\$var1Id), 1); \$o2->set_status('completed'); \$o2->save();
\$o3 = wc_create_order(); \$o3->set_customer_id(\$testUser); \$o3->add_product(wc_get_product(\$var2Id), 1); \$o3->set_status('completed'); \$o3->save();
\$o4 = wc_create_order(); \$o4->set_customer_id(\$testUser); \$o4->add_product(wc_get_product(\$anotherParentId), 1); \$o4->set_status('processing'); \$o4->save();

\$visibility = new DP_Quick_Order_Visibility_Integration();
\$resolver   = new DP_Quick_Order_Already_Ordered_Resolver();
\$q = new DP_Quick_Order_Product_Query( \$visibility, \$resolver );
\$base = [ 'page'=>1, 'per_page'=>50, 'search'=>'QO-AOINT', 'category'=>[], 'brand'=>[], 'attributes'=>[] ];

// === Core semantics ===
wp_set_current_user(\$testUser);
\$r = \$q->query(array_merge( \$base, [ 'already_ordered'=>true ] ) );
\$ids = array_column(\$r['products'],'id');
echo 'simple_ordered_appears: ' . (in_array(\$simpleOrderedId,\$ids) ? 'yes':'no') . PHP_EOL;
echo 'variable_parent_appears_via_one_variation: ' . (in_array(\$variableParentId,\$ids) ? 'yes':'no') . PHP_EOL;
echo 'two_variations_same_parent_once: ' . (count(array_keys(\$ids,\$variableParentId)) === 1 ? 'yes':'no') . PHP_EOL;
echo 'multiple_qualifying_parents_all_present_once: ' . (count(array_keys(\$ids,\$simpleOrderedId))===1 && count(array_keys(\$ids,\$variableParentId))===1 && count(array_keys(\$ids,\$anotherParentId))===1 ? 'yes':'no') . PHP_EOL;
echo 'unordered_control_never_appears: ' . (!in_array(\$unorderedControlId,\$ids) ? 'yes':'no') . PHP_EOL;

// variations still render ALL current variations for a qualifying parent — not filtered to historically-ordered ones
\$variations = \$q->get_variation_details(\$variableParentId);
\$variationIds = array_column(\$variations, 'id');
echo 'all_current_variations_render_normally: ' . (in_array(\$var1Id,\$variationIds) && in_array(\$var2Id,\$variationIds) ? 'yes':'no') . PHP_EOL;

// no qualifying history — different (real) user with zero orders, SAME full-access bucket
// (so a zero result here proves "no orders", not "no visibility access" — an
// unbucketed control would return zero regardless of order history, which
// would not isolate what's actually being tested)
\$emptyUser = wp_insert_user([ 'user_login' => 'qo-ao-int-empty-' . time(), 'user_pass' => wp_generate_password(), 'role' => 'customer' ]);
update_user_meta( \$emptyUser, 'dp_bucket_id', \$fullAccessBucketId );
wp_set_current_user(\$emptyUser);
\$rEmpty = \$q->query(array_merge( \$base, [ 'already_ordered'=>true ] ) );
echo 'no_qualifying_history_zero_results: ' . (0 === \$rEmpty['total'] ? 'yes':'no') . PHP_EOL;
wp_set_current_user(\$testUser);

// disabled/absent — full catalog (all 4 products incl. unordered control)
\$rDisabled = \$q->query(array_merge( \$base, [ 'already_ordered'=>false ] ) );
\$idsDisabled = array_column(\$rDisabled['products'],'id');
echo 'disabled_returns_all_4: ' . (count(array_intersect([\$simpleOrderedId,\$variableParentId,\$anotherParentId,\$unorderedControlId],\$idsDisabled))===4 ? 'yes':'no') . PHP_EOL;

// === Composition ===
\$r = \$q->query(array_merge( \$base, [ 'already_ordered'=>true, 'category'=>[\$catId] ] ) );
\$ids = array_column(\$r['products'],'id');
echo 'ao_plus_category: ' . (in_array(\$simpleOrderedId,\$ids) && !in_array(\$variableParentId,\$ids) && !in_array(\$anotherParentId,\$ids) ? 'yes':'no') . PHP_EOL;

\$r = \$q->query(array_merge( \$base, [ 'already_ordered'=>true, 'brand'=>[\$brandId] ] ) );
\$ids = array_column(\$r['products'],'id');
echo 'ao_plus_brand: ' . (in_array(\$variableParentId,\$ids) && !in_array(\$simpleOrderedId,\$ids) && !in_array(\$anotherParentId,\$ids) ? 'yes':'no') . PHP_EOL;

\$r = \$q->query(array_merge( \$base, [ 'already_ordered'=>true, 'attributes'=>['qo-aoint-attr'=>['qo-aoint-term']] ] ) );
\$ids = array_column(\$r['products'],'id');
echo 'ao_plus_attribute: ' . (in_array(\$anotherParentId,\$ids) && !in_array(\$simpleOrderedId,\$ids) && !in_array(\$variableParentId,\$ids) ? 'yes':'no') . PHP_EOL;

\$r = \$q->query(array_merge( \$base, [ 'already_ordered'=>true, 'price_min'=>40, 'price_max'=>60 ] ) );
\$ids = array_column(\$r['products'],'id');
echo 'ao_plus_price: ' . (in_array(\$simpleOrderedId,\$ids) && in_array(\$variableParentId,\$ids) && !in_array(\$anotherParentId,\$ids) ? 'yes':'no') . PHP_EOL;

\$r = \$q->query(array_merge( \$base, [ 'already_ordered'=>true, 'new'=>true ] ) );
\$ids = array_column(\$r['products'],'id');
echo 'ao_plus_new: ' . (in_array(\$simpleOrderedId,\$ids) && in_array(\$anotherParentId,\$ids) && !in_array(\$variableParentId,\$ids) ? 'yes':'no') . PHP_EOL;

\$r = \$q->query(array_merge( \$base, [ 'already_ordered'=>true, 'best_seller'=>true ] ) );
\$ids = array_column(\$r['products'],'id');
echo 'ao_plus_best_seller: ' . (in_array(\$simpleOrderedId,\$ids) && in_array(\$variableParentId,\$ids) && !in_array(\$anotherParentId,\$ids) ? 'yes':'no') . PHP_EOL;

\$r = \$q->query(array_merge( \$base, [ 'already_ordered'=>true, 'new'=>true, 'best_seller'=>true ] ) );
\$ids = array_column(\$r['products'],'id');
echo 'ao_plus_new_plus_best_seller: ' . (count(\$ids)===1 && in_array(\$simpleOrderedId,\$ids) ? 'yes':'no') . PHP_EOL;

\$r = \$q->query(array_merge( \$base, [ 'already_ordered'=>true, 'orderby'=>'price', 'order'=>'DESC' ] ) );
\$ids = array_column(\$r['products'],'id');
echo 'ao_plus_sort_no_fatal: ' . (in_array(\$simpleOrderedId,\$ids) && in_array(\$variableParentId,\$ids) && in_array(\$anotherParentId,\$ids) ? 'yes':'no') . PHP_EOL;

\$r = \$q->query(array_merge( \$base, [ 'already_ordered'=>true, 'per_page'=>1 ] ) );
echo 'ao_pagination: ' . (\$r['total']===3 && \$r['total_pages']===3 ? 'yes':'no') . PHP_EOL;

// === query_vars inspection: post__in present only when enabled; tax_query/meta_query/date_query intact ===
\$captured = null;
add_action('pre_get_posts', function(\$wpq) use (&\$captured) { if (!empty(\$wpq->query_vars['dp_quick_order'])) { \$captured = \$wpq->query_vars; } }, 20000);
\$q->query(array_merge( \$base, [ 'already_ordered'=>true, 'category'=>[\$catId], 'price_min'=>1, 'price_max'=>999, 'new'=>true ] ) );
// post__in always exists as a WP_Query query_var key (WP_Query::fill_query_vars()
// defaults it to an empty array even when never set by any caller) — isset()
// is therefore NEVER a valid discriminator here. Check the actual VALUE
// instead: when enabled, post__in must hold the resolver's full,
// UN-narrowed output (all 3 already-ordered parents) — category/price/new
// narrow the FINAL result set via their own independent tax_query/meta_query/
// date_query clauses, not by pre-filtering what the resolver returned.
echo 'post_in_present_when_enabled: ' . (is_array(\$captured['post__in'] ?? null) && count(\$captured['post__in']) === 3 && in_array(\$simpleOrderedId, \$captured['post__in']) && in_array(\$variableParentId, \$captured['post__in']) && in_array(\$anotherParentId, \$captured['post__in']) ? 'yes':'no') . PHP_EOL;
echo 'tax_query_intact_alongside_post_in: ' . (!empty(\$captured['tax_query']) ? 'yes':'no') . PHP_EOL;
echo 'meta_query_intact_alongside_post_in: ' . (!empty(\$captured['meta_query']) ? 'yes':'no') . PHP_EOL;
echo 'date_query_intact_alongside_post_in: ' . (!empty(\$captured['date_query']) ? 'yes':'no') . PHP_EOL;

\$captured = null;
\$q->query(array_merge( \$base, [ 'already_ordered'=>false ] ) );
// When disabled, our code never touches post__in — it stays at WP_Query's
// own default (empty array), not merely \"unset\".
echo 'post_in_absent_when_disabled: ' . (empty(\$captured['post__in']) ? 'yes':'no') . PHP_EOL;

// === Cache-hit confirmation at the query layer — resolver's cache is the sole cache, query() never re-invokes wc_get_orders() ===
// Explicit cache-clear first: every earlier query() call above for
// \$testUser already populated the resolver's cache, so without clearing it
// here the very next call would already be a hit — this would make the
// filter-call-count assertion below trivially true for the wrong reason.
wp_cache_delete(\"dp_qo_already_ordered_{\$testUser}\", DP_Quick_Order_Config::ALREADY_ORDERED_CACHE_GROUP);
\$statusFilterCalls = 0;
add_filter('dp_qo_already_ordered_statuses', function(\$s) use (&\$statusFilterCalls) { \$statusFilterCalls++; return \$s; });
\$q->query(array_merge( \$base, [ 'already_ordered'=>true ] ) ); // genuine cache miss — resolver recomputes, filter fires once
\$q->query(array_merge( \$base, [ 'already_ordered'=>true ] ) ); // genuine cache hit — resolver returns cached array, filter does not fire again
echo 'query_layer_uses_resolver_cache_not_duplicated: ' . (1 === \$statusFilterCalls ? 'yes':'no') . PHP_EOL;

// cleanup
foreach ([ \$o1, \$o2, \$o3, \$o4 ] as \$o) { \$o->delete( true ); }
foreach ([ \$simpleOrderedId, \$variableParentId, \$var1Id, \$var2Id, \$anotherParentId, \$unorderedControlId ] as \$id) { wp_delete_post( \$id, true ); }
wp_delete_term(\$catId, 'product_cat');
wp_delete_term(\$brandId, 'product_brand');
wp_delete_term(\$attrTermId, \$attrTaxonomy);
wc_delete_attribute(\$attrId);
wp_delete_user(\$testUser);
wp_delete_user(\$emptyUser);
"
```

Expected: every line prints `yes`. No PHP fatal or warning.

- [ ] **Step 5: PHP syntax check + unrelated-file check**

```bash
C:\xampp2\php83\php.exe -l "C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order\inc\class-product-query.php"
C:\xampp2\php83\php.exe -l "C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order\inc\class-rest-api.php"
```
Expected: `No syntax errors detected` for both.

```bash
git -C C:\xampp2\htdocs\dp-b2b\wp-content status --short
```
Expected: only `class-product-query.php` and `class-rest-api.php` show as modified (plus the already-known pre-existing unrelated dirty files — do not stage those).

- [ ] **Step 6: Commit**

```bash
git add wp-content/plugins/dp-b2b-quick-order/inc/class-product-query.php wp-content/plugins/dp-b2b-quick-order/inc/class-rest-api.php
git commit -m "feat(qo): add Already Ordered filter (parent-level order-history match)"
```

---

### Task 6: Synthetic catalog — total_sales and backdated post_date tiers

**Files:**
- Modify: `wp-content/themes/dreampoint-b2b/inc/dev/class-dev-catalog-generator.php` (Phase 2 loop + two new deterministic-tier methods)
- Modify: `wp-content/themes/dreampoint-b2b/docs/historical/synthetic-b2b-catalog.md` (document new tiers)

**Interfaces:**
- Consumes: existing `$seed = abs( crc32( $sku ) )` pattern, existing `deterministic_stock()`/`deterministic_price()` sibling methods (same file).
- Produces: `deterministic_total_sales( int $seed ): int`, `deterministic_publish_offset_days( int $seed ): int` (both private, same class).

- [ ] **Step 1: Add the two deterministic-tier methods**

Insert as new private methods alongside `deterministic_stock()`/`deterministic_price()` (same "Deterministic randomness (seeded per SKU)" section):

```php
	/**
	 * total_sales tiers built around the LIVE configured Best Seller
	 * threshold (DP_Quick_Order_Config::BEST_SELLER_MIN_SALES) rather than a
	 * hardcoded duplicate of it — if the threshold is ever retuned, this
	 * generator stays correct without a second edit. Falls back to 10 only
	 * if the Quick Order plugin isn't active (defensive — this generator is
	 * dev-only tooling and must not hard-fail on an optional dependency).
	 *
	 * Tiers guarantee ALL THREE required cases are represented, not left to
	 * chance: 15% high sellers (threshold×5–threshold×50, clearly above),
	 * 5% EXACTLY at threshold (boundary-inclusive test — the value the
	 * original two-tier design never produced), 30% moderate
	 * (1–threshold-1, clearly below), 50% zero (clearly below).
	 */
	private function deterministic_total_sales( int $seed ): int {
		$threshold = class_exists( 'DP_Quick_Order_Config' ) ? DP_Quick_Order_Config::BEST_SELLER_MIN_SALES : 10;

		mt_srand( $seed );
		$roll = mt_rand( 1, 100 );

		if ( $roll <= 15 ) {
			return mt_rand( $threshold * 5, $threshold * 50 );
		}
		if ( $roll <= 20 ) {
			return $threshold;
		}
		if ( $roll <= 50 ) {
			return mt_rand( 1, max( 1, $threshold - 1 ) );
		}
		return 0;
	}

	/**
	 * Publish-date offset tiers built around the LIVE configured "New"
	 * window (DP_Quick_Order_Config::NEW_PRODUCT_MAX_AGE_DAYS) rather than a
	 * hardcoded duplicate — falls back to 30 only if the Quick Order plugin
	 * isn't active.
	 *
	 * Tiers guarantee BOTH SIDES of the boundary are deliberately
	 * represented, not left to chance across 200 products: 20% clearly
	 * inside (0 to threshold/2 days ago), 5% near boundary INSIDE
	 * (threshold/2+1 to threshold-1 days ago), 5% near boundary OUTSIDE
	 * (threshold+1 to threshold+14 days ago), 70% clearly outside
	 * (threshold+15 to threshold×4 days ago).
	 *
	 * @return int days to subtract from now for post_date
	 */
	private function deterministic_publish_offset_days( int $seed ): int {
		$threshold = class_exists( 'DP_Quick_Order_Config' ) ? DP_Quick_Order_Config::NEW_PRODUCT_MAX_AGE_DAYS : 30;

		mt_srand( $seed + 1 ); // +1 so this doesn't reuse the same draw as total_sales for the same SKU
		$roll = mt_rand( 1, 100 );

		if ( $roll <= 20 ) {
			return mt_rand( 0, intdiv( $threshold, 2 ) );
		}
		if ( $roll <= 25 ) {
			return mt_rand( intdiv( $threshold, 2 ) + 1, $threshold - 1 );
		}
		if ( $roll <= 30 ) {
			return mt_rand( $threshold + 1, $threshold + 14 );
		}
		return mt_rand( $threshold + 15, $threshold * 4 );
	}
```

- [ ] **Step 2: Apply both in the Phase 2 product loop**

In `generate_simple_products()`, after the existing `$price = $this->deterministic_price( $seed );` line, add:

```php
			$total_sales   = $this->deterministic_total_sales( $seed );
			$publish_days  = $this->deterministic_publish_offset_days( $seed );
			// current_time('timestamp') + gmdate(): deliberate. WooCommerce's
			// set_date_created() treats a plain datetime string (no timezone/
			// offset) as SITE-LOCAL, not UTC (WC_Data::set_date_prop()). Do not
			// "simplify" this to time()+gmdate() or a different date API without
			// re-verifying that assumption — doing so may silently change the
			// stored datetime semantics and introduce timezone/DST regressions.
			$publish_date  = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $publish_days * DAY_IN_SECONDS ) );
```

After `$product->set_regular_price( $price );`, add:

```php
			$product->set_total_sales( $total_sales );
			$product->set_date_created( $publish_date );
```

- [ ] **Step 3: Regenerate on a clean LOCAL batch**

This resets and regenerates the LOCAL dev catalog only — never run
`reset-catalog` against staging (see the reminder below and
`docs/active/status.md`'s persistent-dataset policy, which governs staging,
not local).

```bash
C:\xampp2\php83\php.exe C:\xampp2\htdocs\dp-b2b\wp-cli.phar --path=C:\xampp2\htdocs\dp-b2b dp-b2b reset-catalog
C:\xampp2\php83\php.exe C:\xampp2\htdocs\dp-b2b\wp-cli.phar --path=C:\xampp2\htdocs\dp-b2b dp-b2b generate-catalog --phase=taxonomies
C:\xampp2\php83\php.exe C:\xampp2\htdocs\dp-b2b\wp-cli.phar --path=C:\xampp2\htdocs\dp-b2b dp-b2b generate-catalog --phase=products --count=200
```

**Reminder:** per `docs/active/status.md`'s staging dataset policy, do NOT
run `reset-catalog` against staging — this step is local-only until the
feature is staging-validated.

- [ ] **Step 4: Verify exact per-SKU tier assignment (not just aggregate counts)**

The generator's tier logic is fully deterministic (`mt_srand($seed)`/
`mt_srand($seed+1)` seeded from `crc32($sku)`), so specific SKUs' tier
assignment can be predicted in advance rather than only checked in
aggregate. Pre-computed for `--count=200` with the approved defaults
(`BEST_SELLER_MIN_SALES=10`, `NEW_PRODUCT_MAX_AGE_DAYS=30`):

| SKU | total_sales tier | total_sales value | publish-offset tier | offset (days) |
|---|---|---|---|---|
| DEV-0001 | exactly-at-threshold | 10 | near boundary, inside | 25 |
| DEV-0002 | zero | 0 | clearly outside | 48 |
| DEV-0008 | high | 276 | clearly inside | 6 |
| DEV-0009 | moderate | 1 | near boundary, inside | 27 |
| DEV-0012 | high | 302 | clearly outside | 115 |
| DEV-0032 | high | 485 | near boundary, outside | 38 |

This table gives, in one deterministic set: an exact-boundary Best Seller
case, a near-boundary-inside New case (DEV-0001, doubling as both), a
near-boundary-outside New case (DEV-0032), a both-qualify New+Best-Seller
case (DEV-0008), a Best-Seller-only case (DEV-0012), a neither-qualifies
control (DEV-0002), and a New-only case (DEV-0009).

```bash
C:\xampp2\php83\php.exe C:\xampp2\htdocs\dp-b2b\wp-cli.phar --path=C:\xampp2\htdocs\dp-b2b eval "
\$expected = [
    'DEV-0001' => [ 'sales' => 10,  'offset_days' => 25  ],
    'DEV-0002' => [ 'sales' => 0,   'offset_days' => 48  ],
    'DEV-0008' => [ 'sales' => 276, 'offset_days' => 6   ],
    'DEV-0009' => [ 'sales' => 1,   'offset_days' => 27  ],
    'DEV-0012' => [ 'sales' => 302, 'offset_days' => 115 ],
    'DEV-0032' => [ 'sales' => 485, 'offset_days' => 38  ],
];
\$now = time();
\$allOk = true;
foreach (\$expected as \$sku => \$exp) {
    \$id = wc_get_product_id_by_sku(\$sku);
    if (!\$id) { echo \"MISSING PRODUCT: \$sku\" . PHP_EOL; \$allOk = false; continue; }
    \$product = wc_get_product(\$id);
    \$actualSales = (int) \$product->get_total_sales();
    \$salesOk = \$actualSales === \$exp['sales'];

    \$postDateGmt = get_post_field('post_date_gmt', \$id);
    \$actualAgeSeconds = \$now - strtotime(\$postDateGmt . ' UTC');
    \$expectedAgeSeconds = \$exp['offset_days'] * DAY_IN_SECONDS;
    // 300s tolerance — real wall-clock time elapses between generation and
    // this check; the tier system operates on whole-day granularity, so a
    // few minutes of drift is immaterial to which tier a product landed in.
    \$dateOk = abs(\$actualAgeSeconds - \$expectedAgeSeconds) < 300;

    echo \"\$sku: sales=\$actualSales (expected {\$exp['sales']}) \" . (\$salesOk?'OK':'MISMATCH') . ' | age_seconds=' . \$actualAgeSeconds . ' expected=' . \$expectedAgeSeconds . ' ' . (\$dateOk?'OK':'MISMATCH') . PHP_EOL;
    if (!\$salesOk || !\$dateOk) { \$allOk = false; }
}
echo 'all_representative_skus_match: ' . (\$allOk ? 'yes' : 'no') . PHP_EOL;
"
```
Expected: `all_representative_skus_match: yes`, every row `OK`/`OK`.

- [ ] **Step 5: Verify the real filters against the representative SKUs, plus aggregate tier proportions**

```bash
C:\xampp2\php83\php.exe C:\xampp2\htdocs\dp-b2b\wp-cli.phar --path=C:\xampp2\htdocs\dp-b2b eval "
\$visibility = new DP_Quick_Order_Visibility_Integration();
\$resolver   = new DP_Quick_Order_Already_Ordered_Resolver();
\$q = new DP_Quick_Order_Product_Query( \$visibility, \$resolver );
\$base = [ 'page'=>1, 'per_page'=>200, 'search'=>'', 'category'=>[], 'brand'=>[] ];

\$skuOf = function(\$id) { return get_post_meta(\$id, '_sku', true); };

\$rNew = \$q->query(array_merge(\$base, [ 'new'=>true ]));
\$newIds = array_column(\$rNew['products'], 'id');
\$newSkus = array_map(\$skuOf, \$newIds);
echo 'new_includes_0001: ' . (in_array('DEV-0001', \$newSkus) ? 'yes':'no') . PHP_EOL;
echo 'new_includes_0009: ' . (in_array('DEV-0009', \$newSkus) ? 'yes':'no') . PHP_EOL;
echo 'new_excludes_0002: ' . (!in_array('DEV-0002', \$newSkus) ? 'yes':'no') . PHP_EOL;
echo 'new_excludes_0032: ' . (!in_array('DEV-0032', \$newSkus) ? 'yes':'no') . PHP_EOL;

\$rBest = \$q->query(array_merge(\$base, [ 'best_seller'=>true ]));
\$bestIds = array_column(\$rBest['products'], 'id');
\$bestSkus = array_map(\$skuOf, \$bestIds);
echo 'best_seller_includes_0001_exact_threshold: ' . (in_array('DEV-0001', \$bestSkus) ? 'yes':'no') . PHP_EOL;
echo 'best_seller_includes_0012: ' . (in_array('DEV-0012', \$bestSkus) ? 'yes':'no') . PHP_EOL;
echo 'best_seller_excludes_0002: ' . (!in_array('DEV-0002', \$bestSkus) ? 'yes':'no') . PHP_EOL;
echo 'best_seller_excludes_0009: ' . (!in_array('DEV-0009', \$bestSkus) ? 'yes':'no') . PHP_EOL;

\$rBoth = \$q->query(array_merge(\$base, [ 'new'=>true, 'best_seller'=>true ]));
\$bothSkus = array_map(\$skuOf, array_column(\$rBoth['products'], 'id'));
echo 'new_plus_best_seller_includes_0008: ' . (in_array('DEV-0008', \$bothSkus) ? 'yes':'no') . PHP_EOL;
echo 'new_plus_best_seller_excludes_0012: ' . (!in_array('DEV-0012', \$bothSkus) ? 'yes':'no') . PHP_EOL;
echo 'new_plus_best_seller_excludes_0009: ' . (!in_array('DEV-0009', \$bothSkus) ? 'yes':'no') . PHP_EOL;

echo 'best_seller_count: ' . \$rBest['total'] . ' / new_count: ' . \$rNew['total'] . ' / total: 200' . PHP_EOL;
echo 'aggregate_proportions_sane: ' . (\$rBest['total'] > 0 && \$rBest['total'] < 200 && \$rNew['total'] > 0 && \$rNew['total'] < 200 ? 'yes':'no') . PHP_EOL;
"
```
Expected: every `yes`/`OK` line as such; `aggregate_proportions_sane: yes` (roughly ~20-25% best_seller, ~25-30% new — both strictly between 0 and 200, proving neither branch is all-or-nothing).

- [ ] **Step 6: Idempotency — re-run without reset, confirm no duplicates and stable values**

```bash
C:\xampp2\php83\php.exe C:\xampp2\htdocs\dp-b2b\wp-cli.phar --path=C:\xampp2\htdocs\dp-b2b eval "echo (int) wp_count_posts('product')->publish . PHP_EOL;"
C:\xampp2\php83\php.exe C:\xampp2\htdocs\dp-b2b\wp-cli.phar --path=C:\xampp2\htdocs\dp-b2b dp-b2b generate-catalog --phase=taxonomies
C:\xampp2\php83\php.exe C:\xampp2\htdocs\dp-b2b\wp-cli.phar --path=C:\xampp2\htdocs\dp-b2b dp-b2b generate-catalog --phase=products --count=200
C:\xampp2\php83\php.exe C:\xampp2\htdocs\dp-b2b\wp-cli.phar --path=C:\xampp2\htdocs\dp-b2b eval "echo (int) wp_count_posts('product')->publish . PHP_EOL;"
C:\xampp2\php83\php.exe C:\xampp2\htdocs\dp-b2b\wp-cli.phar --path=C:\xampp2\htdocs\dp-b2b eval "
\$id = wc_get_product_id_by_sku('DEV-0001');
\$product = wc_get_product(\$id);
echo 'DEV-0001 total_sales still 10: ' . ((int) \$product->get_total_sales() === 10 ? 'yes' : 'no') . PHP_EOL;
"
```
Expected: the two `wp_count_posts` numbers are IDENTICAL (no duplicates created on re-run — existing SKU-based skip logic already handles this, this task doesn't change that logic), and `DEV-0001 total_sales still 10: yes` (deterministic value unchanged by re-run, since the SKU already existed and was skipped, not regenerated with fresh randomness).

- [ ] **Step 7: PHP syntax + unrelated-file check**

```bash
C:\xampp2\php83\php.exe -l "C:\xampp2\htdocs\dp-b2b\wp-content\themes\dreampoint-b2b\inc\dev\class-dev-catalog-generator.php"
```
Expected: `No syntax errors detected`.

```bash
git -C C:\xampp2\htdocs\dp-b2b\wp-content status --short
```
Expected: only `class-dev-catalog-generator.php` and `docs/historical/synthetic-b2b-catalog.md` (Step 8 below) show as modified, plus the already-known pre-existing unrelated dirty files.

- [ ] **Step 8: Standing datetime review — gmdate() scope, full branch**

```bash
git -C C:\xampp2\htdocs\dp-b2b\wp-content diff d62ff7d..HEAD -- '*.php' | grep -n 'gmdate('
```
Expected: exactly two live occurrences — the New filter's `post_date_gmt` cutoff
(`class-product-query.php`) and this task's `current_time('timestamp')`-paired
formatting call (`class-dev-catalog-generator.php`). Any third occurrence is a
Critical finding — stop and report it, do not resolve it unilaterally.

- [ ] **Step 9: Already Ordered fixtures — confirm out-of-scope, do not add**

Per the approved spec (`docs/active/quick-order-catalog-filters-spec.md` §3,
"Testing prerequisite") and this task's own file list, the catalog generator
does not create orders or customers — order/customer fixtures for Already
Ordered were already exercised directly inside Task 4's and Task 5's own
WP-CLI verification scripts (real `wc_create_order()`/`WC_Order::add_product()`
calls, real status transitions, real HPOS-backed storage), not through this
generator. Do not add order-generation logic to
`class-dev-catalog-generator.php` in this task — that would be scope
expansion beyond the approved plan. Report this explicitly rather than
silently skipping it.

- [ ] **Step 10: Update `docs/historical/synthetic-b2b-catalog.md`**

Add a row to the "Testing Purpose" table:

```markdown
| Quick Order New/Best Seller filters | Backdated post_date + total_sales tiers (Phase 2), thresholds sourced live from DP_Quick_Order_Config |
```

And extend the Phase 2 section with the two new tier tables (total_sales tiers: high/exact/moderate/zero; publish-date tiers: clearly-inside/near-inside/near-outside/clearly-outside) matching the spec's §2 table.

- [ ] **Step 11: Commit**

```bash
git add wp-content/themes/dreampoint-b2b/inc/dev/class-dev-catalog-generator.php wp-content/themes/dreampoint-b2b/docs/historical/synthetic-b2b-catalog.md
git commit -m "feat(dev-tools): seed total_sales and backdated post_date tiers in synthetic catalog"
```

---

### Task 7: Frontend markup — three checkboxes

**Scope note (revised 2026-07-14):** this task is markup + initial state ONLY
— the three checkboxes (Already Ordered, New, Best Seller). No "In Stock"
checkbox (stays fully native WBW). No Clear All button here — that control
is Task 8's responsibility (its markup and its behavior are meaningless
apart from each other, so they're added together in that task). No JS
change/event behavior — that's Task 8.

**Files:**
- Modify: `wp-content/plugins/dp-b2b-quick-order/templates/quick-order.php` (insert fieldset ABOVE `[wpf-filters id="3"]`, at the very top of the `.col-lg-3` filter column)
- Modify: `wp-content/plugins/dp-b2b-quick-order/assets/dist/quick-order.css` (hand-authored directly — no SCSS/build step for this plugin's CSS, confirmed via `package.json`; scoped layout rules only)

**Interfaces:**
- Produces: DOM contract consumed by Task 8 — `.dp-qo-catalog-filters` fieldset, `.dp-qo-catalog-filter__input[data-qo-filter]` (values `qo_already_ordered`/`qo_new`/`qo_best_seller`), each with a unique `id` (`dp-qo-filter-already-ordered`/`dp-qo-filter-new`/`dp-qo-filter-best-seller`).
- Also produces (server-side): a `$dp_qo_active_filters` array read from the current request, driving initial `checked` state — Task 8's JS re-derives the same state client-side on `popstate`/construction, but the FIRST paint (before any JS runs) is correct because of this task's PHP, avoiding an unchecked-then-checked flash.

- [ ] **Step 1: Compute initial checked state from the URL**

Near the top of the file, immediately after `defined( 'ABSPATH' ) || exit;`:

```php
// Initial checkbox state mirrors the exact REST boolean semantics
// (rest_sanitize_boolean — the same function WP's REST schema layer uses
// for a 'type' => 'boolean' arg) so the server-rendered first paint can
// never disagree with what the REST endpoint would actually interpret.
// Absent, '0', 'false', '', etc. are all correctly falsy — never checked.
$dp_qo_active_filters = [
	'qo_already_ordered' => isset( $_GET['qo_already_ordered'] ) && rest_sanitize_boolean( wp_unslash( $_GET['qo_already_ordered'] ) ),
	'qo_new'             => isset( $_GET['qo_new'] ) && rest_sanitize_boolean( wp_unslash( $_GET['qo_new'] ) ),
	'qo_best_seller'     => isset( $_GET['qo_best_seller'] ) && rest_sanitize_boolean( wp_unslash( $_GET['qo_best_seller'] ) ),
];
```

- [ ] **Step 2: Insert the fieldset at the top of `.col-lg-3`, above WBW**

Change:
```php
			<div class="col-lg-3">
				<?php if ( shortcode_exists( 'wpf-filters' ) ) : ?>
				<div class="dp-qo-filter-area">
					<?php echo do_shortcode( '[wpf-filters id="3"]' ); ?>
				</div>
				<?php endif; ?>
			</div>
```
to:
```php
			<div class="col-lg-3">
				<fieldset class="dp-qo-catalog-filters">
					<legend class="dp-qo-catalog-filters__legend"><?php esc_html_e( 'Brzi filteri', 'dp-b2b-quick-order' ); ?></legend>

					<label class="dp-qo-catalog-filter" for="dp-qo-filter-already-ordered">
						<input
							type="checkbox"
							id="dp-qo-filter-already-ordered"
							class="dp-qo-catalog-filter__input"
							data-qo-filter="qo_already_ordered"
							<?php checked( $dp_qo_active_filters['qo_already_ordered'] ); ?>
						>
						<span class="dp-qo-catalog-filter__label"><?php esc_html_e( 'Već naručeno', 'dp-b2b-quick-order' ); ?></span>
					</label>

					<label class="dp-qo-catalog-filter" for="dp-qo-filter-new">
						<input
							type="checkbox"
							id="dp-qo-filter-new"
							class="dp-qo-catalog-filter__input"
							data-qo-filter="qo_new"
							<?php checked( $dp_qo_active_filters['qo_new'] ); ?>
						>
						<span class="dp-qo-catalog-filter__label"><?php esc_html_e( 'Novo', 'dp-b2b-quick-order' ); ?></span>
					</label>

					<label class="dp-qo-catalog-filter" for="dp-qo-filter-best-seller">
						<input
							type="checkbox"
							id="dp-qo-filter-best-seller"
							class="dp-qo-catalog-filter__input"
							data-qo-filter="qo_best_seller"
							<?php checked( $dp_qo_active_filters['qo_best_seller'] ); ?>
						>
						<span class="dp-qo-catalog-filter__label"><?php esc_html_e( 'Best seller', 'dp-b2b-quick-order' ); ?></span>
					</label>
				</fieldset>

				<?php if ( shortcode_exists( 'wpf-filters' ) ) : ?>
				<div class="dp-qo-filter-area">
					<?php echo do_shortcode( '[wpf-filters id="3"]' ); ?>
				</div>
				<?php endif; ?>
			</div>
```

Note: the fieldset is a sibling BEFORE `.dp-qo-filter-area`, never nested inside it — WBW's shortcode output is untouched, byte-for-byte.

- [ ] **Step 3: Scoped CSS — visual parity with WBW's checkbox appearance, independent implementation**

**Investigated first (required by product-owner instruction): does WBW View 3
render native or custom-styled checkboxes?** Confirmed custom-styled —
`wp-content/plugins/woo-product-filter/modules/woofilters/css/frontend.woofilters.css`,
selector `.wpfMainWrapper input[type="checkbox"]` (lines 1826–1878): `appearance:
none` (square 18×18px box, 1px `#e0e0e0` border, `border-radius: 0`,
transparent background), and on `:checked` a solid `#303030` background +
border with a white checkmark rendered via an inline SVG `background-image`
data URI (Bootstrap-style form-check asset), `::before` explicitly
suppressed. This is NOT native OS checkbox rendering.

**Per the product-owner's principle:** behavior ownership stays split (WBW
owns WBW, Quick Order owns Quick Order — confirmed nowhere in this task do
we touch `.wpfMainWrapper`/`.woobewooCheckbox`/any WBW class or read WBW's
DOM), but visual PRESENTATION must be indistinguishable to the user. Since
WBW's own checkboxes are custom-styled (not native), Quick Order's
checkboxes must independently reproduce that exact visual result — through
Quick Order's OWN `.dp-qo-*` selectors only, styling the plain native
`<input type="checkbox">` markup already in Task 7's Step 2 (still valid,
zero-JS, keyboard/Space/label-click all still native browser behavior —
only the *painted appearance* is overridden, structure and behavior are
untouched). The checkmark SVG below is copied as a plain visual asset (a
literal data URI, no WBW code/class/DOM reference) — reusing a visual icon
for parity is not structural coupling.

Append to `assets/dist/quick-order.css`, near the existing `/* ── WPF filter area ─────` section (this file has no build step — it's edited directly, confirmed via `package.json`'s scripts, which only compile JS):

```css
/* ── Quick Order-owned catalog filters (Already Ordered/New/Best Seller) —
   own block, always rendered above WBW's filter widgets; never styles or
   touches WBW markup/classes. Checkbox appearance below is an independent
   visual match to WBW's own custom-styled checkboxes (frontend.woofilters.css
   .wpfMainWrapper input[type="checkbox"]) — same visual result, zero shared
   selectors, zero shared DOM. Structure/keyboard/label-click behavior stays
   100% native <input type="checkbox"> — only appearance is overridden. ── */
.dp-qo-catalog-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 16px;
    margin: 0 0 16px;
    padding: 0;
    border: 0;
}

.dp-qo-catalog-filters__legend {
    width: 100%;
    padding: 0;
    margin: 0 0 6px;
    font-size: 13px;
    font-weight: 600;
    color: #444;
}

.dp-qo-catalog-filter {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    font-size: 13px;
}

.dp-qo-catalog-filter__input {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    width: 18px;
    min-width: 18px;
    height: 18px;
    margin: 0;
    border: 1px solid #e0e0e0;
    border-radius: 0;
    background-color: transparent;
    background-repeat: no-repeat;
    background-position: center;
    background-size: contain;
    outline: none;
    cursor: pointer;
}

.dp-qo-catalog-filter__input:checked {
    background-color: #303030;
    border-color: #303030;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='3' d='m6 10 3 3 6-6'/%3e%3c/svg%3e");
}

.dp-qo-catalog-filter__input:focus-visible {
    outline: 2px solid #303030 !important;
    outline-offset: 2px;
}
```

`:focus-visible` is added deliberately (WBW's own checkbox CSS sets
`outline: none !important` with no visible replacement — not a pattern to
copy) — this project's accessibility rules require never removing a focus
indicator without a clearly visible alternative; keyboard-focused state
must remain visibly distinguishable regardless of what WBW itself does.
`!important` is required here — this theme's own global CSS already has a
site-wide `input:focus-visible { outline: none !important }` rule, and
`.dp-qo-qty`/`.dp-qo-btn` elsewhere in this same file already need
`!important` for the identical reason — confirmed by CSSOM inspection
during implementation, not assumed.

No dedicated mobile override needed — `flex-wrap: wrap` alone already gives natural wrapping with no horizontal overflow, the same pattern already relied on by `.dp-qo-toolbar__filters` (no mobile-specific override exists for that block either).

- [ ] **Step 4: Manual validation**

Load the Quick Order page locally in a browser, logged in as a B2B test user with catalog visibility (e.g. `vis_full`):
1. View source — confirm `.dp-qo-catalog-filters` renders as the FIRST element inside `.col-lg-3`, before `.dp-qo-filter-area`, and that `[wpf-filters id="3"]`'s own rendered output is completely unchanged (diff it against the pre-Task-7 page source if unsure).
2. Confirm all three checkboxes render, unchecked, when the URL has no `qo_*` params.
3. Load the page with `?qo_new=1` in the URL — confirm ONLY the "Novo" checkbox renders checked (view source, look for `checked="checked"` only on that input).
4. Load with `?qo_new=0` and `?qo_new=false` — confirm "Novo" renders UNCHECKED in both cases (this is the specific "absent/0/false must not render as checked" requirement — `0`/`false` are explicit falsy values, not merely absent, and must be tested as their own case, not assumed to behave like absence).
5. Click each checkbox's visible text label — confirm the checkbox toggles (native `<label for>` behavior, no JS involved yet).
6. Tab to each checkbox with the keyboard — confirm a visible focus outline appears (browser default is acceptable; do not suppress it) — then press Space — confirm it toggles (native browser behavior, no JS required).
7. View source — confirm all three `id` attributes are unique and each `<label for="...">` matches its input's `id` exactly.
8. Resize the browser to a mobile width (or use devtools device emulation) — confirm the three checkboxes wrap onto multiple lines without horizontal overflow or overlapping WBW's rendered filters below them.
9. Open the browser console — confirm zero errors (this task adds no JS at all, so this specifically confirms the new markup/CSS doesn't break anything already loaded).
10. Visually compare a checked Quick Order checkbox against a checked WBW checkbox in View 3 (e.g. check "In Stock" or a category) side by side — confirm matching box size, border, checked color, and checkmark glyph, close enough that a user would not identify them as two different systems.
11. Tab to a Quick Order checkbox and confirm the `:focus-visible` outline is visible (this is a deliberate deviation from WBW's own checkbox CSS, which sets `outline: none` with no visible replacement — do not copy that specific WBW behavior).

- [ ] **Step 5: PHP syntax check + unrelated-file check**

```bash
C:\xampp2\php83\php.exe -l "C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order\templates\quick-order.php"
```
Expected: `No syntax errors detected`.

```bash
git -C C:\xampp2\htdocs\dp-b2b\wp-content status --short
```
Expected: `templates/quick-order.php` and `assets/dist/quick-order.css` show as modified, plus the already-known pre-existing unrelated dirty files (including the pre-existing, already-noted `[wpf-filters id="1"]`→`id="3"` switch already sitting uncommitted in this same template file — fold that into this task's commit, since View ID 3 is a prerequisite for this whole feature to function, exactly as flagged before Task 1 started).

- [ ] **Step 6: Commit**

```bash
git add wp-content/plugins/dp-b2b-quick-order/templates/quick-order.php wp-content/plugins/dp-b2b-quick-order/assets/dist/quick-order.css
git commit -m "feat(qo): add Already Ordered/New/Best Seller checkbox markup above WBW View 3"
```

---

### Task 8: Frontend JS — extraction, checkbox binding, Clear All

**Files:**
- Modify: `wp-content/plugins/dp-b2b-quick-order/assets/src/product-list.js`
- Modify: `wp-content/plugins/dp-b2b-quick-order/templates/quick-order.php` (add the Clear All button markup — Task 7 deliberately left this out, since a button with no behavior is meaningless; it's added here, alongside the behavior that gives it a purpose)
- Modify: `wp-content/plugins/dp-b2b-quick-order/assets/dist/quick-order.css` (minimal styling for the new button, same hand-authored file as Task 7)

**Interfaces:**
- Consumes: `.dp-qo-catalog-filters`, `.dp-qo-catalog-filter__input[data-qo-filter]` (Task 7). WBW's `.wpfClearButton` (existing, read-only).
- Produces: `#woofFilters` gains three optional boolean keys (`qoNew`, `qoBestSeller`, `qoAlreadyOrdered`); `#buildProductsUrl()` emits `qo_new`/`qo_best_seller`/`qo_already_ordered` when set; new `.dp-qo-catalog-filters-clear` button element + click handler.

- [ ] **Step 0: Add the Clear All button markup**

In `templates/quick-order.php`, inside `.dp-qo-catalog-filters` (added in Task 7), after the last `<label class="dp-qo-catalog-filter" ...>` block, before the closing `</fieldset>`:

```php
				<button type="button" class="dp-qo-catalog-filters__clear">
					<?php esc_html_e( 'Poništi', 'dp-b2b-quick-order' ); ?>
				</button>
```

In `assets/dist/quick-order.css`, append to the `.dp-qo-catalog-filters` block added in Task 7:

```css
.dp-qo-catalog-filters__clear {
    background: none;
    border: 0;
    padding: 0;
    font-size: 13px;
    text-decoration: underline;
    color: #444;
    cursor: pointer;
}
```

- [ ] **Step 1: Add the extraction method**

Add as a new private method, directly after `#extractWoofFilters()`:

```javascript
    /**
     * Extract Quick Order-owned filter params — never WBW-managed, no
     * DOM-metadata lookup needed (Quick Order owns both the param name and
     * the control that writes it, unlike the WBW-driven extraction above).
     * @param {URLSearchParams} params
     * @returns {{ qoNew?: boolean, qoBestSeller?: boolean, qoAlreadyOrdered?: boolean }}
     */
    #extractQoOwnedFilters(params) {
        const result = {};
        if (params.has('qo_new'))            result.qoNew = true;
        if (params.has('qo_best_seller'))     result.qoBestSeller = true;
        if (params.has('qo_already_ordered')) result.qoAlreadyOrdered = true;
        return result;
    }
```

- [ ] **Step 2: Make `#onWoofUrlChange()` the ONE canonical URL→state resync path**

**Single-source-of-truth doctrine (verified before writing this step):** the
browser URL must be the only authoritative runtime state; `#woofFilters` is
never an independent store — it is a synchronously-rebuilt-from-the-URL
working cache that exists only to (a) detect whether a re-fetch is actually
needed and (b) provide convenient property access when building the REST
query. It must never be partially mutated in a way that can drift from what
the URL currently says. Concretely, this means every place that changes the
URL must funnel through the SAME rebuild-from-URL logic, never a bespoke
partial merge — a `Object.assign(existing, freshExtraction)` onto an
ALREADY-POPULATED object (as opposed to a fresh one) can silently leave a
stale truthy key behind when a filter is turned OFF, since the fresh
extraction only ever sets keys that are present in the URL and never
explicitly clears absent ones. `#onWoofUrlChange()` avoids this because it
always builds `next` as a brand-new object via `#extractWoofFilters()`
before merging QO keys onto it — never onto a possibly-stale existing
object — so Task 8 makes this method the single call site every URL-writing
action (checkbox toggle, Clear All) goes through, instead of duplicating a
second, easier-to-get-wrong resync path.

In `#bindWoofIntegration()`, after `this.#woofFilters = this.#extractWoofFilters(params);`, add:

```javascript
        Object.assign(this.#woofFilters, this.#extractQoOwnedFilters(params));
        this.#reflectQoCheckboxes();
```

(This one case is safe as a direct assign — `this.#woofFilters` was JUST created fresh on the line above, so there is no pre-existing stale key it could fail to clear.)

In `#onWoofUrlChange()`, change:
```javascript
    #onWoofUrlChange() {
        const params  = new URLSearchParams(window.location.search);
        const next    = this.#extractWoofFilters(params);
        const current = JSON.stringify(this.#woofFilters);
        const orderbyChanged = this.#applyOrderbyParam(params);

        if (JSON.stringify(next) !== current || orderbyChanged) {
            this.#woofFilters = next;
            this.loadPage(1);
        }
    }
```
to:
```javascript
    #onWoofUrlChange() {
        const params  = new URLSearchParams(window.location.search);
        const next    = this.#extractWoofFilters(params);
        const current = JSON.stringify(this.#woofFilters);
        const orderbyChanged = this.#applyOrderbyParam(params);
        Object.assign(next, this.#extractQoOwnedFilters(params)); // next is always a FRESH object — safe merge, no stale-key risk

        if (JSON.stringify(next) !== current || orderbyChanged) {
            this.#woofFilters = next;
            this.#reflectQoCheckboxes();
            this.loadPage(1);
        }
    }
```

This is now the single place `#woofFilters` is ever reassigned after
construction — both WBW-originated changes (via `wpfAjaxSuccess`/`popstate`)
and Quick Order-originated changes (Step 3 below) go through it, so the
checkbox DOM, `#woofFilters`, and the URL can never disagree.

- [ ] **Step 3: Add checkbox state reflection + change binding**

New private method, called once from `#bindWoofIntegration()` (Step 2) and after every checkbox toggle:

```javascript
    /** Reflect current #woofFilters QO booleans onto the checkbox DOM elements. */
    #reflectQoCheckboxes() {
        document.querySelectorAll('.dp-qo-catalog-filter__input').forEach(cb => {
            const key = { qo_new: 'qoNew', qo_best_seller: 'qoBestSeller', qo_already_ordered: 'qoAlreadyOrdered' }[cb.dataset.qoFilter];
            cb.checked = !!this.#woofFilters[key];
        });
    }

    /** Bind checkbox change + Clear All — the one genuinely custom event
     * path in this file: WBW has no integration point for controls it
     * doesn't render. Both handlers below only ever WRITE the URL, then
     * call the same #onWoofUrlChange() used for WBW-originated changes —
     * they never touch #woofFilters directly, so there is exactly one
     * URL-to-state resync path in the whole file, not two. */
    #bindQoOwnedFilters() {
        document.querySelectorAll('.dp-qo-catalog-filter__input').forEach(cb => {
            cb.addEventListener('change', () => {
                const params = new URLSearchParams(window.location.search);
                if (cb.checked) params.set(cb.dataset.qoFilter, '1');
                else params.delete(cb.dataset.qoFilter);
                history.pushState(null, '', `${window.location.pathname}?${params.toString()}`);
                this.#onWoofUrlChange();
            });
        });

        document.querySelector('.dp-qo-catalog-filters__clear')?.addEventListener('click', () => {
            const params = new URLSearchParams(window.location.search);
            ['qo_new', 'qo_best_seller', 'qo_already_ordered'].forEach(k => params.delete(k));
            history.pushState(null, '', `${window.location.pathname}?${params.toString()}`);
            this.#onWoofUrlChange();
            // Real click on WBW's own rendered Clear All (if View 3 renders
            // one) — reuses WBW's actual user-facing control and its real
            // AJAX/wpfAjaxSuccess pipeline, never a private WBW method.
            document.querySelector('.wpfClearButton')?.click();
        });
    }
```

Call `this.#bindQoOwnedFilters();` at the end of the constructor, after `this.#bindWoofIntegration();`.

**Note:** `#onWoofUrlChange()`'s existing change-detection guard
(`JSON.stringify(next) !== current`) means calling it after a Clear All that
had nothing to clear (e.g. clicking it twice in a row) is a harmless no-op —
`loadPage(1)` only fires when something genuinely changed, consistent with
its existing behavior for WBW-originated no-op events.

- [ ] **Step 4: Emit the three params in `#buildProductsUrl()`**

After the existing `if (f.attributes && ...)` block:

```javascript
        if (f.qoNew)            params.set('qo_new', '1');
        if (f.qoBestSeller)     params.set('qo_best_seller', '1');
        if (f.qoAlreadyOrdered) params.set('qo_already_ordered', '1');
```

- [ ] **Step 5: Rebuild the bundle**

```bash
cd C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order
npm run build
```
Expected: `assets/dist/quick-order.js` regenerated with no esbuild errors.

- [ ] **Step 6: Manual browser verification**

Load the Quick Order page as a B2B test user:
1. Check "Novo" — confirm URL gains `qo_new=1`, product list narrows, page resets to 1.
2. **Uncheck "Novo" again** (this specific step is the regression test for the
   single-source-of-truth bug found and fixed during planning — a naive
   partial `Object.assign` merge could leave a stale truthy flag in memory
   even after the checkbox and URL both correctly show "off"): confirm the
   URL genuinely loses `qo_new` (not just visually unchecked), and — via the
   Network tab — confirm the NEXT `/products` REST request does NOT include
   `qo_new` at all. This is the one thing code-reading can't fully confirm;
   it must be observed in a live request.
3. Check "Novo" again, then change a WBW filter (e.g. category) — confirm `qo_new=1` remains in the URL and in the next request.
4. Go to page 2 — confirm `qo_new=1` persists.
5. Click "Poništi" — confirm all three checkboxes uncheck, `qo_*` params removed from URL, and any active WBW filter also clears (via the simulated `.wpfClearButton` click).
6. Browser Back — confirm the page reloads (expected, per WBW's own popstate handler) and the checkbox correctly reflects the prior unchecked/checked state on reload.

- [ ] **Step 7: Commit**

```bash
git add wp-content/plugins/dp-b2b-quick-order/assets/src/product-list.js wp-content/plugins/dp-b2b-quick-order/assets/dist/quick-order.js wp-content/plugins/dp-b2b-quick-order/templates/quick-order.php wp-content/plugins/dp-b2b-quick-order/assets/dist/quick-order.css
git commit -m "feat(qo): wire New/Best Seller/Already Ordered checkboxes into ProductList URL state"
```

---

### Task 9: Docs pointer updates + full validation pass

**Files:**
- Modify: `wp-content/themes/dreampoint-b2b/docs/active/status.md` (new row in the Quick Order feature table)
- Modify: `wp-content/themes/dreampoint-b2b/docs/active/current-phase.md` (pointer to this plan, if this becomes the active phase)

**Interfaces:** none — documentation only.

- [ ] **Step 1: Add a status.md row**

In the main feature table, add:

```markdown
| Catalog filters (New/Best Seller/Already Ordered/In Stock) | ACTIVE (locally verified) | Yes | No | See `docs/active/quick-order-catalog-filters-spec.md`. In Stock fully native WBW; other three are Quick Order-owned, `qo_*` URL params. |
```

- [ ] **Step 2: Run the full manual validation matrix from the spec**

Execute every row of the "Validation matrix" table in
`docs/active/quick-order-catalog-filters-spec.md` §5, including the
Already Ordered order-status scenarios (requires manually placing one test
order per the spec's stated testing prerequisite — this is not automatable
by the synthetic catalog generator).

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/dreampoint-b2b/docs/active/status.md wp-content/themes/dreampoint-b2b/docs/active/current-phase.md
git commit -m "docs(qo): record catalog filters feature status"
```

---

## Self-Review Notes

- **Spec coverage:** §1 New → Task 2 + Task 6. §2 Best Seller → Task 3 + Task 6. §3 Already Ordered (statuses, parent/variation, empty-state, admin, HPOS, cache) → Task 4 + Task 5. §4 UI/URL ownership (markup, event flow, Clear All, back/forward, chips, pagination/sort) → Task 7 + Task 8. §5 validation matrix → Task 9. All covered.
- **Placeholder scan:** no TBD/"add error handling"/"similar to Task N" left in any step; every step has literal code or literal commands with expected output.
- **Type consistency:** `already_ordered` (query arg) / `qo_already_ordered` (REST + URL param) / `qoAlreadyOrdered` (JS internal field) — three different layers, three different established naming conventions in this codebase (snake_case PHP array key, `qo_` URL param, camelCase JS field), each matching its layer's existing sibling (`stock_status`/`stock_status`/`f.stock_status` follows the same three-way pattern already in the codebase) — verified consistent across Tasks 2, 3, 5, 8.

# Quick Order — WOOF/WBW Filter Compatibility

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Integrate Quick Order's REST product query with the existing WBW/WOOF filter ecosystem — accept filter parameters server-side, propagate them from the WOOF filter UI client-side, and guard Quick Order queries against unintended WOOF injection.

**Architecture:** Three-layer integration: (1) PHP guard via `pre_get_posts` at priority 10000 strips any WOOF-injected `wpf_query` flag from Quick Order's WP_Query instances; (2) `class-product-query.php` extended to accept price range and product attribute filters mapped to native WP_Query meta_query/tax_query; (3) `product-list.js` listens for WOOF URL changes via `history.pushState` interception and `popstate`, extracts filter params, and forwards them to the REST endpoint. All filter application is server-authoritative — no client-side product filtering.

**Tech Stack:** PHP 8.x, vanilla JS ES2022 (IIFE via esbuild), WooCommerce WP_Query, WOOF (woo-product-filter), WordPress REST API

---

## Pre-Implementation Analysis (already completed — do not re-read)

**WOOF interference in REST context:** WOOF's `forceProductFilter` (pre_get_posts priority 9999) checks `isFiltered()` which reads `$_GET` for `wpf_`, `orderby`, `pr_*` keys. Quick Order's REST requests carry none of these — `isFiltered()` returns false. WOOF also guards `addFilterClausesRequest` behind `wpf_query` flag or `is_main_query()` — Quick Order queries have neither. **Conclusion: WOOF does not currently interfere with QO REST queries.** The guard added in Task 1 is defensive coding for future WOOF versions.

**Existing isolation decisions preserved:**
- `dp_quick_order => true` query var (already in `class-product-query.php`) — used as guard selector
- `qo_orderby` / `qo_order` param naming — avoids WOOF's `orderby` detection in `isFiltered()`
- `suppress_filters => false` — allows visibility system hooks to fire, must remain

**WOOF URL filter format (for JS mapping):**
- Price range: `?pr_min=100&pr_max=500`
- Category: `?wpf_filter_cat_{id}=1` (QO already handles via its own `category` param)
- Product attributes: `?wpf_filter_pa_{name}=value1|value2`

---

## File Map

| File | Action | Responsibility |
|------|--------|---------------|
| `plugins/dp-b2b-quick-order/inc/class-filter-bridge.php` | **Create** | WOOF isolation guard + wiring hook |
| `plugins/dp-b2b-quick-order/inc/class-product-query.php` | **Modify** | Add price range + attribute filter mapping in `query()` |
| `plugins/dp-b2b-quick-order/inc/class-rest-api.php` | **Modify** | Register `price_min`, `price_max`, `attributes` REST args |
| `plugins/dp-b2b-quick-order/inc/class-plugin.php` | **Modify** | Instantiate `DP_Quick_Order_Filter_Bridge` |
| `plugins/dp-b2b-quick-order/assets/src/product-list.js` | **Modify** | WOOF URL integration: `#woofFilters`, `#bindWoofIntegration()`, `#extractWoofFilters()`, update `loadPage()` |
| `plugins/dp-b2b-quick-order/assets/dist/quick-order.js` | **Output** | Rebuilt by `npm run build` |
| `themes/dreampoint-b2b/docs/status.md` | **Modify** | Mark filter integration as ACTIVE |

---

## Task 1 — WOOF Isolation Guard

**Files:**
- Create: `plugins/dp-b2b-quick-order/inc/class-filter-bridge.php`

- [ ] **Create `class-filter-bridge.php`:**

```php
<?php
defined( 'ABSPATH' ) || exit;

/**
 * Guards Quick Order WP_Query instances against unintended WOOF/WBW mutations.
 *
 * WOOF (woo-product-filter) hooks into pre_get_posts at priority 9999 via
 * forceProductFilter(). In the REST context isFiltered() returns false (no wpf_/orderby
 * GET params), so WOOF does not inject state into Quick Order queries. This guard runs
 * at priority 10000 as defensive coding: if WOOF ever sets wpf_query on a QO query,
 * addFilterClausesRequest() would apply WOOF SQL clause modifications — the guard
 * strips that flag before it takes effect.
 */
class DP_Quick_Order_Filter_Bridge {

	public function __construct() {
		add_action( 'pre_get_posts', [ $this, 'guard_query' ], 10000 );
	}

	/**
	 * Remove any wpf_query flag that WOOF may have set on a Quick Order WP_Query.
	 * Runs after WOOF's forceProductFilter (priority 9999).
	 */
	public function guard_query( WP_Query $query ): void {
		if ( empty( $query->query_vars['dp_quick_order'] ) ) {
			return;
		}
		if ( ! empty( $query->query_vars['wpf_query'] ) ) {
			$query->set( 'wpf_query', null );
		}
	}
}
```

- [ ] **Run:** no automated test — verify file saved without syntax errors via PHP lint:

```powershell
& "C:\xampp2\php\php.exe" -l "C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order\inc\class-filter-bridge.php"
```

Expected output: `No syntax errors detected`

- [ ] **Commit:**

```powershell
Set-Location "C:\xampp2\htdocs\dp-b2b\wp-content"
git add plugins/dp-b2b-quick-order/inc/class-filter-bridge.php
git commit -m "feat(quick-order): add WOOF isolation guard for dp_quick_order queries"
```

---

## Task 2 — Wire FilterBridge into Plugin

**Files:**
- Modify: `plugins/dp-b2b-quick-order/inc/class-plugin.php`

Current `class-plugin.php` declares private properties for each class and instantiates them in `init()`. Add `DP_Quick_Order_Filter_Bridge`.

- [ ] **Add property and instantiation in `class-plugin.php`:**

Add after `private DP_Quick_Order_Visibility_Integration $visibility;`:
```php
private DP_Quick_Order_Filter_Bridge $filter_bridge;
```

Add in `init()` after `$this->visibility = new DP_Quick_Order_Visibility_Integration();`:
```php
$this->filter_bridge = new DP_Quick_Order_Filter_Bridge();
```

Full `class-plugin.php` after edit:

```php
<?php
defined( 'ABSPATH' ) || exit;

class DP_Quick_Order_Plugin {

	private static ?self $instance = null;

	private DP_Quick_Order_Visibility_Integration $visibility;
	private DP_Quick_Order_Filter_Bridge $filter_bridge;
	private DP_Quick_Order_Product_Query $product_query;
	private DP_Quick_Order_Cart_Sync $cart_sync;
	private DP_Quick_Order_Rest_Api $rest_api;
	private DP_Quick_Order_Assets $assets;
	private DP_Quick_Order_Frontend $frontend;

	private function __construct() {
		$this->init();
	}

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function init(): void {
		$this->visibility    = new DP_Quick_Order_Visibility_Integration();
		$this->filter_bridge = new DP_Quick_Order_Filter_Bridge();
		$this->product_query = new DP_Quick_Order_Product_Query( $this->visibility );
		$this->cart_sync     = new DP_Quick_Order_Cart_Sync();
		$this->rest_api      = new DP_Quick_Order_Rest_Api( $this->product_query, $this->cart_sync );
		$this->assets        = new DP_Quick_Order_Assets();
		$this->frontend      = new DP_Quick_Order_Frontend( $this->assets );
	}
}
```

- [ ] **Verify main plugin file loads the new class** — confirm `dp-b2b-quick-order.php` requires all `inc/class-*.php` files. If it uses `glob()` or `require_all`, it will auto-load. If manual, add the require.

Check with:
```powershell
Select-String -Path "C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order\dp-b2b-quick-order.php" -Pattern "class-filter-bridge|require|include|glob"
```

If the plugin uses `glob( 'inc/class-*.php' )`, no change needed. If manual `require_once` per file, add:
```php
require_once plugin_dir_path( __FILE__ ) . 'inc/class-filter-bridge.php';
```

- [ ] **PHP lint both files:**

```powershell
& "C:\xampp2\php\php.exe" -l "C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order\inc\class-plugin.php"
& "C:\xampp2\php\php.exe" -l "C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order\dp-b2b-quick-order.php"
```

- [ ] **Commit:**

```powershell
git add plugins/dp-b2b-quick-order/inc/class-plugin.php plugins/dp-b2b-quick-order/dp-b2b-quick-order.php
git diff --cached --quiet || git commit -m "feat(quick-order): wire DP_Quick_Order_Filter_Bridge into plugin bootstrap"
```

---

## Task 3 — Price Range + Attribute Filter Support (ProductQuery)

**Files:**
- Modify: `plugins/dp-b2b-quick-order/inc/class-product-query.php` — `query()` method

Current `query()` accepts: `page`, `per_page`, `search`, `category`, `brand`, `orderby`, `order`.
Add: `price_min` (float|null), `price_max` (float|null), `attributes` (array — pre-decoded by REST layer).

The `attributes` array format: `['color' => ['red', 'blue'], 'size' => ['M', 'L']]`
Maps to: `tax_query` entries with taxonomy `pa_{name}`, field `slug`, operator `IN`.
Each taxonomy is validated via `taxonomy_exists()` before use.

Price maps to `meta_query` on `_price`. Uses BETWEEN for both bounds, `>=` or `<=` for single bound.

- [ ] **Add price + attribute block in `query()` after the brand tax_query block:**

Replace the section from `if ( ! empty( $args['brand'] ) )` to `$this->visibility->apply_to_query( $query_args );` with:

```php
		if ( ! empty( $args['brand'] ) ) {
			$query_args['tax_query'][] = [
				'taxonomy' => 'product_brand',
				'field'    => 'term_id',
				'terms'    => (int) $args['brand'],
			];
		}

		// Price range filter — maps to _price meta.
		$price_min = isset( $args['price_min'] ) && '' !== $args['price_min'] ? (float) $args['price_min'] : null;
		$price_max = isset( $args['price_max'] ) && '' !== $args['price_max'] ? (float) $args['price_max'] : null;

		if ( null !== $price_min && null !== $price_max ) {
			$query_args['meta_query'][] = [
				'key'     => '_price',
				'value'   => [ $price_min, $price_max ],
				'compare' => 'BETWEEN',
				'type'    => 'NUMERIC',
			];
		} elseif ( null !== $price_min ) {
			$query_args['meta_query'][] = [
				'key'     => '_price',
				'value'   => $price_min,
				'compare' => '>=',
				'type'    => 'NUMERIC',
			];
		} elseif ( null !== $price_max ) {
			$query_args['meta_query'][] = [
				'key'     => '_price',
				'value'   => $price_max,
				'compare' => '<=',
				'type'    => 'NUMERIC',
			];
		}

		// Product attribute filters — maps each to a tax_query entry.
		// $args['attributes'] is an assoc array: ['color' => ['red','blue'], 'size' => ['M']].
		if ( ! empty( $args['attributes'] ) && is_array( $args['attributes'] ) ) {
			foreach ( $args['attributes'] as $attr_name => $terms ) {
				$taxonomy = 'pa_' . sanitize_key( (string) $attr_name );
				if ( ! taxonomy_exists( $taxonomy ) ) {
					continue;
				}
				$slugs = array_filter( array_map( 'sanitize_key', (array) $terms ) );
				if ( empty( $slugs ) ) {
					continue;
				}
				$query_args['tax_query'][] = [
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => array_values( $slugs ),
					'operator' => 'IN',
				];
			}
		}

		$this->visibility->apply_to_query( $query_args );
```

- [ ] **PHP lint:**

```powershell
& "C:\xampp2\php\php.exe" -l "C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order\inc\class-product-query.php"
```

Expected: `No syntax errors detected`

- [ ] **Smoke test via WP-CLI:**

Start PHP-CLI server if not running. Make a direct REST call to verify price range works:

```powershell
$nonce = "PASTE_VALID_NONCE_HERE"
Invoke-RestMethod -Uri "http://localhost:8080/dp-b2b/wp-json/dreampoint-b2b/v1/quick-order/products?price_min=50&price_max=500" -Headers @{"X-WP-Nonce"=$nonce} | ConvertTo-Json -Depth 3
```

Expected: JSON with `products` array (possibly empty if no products in that range), `total`, `total_pages` — no PHP errors.

- [ ] **Commit:**

```powershell
git add plugins/dp-b2b-quick-order/inc/class-product-query.php
git commit -m "feat(quick-order): add price range and attribute filter support in ProductQuery"
```

---

## Task 4 — REST Schema Extension

**Files:**
- Modify: `plugins/dp-b2b-quick-order/inc/class-rest-api.php`

Register three new REST args on the products route and pass them through to `query()`.

`attributes` is received as a JSON string, decoded server-side before passing to `query()`.
Invalid JSON → empty array (no filter applied, not an error).

- [ ] **Add `price_min`, `price_max`, `attributes` to route args in `register_routes()`:**

Replace the `'args'` block of the products route:

```php
			'args'                => [
				'page'     => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
				'per_page' => [ 'type' => 'integer', 'default' => DP_Quick_Order_Config::PRODUCTS_PER_PAGE_DEFAULT, 'minimum' => 1, 'maximum' => DP_Quick_Order_Config::PRODUCTS_PER_PAGE_MAX ],
				'search'   => [ 'type' => 'string', 'default' => '' ],
				'category' => [ 'type' => 'integer', 'default' => 0 ],
				'brand'    => [ 'type' => 'integer', 'default' => 0 ],
				'qo_orderby' => [ 'type' => 'string', 'enum' => [ 'title', 'price' ], 'default' => 'title' ],
				'qo_order'   => [ 'type' => 'string', 'enum' => [ 'asc', 'desc' ], 'default' => 'asc' ],
				'price_min'  => [ 'type' => 'number', 'minimum' => 0, 'default' => null ],
				'price_max'  => [ 'type' => 'number', 'minimum' => 0, 'default' => null ],
				'attributes' => [ 'type' => 'string', 'default' => '' ],
			],
```

- [ ] **Pass new params in `get_products()`:**

Replace the `$results = $this->product_query->query(...)` call:

```php
	public function get_products( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$raw_attrs  = $request->get_param( 'attributes' );
		$attributes = [];
		if ( '' !== $raw_attrs ) {
			$decoded = json_decode( $raw_attrs, true );
			if ( is_array( $decoded ) ) {
				$attributes = $decoded;
			}
		}

		$results = $this->product_query->query( [
			'page'       => $request->get_param( 'page' ),
			'per_page'   => $request->get_param( 'per_page' ),
			'search'     => $request->get_param( 'search' ),
			'category'   => $request->get_param( 'category' ),
			'brand'      => $request->get_param( 'brand' ),
			'orderby'    => $request->get_param( 'qo_orderby' ),
			'order'      => $request->get_param( 'qo_order' ),
			'price_min'  => $request->get_param( 'price_min' ),
			'price_max'  => $request->get_param( 'price_max' ),
			'attributes' => $attributes,
		] );

		return rest_ensure_response( $results );
	}
```

- [ ] **PHP lint:**

```powershell
& "C:\xampp2\php\php.exe" -l "C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order\inc\class-rest-api.php"
```

- [ ] **Verify REST schema includes new params** — load the REST discovery endpoint:

```powershell
Invoke-RestMethod -Uri "http://localhost:8080/dp-b2b/wp-json/dreampoint-b2b/v1/quick-order/products" -Method OPTIONS | ConvertTo-Json -Depth 5
```

Expected: `price_min`, `price_max`, `attributes` appear in the `args` schema.

- [ ] **Test price filter via REST:**

```powershell
$nonce = "PASTE_VALID_NONCE_HERE"
$uri = "http://localhost:8080/dp-b2b/wp-json/dreampoint-b2b/v1/quick-order/products?price_min=10&price_max=200"
Invoke-RestMethod -Uri $uri -Headers @{"X-WP-Nonce"=$nonce} | Select-Object total, total_pages
```

Expected: numeric `total` and `total_pages` — no 4xx, no PHP warnings.

- [ ] **Test attributes filter via REST** (use a known slug from your catalog):

```powershell
$nonce = "PASTE_VALID_NONCE_HERE"
# Replace 'color' and 'crna' with a real attribute name and term slug from your catalog
$attrs = [System.Uri]::EscapeDataString('{"color":["crna"]}')
$uri = "http://localhost:8080/dp-b2b/wp-json/dreampoint-b2b/v1/quick-order/products?attributes=$attrs"
Invoke-RestMethod -Uri $uri -Headers @{"X-WP-Nonce"=$nonce} | Select-Object total
```

Expected: products with that attribute returned (or 0 if none) — no errors.

- [ ] **Commit:**

```powershell
git add plugins/dp-b2b-quick-order/inc/class-rest-api.php
git commit -m "feat(quick-order): register price_min, price_max, attributes REST args"
```

---

## Task 5 — WOOF URL Integration (product-list.js)

**Files:**
- Modify: `plugins/dp-b2b-quick-order/assets/src/product-list.js`

Add three capabilities to `ProductList`:
1. `#woofFilters` — state object holding current WOOF filter values (price range + attributes)
2. `#bindWoofIntegration()` — intercepts `history.pushState` and `popstate` to detect WOOF URL changes
3. `#extractWoofFilters(params)` — parses URLSearchParams, returns `{ price_min, price_max, attributes }`
4. `loadPage()` — extended to include `#woofFilters` in the REST URL

**WOOF URL format mapping:**
- `pr_min` → `price_min`
- `pr_max` → `price_max`
- `wpf_filter_pa_{name}=val1|val2` → `attributes: { name: ['val1', 'val2'] }`

WOOF updates the browser URL via `history.pushState` when a filter is applied. Quick Order intercepts this at the `history.pushState` level. If WOOF is not on the page, no WOOF params appear in the URL and `#extractWoofFilters` returns an empty object — no re-fetch, no regression.

**History.pushState interception safety:** We wrap `history.pushState` in the constructor only if WOOF signals are present (detect by checking `typeof WOOF_* === 'undefined'` or simply always wrap — wrapping is harmless if WOOF never fires). We use a named function reference to allow cleanup if needed.

- [ ] **Replace `product-list.js` with the full updated version:**

```javascript
'use strict';

/**
 * Product list renderer.
 *
 * Fetches /products (paginated), renders rows into .dp-qo-tbody,
 * lazy-loads variation options for variable products after each page render.
 * Integrates with WOOF/WBW filter URL changes: when WOOF updates the browser URL
 * with wpf_filter_pa_* or pr_min/pr_max params, re-fetches with those filters mapped
 * to Quick Order's server-side REST params.
 */
export class ProductList {
    /** @type {object} dpQuickOrder config */
    #config;
    /** @type {HTMLElement} */
    #tbody;
    /** @type {HTMLElement} */
    #paginationEl;
    #currentPage  = 1;
    #totalPages   = 1;
    #orderBy      = 'title';
    #orderDir     = 'asc';
    /** WOOF-sourced filter state. Reset to {} on each URL change parse. */
    #woofFilters  = {};

    /**
     * @param {object} config  window.dpQuickOrder
     */
    constructor(config) {
        this.#config       = config;
        this.#tbody        = document.querySelector('.dp-qo-tbody');
        this.#paginationEl = document.querySelector('.dp-qo-pagination');
        this.#bindSortHeaders();
        this.#updateSortIndicators();
        this.#bindWoofIntegration();
    }

    /**
     * Fetch and render a product page.
     * @param {number} page
     */
    async loadPage(page = 1) {
        if (!this.#tbody) return;
        this.#currentPage = page;
        this.#tbody.innerHTML = `<tr><td colspan="6" class="dp-qo-loading">Učitavanje...</td></tr>`;

        let data;
        try {
            const url = this.#buildProductsUrl(page);
            const res = await fetch(url, { headers: { 'X-WP-Nonce': this.#config.wpNonce } });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            data = await res.json();
        } catch (err) {
            this.#tbody.innerHTML = `<tr><td colspan="6" class="dp-qo-error">Greška pri učitavanju proizvoda.</td></tr>`;
            return;
        }

        this.#totalPages = data.total_pages ?? 1;
        this.#renderRows(data.products ?? []);
        this.#renderPagination();
        this.#loadAllVariations();
    }

    /**
     * Build the REST URL for a product page, including sort and any active WOOF filters.
     * @param {number} page
     * @returns {string}
     */
    #buildProductsUrl(page) {
        const params = new URLSearchParams({
            page,
            per_page:   this.#config.perPage ?? 50,
            qo_orderby: this.#orderBy,
            qo_order:   this.#orderDir,
        });

        const f = this.#woofFilters;
        if (f.price_min > 0)                           params.set('price_min', f.price_min);
        if (f.price_max > 0)                           params.set('price_max', f.price_max);
        if (f.attributes && Object.keys(f.attributes).length) {
            params.set('attributes', JSON.stringify(f.attributes));
        }

        return `${this.#config.productsUrl}?${params.toString()}`;
    }

    #renderRows(products) {
        if (!products.length) {
            this.#tbody.innerHTML = `<tr><td colspan="6" class="dp-qo-empty">Nema dostupnih proizvoda.</td></tr>`;
            return;
        }
        this.#tbody.innerHTML = products.map(p => this.#rowHTML(p)).join('');
    }

    #rowHTML(product) {
        const isVariable  = product.type === 'variable';
        const rowKey      = `${product.id}_0`;
        const disableQty  = isVariable || product.stock?.status === 'outofstock';

        let stockClass, stockText;
        if (isVariable) {
            stockClass = 'dp-qo-stock--neutral';
            stockText  = 'Odaberi varijaciju';
        } else {
            const stockLabel = { instock: 'Na stanju', outofstock: 'Nema na stanju', onbackorder: 'Po narudžbi' };
            stockClass = `dp-qo-stock--${escHtml(product.stock?.status ?? 'outofstock')}`;
            stockText  = stockLabel[product.stock?.status] ?? (product.stock?.status ?? '');
        }

        const variationCell = isVariable
            ? `<select class="dp-qo-variation" data-product-id="${product.id}" disabled>
                 <option value="">— Učitavanje —</option>
               </select>`
            : '';

        return `
<tr class="dp-qo-row"
    data-product-id="${product.id}"
    data-type="${escHtml(product.type)}"
    data-row-key="${rowKey}">
  <td class="dp-qo-col-name">
    <strong class="dp-qo-name">${escHtml(product.name)}</strong>
    <small class="dp-qo-sku">${escHtml(product.sku)}</small>
  </td>
  <td class="dp-qo-col-stock">
    <span class="dp-qo-stock ${stockClass}">${stockText}</span>
  </td>
  <td class="dp-qo-col-price">${product.price_html ?? ''}</td>
  <td class="dp-qo-col-variation">${variationCell}</td>
  <td class="dp-qo-col-qty">
    <div class="dp-qo-qty-wrap">
      <button class="dp-qo-qty-btn dp-qo-qty-minus" type="button" aria-label="Smanji količinu"${disableQty ? ' disabled' : ''}>−</button>
      <input type="number"
             class="dp-qo-qty"
             data-row-key="${rowKey}"
             value="0" min="0" step="1"
             ${disableQty ? 'disabled' : ''}>
      <button class="dp-qo-qty-btn dp-qo-qty-plus" type="button" aria-label="Povećaj količinu"${disableQty ? ' disabled' : ''}>+</button>
    </div>
  </td>
  <td class="dp-qo-col-status"><span class="dp-qo-status-icon" aria-hidden="true"></span></td>
</tr>`.trim();
    }

    /** Kick off parallel variation fetches for all variable rows on current page. */
    #loadAllVariations() {
        const rows = this.#tbody.querySelectorAll('[data-type="variable"]');
        rows.forEach(row => this.#loadVariationOptions(row));
    }

    async #loadVariationOptions(row) {
        const productId = row.dataset.productId;
        const select    = row.querySelector('.dp-qo-variation');
        if (!select) return;

        let variations;
        try {
            const url = `${this.#config.productsUrl}/${productId}/variations`;
            const res = await fetch(url, { headers: { 'X-WP-Nonce': this.#config.wpNonce } });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            variations = await res.json();
        } catch {
            select.innerHTML = `<option value="">— Greška —</option>`;
            return;
        }

        select.innerHTML =
            `<option value="">— Odaberi varijaciju —</option>` +
            variations.map(v => {
                const stockSuffix = v.stock_status === 'outofstock' ? ' (nema na stanju)' : '';
                return `<option value="${v.id}" data-stock="${escHtml(v.stock_status)}">${escHtml(v.label)}${stockSuffix}</option>`;
            }).join('');

        select.disabled = false;
    }

    #renderPagination() {
        if (!this.#paginationEl) return;

        const hasPrev = this.#currentPage > 1;
        const hasNext = this.#currentPage < this.#totalPages;

        this.#paginationEl.innerHTML =
            (hasPrev ? `<button class="dp-qo-btn" data-page="${this.#currentPage - 1}">← Prethodna</button>` : '') +
            `<span class="dp-qo-page-info">Strana ${this.#currentPage} / ${this.#totalPages}</span>` +
            (hasNext ? `<button class="dp-qo-btn" data-page="${this.#currentPage + 1}">Sljedeća →</button>` : '');

        this.#paginationEl.querySelectorAll('[data-page]').forEach(btn => {
            btn.addEventListener('click', () => this.loadPage(parseInt(btn.dataset.page, 10)));
        });
    }

    #bindSortHeaders() {
        document.querySelectorAll('.dp-qo-table th[data-sort]').forEach(th => {
            th.addEventListener('click', () => {
                const col = th.dataset.sort;
                if (this.#orderBy === col) {
                    this.#orderDir = this.#orderDir === 'asc' ? 'desc' : 'asc';
                } else {
                    this.#orderBy  = col;
                    this.#orderDir = 'asc';
                }
                this.#updateSortIndicators();
                this.loadPage(1);
            });
        });
    }

    #updateSortIndicators() {
        document.querySelectorAll('.dp-qo-table th[data-sort]').forEach(th => {
            const arrow = th.querySelector('.dp-qo-sort-arrow');
            if (!arrow) return;
            arrow.textContent = th.dataset.sort === this.#orderBy
                ? (this.#orderDir === 'asc' ? ' ↑' : ' ↓')
                : '';
        });
    }

    /**
     * Intercept history.pushState and popstate so that when WOOF updates the browser
     * URL with filter params, Quick Order re-fetches its product list from the server.
     *
     * WOOF uses history.pushState to apply filters without a page reload.
     * We wrap pushState once and listen for popstate (back/forward navigation).
     * If WOOF is not on the page, no wpf_ or pr_ params ever appear and this is a no-op.
     */
    #bindWoofIntegration() {
        const onUrlChange = () => this.#onWoofUrlChange();

        const origPushState = history.pushState.bind(history);
        history.pushState = (state, title, url) => {
            origPushState(state, title, url);
            onUrlChange();
        };

        window.addEventListener('popstate', onUrlChange);
    }

    #onWoofUrlChange() {
        const params  = new URLSearchParams(window.location.search);
        const next    = this.#extractWoofFilters(params);
        const current = JSON.stringify(this.#woofFilters);

        if (JSON.stringify(next) !== current) {
            this.#woofFilters = next;
            this.loadPage(1);
        }
    }

    /**
     * Extract Quick Order filter params from a WOOF-updated URL.
     *
     * WOOF URL format → QO REST params:
     *   pr_min=100            → price_min: 100
     *   pr_max=500            → price_max: 500
     *   wpf_filter_pa_color=red|blue → attributes.color: ['red', 'blue']
     *
     * Category (wpf_filter_cat_*) is intentionally excluded — QO uses its own
     * category param, and WOOF category slugs differ from QO's term ID format.
     *
     * @param {URLSearchParams} params
     * @returns {{ price_min?: number, price_max?: number, attributes?: object }}
     */
    #extractWoofFilters(params) {
        const result = {};

        const prMin = parseFloat(params.get('pr_min') ?? '');
        const prMax = parseFloat(params.get('pr_max') ?? '');
        if (!isNaN(prMin) && prMin > 0) result.price_min = prMin;
        if (!isNaN(prMax) && prMax > 0) result.price_max = prMax;

        const attrs = {};
        for (const [key, val] of params) {
            if (!key.startsWith('wpf_filter_pa_')) continue;
            const attrName = key.slice('wpf_filter_pa_'.length);
            if (!attrName) continue;
            // WOOF uses | as multi-value delimiter
            attrs[attrName] = val.split('|').filter(Boolean);
        }
        if (Object.keys(attrs).length) result.attributes = attrs;

        return result;
    }
}

/** Escape HTML for safe insertion into innerHTML. */
function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
}
```

- [ ] **Verify the source file saved — check line count:**

```powershell
(Get-Content "C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order\assets\src\product-list.js").Count
```

Expected: ~230+ lines (the updated file is longer than the previous ~197 lines).

- [ ] **Commit source:**

```powershell
git add plugins/dp-b2b-quick-order/assets/src/product-list.js
git commit -m "feat(quick-order): WOOF URL filter integration — intercept pushState, propagate pr_min/pr_max/wpf_filter_pa_* to REST"
```

---

## Task 6 — Build JS Bundle

**Files:**
- Output: `plugins/dp-b2b-quick-order/assets/dist/quick-order.js`

- [ ] **Run build from plugin root:**

```powershell
Set-Location "C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order"
npm run build
```

Expected output: esbuild completes with no errors. `assets/dist/quick-order.js` timestamp updated.

- [ ] **Verify dist file updated:**

```powershell
(Get-Item "C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order\assets\dist\quick-order.js").LastWriteTime
```

Must reflect current timestamp.

- [ ] **Commit dist:**

```powershell
Set-Location "C:\xampp2\htdocs\dp-b2b\wp-content"
git add plugins/dp-b2b-quick-order/assets/dist/quick-order.js
git commit -m "build(quick-order): rebuild bundle — WOOF filter integration"
```

---

## Task 7 — Browser Verification

- [ ] **Open Quick Order page in browser:**

Navigate to: `http://localhost:8080/dp-b2b/quick-order/` (or the actual Quick Order page URL)

Log in as a B2B test user (`vis_full` / `TestVis2025!`).

- [ ] **Verify initial load works** — product table loads, pagination renders, no JS console errors.

- [ ] **Verify WOOF filter integration (if WOOF widget is on the page):**

If the Quick Order page has a WOOF filter widget:
1. Apply a price filter (e.g., price range 50–200)
2. Watch browser DevTools Network tab for a new GET request to `/wp-json/dreampoint-b2b/v1/quick-order/products?price_min=50&price_max=200`
3. Verify products table re-renders with filtered results

If WOOF widget is NOT on the Quick Order page (most likely):
1. Manually change the URL to add `?pr_min=10&pr_max=200` then press Enter — this triggers `popstate` equivalent
2. OR open browser console and run: `history.pushState({}, '', location.pathname + '?pr_min=10&pr_max=200')` — this triggers the `pushState` wrapper and should trigger a re-fetch
3. Watch Network tab for the REST call with `price_min=10&price_max=200`

- [ ] **Verify REST attribute filter (browser console test):**

Open browser console on Quick Order page (logged in as B2B user). Run:
```javascript
history.pushState({}, '', location.pathname + '?wpf_filter_pa_boja=crna');
```

Watch Network tab — should fire a REST call to:
`/wp-json/dreampoint-b2b/v1/quick-order/products?...&attributes={"boja":["crna"]}`

(Replace `boja`/`crna` with a real product attribute name/term from your catalog.)

- [ ] **Verify WOOF does NOT interfere with initial load:**

Check that the first product page load (no WOOF params) returns the full catalog (visibility-filtered). No spurious WOOF filter injection in the Network request URL.

---

## Task 8 — Update docs/status.md

**Files:**
- Modify: `themes/dreampoint-b2b/docs/status.md`

- [ ] **Add filter integration row:**

In `docs/status.md`, add a row to the status table after the existing "Sorting" row:

```markdown
| Filter integration (WOOF/WBW) | ACTIVE | Yes | Yes | Price range + pa_* attribute filters via REST. WOOF URL change propagation via pushState wrapper. Isolation guard strips wpf_query from QO WP_Query instances. |
```

- [ ] **Update "Last updated" date at top of file to `2026-05-13`.**

- [ ] **Commit:**

```powershell
Set-Location "C:\xampp2\htdocs\dp-b2b\wp-content"
git add themes/dreampoint-b2b/docs/status.md
git commit -m "docs: mark Quick Order filter integration as ACTIVE"
```

---

## Self-Review

**Spec coverage:**

| Requirement | Task |
|-------------|------|
| WBW/WOOF query compatibility (no injection) | Task 1 — WOOF isolation guard |
| Filter param propagation (price + attributes) | Tasks 3 + 4 — REST extension + JS integration |
| Pagination compatibility | Unchanged — `loadPage(1)` on filter change resets to page 1 ✓ |
| Visibility-safe filtering | Task 3 — `taxonomy_exists()` guard; visibility hooks fire via `suppress_filters=false` ✓ |
| Server-authoritative query handling | PHP maps params to WP_Query — no client-side filtering ✓ |
| Avoiding global Woo query pollution | `qo_orderby`/`qo_order` naming preserved; guard strips `wpf_query` ✓ |
| CartSync stability | `cart-sync.js`, `row-sync.js`, `sync-queue.js` not touched ✓ |
| REST architecture preserved | No new endpoints, no architecture changes ✓ |

**Placeholder scan:** None. All tasks contain complete code.

**Type consistency:**
- `#woofFilters` defined as `{}` in constructor, `#extractWoofFilters` returns `{ price_min?, price_max?, attributes? }` — consistent across `#onWoofUrlChange`, `#buildProductsUrl` ✓
- `$args['attributes']` in `query()` expects a decoded array — REST layer decodes JSON before passing ✓
- `$args['price_min']` / `$args['price_max']` — nullable, checked with `isset(...) && '' !== ...` ✓

**No prohibited patterns used:**
- No `get_available_variations()` ✓
- No CartSync/SyncQueue modifications ✓
- No `wc_query = 'product_query'` pollution ✓
- No `orderby` in REST params (preserved `qo_orderby`) ✓
- All taxonomy inputs validated via `taxonomy_exists()` before use in WP_Query ✓
- No client-side filtering ✓

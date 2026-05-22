# Quick Order UX Validation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a minimal but realistic Quick Order interaction UI (dense table + variation selects + row sync states + cart footer) that exercises the existing CartSync architecture under real B2B interaction pressure.

**Architecture:** Product rows are client-rendered via REST `/products` endpoint. Variable products expose a lazy-loaded variation `<select>` (always visible); quantity input stays disabled until a variation is selected. Variation switching with existing cart quantity fires an implicit replace (schedule remove-old + add-new in the same debounce window). CartSync dispatches three custom DOM events (`dp:sync:start`, `dp:sync:success`, `dp:sync:error`) that row state management subscribes to. Pagination re-renders rows in-place; CartSync continues across page changes.

**Tech Stack:** PHP 8.1, WooCommerce REST, vanilla JS (ES2022, IIFE via esbuild), no frontend state manager, no frameworks.

---

## File Map

### Modified
| File | Change |
|------|--------|
| `plugins/dp-b2b-quick-order/assets/src/cart-sync.js` | Add event dispatch (`dp:sync:start`, `dp:sync:success`, `dp:sync:error`) |
| `plugins/dp-b2b-quick-order/assets/src/quick-order.js` | Import + init `ProductList` and `RowSync` |
| `plugins/dp-b2b-quick-order/inc/class-rest-api.php` | Add `GET /products/{id}/variations` route + callback |
| `plugins/dp-b2b-quick-order/inc/class-product-query.php` | Add `get_variation_details(int $product_id): array` |
| `plugins/dp-b2b-quick-order/inc/class-assets.php` | Add `productsUrl` to localized config |
| `plugins/dp-b2b-quick-order/templates/quick-order.php` | Replace stub with real HTML structure |
| `plugins/dp-b2b-quick-order/assets/dist/quick-order.css` | Write minimal functional styles |

### Created
| File | Responsibility |
|------|---------------|
| `plugins/dp-b2b-quick-order/assets/src/product-list.js` | Product fetching, row rendering, lazy variation load, pagination |
| `plugins/dp-b2b-quick-order/assets/src/row-sync.js` | Row interaction wiring, variation replace, row state management, cart footer |

---

## Task 1 — CartSync: add event dispatch

**Files:**
- Modify: `plugins/dp-b2b-quick-order/assets/src/cart-sync.js`

Three events are dispatched on `document`:
- `dp:sync:start` — right before the fetch fires; carries `items` for the current batch
- `dp:sync:success` — inside `#onSuccess`, after optimistic state is reconciled; carries `synced` array + `totals`
- `dp:sync:error` — inside `#onError`, after snapshot is restored

- [ ] **Step 1: Add `dp:sync:start` dispatch to `#dispatch()`**

In `cart-sync.js`, locate the line `const token = ++this.#token;` and add the dispatch immediately after it, before the `fetch` call. The complete updated `#dispatch()` method body (only the changed section shown; replace the try block opening):

```js
        const token = ++this.#token;

        document.dispatchEvent(new CustomEvent('dp:sync:start', { detail: { items } }));

        try {
            const response = await fetch(this.#config.cartSyncUrl, {
```

- [ ] **Step 2: Add `dp:sync:success` dispatch to `#onSuccess()`**

Replace the entire `#onSuccess()` method:

```js
    #onSuccess(data) {
        if (!Array.isArray(data.synced)) return;

        for (const item of data.synced) {
            const key = `${item.product_id}_${item.variation_id}`;
            switch (item.action) {
                case 'removed':
                case 'skipped':
                case 'failed':
                    this.#optimisticState.delete(key);
                    break;
                case 'out_of_stock':
                    if (item.quantity_allowed > 0) {
                        this.#optimisticState.set(key, item.quantity_allowed);
                    } else {
                        this.#optimisticState.delete(key);
                    }
                    break;
                default:
                    if (item.quantity != null) {
                        this.#optimisticState.set(key, item.quantity);
                    }
            }
        }

        document.dispatchEvent(new CustomEvent('dp:sync:success', {
            detail: { synced: data.synced, totals: data.totals ?? null },
        }));
    }
```

- [ ] **Step 3: Add `dp:sync:error` dispatch to `#onError()`**

Replace the entire `#onError()` method:

```js
    #onError(snapshot) {
        this.#optimisticState = snapshot;
        document.dispatchEvent(new CustomEvent('dp:sync:error', { detail: {} }));
    }
```

- [ ] **Step 4: Verify syntax (esbuild dry-run)**

```powershell
cd C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order
node_modules\.bin\esbuild assets/src/cart-sync.js --bundle=false 2>&1
```
Expected: no errors, output emitted to stdout.

- [ ] **Step 5: Commit**

```bash
cd /c/xampp2/htdocs/dp-b2b/wp-content
git add plugins/dp-b2b-quick-order/assets/src/cart-sync.js
git commit -m "feat: dispatch dp:sync:start/success/error DOM events from CartSync"
```

---

## Task 2 — REST API: variation details endpoint

**Files:**
- Modify: `plugins/dp-b2b-quick-order/inc/class-product-query.php`
- Modify: `plugins/dp-b2b-quick-order/inc/class-rest-api.php`

The endpoint returns a lightweight array of variation objects for a single variable product. Hydrates only the variations for the requested product — O(variation_count), cached via WC object cache.

- [ ] **Step 1: Add `get_variation_details()` to `DP_Quick_Order_Product_Query`**

Add this method to `class-product-query.php`, after the `query()` method:

```php
	/**
	 * Returns lightweight variation details for a variable product.
	 * Loads each variation via wc_get_product() — uses WC object cache (Redis).
	 * Never calls get_available_variations() — avoids full variation tree hydration.
	 *
	 * @param int $product_id Parent variable product ID.
	 * @return list<array{id:int,sku:string,label:string,price:string,price_html:string,stock_status:string,stock_qty:int|null}>
	 */
	public function get_variation_details( int $product_id ): array {
		$product = wc_get_product( $product_id );

		if ( ! $product instanceof WC_Product_Variable ) {
			return [];
		}

		$result = [];

		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );

			if ( ! $variation instanceof WC_Product_Variation || ! $variation->exists() ) {
				continue;
			}

			$attr_parts = [];
			foreach ( $variation->get_attributes() as $key => $value ) {
				if ( '' === $value ) {
					continue;
				}
				$attr_label = wc_attribute_label( str_replace( 'attribute_', '', $key ), $variation );
				$attr_parts[] = $attr_label . ': ' . $value;
			}
			$label = $attr_parts
				? implode( ' / ', $attr_parts )
				/* translators: %d: variation ID */
				: sprintf( __( 'Variation #%d', 'dp-b2b-quick-order' ), $variation_id );

			$result[] = [
				'id'           => $variation_id,
				'sku'          => $variation->get_sku(),
				'label'        => $label,
				'price'        => (string) $variation->get_price(),
				'price_html'   => $variation->get_price_html(),
				'stock_status' => $variation->get_stock_status(),
				'stock_qty'    => $variation->get_manage_stock() ? (int) $variation->get_stock_quantity() : null,
			];
		}

		return $result;
	}
```

- [ ] **Step 2: Verify syntax**

```bash
"C:\xampp2\php\php.exe" -l C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order\inc\class-product-query.php
```
Expected: `No syntax errors detected`

- [ ] **Step 3: Register `/products/{id}/variations` route in `class-rest-api.php`**

In `register_routes()`, add after the existing `/cart/sync` route registration:

```php
		register_rest_route( DP_Quick_Order_Config::REST_NAMESPACE, '/' . DP_Quick_Order_Config::REST_BASE . '/products/(?P<id>\d+)/variations', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_variations' ],
			'permission_callback' => [ $this, 'is_b2b_user' ],
			'args'                => [
				'id' => [ 'type' => 'integer', 'minimum' => 1 ],
			],
		] );
```

- [ ] **Step 4: Add `get_variations()` callback to `DP_Quick_Order_Rest_Api`**

Add this method after `get_products()`:

```php
	public function get_variations( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$product_id = absint( $request->get_param( 'id' ) );
		$product    = wc_get_product( $product_id );

		if ( ! $product instanceof WC_Product_Variable ) {
			return new WP_Error(
				'not_variable',
				__( 'Product is not a variable product.', 'dp-b2b-quick-order' ),
				[ 'status' => 404 ]
			);
		}

		return rest_ensure_response( $this->product_query->get_variation_details( $product_id ) );
	}
```

- [ ] **Step 5: Verify syntax**

```bash
"C:\xampp2\php\php.exe" -l C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order\inc\class-rest-api.php
```
Expected: `No syntax errors detected`

- [ ] **Step 6: Manual REST test**

Log in to WP Admin, get a nonce via browser console: `wpApiSettings.nonce` or use the Quick Order page and read `window.dpQuickOrder.wpNonce`.

Find a variable product ID (e.g. from WP Admin → Products). Replace `<NONCE>` and `<ID>` below:

```bash
curl -s "http://localhost:8080/dp-b2b/wp-json/dreampoint-b2b/v1/quick-order/products/<ID>/variations" \
  -H "X-WP-Nonce: <NONCE>"
```
Expected: JSON array with objects containing `id`, `sku`, `label`, `price`, `stock_status`.

For a non-variable product ID: expected HTTP 404 with `code: "not_variable"`.

- [ ] **Step 7: Commit**

```bash
cd /c/xampp2/htdocs/dp-b2b/wp-content
git add plugins/dp-b2b-quick-order/inc/class-product-query.php plugins/dp-b2b-quick-order/inc/class-rest-api.php
git commit -m "feat: add lightweight variation details endpoint GET /products/{id}/variations"
```

---

## Task 3 — Assets: add `productsUrl` to localized config

**Files:**
- Modify: `plugins/dp-b2b-quick-order/inc/class-assets.php`

- [ ] **Step 1: Add `productsUrl` to `wp_localize_script` call**

In `class-assets.php`, locate the `wp_localize_script` block. Add `productsUrl` after `cartSyncUrl`:

```php
		wp_localize_script( 'dp-quick-order', 'dpQuickOrder', [
			'restUrl'     => esc_url_raw( rest_url( DP_Quick_Order_Config::REST_NAMESPACE . '/' ) ),
			'cartSyncUrl' => esc_url_raw( rest_url(
				DP_Quick_Order_Config::REST_NAMESPACE . '/' .
				DP_Quick_Order_Config::REST_BASE . '/cart/sync'
			) ),
			'productsUrl' => esc_url_raw( rest_url(
				DP_Quick_Order_Config::REST_NAMESPACE . '/' .
				DP_Quick_Order_Config::REST_BASE . '/products'
			) ),
			'storeUrl'    => esc_url_raw( rest_url( 'wc/store/v1/' ) ),
			'nonce'       => wp_create_nonce( DP_Quick_Order_Config::NONCE_ACTION ),
			'wpNonce'     => wp_create_nonce( 'wp_rest' ),
			'debounceMs'  => DP_Quick_Order_Config::CART_SYNC_DEBOUNCE_MS,
			'timeoutMs'   => DP_Quick_Order_Config::CART_SYNC_TIMEOUT_MS,
			'i18n'        => [],
		] );
```

- [ ] **Step 2: Verify syntax**

```bash
"C:\xampp2\php\php.exe" -l C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order\inc\class-assets.php
```
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
cd /c/xampp2/htdocs/dp-b2b/wp-content
git add plugins/dp-b2b-quick-order/inc/class-assets.php
git commit -m "chore: add productsUrl to dpQuickOrder localized JS config"
```

---

## Task 4 — Template: replace stub with real HTML structure

**Files:**
- Modify: `plugins/dp-b2b-quick-order/templates/quick-order.php`

- [ ] **Step 1: Replace entire template content**

```php
<?php
defined( 'ABSPATH' ) || exit;
?>
<div id="dp-quick-order"
	class="dp-quick-order"
	data-rest-url="<?php echo esc_attr( rest_url( DP_Quick_Order_Config::REST_NAMESPACE . '/' ) ); ?>"
	data-nonce="<?php echo esc_attr( wp_create_nonce( DP_Quick_Order_Config::NONCE_ACTION ) ); ?>"
>
	<div class="dp-qo-pagination"></div>

	<div class="dp-qo-table-wrap">
		<table class="dp-qo-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Naziv', 'dp-b2b-quick-order' ); ?></th>
					<th><?php esc_html_e( 'Stanje', 'dp-b2b-quick-order' ); ?></th>
					<th><?php esc_html_e( 'Cijena', 'dp-b2b-quick-order' ); ?></th>
					<th><?php esc_html_e( 'Varijacija', 'dp-b2b-quick-order' ); ?></th>
					<th><?php esc_html_e( 'Kol.', 'dp-b2b-quick-order' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody class="dp-qo-tbody">
				<tr>
					<td colspan="6" class="dp-qo-loading">
						<?php esc_html_e( 'Učitavanje...', 'dp-b2b-quick-order' ); ?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="dp-qo-footer">
		<div class="dp-qo-footer__total"></div>
		<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="dp-qo-footer__cart-link">
			<?php esc_html_e( 'Idi na košaricu →', 'dp-b2b-quick-order' ); ?>
		</a>
	</div>
</div>
```

- [ ] **Step 2: Commit**

```bash
cd /c/xampp2/htdocs/dp-b2b/wp-content
git add plugins/dp-b2b-quick-order/templates/quick-order.php
git commit -m "feat: replace Quick Order template stub with functional HTML structure"
```

---

## Task 5 — Create `product-list.js`

**Files:**
- Create: `plugins/dp-b2b-quick-order/assets/src/product-list.js`

Responsibilities: fetch `/products`, render rows into `.dp-qo-tbody`, lazy-fetch variation options for each variable product row, render pagination controls.

Row identity rules:
- Simple product: `rowKey = "${id}_0"`, quantity input always enabled
- Variable product: `rowKey = "${id}_0"` initially, becomes `"${id}_${variationId}"` on variation selection (managed by `row-sync.js`)

- [ ] **Step 1: Create `product-list.js`**

```js
'use strict';

/**
 * Product list renderer.
 *
 * Fetches /products (paginated), renders rows into .dp-qo-tbody,
 * lazy-loads variation options for variable products after each page render.
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

    /**
     * @param {object} config  window.dpQuickOrder
     */
    constructor(config) {
        this.#config      = config;
        this.#tbody       = document.querySelector('.dp-qo-tbody');
        this.#paginationEl = document.querySelector('.dp-qo-pagination');
    }

    /**
     * Fetch and render a product page.
     * @param {number} page
     */
    async loadPage(page = 1) {
        this.#currentPage = page;
        this.#tbody.innerHTML = `<tr><td colspan="6" class="dp-qo-loading">Učitavanje...</td></tr>`;

        let data;
        try {
            const url = `${this.#config.productsUrl}?page=${page}&per_page=${this.#config.perPage ?? 50}`;
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

    #renderRows(products) {
        if (!products.length) {
            this.#tbody.innerHTML = `<tr><td colspan="6" class="dp-qo-empty">Nema dostupnih proizvoda.</td></tr>`;
            return;
        }
        this.#tbody.innerHTML = products.map(p => this.#rowHTML(p)).join('');
    }

    #rowHTML(product) {
        const isVariable = product.type === 'variable';
        const rowKey     = `${product.id}_0`;

        const stockLabel = { instock: 'Na stanju', outofstock: 'Nema na stanju', onbackorder: 'Po narudžbi' };
        const stockClass = `dp-qo-stock--${product.stock?.status ?? 'outofstock'}`;
        const stockText  = stockLabel[product.stock?.status] ?? (product.stock?.status ?? '');

        const variationCell = isVariable
            ? `<select class="dp-qo-variation" data-product-id="${product.id}" disabled>
                 <option value="">— Učitavanje —</option>
               </select>`
            : '';

        return `
<tr class="dp-qo-row"
    data-product-id="${product.id}"
    data-type="${product.type}"
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
    <input type="number"
           class="dp-qo-qty"
           data-row-key="${rowKey}"
           value="0" min="0" step="1"
           ${isVariable ? 'disabled' : ''}>
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
}

/** Escape HTML for safe insertion into innerHTML. */
function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
}
```

- [ ] **Step 2: Syntax-check with esbuild dry-run**

```powershell
cd C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order
node_modules\.bin\esbuild assets/src/product-list.js --bundle=false 2>&1
```
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
cd /c/xampp2/htdocs/dp-b2b/wp-content
git add plugins/dp-b2b-quick-order/assets/src/product-list.js
git commit -m "feat: add ProductList — product rendering, pagination, lazy variation load"
```

---

## Task 6 — Create `row-sync.js`

**Files:**
- Create: `plugins/dp-b2b-quick-order/assets/src/row-sync.js`

Responsibilities:
- Event delegation on `.dp-qo-tbody` for quantity `input` events and variation `change` events
- Variation replace: on variation change with qty > 0, fires `schedule(oldKey, 0)` + `schedule(newKey, qty)` in the same synchronous call (both land in the SyncQueue before debounce fires)
- Row state management via CSS classes: `is-pending` → `is-syncing` → `is-synced`/`is-error`/`is-out-of-stock`
- Cart footer total update from `dp:sync:success` event

Row state CSS class contract:
- `is-pending` — set immediately on user input (item is in queue, not yet sent)
- `is-syncing` — set when `dp:sync:start` fires for that rowKey (request inflight)
- `is-synced` — set on successful sync (`added`/`updated`)
- `is-error` — set on `failed` action or general sync error
- `is-out-of-stock` — set when server returns `out_of_stock` (quantity corrected in input)

- [ ] **Step 1: Create `row-sync.js`**

```js
'use strict';

/**
 * Row interaction controller.
 *
 * Wires quantity inputs and variation selects to CartSync via event delegation.
 * Subscribes to CartSync DOM events for per-row state feedback.
 * Updates cart footer total on each successful sync.
 */
export class RowSync {
    /** @type {import('./cart-sync.js').CartSync} */
    #sync;
    /** @type {HTMLElement} */
    #tbody;
    /** @type {HTMLElement|null} */
    #footerTotal;

    /**
     * @param {import('./cart-sync.js').CartSync} sync
     */
    constructor(sync) {
        this.#sync        = sync;
        this.#tbody       = document.querySelector('.dp-qo-tbody');
        this.#footerTotal = document.querySelector('.dp-qo-footer__total');

        this.#bindTableEvents();
        this.#bindSyncEvents();
    }

    // ── Table interaction ────────────────────────────────────────────────────

    #bindTableEvents() {
        // Event delegation — handles dynamically rendered rows on every page.
        this.#tbody.addEventListener('input', e => {
            if (e.target.matches('.dp-qo-qty')) this.#onQtyInput(e.target);
        });

        this.#tbody.addEventListener('change', e => {
            if (e.target.matches('.dp-qo-variation')) this.#onVariationChange(e.target);
            // Catch paste/spinner-click quantity changes missed by 'input'.
            if (e.target.matches('.dp-qo-qty'))       this.#onQtyInput(e.target);
        });
    }

    #onQtyInput(input) {
        const rowKey = input.dataset.rowKey;
        if (!rowKey) return;

        const qty = Math.max(0, parseInt(input.value, 10) || 0);
        this.#sync.schedule(rowKey, qty);

        const row = input.closest('.dp-qo-row');
        if (row) this.#setRowState(row, 'pending');
    }

    #onVariationChange(select) {
        const row = select.closest('.dp-qo-row');
        if (!row) return;

        const productId   = row.dataset.productId;
        const qtyInput    = row.querySelector('.dp-qo-qty');
        const variationId = select.value;

        if (!variationId) {
            // User reset to placeholder — disable qty, clear row key to neutral.
            if (qtyInput) {
                qtyInput.disabled = true;
                qtyInput.value    = 0;
            }
            row.dataset.rowKey = `${productId}_0`;
            if (qtyInput) qtyInput.dataset.rowKey = `${productId}_0`;
            return;
        }

        const oldKey      = row.dataset.rowKey;
        const newKey      = `${productId}_${variationId}`;
        const currentQty  = qtyInput ? Math.max(0, parseInt(qtyInput.value, 10) || 0) : 0;

        // Update row identity before scheduling — event listeners use data-row-key.
        row.dataset.rowKey = newKey;
        if (qtyInput) {
            qtyInput.dataset.rowKey = newKey;
            qtyInput.disabled       = false;
        }

        // Implicit replace: if previous variation had a cart quantity, do remove+add
        // in the same synchronous block so both land in one debounce window.
        const hadOldVariation = oldKey !== `${productId}_0`;
        if (hadOldVariation && currentQty > 0) {
            this.#sync.schedule(oldKey, 0);        // remove old variation
            this.#sync.schedule(newKey, currentQty); // add new variation with same qty
            this.#setRowState(row, 'pending');
        }
        // If qty is 0, user will set quantity manually — no sync yet.
    }

    // ── CartSync event subscribers ───────────────────────────────────────────

    #bindSyncEvents() {
        document.addEventListener('dp:sync:start', e => {
            const items = e.detail.items ?? [];
            items.forEach(item => {
                const rowKey = `${item.product_id}_${item.variation_id}`;
                const row    = this.#tbody.querySelector(`[data-row-key="${rowKey}"]`);
                if (row) this.#setRowState(row, 'syncing');
            });
        });

        document.addEventListener('dp:sync:success', e => {
            const synced = e.detail.synced ?? [];
            synced.forEach(item => {
                const rowKey = `${item.product_id}_${item.variation_id}`;
                const row    = this.#tbody.querySelector(`[data-row-key="${rowKey}"]`);
                if (!row) return; // row may be on a different page — ignore silently

                switch (item.action) {
                    case 'added':
                    case 'updated':
                        this.#setRowState(row, 'synced');
                        if (item.quantity != null) {
                            const inp = row.querySelector('.dp-qo-qty');
                            if (inp) inp.value = item.quantity;
                        }
                        break;

                    case 'removed':
                        this.#setRowState(row, '');
                        const remInp = row.querySelector('.dp-qo-qty');
                        if (remInp) remInp.value = 0;
                        break;

                    case 'out_of_stock': {
                        this.#setRowState(row, 'out-of-stock');
                        const inp = row.querySelector('.dp-qo-qty');
                        if (inp) inp.value = item.quantity_allowed ?? 0;
                        break;
                    }

                    case 'failed':
                        this.#setRowState(row, 'error');
                        break;
                }
            });

            if (e.detail.totals) this.#updateFooter(e.detail.totals);
        });

        document.addEventListener('dp:sync:error', () => {
            // General network/server failure — move all syncing rows to error state.
            this.#tbody.querySelectorAll('.dp-qo-row.is-syncing').forEach(row => {
                this.#setRowState(row, 'error');
            });
        });
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    #setRowState(row, state) {
        row.classList.remove('is-pending', 'is-syncing', 'is-synced', 'is-error', 'is-out-of-stock');
        if (state) row.classList.add(`is-${state}`);

        const icon   = row.querySelector('.dp-qo-status-icon');
        const labels = { pending: '…', syncing: '↺', synced: '✓', error: '✗', 'out-of-stock': '!' };
        if (icon) icon.textContent = labels[state] ?? '';
    }

    #updateFooter(totals) {
        if (!this.#footerTotal || !totals) return;

        const lang      = document.documentElement.lang || 'hr';
        const currency  = totals.currency || 'EUR';
        const formatted = new Intl.NumberFormat(lang, { style: 'currency', currency }).format(totals.total ?? 0);
        this.#footerTotal.textContent = `Ukupno: ${formatted}`;
    }
}
```

- [ ] **Step 2: Syntax-check**

```powershell
cd C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order
node_modules\.bin\esbuild assets/src/row-sync.js --bundle=false 2>&1
```
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
cd /c/xampp2/htdocs/dp-b2b\wp-content
git add plugins/dp-b2b-quick-order/assets/src/row-sync.js
git commit -m "feat: add RowSync — quantity wiring, variation replace, row states, cart footer"
```

---

## Task 7 — Entry point: wire `ProductList` and `RowSync`

**Files:**
- Modify: `plugins/dp-b2b-quick-order/assets/src/quick-order.js`

- [ ] **Step 1: Replace entire `quick-order.js`**

```js
'use strict';

import { SyncQueue }   from './sync-queue.js';
import { CartSync }    from './cart-sync.js';
import { ProductList } from './product-list.js';
import { RowSync }     from './row-sync.js';

(function () {
    const config = window.dpQuickOrder;
    if (!config || !config.cartSyncUrl || !config.wpNonce || !config.productsUrl) return;

    const queue = new SyncQueue();
    const sync  = new CartSync(queue, {
        cartSyncUrl: config.cartSyncUrl,
        wpNonce:     config.wpNonce,
        debounceMs:  config.debounceMs ?? 300,
        timeoutMs:   config.timeoutMs  ?? 10000,
    });

    // RowSync binds event delegation on tbody — must init before ProductList renders.
    const rowSync     = new RowSync(sync);
    const productList = new ProductList(config);

    const boot = () => productList.loadPage(1);
    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', boot)
        : boot();

    // Expose for browser-console stress testing.
    config.sync        = sync;
    config.queue       = queue;
    config.productList = productList;
    config.rowSync     = rowSync;
})();
```

- [ ] **Step 2: Syntax-check**

```powershell
cd C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order
node_modules\.bin\esbuild assets/src/quick-order.js --bundle=false 2>&1
```
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
cd /c/xampp2/htdocs/dp-b2b\wp-content
git add plugins/dp-b2b-quick-order/assets/src/quick-order.js
git commit -m "chore: wire ProductList and RowSync into Quick Order entry point"
```

---

## Task 8 — CSS: minimal functional styles

**Files:**
- Modify: `plugins/dp-b2b-quick-order/assets/dist/quick-order.css`

- [ ] **Step 1: Write `quick-order.css`**

```css
/* ── Layout ─────────────────────────────────────────────────── */
.dp-quick-order { font-size: 14px; }

.dp-qo-table-wrap { overflow-x: auto; margin: 12px 0; }

.dp-qo-table { width: 100%; border-collapse: collapse; }

.dp-qo-table th,
.dp-qo-table td {
    padding: 7px 10px;
    border-bottom: 1px solid #e2e2e2;
    vertical-align: middle;
}

.dp-qo-table th { text-align: left; font-weight: 600; background: #f7f7f7; }

/* ── Product name / SKU ──────────────────────────────────────── */
.dp-qo-name { display: block; font-weight: 500; }
.dp-qo-sku  { display: block; color: #888; font-size: 12px; }

/* ── Stock badge ─────────────────────────────────────────────── */
.dp-qo-stock { display: inline-block; font-size: 11px; padding: 2px 6px; border-radius: 3px; white-space: nowrap; }
.dp-qo-stock--instock     { background: #e8f5e9; color: #2e7d32; }
.dp-qo-stock--outofstock  { background: #fce4ec; color: #c62828; }
.dp-qo-stock--onbackorder { background: #fff3e0; color: #e65100; }

/* ── Variation select ────────────────────────────────────────── */
.dp-qo-variation { max-width: 200px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 13px; }

/* ── Quantity input ──────────────────────────────────────────── */
.dp-qo-qty { width: 64px; text-align: center; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 14px; }
.dp-qo-qty:disabled { background: #f3f3f3; opacity: 0.5; cursor: not-allowed; }

/* ── Row states ──────────────────────────────────────────────── */
.dp-qo-row.is-pending      { background: #fffde7; }
.dp-qo-row.is-syncing      { background: #e3f2fd; opacity: 0.8; }
.dp-qo-row.is-synced       { background: #e8f5e9; }
.dp-qo-row.is-error        { background: #fce4ec; }
.dp-qo-row.is-out-of-stock { background: #fff3e0; }

/* ── Status icon ─────────────────────────────────────────────── */
.dp-qo-status-icon { display: inline-block; width: 18px; text-align: center; font-size: 15px; }

/* ── Pagination ──────────────────────────────────────────────── */
.dp-qo-pagination { display: flex; align-items: center; gap: 10px; padding: 8px 0; }

.dp-qo-btn { padding: 5px 12px; border: 1px solid #ccc; background: #fff; border-radius: 3px; cursor: pointer; font-size: 13px; }
.dp-qo-btn:hover { background: #f3f3f3; }

.dp-qo-page-info { font-size: 13px; color: #666; }

/* ── Cart footer ─────────────────────────────────────────────── */
.dp-qo-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 16px;
    padding: 12px 0;
    border-top: 2px solid #e2e2e2;
    margin-top: 8px;
}

.dp-qo-footer__total { font-weight: 600; font-size: 15px; }

.dp-qo-footer__cart-link {
    display: inline-block;
    padding: 8px 18px;
    background: #222;
    color: #fff;
    text-decoration: none;
    border-radius: 3px;
    font-size: 13px;
}
.dp-qo-footer__cart-link:hover { background: #444; color: #fff; }

/* ── Loading / empty / error states ─────────────────────────── */
.dp-qo-loading, .dp-qo-empty, .dp-qo-error {
    text-align: center;
    padding: 24px;
    font-style: italic;
    color: #aaa;
}
.dp-qo-error { color: #c62828; }
```

- [ ] **Step 2: Commit**

```bash
cd /c/xampp2/htdocs/dp-b2b\wp-content
git add plugins/dp-b2b-quick-order/assets/dist/quick-order.css
git commit -m "feat: add minimal functional Quick Order CSS (row states, table, footer)"
```

---

## Task 9 — Build JS bundle + browser verification

**Files:**
- Rebuild: `plugins/dp-b2b-quick-order/assets/dist/quick-order.js`

- [ ] **Step 1: Build bundle**

```powershell
cd C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order
npm run build
```
Expected: `assets/dist/quick-order.js` rebuilt, no esbuild errors.

- [ ] **Step 2: Verify bundle size is reasonable**

```powershell
(Get-Item assets\dist\quick-order.js).Length
```
Expected: < 50 KB (minified IIFE). If larger, investigate — likely an accidental large import.

- [ ] **Step 3: Browser — page loads and product table renders**

Navigate to `http://localhost:8080/dp-b2b/quick-order/` while logged in as `vis_full`.

Open DevTools console. Verify:
```js
typeof window.dpQuickOrder.sync        // "object"
typeof window.dpQuickOrder.productList // "object"
typeof window.dpQuickOrder.rowSync     // "object"
window.dpQuickOrder.productsUrl        // ends with "/quick-order/products"
```

Verify in DOM: `.dp-qo-tbody` contains `<tr class="dp-qo-row">` elements (not loading placeholder).

- [ ] **Step 4: Browser — variation select populates and enables**

Find a `[data-type="variable"]` row. Within ~1 second of page load, the variation `<select>` should:
1. Change from `— Učitavanje —` to `— Odaberi varijaciju —` + option list
2. No longer have the `disabled` attribute

Verify in Network tab: a GET to `/quick-order/products/{id}/variations` was made for each variable product on the page.

- [ ] **Step 5: Browser — simple product quantity sync**

Find a simple product row. Set quantity to `3`. Wait 400ms. In Network tab:
- One POST to `/quick-order/cart/sync`
- Request body: `{"items":[{"product_id":N,"variation_id":0,"quantity":3}],"token":1}`
- Row state: `is-pending` while typing → `is-syncing` during request → `is-synced` on success

In DevTools console:
```js
window.dpQuickOrder.sync.getOptimisticQuantity(`${productId}_0`) // 3
```

- [ ] **Step 6: Browser — variation selection enables quantity**

Find a variable product. Verify quantity input is `disabled` before variation selection. Select any variation from the dropdown. Verify quantity input becomes enabled. Set quantity to `2`. Verify sync fires with correct `variation_id`.

- [ ] **Step 7: Browser — variation replace with existing cart qty**

Using the same variable product: select Variation A, set qty to `3` → wait for sync. Then change the variation select to Variation B.

In Network tab: verify ONE POST containing two items:
```json
{"items": [
  {"product_id": N, "variation_id": A_ID, "quantity": 0},
  {"product_id": N, "variation_id": B_ID, "quantity": 3}
], "token": ...}
```

Row should not flicker to zero. After sync success: row shows `is-synced`, qty shows `3`.

- [ ] **Step 8: Browser — cart footer updates**

After any successful sync, verify `.dp-qo-footer__total` shows a formatted currency total (e.g. `Ukupno: 29,97 €`).

- [ ] **Step 9: Browser — pagination**

If catalog has >50 products: click "Sljedeća →". Verify:
- New rows render
- Previous page's CartSync inflight requests complete in background (check Network tab — no cancelled requests from the old page)
- New page's rows are interactive

- [ ] **Step 10: Commit built assets + finalize**

```bash
cd /c/xampp2/htdocs/dp-b2b\wp-content
git add plugins/dp-b2b-quick-order/assets/dist/quick-order.js
git commit -m "build: rebuild Quick Order bundle with ProductList, RowSync, CartSync events"
```

---

## Self-Review

### Spec coverage

| Requirement | Task |
|-------------|------|
| Dense table rendering | Task 5 (`#rowHTML`) |
| Variation select always visible | Task 5 (`#rowHTML` — variable product cell) |
| Qty disabled until variation selected | Task 5 (initial `disabled`) + Task 6 (`#onVariationChange`) |
| Implicit replace semantics (remove+add same window) | Task 6 (`#onVariationChange`) |
| Atomic-feel replace (no flicker) | Task 6 (row identity updated before schedule calls) |
| CartSync events (`dp:sync:*`) | Task 1 |
| Row states: pending/syncing/synced/error/out-of-stock | Task 6 (`#setRowState`) |
| Cart footer total | Task 6 (`#updateFooter`) |
| Pagination | Task 5 (`#renderPagination`, `loadPage`) |
| CartSync continues during pagination | Architectural (CartSync is page-lifecycle-independent; pagination only replaces DOM) |
| Lazy variation data fetch | Task 5 (`#loadAllVariations`, `#loadVariationOptions`) |
| Variation endpoint (PHP) | Task 2 |
| `productsUrl` in localized config | Task 3 |
| Template HTML structure | Task 4 |
| Minimal CSS + row state classes | Task 8 |

### Placeholder scan: none found.

### Type consistency

- `rowKey` format `"${productId}_${variationId}"` used consistently:
  - `product-list.js` `#rowHTML`: `data-row-key="${product.id}_0"`
  - `row-sync.js` `#onQtyInput`: reads `input.dataset.rowKey`
  - `row-sync.js` `#onVariationChange`: constructs `newKey = \`${productId}_${variationId}\``
  - `row-sync.js` sync event handlers: constructs `\`${item.product_id}_${item.variation_id}\``
  - Matches existing `CartSync` and `SyncQueue` key format

- `dp:sync:start` event `detail.items` is the same `items[]` array that was flushed from `SyncQueue.flush()` — shape `{product_id, variation_id, quantity}` — matches what `row-sync.js` constructs keys from.

- `dp:sync:success` `detail.synced` is `data.synced` from the server response — shape `{product_id, variation_id, action, quantity?, quantity_allowed?, error?}` — matches what `row-sync.js` switch statement handles.

- `RowSync` constructor takes `(sync)` — `quick-order.js` calls `new RowSync(sync)` ✓
- `ProductList` constructor takes `(config)` — `quick-order.js` calls `new ProductList(config)` ✓
- `CartSync` constructor takes `(queue, config)` — unchanged ✓

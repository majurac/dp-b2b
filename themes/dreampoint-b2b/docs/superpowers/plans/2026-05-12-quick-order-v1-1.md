# Quick Order V1.1 Usability Pass — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Five surgical usability improvements to the Quick Order plugin — admin bypass, variable stock UX, container wrapper, server-authoritative sorting, and plugin-local qty +/- controls.

**Architecture:** All changes are incremental within existing file boundaries. PHP changes go through existing filter/REST/query layer. JS changes stay within ProductList and RowSync classes. CartSync, SyncQueue, debounce, stale-token, and variation-replace semantics are untouched.

**Tech Stack:** PHP 8.3+, vanilla JS ES2022 (IIFE bundle via esbuild), WooCommerce REST, WordPress filters

---

## File Map

| File | Changes |
|------|---------|
| `inc/class-access-guard.php` | Add `manage_woocommerce` bypass to `handle_user_allowed()` |
| `inc/class-rest-api.php` | Add `orderby` + `order` REST args, pass to product_query |
| `inc/class-product-query.php` | Map `orderby`/`order` → WP_Query args |
| `templates/quick-order.php` | Add `.container` wrapper; add `data-sort` + `.dp-qo-sort-arrow` to th |
| `assets/src/product-list.js` | Variable stock neutral state; sort state + headers; qty +/- HTML |
| `assets/src/row-sync.js` | Stock badge update on variation change; qty +/- click delegation; btn enable/disable |
| `assets/dist/quick-order.css` | Neutral badge; sort header; qty-wrap styles |
| `assets/dist/quick-order.js` | Rebuilt via `npm run build` |

---

## Task 1 — Admin Bypass

**Files:**
- Modify: `inc/class-access-guard.php` — method `handle_user_allowed()` at line 77

- [ ] **Edit `handle_user_allowed()`** — add `manage_woocommerce` early return before the bucket check:

```php
public function handle_user_allowed( bool $default, int $user_id ): bool {
    if ( ! $user_id ) {
        return false;
    }
    if ( current_user_can( 'manage_woocommerce' ) ) {
        return true;
    }
    return ! empty( get_user_meta( $user_id, 'dp_bucket_id', true ) );
}
```

- [ ] **Verify** — log in as administrator, visit Quick Order page. Access denied message must not appear.

---

## Task 2 — Variable Parent Stock Neutral State

**Files:**
- Modify: `assets/src/product-list.js` — method `#rowHTML()` lines 62-99

The stock label/class block currently runs unconditionally. Change to branch on `isVariable`:

- [ ] **Replace stock label block in `#rowHTML()`:**

```javascript
#rowHTML(product) {
    const isVariable = product.type === 'variable';
    const rowKey     = `${product.id}_0`;

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
    <input type="number"
           class="dp-qo-qty"
           data-row-key="${rowKey}"
           value="0" min="0" step="1"
           ${isVariable ? 'disabled' : ''}>
  </td>
  <td class="dp-qo-col-status"><span class="dp-qo-status-icon" aria-hidden="true"></span></td>
</tr>`.trim();
}
```

---

## Task 3 — Container Wrapper + Sort th Attributes

**Files:**
- Modify: `templates/quick-order.php`

- [ ] **Wrap content in `.container`, add `data-sort` + `.dp-qo-sort-arrow` to Naziv and Cijena headers:**

```php
<?php
defined( 'ABSPATH' ) || exit;
?>
<div id="dp-quick-order"
	class="dp-quick-order"
	data-rest-url="<?php echo esc_attr( rest_url( DP_Quick_Order_Config::REST_NAMESPACE . '/' ) ); ?>"
	data-nonce="<?php echo esc_attr( wp_create_nonce( DP_Quick_Order_Config::NONCE_ACTION ) ); ?>"
>
	<div class="container">

		<div class="dp-qo-pagination"></div>

		<div class="dp-qo-table-wrap">
			<table class="dp-qo-table">
				<thead>
					<tr>
						<th data-sort="title"><?php esc_html_e( 'Naziv', 'dp-b2b-quick-order' ); ?><span class="dp-qo-sort-arrow" aria-hidden="true"></span></th>
						<th><?php esc_html_e( 'Stanje', 'dp-b2b-quick-order' ); ?></th>
						<th data-sort="price"><?php esc_html_e( 'Cijena', 'dp-b2b-quick-order' ); ?><span class="dp-qo-sort-arrow" aria-hidden="true"></span></th>
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

	</div><!-- .container -->
</div><!-- #dp-quick-order -->
```

---

## Task 4 — Server-Authoritative Sorting (PHP)

**Files:**
- Modify: `inc/class-rest-api.php` — `register_routes()` products args + `get_products()`
- Modify: `inc/class-product-query.php` — `query()` method

- [ ] **Add `orderby` + `order` to products route args in `register_routes()`:**

```php
'args' => [
    'page'     => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
    'per_page' => [ 'type' => 'integer', 'default' => DP_Quick_Order_Config::PRODUCTS_PER_PAGE_DEFAULT, 'minimum' => 1, 'maximum' => DP_Quick_Order_Config::PRODUCTS_PER_PAGE_MAX ],
    'search'   => [ 'type' => 'string', 'default' => '' ],
    'category' => [ 'type' => 'integer', 'default' => 0 ],
    'brand'    => [ 'type' => 'integer', 'default' => 0 ],
    'orderby'  => [ 'type' => 'string', 'enum' => [ 'title', 'price' ], 'default' => 'title' ],
    'order'    => [ 'type' => 'string', 'enum' => [ 'asc', 'desc' ], 'default' => 'asc' ],
],
```

- [ ] **Pass `orderby` + `order` in `get_products()`:**

```php
public function get_products( WP_REST_Request $request ): WP_REST_Response|WP_Error {
    $results = $this->product_query->query( [
        'page'     => $request->get_param( 'page' ),
        'per_page' => $request->get_param( 'per_page' ),
        'search'   => $request->get_param( 'search' ),
        'category' => $request->get_param( 'category' ),
        'brand'    => $request->get_param( 'brand' ),
        'orderby'  => $request->get_param( 'orderby' ),
        'order'    => $request->get_param( 'order' ),
    ] );

    return rest_ensure_response( $results );
}
```

- [ ] **Map `orderby`/`order` in `class-product-query.php` `query()` — insert after the initial `$query_args` array, before `if ( ! empty( $args['search'] ) )`:**

```php
$orderby = $args['orderby'] ?? 'title';
$order   = strtoupper( $args['order'] ?? 'ASC' );
$order   = in_array( $order, [ 'ASC', 'DESC' ], true ) ? $order : 'ASC';

if ( 'price' === $orderby ) {
    $query_args['orderby']  = 'meta_value_num';
    $query_args['meta_key'] = '_price';
} else {
    $query_args['orderby'] = 'title';
}
$query_args['order'] = $order;
```

---

## Task 5 — Sort UI + Qty +/- HTML (product-list.js complete rewrite of changed methods)

**Files:**
- Modify: `assets/src/product-list.js`

This task adds:
- `#orderBy` + `#orderDir` private fields
- `#bindSortHeaders()` + `#updateSortIndicators()` methods
- URL params in `loadPage()`
- `#bindSortHeaders()` + initial indicator call in constructor
- Qty +/- buttons in `#rowHTML()`

- [ ] **Replace `product-list.js` with the full updated version:**

```javascript
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
    #orderBy      = 'title';
    #orderDir     = 'asc';

    /**
     * @param {object} config  window.dpQuickOrder
     */
    constructor(config) {
        this.#config       = config;
        this.#tbody        = document.querySelector('.dp-qo-tbody');
        this.#paginationEl = document.querySelector('.dp-qo-pagination');
        this.#bindSortHeaders();
        this.#updateSortIndicators();
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
            const url = `${this.#config.productsUrl}?page=${page}&per_page=${this.#config.perPage ?? 50}&orderby=${this.#orderBy}&order=${this.#orderDir}`;
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
      <button class="dp-qo-qty-btn dp-qo-qty-minus" type="button" aria-label="Smanji količinu"${isVariable ? ' disabled' : ''}>−</button>
      <input type="number"
             class="dp-qo-qty"
             data-row-key="${rowKey}"
             value="0" min="0" step="1"
             ${isVariable ? 'disabled' : ''}>
      <button class="dp-qo-qty-btn dp-qo-qty-plus" type="button" aria-label="Povećaj količinu"${isVariable ? ' disabled' : ''}>+</button>
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
}

/** Escape HTML for safe insertion into innerHTML. */
function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
}
```

---

## Task 6 — Row-Sync Changes (stock badge + qty +/- interaction)

**Files:**
- Modify: `assets/src/row-sync.js`

Adds: `#updateStockBadge()` helper, stock badge calls in `#onVariationChange()`, `#onQtyButton()` method, click delegation, btn enable/disable in variation change.

- [ ] **Replace `row-sync.js` with the full updated version:**

```javascript
'use strict';

/**
 * Row interaction controller.
 *
 * Wires quantity inputs and variation selects to CartSync via event delegation.
 * Handles row state feedback (idle/pending/synced/error) via CartSync events.
 */
export class RowSync {
    /** @type {import('./cart-sync.js').CartSync} */
    #sync;
    /** @type {HTMLElement|null} */
    #tbody;

    /**
     * @param {import('./cart-sync.js').CartSync} sync
     */
    constructor(sync) {
        this.#sync  = sync;
        this.#tbody = document.querySelector('.dp-qo-tbody');
        if (!this.#tbody) return;
        this.#bindTableEvents();
        this.#initStateListeners();
    }

    #initStateListeners() {
        document.addEventListener('dp:sync:start', e => {
            for (const item of e.detail.items) {
                const row = this.#findRow(`${item.product_id}_${item.variation_id}`);
                if (row) this.#setState(row, 'pending');
            }
        });

        document.addEventListener('dp:sync:success', e => {
            for (const item of e.detail.synced) {
                const row = this.#findRow(`${item.product_id}_${item.variation_id}`);
                if (!row) continue;

                this.#setState(row, this.#resolveState(item.action));

                // Reflect server-corrected quantity in qty input for out_of_stock.
                if (item.action === 'out_of_stock') {
                    const input = row.querySelector('.dp-qo-qty');
                    if (input) input.value = item.quantity_allowed ?? 0;
                }
            }
        });

        document.addEventListener('dp:sync:error', () => {
            if (!this.#tbody) return;
            this.#tbody.querySelectorAll('.dp-qo-row.is-pending').forEach(row => {
                this.#setState(row, 'error');
            });
        });
    }

    /** Map a server action string to a row UI state. */
    #resolveState(action) {
        switch (action) {
            case 'removed':
            case 'skipped':      return 'idle';
            case 'failed':
            case 'out_of_stock': return 'error';
            default:             return 'synced';
        }
    }

    /**
     * Apply state class and status icon to a row.
     * Clears all state classes before applying the new one.
     * 'idle' removes all classes (default DOM state, no visual indicator).
     *
     * @param {HTMLElement} row
     * @param {'idle'|'pending'|'synced'|'error'} state
     */
    #setState(row, state) {
        row.classList.remove('is-pending', 'is-synced', 'is-error');
        if (state !== 'idle') row.classList.add(`is-${state}`);

        const icon = row.querySelector('.dp-qo-status-icon');
        if (!icon) return;
        const map = { pending: '…', synced: '✓', error: '✕', idle: '' };
        icon.textContent = map[state] ?? '';
    }

    /**
     * Find a row by its data-row-key attribute.
     * rowKey is always "${productId}_${variationId}" — safe integer format, no escaping needed.
     *
     * @param {string} rowKey
     * @returns {HTMLElement|null}
     */
    #findRow(rowKey) {
        return this.#tbody?.querySelector(`[data-row-key="${rowKey}"]`) ?? null;
    }

    #bindTableEvents() {
        // 'input' covers typing, paste, cut, and spinner clicks for qty inputs.
        this.#tbody.addEventListener('input', e => {
            if (e.target.matches('.dp-qo-qty')) this.#onQtyInput(e.target);
        });
        // 'change' fires on select commit — variation selection.
        this.#tbody.addEventListener('change', e => {
            if (e.target.matches('.dp-qo-variation')) this.#onVariationChange(e.target);
        });
        // +/- button clicks — adjust value and fire input event into existing handler.
        this.#tbody.addEventListener('click', e => {
            if (e.target.matches('.dp-qo-qty-minus')) this.#onQtyButton(e.target, -1);
            else if (e.target.matches('.dp-qo-qty-plus'))  this.#onQtyButton(e.target, +1);
        });
    }

    #onQtyInput(input) {
        // Skip disabled inputs — variable products before variation selection.
        if (input.disabled) return;

        const rowKey = input.dataset.rowKey;
        if (!rowKey) return;

        const qty = Math.max(0, parseInt(input.value, 10) || 0);
        this.#sync.schedule(rowKey, qty);
    }

    #onQtyButton(btn, delta) {
        if (btn.disabled) return;
        const input = btn.closest('.dp-qo-qty-wrap')?.querySelector('.dp-qo-qty');
        if (!input || input.disabled) return;
        const next = Math.max(0, (parseInt(input.value, 10) || 0) + delta);
        input.value = next;
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    #onVariationChange(select) {
        const row = select.closest('.dp-qo-row');
        if (!row) return;

        const productId = parseInt(row.dataset.productId, 10);
        if (!productId) return; // malformed row — prevents "undefined_N" keys

        const qtyInput    = row.querySelector('.dp-qo-qty');
        const variationId = parseInt(select.value, 10) || 0; // NaN/empty/non-numeric → 0

        if (!variationId) {
            // User reset to placeholder — disable qty + buttons, reset row key to neutral.
            row.dataset.rowKey = `${productId}_0`;
            if (qtyInput) {
                qtyInput.dataset.rowKey = `${productId}_0`;
                qtyInput.disabled       = true;
                qtyInput.value          = '0';
            }
            row.querySelectorAll('.dp-qo-qty-btn').forEach(b => { b.disabled = true; });
            this.#updateStockBadge(row, null);
            return;
        }

        const oldKey = row.dataset.rowKey;
        const newKey = `${productId}_${variationId}`;

        if (oldKey === newKey) return; // same variation re-selected — no-op

        const currentQty = qtyInput ? Math.max(0, parseInt(qtyInput.value, 10) || 0) : 0;

        // Update row identity before scheduling — qty input listener reads data-row-key.
        row.dataset.rowKey = newKey;
        if (qtyInput) {
            qtyInput.dataset.rowKey = newKey;
            qtyInput.disabled       = false;
        }
        row.querySelectorAll('.dp-qo-qty-btn').forEach(b => { b.disabled = false; });

        // Update stock badge from selected variation's data-stock attribute.
        const selectedStock = select.options[select.selectedIndex]?.dataset.stock ?? 'instock';
        this.#updateStockBadge(row, selectedStock);

        // Implicit replace: remove old variation + add new variation in the same synchronous
        // call so both land in one debounce window. Feels atomic from user perspective.
        // In-flight abort (if any) is handled by CartSync's AbortController — not our concern.
        const hadOldVariation = oldKey !== `${productId}_0`;
        if (hadOldVariation && currentQty > 0) {
            this.#sync.schedule(oldKey, 0);          // remove old
            this.#sync.schedule(newKey, currentQty); // add new with same qty
        }
        // First selection or qty still 0: nothing to sync until user enters a quantity.
    }

    /**
     * Update the stock badge in a row from a variation stock status string.
     * Pass null to reset to neutral (before variation selection).
     *
     * @param {HTMLElement} row
     * @param {string|null} stockStatus
     */
    #updateStockBadge(row, stockStatus) {
        const badge = row.querySelector('.dp-qo-stock');
        if (!badge) return;
        const labels = { instock: 'Na stanju', outofstock: 'Nema na stanju', onbackorder: 'Po narudžbi' };
        if (!stockStatus) {
            badge.className   = 'dp-qo-stock dp-qo-stock--neutral';
            badge.textContent = 'Odaberi varijaciju';
        } else {
            badge.className   = `dp-qo-stock dp-qo-stock--${stockStatus}`;
            badge.textContent = labels[stockStatus] ?? stockStatus;
        }
    }
}
```

---

## Task 7 — CSS

**Files:**
- Modify: `assets/dist/quick-order.css`

- [ ] **Append to `quick-order.css`:**

```css
/* ── Neutral stock badge (variable before selection) ─────────── */
.dp-qo-stock--neutral { background: #f5f5f5; color: #999; }

/* ── Sortable column headers ─────────────────────────────────── */
.dp-qo-table th[data-sort] { cursor: pointer; user-select: none; }
.dp-qo-table th[data-sort]:hover { background: #efefef; }
.dp-qo-sort-arrow { font-size: 11px; margin-left: 3px; }

/* ── Qty +/- wrap ─────────────────────────────────────────────── */
.dp-qo-qty-wrap { display: inline-flex; align-items: center; gap: 3px; }
.dp-qo-qty-btn {
    width: 26px; height: 26px;
    padding: 0;
    border: 1px solid #ccc;
    background: #fff;
    border-radius: 3px;
    cursor: pointer;
    font-size: 15px;
    line-height: 1;
}
.dp-qo-qty-btn:hover:not(:disabled) { background: #f3f3f3; }
.dp-qo-qty-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.dp-qo-qty { width: 52px; }
```

---

## Task 8 — Build JS Bundle

**Files:**
- Output: `assets/dist/quick-order.js`

- [ ] **Run build from plugin root:**

```powershell
Set-Location "C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order"
npm run build
```

Expected output: `assets/dist/quick-order.js` updated — no errors.

- [ ] **Verify dist file timestamp updated** — check file modified date is current.

---

## Self-Review Notes

- All 5 approved tasks covered: admin bypass ✓, variable stock UX ✓, container wrapper ✓, sorting ✓, qty +/- ✓
- Sorting: both `orderby` AND `order` params validated server-side with enum allowlist ✓
- CartSync / SyncQueue / cart-sync.js / debounce / stale-token / variation-replace: not touched ✓
- Theme cart-quantity.js: not imported or referenced ✓
- Qty +/- dispatch `input` event → existing RowSync `#onQtyInput` handles sync ✓
- Stock badge update uses `data-stock` already present on variation `<option>` elements ✓
- `manage_woocommerce` bypass uses same `dp_b2b_quick_order_user_allowed` filter path as REST ✓

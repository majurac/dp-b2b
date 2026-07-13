# Quick Order — Selected Variations Chips, Sort Dropdown, Qty Checkmark Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

> **Post-implementation note (2026-07-13):** Tasks 2 and 6 below (custom
> `<select>` dropdown, `dp_qo_get_sort_options()`, `#bindSortDropdown()`) were
> executed as written, then **superseded the same day** by a follow-up
> decision to use WBW Product Filter's own native "Sort By" control instead
> (a second synchronized WBW filter view, id=2, rendered in `.dp-qo-sort` via
> `[wpf-filters id=2]`). All custom-dropdown code listed in Tasks 2 and 6 was
> removed; `product-list.js` instead gained `#applyOrderbyParam()` inside the
> existing `#bindWoofIntegration()`/`#onWoofUrlChange()`, translating WBW's
> public `?orderby=` URL parameter into the same `qo_orderby`/`qo_order` REST
> params. Task 5's chip-label source was also revised: `get_variation_details()`
> now returns a structured `attributes: {label,value}[]` field (in addition to
> the unchanged flat `label`), and `VariationChipsController` joins those
> resolved pairs directly instead of string-splitting the flat label. See
> `docs/superpowers/specs/2026-07-13-quick-order-toolbar-chips-design.md` §3–4
> for the as-shipped architecture and investigation findings. The step-by-step
> content below is kept as the historical execution record.

**Goal:** Add three UX layers to the Quick Order plugin (`wp-content/plugins/dp-b2b-quick-order/`): a "selected variations" chip row merged visually into the existing WBW Active Filters toolbar, a native sort dropdown replacing click-to-sort table headers, and a transient checkmark confirming quantity changes.

**Architecture:** Pure presentation layer on top of the frozen local-state architecture (`docs/frozen/quick-order-local-state-architecture.md`). `QuickOrderState` gains one additive getter only. A new `VariationChipsController` renders chips from state + a label cache fed by `ProductList`'s render notifications (event-driven, not DOM-scraped). WBW's own `.selected-prod_atributes` subtree is never read from or written to. Sort UI swaps from clickable `<th>` headers to a `<select>`, same REST params underneath.

**Tech Stack:** Vanilla JS (ES2022+, esbuild bundling, IIFE output), PHP 8.3 (WordPress/WooCommerce), hand-written CSS (no build step for CSS in this plugin). No JS/PHP unit test framework exists in this plugin (`package.json` has esbuild only) — verification is via `php -l` for PHP syntax and Playwright browser checks against the local XAMPP site for behavior, matching how the rest of this plugin was validated (see frozen doc's "verified via Playwright against local XAMPP").

## Global Constraints

- `QuickOrderState`'s stored row shape (`quantity, unitPrice, productId, variationId`), `setQuantity()` signature, REST endpoints, footer formulas, bulk add-to-cart, pagination, filtering, and stock validation are unchanged — spec §"Explicitly out of scope."
- `.selected-prod_atributes` and everything under it (`.wpfSelectedParameters`, `.wpfSelectedParameter`, `.wpfSelectedParametersClear`) is a black box: never read, never written, never depended on for structure — spec architecture decision 1.
- Quick Order's own chips are never injected inside `.selected-prod_atributes` or `.wpfSelectedParameters` — they live in a sibling `.dp-qo-selected-variations` container — spec architecture decision 2.
- Variation labels flow from `ProductList` (producer) to `VariationChipsController` via the existing `dp:qo:rows-rendered` CustomEvent, extended with `detail.variationLabels: {rowKey, label}[]` — this is the canonical sync path, not DOM scanning — spec architecture decision 3.
- `dp_qo_get_sort_options()` is the single source of truth for sort `<option>`s — the template loops it, never hardcodes options — spec architecture decision 4.
- `.dp-qo-chip` is a new, self-contained CSS component with zero dependency on WBW's CSS classes or HTML — spec architecture decision 5.
- Chip render order follows `QuickOrderState.getActiveRowKeys()`'s `Map` insertion order — never sorted/re-ordered — spec Feature 1 "Order."
- Checkmark flash timers use a `WeakMap<HTMLElement, number>` keyed by the checkmark DOM element, cleared before rescheduling — never stack, never leak — spec Feature 3.
- Windows/local dev: PHP binary is `C:\xampp2\php83\php.exe` (POSIX `/c/xampp2/php83/php.exe`) — never the stale `C:\xampp2\php\php.exe`. esbuild runs via `npm run build` from the plugin root.
- Local site: `http://localhost:8080/dp-b2b/quick-order/`, admin login `admin` / `armin123#` (see `.claude/environment.md`).

---

## File Structure

| File | Responsibility |
|---|---|
| `assets/src/quick-order-state.js` | Modify — add `getActiveRowKeys()` |
| `templates/quick-order.php` | Modify — `.dp-qo-toolbar` wrapper, chip placeholder, sort `<select>`, remove `data-sort` th markup |
| `inc/class-frontend.php` | Modify — add plain function `dp_qo_get_sort_options()` |
| `assets/src/product-list.js` | Modify — variation label event payload, `data-variation-label` attr, checkmark span, sort dropdown binding |
| `assets/src/variation-chips.js` | New — `VariationChipsController` (chip render, remove wiring) |
| `assets/src/row-controller.js` | Modify — `chips` dependency, checkmark flash timers |
| `assets/src/quick-order.js` | Modify — wire `VariationChipsController` |
| `assets/dist/quick-order.css` | Modify — toolbar/chip/sort/checkmark styles |
| `assets/dist/quick-order.js` | Rebuilt via `npm run build` (Task 8) |
| `docs/frozen/quick-order-local-state-architecture.md` | Modify — §8 revision note (Task 9) |

---

### Task 1: `QuickOrderState.getActiveRowKeys()`

**Files:**
- Modify: `wp-content/plugins/dp-b2b-quick-order/assets/src/quick-order-state.js:39-42` (right after `getRowCount()`)

**Interfaces:**
- Consumes: existing private `#rows` `Map<string, {productId, variationId, quantity, unitPrice}>`
- Produces: `getActiveRowKeys(): string[]` — row keys with quantity > 0, in `Map` insertion order. Consumed by `VariationChipsController.render()` in Task 4.

- [ ] **Step 1: Add the getter**

In `assets/src/quick-order-state.js`, immediately after `getRowCount()` (currently lines 39-42):

```js
    /** @returns {number} count of distinct rows with quantity > 0 — footer "N varijacija" */
    getRowCount() {
        return this.#rows.size;
    }

    /**
     * @returns {string[]} row keys with quantity > 0, in insertion order
     * (JS `Map` iterates in insertion order by spec — this is deterministic,
     * never sorted). Consumed by VariationChipsController so chips never
     * reorder themselves as the user edits quantities.
     */
    getActiveRowKeys() {
        return [...this.#rows.keys()];
    }
```

- [ ] **Step 2: Rebuild and verify in the browser console**

Run (from `wp-content/plugins/dp-b2b-quick-order/`):
```bash
npm run build
```
Expected: exits 0, `assets/dist/quick-order.js` timestamp updates.

Navigate to `http://localhost:8080/dp-b2b/quick-order/` (logged in as admin), set quantity to 2 on any product row's qty input, then in the browser devtools console:
```js
window.dpQuickOrder.state.getActiveRowKeys()
```
Expected: array containing that row's `"${productId}_0"` key (or `"${productId}_${variationId}"` for a variation). Set a second row's qty, re-run — expected: array of length 2, first-set key first (insertion order).

- [ ] **Step 3: Commit**

```bash
git add wp-content/plugins/dp-b2b-quick-order/assets/src/quick-order-state.js wp-content/plugins/dp-b2b-quick-order/assets/dist/quick-order.js
git commit -m "feat(qo): add QuickOrderState.getActiveRowKeys() getter"
```

---

### Task 2: Toolbar markup — `.dp-qo-toolbar`, chip placeholder, sort `<select>`, sort options helper

**Files:**
- Modify: `wp-content/plugins/dp-b2b-quick-order/templates/quick-order.php:20-81`
- Modify: `wp-content/plugins/dp-b2b-quick-order/inc/class-frontend.php` (add function at end of file)

**Interfaces:**
- Produces: `dp_qo_get_sort_options(): array<string,string>` (PHP, global function) — value => translated label. Consumed by the template in this same task, and referenced (not called) by Task 6's JS dropdown handler which only parses the `<select>`'s selected `value`.
- Produces: DOM structure `.dp-qo-toolbar > .dp-qo-toolbar__filters > (.selected-prod_atributes output, unchanged) + .dp-qo-selected-variations` and `.dp-qo-toolbar > .dp-qo-sort > select.dp-qo-sort-select`. Consumed by Task 3 (`VariationChipsController` queries `.dp-qo-selected-variations`) and Task 6 (`#bindSortDropdown()` queries `.dp-qo-sort-select`).
- Produces: `<th>` Naziv/Cijena no longer carry `data-sort` or `.dp-qo-sort-arrow` — consumed by Task 6 (old `#bindSortHeaders()`/`#updateSortIndicators()` are deleted, nothing left to bind to).

- [ ] **Step 1: Add `dp_qo_get_sort_options()` to `inc/class-frontend.php`**

Append to the end of `wp-content/plugins/dp-b2b-quick-order/inc/class-frontend.php` (after the closing `}` of `DP_Quick_Order_Frontend`):

```php
<?php
defined( 'ABSPATH' ) || exit;

class DP_Quick_Order_Frontend {

	public function __construct(
		private readonly DP_Quick_Order_Assets $assets
	) {
		add_shortcode( DP_Quick_Order_Config::SHORTCODE, [ $this, 'render_shortcode' ] );
	}

	public function render_shortcode( array $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to use Quick Order.', 'dp-b2b-quick-order' ) . '</p>';
		}

		$user_id = get_current_user_id();
		if ( ! (bool) apply_filters( 'dp_b2b_quick_order_user_allowed', true, $user_id ) ) {
			return '<p class="dp-qo-access-denied">' . esc_html__( 'You do not have access to the Quick Order.', 'dp-b2b-quick-order' ) . '</p>';
		}

		ob_start();
		$this->render_template();
		return (string) ob_get_clean();
	}

	private function render_template(): void {
		$template = DP_QUICK_ORDER_PATH . 'templates/quick-order.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}
}

/**
 * Quick Order's sort dropdown options: value => translated label.
 * Single source of truth — templates/quick-order.php loops this to render
 * <option> tags; assets/src/product-list.js parses the selected value's
 * generic "field-direction" shape and has no knowledge of this list's
 * contents. Adding/reordering/relabeling a sort option only touches this
 * function.
 *
 * @return array<string,string>
 */
function dp_qo_get_sort_options(): array {
	return [
		'title-asc'  => __( 'Naziv (A-Ž)', 'dp-b2b-quick-order' ),
		'title-desc' => __( 'Naziv (Ž-A)', 'dp-b2b-quick-order' ),
		'price-asc'  => __( 'Cijena: niža prema višoj', 'dp-b2b-quick-order' ),
		'price-desc' => __( 'Cijena: viša prema nižoj', 'dp-b2b-quick-order' ),
	];
}
```

- [ ] **Step 2: Verify PHP syntax**

Run:
```bash
"/c/xampp2/php83/php.exe" -l "/c/xampp2/htdocs/dp-b2b/wp-content/plugins/dp-b2b-quick-order/inc/class-frontend.php"
```
Expected: `No syntax errors detected`

- [ ] **Step 3: Restructure `templates/quick-order.php`**

Replace lines 20-81 (from `<div class="col-lg-9">` through the closing `</thead>`) with:

```php
			<div class="col-lg-9">

				<div class="dp-qo-pagination"></div>

				<div class="dp-qo-toolbar">
					<div class="dp-qo-toolbar__filters">
						<?php
						$attribute_taxonomies = wc_get_attribute_taxonomies();
						if ( ! empty( $attribute_taxonomies ) ) {
							foreach ( $attribute_taxonomies as $attribute ) {
								$slug = 'pa_' . $attribute->attribute_name;

								if ( empty( $GLOBALS['_var_atts_in']['attribute_' . $slug] ) ) {
									continue;
								}

								$terms = get_terms([
									'taxonomy'   => $slug,
									'hide_empty' => true,
								]);

								if ( empty( $terms ) || is_wp_error( $terms ) ) continue;

								$label = wc_attribute_label( $slug );

								// Detect WBW filter param (e.g. wpf_filter_boja=crna%7Cplava)
								$param_key = 'wpf_filter_' . $attribute->attribute_name;
								$selected_terms = [];
								if ( ! empty( $_GET[ $param_key ] ) ) {
									$selected_terms = array_filter( explode( '|', sanitize_text_field( $_GET[ $param_key ] ) ) );
								}

								$count = count( $selected_terms );
								$selected_class = $count > 0 ? 'selected' : '';

								echo '<div class="wpf_item wpf_item_' . esc_attr( $slug ) . ' ' . esc_attr( $selected_class ) . '">';
								echo '<div class="wpf_item_name">' . esc_html( $label );
								if ( $count ) {
									echo ' <span class="count">' . intval( $count ) . '</span>';
								}
								echo '</div></div>';
							}
						} else {
							echo 'No product attributes found.';
						}
						?>

						<?php echo do_shortcode( '[wpf-selected-filters id=1]' ); ?>

						<div class="dp-qo-selected-variations" hidden></div>
					</div><!-- /.dp-qo-toolbar__filters -->

					<div class="dp-qo-sort">
						<label for="dp-qo-sort-select"><?php esc_html_e( 'Sortiraj po', 'dp-b2b-quick-order' ); ?></label>
						<select id="dp-qo-sort-select" class="dp-qo-sort-select">
							<?php foreach ( dp_qo_get_sort_options() as $option_value => $option_label ) : ?>
								<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $option_value, 'title-asc' ); ?>><?php echo esc_html( $option_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div><!-- /.dp-qo-toolbar -->

				<div class="dp-qo-table-wrap">
					<table class="dp-qo-table">
						<thead>
							<tr>
								<th class="dp-qo-col-thumb"></th>
								<th><?php esc_html_e( 'Naziv', 'dp-b2b-quick-order' ); ?></th>
								<th><?php esc_html_e( 'Stanje', 'dp-b2b-quick-order' ); ?></th>
								<th><?php esc_html_e( 'Cijena', 'dp-b2b-quick-order' ); ?></th>
								<th><?php esc_html_e( 'Kol.', 'dp-b2b-quick-order' ); ?></th>
							</tr>
						</thead>
```

Leave everything from `<tbody class="dp-qo-tbody">` onward (through end of file) unchanged.

- [ ] **Step 4: Verify PHP syntax**

Run:
```bash
"/c/xampp2/php83/php.exe" -l "/c/xampp2/htdocs/dp-b2b/wp-content/plugins/dp-b2b-quick-order/templates/quick-order.php"
```
Expected: `No syntax errors detected`

- [ ] **Step 5: Verify rendered DOM structure with Playwright**

Navigate to `http://localhost:8080/dp-b2b/quick-order/` (logged in as admin) and evaluate:
```js
() => {
  const toolbar = document.querySelector('.dp-qo-toolbar');
  const filters  = document.querySelector('.dp-qo-toolbar__filters');
  const chips    = document.querySelector('.dp-qo-selected-variations');
  const wbw      = document.querySelector('.selected-prod_atributes');
  const sort     = document.querySelector('.dp-qo-sort-select');
  return {
    toolbarExists: !!toolbar,
    filtersContainsWbw: !!(filters && wbw && filters.contains(wbw)),
    filtersContainsChips: !!(filters && chips && filters.contains(chips)),
    chipsHidden: chips?.hidden,
    sortOptionCount: sort?.options.length,
    sortOptionValues: sort ? [...sort.options].map(o => o.value) : null,
    thDataSortCount: document.querySelectorAll('.dp-qo-table th[data-sort]').length,
  };
}
```
Expected: `toolbarExists: true`, `filtersContainsWbw: true`, `filtersContainsChips: true`, `chipsHidden: true`, `sortOptionCount: 4`, `sortOptionValues: ["title-asc","title-desc","price-asc","price-desc"]`, `thDataSortCount: 0`.

- [ ] **Step 6: Commit**

```bash
git add wp-content/plugins/dp-b2b-quick-order/templates/quick-order.php wp-content/plugins/dp-b2b-quick-order/inc/class-frontend.php
git commit -m "feat(qo): add toolbar wrapper, selected-variations placeholder, sort dropdown markup"
```

---

### Task 3: `ProductList` — variation label event payload + `data-variation-label` attribute

**Files:**
- Modify: `wp-content/plugins/dp-b2b-quick-order/assets/src/product-list.js`

**Interfaces:**
- Consumes: existing `v.label` (string) already present on each item from `GET /products/{id}/variations`, and `escHtml()` (existing module-scope helper, unchanged).
- Produces: `dp:qo:rows-rendered` `CustomEvent` now always carries `detail.variationLabels: {rowKey: string, label: string}[]` (empty array on the initial-skeleton dispatch, populated on the post-variation-load dispatch). Consumed by `VariationChipsController` in Task 4.
- Produces: each `.dp-qo-variation-row` now also carries `data-variation-label="<escaped label>"` — a byproduct only (debugging aid), not read by any JS in this plan.

- [ ] **Step 1: Extend the initial-render dispatch (in `loadPage()`)**

In `assets/src/product-list.js`, find (inside `loadPage()`):

```js
        this.#totalPages = data.total_pages ?? 1;
        this.#renderRows(data.products ?? []);
        this.#renderPagination();
        document.dispatchEvent(new CustomEvent('dp:qo:rows-rendered'));
        this.#loadAllVariations();
```

Replace with:

```js
        this.#totalPages = data.total_pages ?? 1;
        this.#renderRows(data.products ?? []);
        this.#renderPagination();
        // Skeleton render: variable-product parent rows exist but their
        // variations (and labels) haven't been fetched yet — empty payload.
        document.dispatchEvent(new CustomEvent('dp:qo:rows-rendered', { detail: { variationLabels: [] } }));
        this.#loadAllVariations();
```

- [ ] **Step 2: Add `label` to `#variationQtyLineHTML()`'s signature and markup**

Find:

```js
    /**
     * One variation's line within the Kol. (qty) column — carries the
     * variation's full dataset (product/variation id, row key, price) on
     * the same `.dp-qo-variation-row` class RowController already resolves
     * via `.closest('.dp-qo-row, .dp-qo-variation-row')`.
     */
    #variationQtyLineHTML({ rowKey, productId, variationId, price, disableQty }) {
        return `
<div class="dp-qo-variation-row dp-qo-variation-line"
     data-product-id="${productId}"
     data-variation-id="${variationId}"
     data-row-key="${rowKey}"
     data-price="${price}">
  ${this.#qtyControlsHTML(rowKey, disableQty)}
</div>`.trim();
    }
```

Replace with:

```js
    /**
     * One variation's line within the Kol. (qty) column — carries the
     * variation's full dataset (product/variation id, row key, price) on
     * the same `.dp-qo-variation-row` class RowController already resolves
     * via `.closest('.dp-qo-row, .dp-qo-variation-row')`. `data-variation-label`
     * is a debugging byproduct only — VariationChipsController's canonical
     * label source is the `dp:qo:rows-rendered` event payload, not this
     * attribute (see #loadVariationOptions below).
     */
    #variationQtyLineHTML({ rowKey, productId, variationId, price, disableQty, label }) {
        return `
<div class="dp-qo-variation-row dp-qo-variation-line"
     data-product-id="${productId}"
     data-variation-id="${variationId}"
     data-row-key="${rowKey}"
     data-price="${price}"
     data-variation-label="${label}">
  ${this.#qtyControlsHTML(rowKey, disableQty)}
</div>`.trim();
    }
```

- [ ] **Step 3: Collect and dispatch `variationLabels` in `#loadVariationOptions()`**

Find:

```js
        const stockLabel  = { instock: 'Na stanju', outofstock: 'Nema na stanju', onbackorder: 'Po narudžbi' };
        const labelLines  = [];
        const stockLines  = [];
        const priceLines  = [];
        const qtyLines    = [];

        variations.forEach(v => {
            const rowKey     = `${productId}_${v.id}`;
            const stockClass = `dp-qo-stock--${escHtml(v.stock_status)}`;
            const stockText  = stockLabel[v.stock_status] ?? v.stock_status;
            const disableQty = v.stock_status === 'outofstock';

            labelLines.push(this.#variationLabelLineHTML(escHtml(v.label), escHtml(v.sku)));
            stockLines.push(this.#variationStockLineHTML(stockClass, stockText));
            priceLines.push(this.#variationPriceLineHTML(v.price_html));
            qtyLines.push(this.#variationQtyLineHTML({
                rowKey, productId: Number(productId), variationId: v.id, price: v.price, disableQty,
            }));
        });

        labelsEl.classList.remove('dp-qo-variation-list--loading');
        labelsEl.innerHTML = labelLines.join('');
        stocksEl.innerHTML = stockLines.join('');
        pricesEl.innerHTML = priceLines.join('');
        qtysEl.innerHTML   = qtyLines.join('');
        document.dispatchEvent(new CustomEvent('dp:qo:rows-rendered'));
    }
```

Replace with:

```js
        const stockLabel     = { instock: 'Na stanju', outofstock: 'Nema na stanju', onbackorder: 'Po narudžbi' };
        const labelLines     = [];
        const stockLines     = [];
        const priceLines     = [];
        const qtyLines       = [];
        const variationLabels = [];

        variations.forEach(v => {
            const rowKey       = `${productId}_${v.id}`;
            const stockClass   = `dp-qo-stock--${escHtml(v.stock_status)}`;
            const stockText    = stockLabel[v.stock_status] ?? v.stock_status;
            const disableQty   = v.stock_status === 'outofstock';
            const escapedLabel = escHtml(v.label);

            labelLines.push(this.#variationLabelLineHTML(escapedLabel, escHtml(v.sku)));
            stockLines.push(this.#variationStockLineHTML(stockClass, stockText));
            priceLines.push(this.#variationPriceLineHTML(v.price_html));
            qtyLines.push(this.#variationQtyLineHTML({
                rowKey, productId: Number(productId), variationId: v.id, price: v.price, disableQty,
                label: escapedLabel,
            }));
            variationLabels.push({ rowKey, label: v.label });
        });

        labelsEl.classList.remove('dp-qo-variation-list--loading');
        labelsEl.innerHTML = labelLines.join('');
        stocksEl.innerHTML = stockLines.join('');
        pricesEl.innerHTML = priceLines.join('');
        qtysEl.innerHTML   = qtyLines.join('');
        document.dispatchEvent(new CustomEvent('dp:qo:rows-rendered', { detail: { variationLabels } }));
    }
```

- [ ] **Step 4: Rebuild and verify the event payload with Playwright**

Run:
```bash
npm run build
```
Expected: exits 0.

Navigate to `http://localhost:8080/dp-b2b/quick-order/` and evaluate:
```js
async () => {
  const captured = [];
  document.addEventListener('dp:qo:rows-rendered', e => captured.push(e.detail?.variationLabels ?? null));
  // Trigger a fresh render cycle so both dispatch sites fire again.
  window.dpQuickOrder.productList.loadPage(1);
  await new Promise(r => setTimeout(r, 1500));
  return captured;
}
```
Expected: an array of at least 2 entries — the first (skeleton render) is `[]`; later entries (one per variable product on the page whose variations resolved) are arrays of `{rowKey, label}` objects, e.g. `{"rowKey":"622_701","label":"Boja: Crna / Veličina: XL"}`. If the current page has zero variable products, this returns only `[]` entries — acceptable, but note it in the task result so Task 4's verification picks a page/product known to have variations.

- [ ] **Step 5: Commit**

```bash
git add wp-content/plugins/dp-b2b-quick-order/assets/src/product-list.js wp-content/plugins/dp-b2b-quick-order/assets/dist/quick-order.js
git commit -m "feat(qo): emit variation labels via dp:qo:rows-rendered event payload"
```

---

### Task 4: `VariationChipsController` — new file, wired into `RowController` and `quick-order.js`

**Files:**
- Create: `wp-content/plugins/dp-b2b-quick-order/assets/src/variation-chips.js`
- Modify: `wp-content/plugins/dp-b2b-quick-order/assets/src/row-controller.js`
- Modify: `wp-content/plugins/dp-b2b-quick-order/assets/src/quick-order.js`

**Interfaces:**
- Consumes: `QuickOrderState.getActiveRowKeys()` (Task 1), `dp:qo:rows-rendered` event's `detail.variationLabels` (Task 3), DOM container `.dp-qo-selected-variations` (Task 2).
- Produces: `class VariationChipsController { constructor(state); render(): void; onRemove(handler: (rowKey: string) => void): void }`. Consumed by `RowController` (calls `.render()`) and `quick-order.js` (wires `.onRemove()` and calls `.render()` after submit).
- Produces: `RowController` constructor becomes `(state, footer, chips)` — the third parameter is required from this task forward.

- [ ] **Step 1: Create `variation-chips.js`**

```js
'use strict';

/**
 * Renders the Quick Order "selected variations" chip row — one chip per
 * variation row with quantity > 0, each removable via an inline × control.
 * Lives beside (never inside) the WBW Product Filter's own selected-filters
 * markup; that subtree is a black box this controller never reads from or
 * writes into. See
 * docs/superpowers/specs/2026-07-13-quick-order-toolbar-chips-design.md §3.
 */
export class VariationChipsController {
    /** @type {import('./quick-order-state.js').QuickOrderState} */
    #state;
    /** @type {HTMLElement|null} */
    #container;
    /** @type {Map<string, string>} rowKey -> variation label, populated from ProductList's render notifications — never from DOM scanning. */
    #labels = new Map();
    /** @type {((rowKey: string) => void)|null} */
    #onRemove = null;

    /**
     * @param {import('./quick-order-state.js').QuickOrderState} state
     */
    constructor(state) {
        this.#state     = state;
        this.#container = document.querySelector('.dp-qo-selected-variations');

        document.addEventListener('dp:qo:rows-rendered', e => {
            for (const { rowKey, label } of e.detail?.variationLabels ?? []) {
                this.#labels.set(rowKey, label);
            }
        });

        this.#container?.addEventListener('click', e => {
            const btn = e.target.closest('.dp-qo-chip__remove');
            if (!btn || !this.#onRemove) return;
            this.#onRemove(btn.dataset.rowKey);
        });
    }

    /**
     * @param {(rowKey: string) => void} handler Called with the row key of a
     *   removed chip. The caller owns zeroing state, re-syncing the real qty
     *   input, and re-rendering the footer — this controller only renders.
     */
    onRemove(handler) {
        this.#onRemove = handler;
    }

    render() {
        if (!this.#container) return;

        const chipsHtml = this.#state.getActiveRowKeys()
            .filter(rowKey => this.#labels.has(rowKey))
            .map(rowKey => this.#chipHTML(rowKey, this.#labels.get(rowKey)))
            .join('');

        this.#container.innerHTML = chipsHtml;
        this.#container.hidden = chipsHtml === '';
    }

    #chipHTML(rowKey, label) {
        const text = formatVariationLabel(label);
        return `
<span class="dp-qo-chip" data-row-key="${escHtml(rowKey)}">
  ${escHtml(text)}
  <button type="button" class="dp-qo-chip__remove" data-row-key="${escHtml(rowKey)}" aria-label="Ukloni ${escHtml(text)}">×</button>
</span>`.trim();
    }
}

/**
 * Presentation-only label formatting — the sole place chip text
 * transformation happens. Future formatting changes touch only this
 * function, never `render()`.
 */
function formatVariationLabel(label) {
    return label.split(' / ').join(' • ');
}

/** Escape HTML for safe insertion into innerHTML — same behavior as product-list.js's module-scope helper. */
function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
}
```

- [ ] **Step 2: Wire `chips` into `RowController`**

In `assets/src/row-controller.js`, find:

```js
export class RowController {
    /** @type {import('./quick-order-state.js').QuickOrderState} */
    #state;
    /** @type {import('./footer-controller.js').FooterController} */
    #footer;
    /** @type {HTMLElement|null} */
    #tbody;

    /**
     * @param {import('./quick-order-state.js').QuickOrderState} state
     * @param {import('./footer-controller.js').FooterController} footer
     */
    constructor(state, footer) {
        this.#state  = state;
        this.#footer = footer;
        this.#tbody  = document.querySelector('.dp-qo-tbody');
        if (!this.#tbody) return;
        this.#bindTableEvents();
    }
```

Replace with:

```js
export class RowController {
    /** @type {import('./quick-order-state.js').QuickOrderState} */
    #state;
    /** @type {import('./footer-controller.js').FooterController} */
    #footer;
    /** @type {import('./variation-chips.js').VariationChipsController} */
    #chips;
    /** @type {HTMLElement|null} */
    #tbody;
    /** @type {WeakMap<HTMLElement, number>} checkmark element -> active fade-out timeout id */
    #checkTimers = new WeakMap();

    /**
     * @param {import('./quick-order-state.js').QuickOrderState} state
     * @param {import('./footer-controller.js').FooterController} footer
     * @param {import('./variation-chips.js').VariationChipsController} chips
     */
    constructor(state, footer, chips) {
        this.#state  = state;
        this.#footer = footer;
        this.#chips  = chips;
        this.#tbody  = document.querySelector('.dp-qo-tbody');
        if (!this.#tbody) return;
        this.#bindTableEvents();
    }
```

- [ ] **Step 3: Call `chips.render()` from `#onQtyInput`**

Find:

```js
        this.#state.setQuantity(rowKey, qty, { productId, variationId, unitPrice });
        this.#footer.render();
    }
```

Replace with:

```js
        this.#state.setQuantity(rowKey, qty, { productId, variationId, unitPrice });
        this.#footer.render();
        this.#chips.render();
    }
```

- [ ] **Step 4: Wire `VariationChipsController` into `quick-order.js`**

In `assets/src/quick-order.js`, find:

```js
import { QuickOrderState }  from './quick-order-state.js';
import { FooterController } from './footer-controller.js';
import { RowController }    from './row-controller.js';
import { ProductList }      from './product-list.js';
import { CartSubmit }       from './cart-submit.js';

(function () {
    const config = window.dpQuickOrder;
    if (!config || !config.cartSyncUrl || !config.wpNonce || !config.productsUrl) return;

    const state       = new QuickOrderState();
    const footer      = new FooterController(state);
    const rowCtrl     = new RowController(state, footer);
    const productList = new ProductList(config);
    const submit      = new CartSubmit(state, config);
```

Replace with:

```js
import { QuickOrderState }        from './quick-order-state.js';
import { FooterController }       from './footer-controller.js';
import { RowController }          from './row-controller.js';
import { ProductList }            from './product-list.js';
import { CartSubmit }             from './cart-submit.js';
import { VariationChipsController } from './variation-chips.js';

(function () {
    const config = window.dpQuickOrder;
    if (!config || !config.cartSyncUrl || !config.wpNonce || !config.productsUrl) return;

    const state  = new QuickOrderState();
    const footer = new FooterController(state);
    const chips  = new VariationChipsController(state);

    // Forward reference: chips' remove-click callback needs rowCtrl.hydrateAll(),
    // but RowController's constructor needs chips — assigned right after
    // construction, before any user interaction can fire the callback.
    let rowCtrl;
    chips.onRemove(rowKey => {
        state.setQuantity(rowKey, 0);
        rowCtrl.hydrateAll();
        footer.render();
        chips.render();
    });

    rowCtrl = new RowController(state, footer, chips);
    const productList = new ProductList(config);
    const submit       = new CartSubmit(state, config);
```

Then find (later in the same file, in the submit success handler):

```js
        state.clearKeys(addedKeys);
        footer.render();
        rowCtrl.hydrateAll();
```

Replace with:

```js
        state.clearKeys(addedKeys);
        footer.render();
        rowCtrl.hydrateAll();
        chips.render();
```

- [ ] **Step 5: Rebuild**

Run:
```bash
npm run build
```
Expected: exits 0, no esbuild errors (confirms the new `variation-chips.js` module resolves and bundles cleanly).

- [ ] **Step 6: Verify end-to-end with Playwright**

Navigate to `http://localhost:8080/dp-b2b/quick-order/`. Find a variable product's first variation row and set its quantity via the UI (click `+` once), then evaluate:

```js
() => {
  const container = document.querySelector('.dp-qo-selected-variations');
  return {
    hidden: container.hidden,
    chipCount: container.querySelectorAll('.dp-qo-chip').length,
    chipText: container.querySelector('.dp-qo-chip')?.textContent.trim(),
  };
}
```
Expected: `hidden: false`, `chipCount: 1`, `chipText` contains the variation's formatted label (e.g. `"Boja: Crna • Veličina: XL ×"` — includes the × button's text as part of `textContent`).

Click the chip's `.dp-qo-chip__remove` button, then evaluate:
```js
() => {
  const container = document.querySelector('.dp-qo-selected-variations');
  const qtyInputs = [...document.querySelectorAll('.dp-qo-qty')].filter(i => i.value !== '0');
  return { hidden: container.hidden, chipCount: container.querySelectorAll('.dp-qo-chip').length, nonZeroQtyInputs: qtyInputs.length };
}
```
Expected: `hidden: true`, `chipCount: 0`, `nonZeroQtyInputs: 0` (confirms the qty input was reset, not just the chip removed).

Also confirm no console errors were logged during this interaction (check the Playwright console log).

- [ ] **Step 7: Commit**

```bash
git add wp-content/plugins/dp-b2b-quick-order/assets/src/variation-chips.js wp-content/plugins/dp-b2b-quick-order/assets/src/row-controller.js wp-content/plugins/dp-b2b-quick-order/assets/src/quick-order.js wp-content/plugins/dp-b2b-quick-order/assets/dist/quick-order.js
git commit -m "feat(qo): add VariationChipsController with remove-to-zero wiring"
```

---

### Task 5: CSS — toolbar layout and chip styling

**Files:**
- Modify: `wp-content/plugins/dp-b2b-quick-order/assets/dist/quick-order.css`

**Interfaces:**
- Consumes: class names from Task 2 (`.dp-qo-toolbar`, `.dp-qo-toolbar__filters`, `.dp-qo-selected-variations`, `.dp-qo-sort`, `.dp-qo-sort-select`) and Task 4 (`.dp-qo-chip`, `.dp-qo-chip__remove`).
- Produces: no new class names — pure styling.

- [ ] **Step 1: Replace the `/* ── WPF filter area ── */` block**

Find (this plugin's CSS is hand-written, no SCSS build step — edit `assets/dist/quick-order.css` directly):

```css
/* ── WPF filter area ─────────────────────────────────────────── */
.dp-qo-filter-area { margin-bottom: 16px; }
```

Replace with:

```css
/* ── WPF filter area ─────────────────────────────────────────── */
.dp-qo-filter-area { margin-bottom: 16px; }

/* ── Toolbar: WBW active-filter chips + Quick Order variation chips
   (left group) + sort dropdown (right group). .selected-prod_atributes is
   WBW's own subtree — styled only via layout on this shared ancestor,
   never read from or written into directly. ── */
.dp-qo-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 16px;
}

.dp-qo-toolbar__filters {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    flex: 1 1 auto;
    min-width: 0;
}

.dp-qo-selected-variations {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}
.dp-qo-selected-variations[hidden] { display: none; }

.dp-qo-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 6px 4px 10px;
    background: #f5f5f5;
    border: 1px solid #e2e2e2;
    border-radius: 14px;
    font-size: 12px;
    line-height: 1.4;
    color: #333;
    white-space: nowrap;
}

.dp-qo-chip__remove {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    padding: 0;
    border: none;
    border-radius: 50%;
    background: transparent;
    color: #888;
    font-size: 14px;
    line-height: 1;
    cursor: pointer;
}
.dp-qo-chip__remove:hover { background: #e2e2e2; color: #333; }

.dp-qo-sort {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    font-size: 13px;
    color: #444;
}

.dp-qo-sort-select {
    padding: 5px 10px;
    border: 1px solid #ccc;
    border-radius: 3px;
    background: #fff;
    font-size: 13px;
    cursor: pointer;
}
```

- [ ] **Step 2: Add a focus-visible rule for the new interactive elements**

Find (near the end of the file):

```css
.dp-qo-qty:focus-visible,
.dp-qo-qty-btn:focus-visible,
.dp-qo-footer__add-to-cart:focus-visible,
.dp-qo-footer__cart-link:focus-visible,
.dp-qo-btn:focus-visible,
.dp-qo-table th[data-sort]:focus-visible {
    outline: 2px solid #025788 !important;
    outline-offset: 2px;
}
```

Replace with:

```css
.dp-qo-qty:focus-visible,
.dp-qo-qty-btn:focus-visible,
.dp-qo-footer__add-to-cart:focus-visible,
.dp-qo-footer__cart-link:focus-visible,
.dp-qo-btn:focus-visible,
.dp-qo-chip__remove:focus-visible,
.dp-qo-sort-select:focus-visible {
    outline: 2px solid #025788 !important;
    outline-offset: 2px;
}
```

(The `.dp-qo-table th[data-sort]` selector is removed — that markup no longer exists after Task 2.)

- [ ] **Step 3: Verify visually with Playwright**

Navigate to `http://localhost:8080/dp-b2b/quick-order/`, set a variation's quantity so a chip renders, then take a screenshot and confirm no layout overflow:

```js
() => {
  const toolbar = document.querySelector('.dp-qo-toolbar');
  const sort    = document.querySelector('.dp-qo-sort');
  const rect    = toolbar.getBoundingClientRect();
  const sortRect = sort.getBoundingClientRect();
  return {
    toolbarOverflowsViewport: rect.right > window.innerWidth,
    sortIsRightAligned: Math.abs(sortRect.right - rect.right) < 5,
  };
}
```
Expected: `toolbarOverflowsViewport: false`, `sortIsRightAligned: true` (sort dropdown hugs the toolbar's right edge on a normal-width viewport).

- [ ] **Step 4: Commit**

```bash
git add wp-content/plugins/dp-b2b-quick-order/assets/dist/quick-order.css
git commit -m "style(qo): add toolbar, chip, and sort dropdown styles"
```

---

### Task 6: Sort dropdown — replace click-to-sort in `product-list.js`

**Files:**
- Modify: `wp-content/plugins/dp-b2b-quick-order/assets/src/product-list.js`

**Interfaces:**
- Consumes: `.dp-qo-sort-select` (Task 2), existing private `#orderBy`/`#orderDir` fields and `loadPage()` method (unchanged).
- Produces: none new — this removes `#bindSortHeaders()`/`#updateSortIndicators()` and their constructor calls, replacing with `#bindSortDropdown()`.

- [ ] **Step 1: Replace the constructor's sort-binding calls**

Find:

```js
    constructor(config) {
        this.#config       = config;
        this.#tbody        = document.querySelector('.dp-qo-tbody');
        this.#paginationEl = document.querySelector('.dp-qo-pagination');
        this.#bindSortHeaders();
        this.#updateSortIndicators();
        this.#bindWoofIntegration();
    }
```

Replace with:

```js
    constructor(config) {
        this.#config       = config;
        this.#tbody        = document.querySelector('.dp-qo-tbody');
        this.#paginationEl = document.querySelector('.dp-qo-pagination');
        this.#bindSortDropdown();
        this.#bindWoofIntegration();
    }
```

- [ ] **Step 2: Replace `#bindSortHeaders()`/`#updateSortIndicators()` with `#bindSortDropdown()`**

Find:

```js
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
```

Replace with:

```js
    /**
     * Sort UI is a single <select> whose option values are "field-direction"
     * pairs (e.g. "price-desc", "title-asc") — generic parsing here, no
     * knowledge of the option list's actual contents. The option list itself
     * lives server-side in dp_qo_get_sort_options() (inc/class-frontend.php),
     * looped by templates/quick-order.php.
     */
    #bindSortDropdown() {
        const select = document.querySelector('.dp-qo-sort-select');
        if (!select) return;
        select.addEventListener('change', () => {
            const [orderBy, orderDir] = select.value.split('-');
            if (!orderBy || !orderDir) return;
            this.#orderBy  = orderBy;
            this.#orderDir = orderDir;
            this.loadPage(1);
        });
    }
```

- [ ] **Step 3: Rebuild**

Run:
```bash
npm run build
```
Expected: exits 0.

- [ ] **Step 4: Verify with Playwright — dropdown drives REST params and re-sorts**

Navigate to `http://localhost:8080/dp-b2b/quick-order/`. Start network request capture, then select `"Cijena: viša prema nižoj"` from `.dp-qo-sort-select` (this fires a native `change` event):

```js
() => {
  const select = document.querySelector('.dp-qo-sort-select');
  select.value = 'price-desc';
  select.dispatchEvent(new Event('change', { bubbles: true }));
}
```

Then check the most recent outgoing request to the products endpoint (via the browser's network requests list) and confirm its query string contains `qo_orderby=price&qo_order=desc` (or `qo_order=desc&qo_orderby=price` — order-independent). Also confirm the table re-rendered (no leftover `.dp-qo-loading` row) and no console errors were logged.

- [ ] **Step 5: Commit**

```bash
git add wp-content/plugins/dp-b2b-quick-order/assets/src/product-list.js wp-content/plugins/dp-b2b-quick-order/assets/dist/quick-order.js
git commit -m "feat(qo): drive sort via dropdown instead of click-to-sort headers"
```

---

### Task 7: Quantity change checkmark

**Files:**
- Modify: `wp-content/plugins/dp-b2b-quick-order/assets/src/product-list.js`
- Modify: `wp-content/plugins/dp-b2b-quick-order/assets/src/row-controller.js`
- Modify: `wp-content/plugins/dp-b2b-quick-order/assets/dist/quick-order.css`

**Interfaces:**
- Consumes: `RowController.#checkTimers` (declared in Task 4 Step 2) and `#onQtyInput()`'s existing `qty`/`row` locals.
- Produces: `.dp-qo-qty-check` element present in every `.dp-qo-qty-wrap` (simple and variation rows alike, since both share `#qtyControlsHTML()`).

- [ ] **Step 1: Add the checkmark span to `#qtyControlsHTML()`**

In `assets/src/product-list.js`, find:

```js
    /** Shared qty +/- controls markup, used by both simple-product/data rows and variation rows. */
    #qtyControlsHTML(rowKey, disableQty) {
        return `
<div class="dp-qo-qty-wrap">
  <button class="dp-qo-qty-btn dp-qo-qty-minus" type="button" aria-label="Smanji količinu"${disableQty ? ' disabled' : ''}>−</button>
  <input type="number"
         class="dp-qo-qty"
         data-row-key="${rowKey}"
         value="0" min="0" step="1"
         ${disableQty ? 'disabled' : ''}>
  <button class="dp-qo-qty-btn dp-qo-qty-plus" type="button" aria-label="Povećaj količinu"${disableQty ? ' disabled' : ''}>+</button>
</div>`.trim();
    }
```

Replace with:

```js
    /** Shared qty +/- controls markup, used by both simple-product/data rows and variation rows. */
    #qtyControlsHTML(rowKey, disableQty) {
        return `
<div class="dp-qo-qty-wrap">
  <button class="dp-qo-qty-btn dp-qo-qty-minus" type="button" aria-label="Smanji količinu"${disableQty ? ' disabled' : ''}>−</button>
  <input type="number"
         class="dp-qo-qty"
         data-row-key="${rowKey}"
         value="0" min="0" step="1"
         ${disableQty ? 'disabled' : ''}>
  <button class="dp-qo-qty-btn dp-qo-qty-plus" type="button" aria-label="Povećaj količinu"${disableQty ? ' disabled' : ''}>+</button>
  <span class="dp-qo-qty-check" aria-hidden="true">✓</span>
</div>`.trim();
    }
```

- [ ] **Step 2: Add `#flashCheck()` to `RowController` and call it from `#onQtyInput`**

In `assets/src/row-controller.js`, find (the end of `#onQtyInput`, already modified in Task 4 Step 3):

```js
        this.#state.setQuantity(rowKey, qty, { productId, variationId, unitPrice });
        this.#footer.render();
        this.#chips.render();
    }
```

Replace with:

```js
        this.#state.setQuantity(rowKey, qty, { productId, variationId, unitPrice });
        this.#footer.render();
        this.#chips.render();
        this.#flashCheck(row, qty);
    }

    /**
     * Flash the row's checkmark on a successful quantity change. Restarts
     * the fade-out timer on rapid repeated changes instead of stacking
     * animations or timers — the WeakMap lookup always clears any existing
     * timeout for this element before scheduling a new one, so at most one
     * timeout is ever active per checkmark. Keying by element (not row key)
     * also means the timer is released automatically once the element
     * leaves the DOM (pagination/sort/re-render) — no manual cleanup needed.
     * Hides immediately, no animation, if quantity returns to 0.
     * @param {HTMLElement} row
     * @param {number} qty
     */
    #flashCheck(row, qty) {
        const check = row.querySelector('.dp-qo-qty-check');
        if (!check) return;

        const existingTimer = this.#checkTimers.get(check);
        if (existingTimer) clearTimeout(existingTimer);

        if (qty <= 0) {
            check.classList.remove('is-visible');
            this.#checkTimers.delete(check);
            return;
        }

        check.classList.add('is-visible');
        const timer = setTimeout(() => {
            check.classList.remove('is-visible');
            this.#checkTimers.delete(check);
        }, 1000);
        this.#checkTimers.set(check, timer);
    }
```

- [ ] **Step 3: Add checkmark CSS**

In `assets/dist/quick-order.css`, find:

```css
/* ── Qty +/- wrap ─────────────────────────────────────────────── */
.dp-qo-qty-wrap { display: inline-flex; align-items: center; gap: 3px; }
```

Replace with:

```css
/* ── Qty +/- wrap ─────────────────────────────────────────────── */
.dp-qo-qty-wrap { display: inline-flex; align-items: center; gap: 3px; }

/* ── Quantity change checkmark ───────────────────────────────────
   Space is always reserved (fixed width, opacity/transform-only
   transition) so its appearance/disappearance never shifts layout. ── */
.dp-qo-qty-check {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    flex-shrink: 0;
    color: #2e7d32;
    font-size: 14px;
    line-height: 1;
    opacity: 0;
    transform: scale(0.8);
    transition: opacity 0.15s ease, transform 0.15s ease;
    pointer-events: none;
}
.dp-qo-qty-check.is-visible { opacity: 1; transform: scale(1); }
```

- [ ] **Step 4: Rebuild**

Run:
```bash
npm run build
```
Expected: exits 0.

- [ ] **Step 5: Verify with Playwright — no stacking, restart-on-repeat, immediate hide at zero**

Navigate to `http://localhost:8080/dp-b2b/quick-order/`. Pick any row's `+` button, click it 3 times rapidly, then evaluate:

```js
() => {
  const btn = document.querySelector('.dp-qo-row .dp-qo-qty-plus');
  const check = btn.closest('.dp-qo-qty-wrap').querySelector('.dp-qo-qty-check');
  return { isVisible: check.classList.contains('is-visible') };
}
```
Expected: `isVisible: true` (single, current animation — not verifiable via DOM alone that timers didn't stack, but functionally: clicking 3× rapidly must not throw and must leave exactly one `.dp-qo-qty-check` element in the `is-visible` state, not multiple checkmarks).

Wait 1.2 seconds (`await new Promise(r => setTimeout(r, 1200))`), then re-check: expected `isVisible: false` (faded out on its own).

Click `+` once more, then immediately click `−` three times to return qty to 0, then evaluate immediately (no wait):
```js
() => {
  const btn = document.querySelector('.dp-qo-row .dp-qo-qty-minus');
  const check = btn.closest('.dp-qo-qty-wrap').querySelector('.dp-qo-qty-check');
  return { isVisible: check.classList.contains('is-visible'), qty: btn.closest('.dp-qo-qty-wrap').querySelector('.dp-qo-qty').value };
}
```
Expected: `isVisible: false`, `qty: "0"` (checkmark hidden immediately, no fade, when quantity returns to 0).

Confirm no console errors throughout.

- [ ] **Step 6: Commit**

```bash
git add wp-content/plugins/dp-b2b-quick-order/assets/src/product-list.js wp-content/plugins/dp-b2b-quick-order/assets/src/row-controller.js wp-content/plugins/dp-b2b-quick-order/assets/dist/quick-order.css wp-content/plugins/dp-b2b-quick-order/assets/dist/quick-order.js
git commit -m "feat(qo): add quantity change checkmark with restart-not-stack timing"
```

---

### Task 8: Full regression pass

**Files:** none (verification only)

**Interfaces:** none — this task exercises the whole plugin end-to-end.

- [ ] **Step 1: Rebuild once more from a clean state**

Run (from the plugin root):
```bash
npm run build
```
Expected: exits 0.

- [ ] **Step 2: Playwright regression checklist**

Navigate to `http://localhost:8080/dp-b2b/quick-order/` logged in as admin and verify each item, noting pass/fail:

1. Page loads with no console errors.
2. `.dp-qo-toolbar` renders with WBW's `.selected-prod_atributes` on the left and `.dp-qo-sort` on the right.
3. Apply a WBW filter (e.g. a brand checkbox + "Filter") — confirm WBW's own chips still render and its "Clear All" still works, completely unaffected by this work.
4. Set quantity > 0 on a variation row — chip appears in `.dp-qo-selected-variations`, positioned after WBW's chips in the same wrapped row.
5. Set quantity > 0 on a **simple** product row — confirm no chip appears for it (chips are variation-only).
6. Change the sort dropdown — table re-sorts, no console errors, `.dp-qo-table th` no longer clickable/no sort arrows present.
7. Click a chip's × — quantity resets to 0 in the table, chip disappears, footer subtotal/count update correctly.
8. Rapid-click a qty `+` button 5× — checkmark does not stack, fades once, no orphaned visible checkmarks after 2 seconds.
9. Set quantities on 2+ rows across 2 different pages (use pagination), confirm both chips are visible simultaneously and the footer's item/row counts include both.
10. Click "Dodaj u košaricu" with at least one item selected — confirm the bulk add-to-cart still completes successfully (existing Toastify/mini-cart behavior fires), and afterward the chip row is empty (`hidden`).

Report any failing item with the exact reproduction step before proceeding — do not mark this task complete with a failing checklist item.

- [ ] **Step 3: No commit for this task** (verification only, nothing to stage).

---

### Task 9: Frozen doc revision note

**Files:**
- Modify: `docs/frozen/quick-order-local-state-architecture.md` §8

**Interfaces:** none — documentation only.

- [ ] **Step 1: Add a revision note to the file header**

Find (in `docs/frozen/quick-order-local-state-architecture.md`):

```markdown
**Revision — 2026-07-10 (real-column correction):** the grouped layout's
first implementation collapsed a variable row into a single
`<td colspan="5">` housing a flex-simulated `.dp-qo-row__product` /
`.dp-qo-row__variations` layout — this broke native table column alignment
with the rest of the table and was corrected before production use. §6 below
now describes the corrected, staging-validated model: a variable row keeps
the table's real `<td>` columns (no `colspan`), with per-variation content
stacked vertically inside the existing Naziv/Stanje/Cijena/Kol. cells.
`.dp-qo-row__product` and `.dp-qo-row__variations` no longer exist. As with
the prior revision, this is a documentation/markup correction only — §2–§5
are unaffected.

---
```

Replace with:

```markdown
**Revision — 2026-07-10 (real-column correction):** the grouped layout's
first implementation collapsed a variable row into a single
`<td colspan="5">` housing a flex-simulated `.dp-qo-row__product` /
`.dp-qo-row__variations` layout — this broke native table column alignment
with the rest of the table and was corrected before production use. §6 below
now describes the corrected, staging-validated model: a variable row keeps
the table's real `<td>` columns (no `colspan`), with per-variation content
stacked vertically inside the existing Naziv/Stanje/Cijena/Kol. cells.
`.dp-qo-row__product` and `.dp-qo-row__variations` no longer exist. As with
the prior revision, this is a documentation/markup correction only — §2–§5
are unaffected.

**Revision — 2026-07-13 (sort UI):** §8's disposition table originally
described `ProductList`'s pagination/sort/WOOF logic as unchanged from the
superseded architecture. Sort is no longer click-to-sort table headers — it
is now a `<select>` dropdown (`dp_qo_get_sort_options()` in
`inc/class-frontend.php`, bound in `product-list.js`'s `#bindSortDropdown()`)
driving the same `qo_orderby`/`qo_order` REST params. This was an explicit,
approved deviation, not a silent drift — the underlying REST contract,
`#orderBy`/`#orderDir` state, and WOOF filter-bridge integration are
unaffected. See
`docs/superpowers/specs/2026-07-13-quick-order-toolbar-chips-design.md`.

---
```

- [ ] **Step 2: Commit**

```bash
git add docs/frozen/quick-order-local-state-architecture.md
git commit -m "docs(qo): note sort-UI deviation in frozen architecture §8"
```

---

## Self-Review Notes

- **Spec coverage:** every architecture decision (1–5) and every "Files touched" entry in
  `docs/superpowers/specs/2026-07-13-quick-order-toolbar-chips-design.md` maps to a task
  above (state getter → Task 1; toolbar/sort markup + helper → Task 2; label event →
  Task 3; chips controller + wiring → Task 4; CSS → Task 5; sort binding → Task 6;
  checkmark → Task 7; frozen-doc note → Task 9). Task 8 covers end-to-end verification
  the individual tasks can't fully exercise alone (multi-page chip persistence, submit
  interaction with WBW untouched).
- **Placeholder scan:** no TBD/TODO; every step has complete, copy-pasteable code or an
  exact command with an exact expected result.
- **Type/name consistency:** `VariationChipsController(state)` constructor, `.render()`,
  `.onRemove(handler)` are used identically across Tasks 4, 6 references, and the plan's
  Interfaces blocks. `RowController(state, footer, chips)` 3-arg constructor is
  introduced in Task 4 and never referenced with the old 2-arg shape afterward.
  `getActiveRowKeys()` (Task 1) is the exact name used in Task 4's `VariationChipsController`.
  `dp_qo_get_sort_options()` (Task 2) is the exact name referenced in Task 6's comment.

# Quick Order — Local State Workspace Transformation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform Quick Order from a cart-driven interface (every quantity change writes to the WooCommerce cart in real time) into an independent local-state ordering workspace where the WC cart is written to once, on explicit "Dodaj u košaricu" submit.

**Architecture:** Full design rationale, reuse/replace breakdown, and locked decisions: `docs/frozen/quick-order-local-state-architecture.md`. This plan implements that document. Do not deviate from it without updating it first.

**Tech Stack:** PHP 8.3+, vanilla JS ES2022 (IIFE bundle via esbuild), WooCommerce REST, WordPress filters, Sass (theme side).

## Global Constraints

- No automated test framework exists for this codebase (no PHPUnit, no Jest/Vitest) — every task's verification step is a manual browser check, not an automated test. This is a deliberate deviation from the writing-plans skill's default TDD template, matching this project's established convention (`CLAUDE.md` → "For functional testing: use browser / Playwright only").
- Text domain for plugin strings: `dp-b2b-quick-order`. Text domain for theme strings: `dreampoint-b2b`.
- Row key format is locked: `"${productId}_${variationId}"`, `variationId=0` for simple products — same format the reused `/cart/sync` endpoint expects.
- `CART_SYNC_MAX_BATCH = 50` (server-side, `inc/class-config.php`) is unchanged — the frontend must chunk bulk submits over 50 rows, not raise the server cap.
- No new npm/composer dependencies.
- No `localStorage`/`sessionStorage` — local state is memory-only, discarded on refresh/navigation by design.
- Reuse existing REST routes and PHP validation (`/products`, `/products/{id}/variations`, `/cart/sync`) unchanged — only frontend calling patterns change.
- All new/changed JS labels come from `wp_localize_script`'s `i18n` object — never hardcoded strings in JS (existing project rule).
- Windows/XAMPP environment — use PowerShell for filesystem mutations (file delete/rename), Bash for git operations. See `~/.claude/rules/windows-shell.md`.

---

## File Map

### Plugin — `wp-content/plugins/dp-b2b-quick-order/`

| File | Change |
|------|--------|
| `inc/class-assets.php` | Modify — add `currency`, `cartSyncMaxBatch`, populated `i18n` to localized config |
| `assets/src/quick-order-state.js` | **New** — local ephemeral state model |
| `assets/src/footer-controller.js` | **New** — renders footer from local state |
| `assets/src/row-controller.js` | **New** — replaces `row-sync.js`; writes qty changes to local state, no network calls |
| `assets/src/cart-submit.js` | **New** — replaces `cart-sync.js`; chunked bulk submit to `/cart/sync` |
| `assets/src/product-list.js` | Modify — row rendering: remove product links, reworded SKU label, variations render as independent rows (no `<select>`), row hydration from state |
| `assets/src/quick-order.js` | Modify — entry point rewiring for the new modules |
| `assets/src/row-sync.js` | **Delete** — superseded by `row-controller.js` |
| `assets/src/cart-sync.js` | **Delete** — superseded by `cart-submit.js` |
| `assets/src/sync-queue.js` | **Delete** — superseded by `quick-order-state.js` |
| `templates/quick-order.php` | Modify — table header (drop Varijacija/status columns), footer markup (count/rows/subtotal + two buttons) |
| `assets/dist/quick-order.css` | Modify — remove dropdown/status-icon/row-state styles, add footer + variation-row styles |
| `assets/dist/quick-order.js` | Rebuilt via `npm run build` |

### Theme — `wp-content/themes/dreampoint-b2b/`

| File | Change |
|------|--------|
| `inc/template-tags.php` | Modify — add `dreampoint_b2b_is_quick_order_page()` helper |
| `header.php` | Modify — conditional minimal Quick Order header branch |
| `footer.php` | Modify — extend existing newsletter/footer-hide condition to include the Quick Order page |
| `sass/pages/quick-order.scss` | **New** — minimal header + fixed footer layout styles |
| `sass/style.scss` | Modify — import the new partial |
| `style.css` | Rebuilt via `npm run build:css` |

---

## Task 1 — Theme Helper: `dreampoint_b2b_is_quick_order_page()`

**Files:**
- Modify: `inc/template-tags.php`

**Interfaces:**
- Produces: `dreampoint_b2b_is_quick_order_page(): bool` — used by Tasks 2, 3, 11.

- [ ] **Add the helper function.** Append to `inc/template-tags.php`:

```php
/**
 * True on the Quick Order page. Guards against the plugin being inactive —
 * DP_Quick_Order_Config may not exist if dp-b2b-quick-order is deactivated.
 */
function dreampoint_b2b_is_quick_order_page(): bool {
	return class_exists( 'DP_Quick_Order_Config' )
		&& is_page( DP_Quick_Order_Config::PAGE_SLUG );
}
```

- [ ] **Verify:** visit any normal page locally, confirm no PHP notice/fatal (function is simply unused there). No visual change yet.

- [ ] **Commit:**

```bash
git add wp-content/themes/dreampoint-b2b/inc/template-tags.php
git commit -m "feat: add Quick Order page detection helper"
```

---

## Task 2 — Theme: Minimal Quick Order Header

**Files:**
- Modify: `header.php`

**Interfaces:**
- Consumes: `dreampoint_b2b_is_quick_order_page()` (Task 1)
- Consumes: `dreampoint_b2b_woocommerce_cart_link()` (existing, `inc/woocommerce.php`) — reused for the cart icon

The existing header is wrapped in one large conditional starting at line 41
(`if ((!is_account_page() || is_user_logged_in())) :`) and ending after the
inner-heading block (just before `footer.php`'s closing `</div><!-- /.inner-page -->`).
`footer.php` unconditionally closes `.inner-page` and `#page` — so whichever
header branch renders, both wrapper divs must still open exactly once.

- [ ] **Branch the header block.** Replace the single condition at the top of
  the header block with an `if/else`: Quick Order gets a minimal header: same
  `#page` structure, no notifications, no full `<header>`, no inner-heading/
  breadcrumbs. Both branches must open `<div class="inner-page" id="primary-content">`
  so `footer.php` closes it correctly.

  In `header.php`, replace:

```php
    <?php 
        
        /**
         * Main Header
         * Shows on all pages except login/register and entrance page
         */
        if ((!is_account_page() || is_user_logged_in())) : 
    ?>
```

  with:

```php
    <?php
        $is_quick_order = dreampoint_b2b_is_quick_order_page();
    ?>
    <?php if ( $is_quick_order ) : ?>
        <header id="header" class="dp-qo-header" role="banner">
            <div class="dp-qo-header__inner">
                <div class="dp-qo-header__left">
                    <?php if ( ! empty( $company_logo['url'] ) ) : ?>
                        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="dp-qo-header__logo" aria-label="<?php esc_attr_e( 'Početna stranica', 'dreampoint-b2b' ); ?>">
                            <img
                                src="<?php echo esc_url( $company_logo['url'] ); ?>"
                                alt="<?php echo esc_attr( $company_logo['alt'] ?: get_bloginfo( 'name' ) . ' Logo' ); ?>"
                                width="<?php echo isset( $company_logo['width'] ) ? absint( $company_logo['width'] ) : ''; ?>"
                                height="<?php echo isset( $company_logo['height'] ) ? absint( $company_logo['height'] ) : ''; ?>"
                            >
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="dp-qo-header__back">
                        <i class="icon-arrow-left-long" aria-hidden="true"></i>
                        <?php esc_html_e( 'Povratak u katalog', 'dreampoint-b2b' ); ?>
                    </a>
                </div>
                <div class="dp-qo-header__center">
                    <?php esc_html_e( 'QUICK ORDER', 'dreampoint-b2b' ); ?>
                </div>
                <div class="dp-qo-header__right">
                    <?php
                    if ( function_exists( 'dreampoint_b2b_woocommerce_cart_link' ) ) {
                        dreampoint_b2b_woocommerce_cart_link();
                    }
                    ?>
                </div>
            </div>
        </header>
        <!-- Mini Cart (reused — cart icon above opens it, same as the main header) -->
        <div class="my-custom-mini-cart-container widget_shopping_cart_content" role="complementary" aria-label="<?php esc_attr_e( 'košarica', 'dreampoint-b2b' ); ?>">
            <?php
            if ( function_exists( 'woocommerce_mini_cart' ) ) {
                woocommerce_mini_cart();
            }
            ?>
        </div>
        <div class="inner-page inner-page--quick-order" id="primary-content">
    <?php elseif ( ! is_account_page() || is_user_logged_in() ) : ?>
```

  Then, at the end of the existing conditional block (currently the single
  `<?php endif; ?>` right before `footer.php` takes over — the last line of
  the file), add the matching close:

```php
    <?php endif; ?>
```

  (This second `endif` closes the new `if/elseif` — the file already ends
  with one `endif` for the old single condition; that one becomes the `elseif`
  branch's closer, and this task adds the wrapping `if`'s. Read the full
  updated file after editing to confirm exactly one `if`/`elseif` pair with
  one matching `endif`, not two independent conditionals — a mismatched
  count here breaks every page on the site, not just Quick Order.)

- [ ] **Verify — non-Quick-Order pages unaffected:** visit the homepage and a
  product category page locally. Full header (logo, nav, search, mini-cart,
  breadcrumbs) must render exactly as before.

- [ ] **Verify — Quick Order page:** visit `/quick-order/` (or whatever the
  local Quick Order page slug resolves to). Confirm: logo + "Povratak u
  katalog" on the left, "QUICK ORDER" centered, cart icon on the right. No
  main nav, no account menu, no mega menu, no breadcrumbs/page title block
  above the table.

- [ ] **Commit:**

```bash
git add wp-content/themes/dreampoint-b2b/header.php
git commit -m "feat: add minimal conditional header for Quick Order page"
```

---

## Task 3 — Theme: Hide Global Footer on Quick Order Page

**Files:**
- Modify: `footer.php`

`footer.php` already hides the newsletter block on cart/checkout/account
pages (`if (!is_cart() && !is_checkout() && !is_account_page())`). The Quick
Order page has its own fixed summary footer (Task 9) — the full site footer
(newsletter, sitemap columns, social, copyright) rendering underneath it would
contradict "Quick Order should keep user focused" (brief §5) and the fixed
footer's own visual claim to the bottom of the viewport.

**This is an inferred decision, not explicitly stated in the brief — flag it
during manual verification (last step below) and confirm with the project
owner if the visual result looks wrong; the fix is a one-line condition
change either way.**

- [ ] **Extend the hide condition.** In `footer.php`, replace:

```php
if (!is_cart() && !is_checkout() && !is_account_page()) :
```

  with:

```php
if (!is_cart() && !is_checkout() && !is_account_page() && !dreampoint_b2b_is_quick_order_page()) :
```

  This hides only the newsletter block. The `<footer class="page-footer">`
  block after it is NOT gated by this condition today (it always renders) —
  leave that alone for this task; whether the full sitemap footer should also
  be suppressed on Quick Order is exactly the open question flagged above.

- [ ] **Verify:** visit `/quick-order/` locally — newsletter block must not
  appear. Visit a normal page — newsletter block still appears as before.
  Scroll to the bottom of the Quick Order page and note (for the manual
  testing checklist, Task 15) whether the full site footer below the fixed
  Quick Order footer looks acceptable or needs the same treatment.

- [ ] **Commit:**

```bash
git add wp-content/themes/dreampoint-b2b/footer.php
git commit -m "fix: hide newsletter block on Quick Order page"
```

---

## Task 4 — Plugin PHP: Extend Localized Config

**Files:**
- Modify: `inc/class-assets.php`

**Interfaces:**
- Produces: `window.dpQuickOrder.currency`, `window.dpQuickOrder.cartSyncMaxBatch`, populated `window.dpQuickOrder.i18n` — consumed by Tasks 6, 7, 10, 11.

- [ ] **Add currency, batch cap, and i18n strings.** In `inc/class-assets.php`,
  replace the `wp_localize_script()` call:

```php
		wp_localize_script( 'dp-quick-order', 'dpQuickOrder', [
			'restUrl'        => esc_url_raw( rest_url( DP_Quick_Order_Config::REST_NAMESPACE . '/' ) ),
			'cartSyncUrl'    => esc_url_raw( rest_url(
				DP_Quick_Order_Config::REST_NAMESPACE . '/' .
				DP_Quick_Order_Config::REST_BASE . '/cart/sync'
			) ),
			'productsUrl'    => esc_url_raw( rest_url(
				DP_Quick_Order_Config::REST_NAMESPACE . '/' .
				DP_Quick_Order_Config::REST_BASE . '/products'
			) ),
			'storeUrl'       => esc_url_raw( rest_url( 'wc/store/v1/' ) ),
			'nonce'          => wp_create_nonce( DP_Quick_Order_Config::NONCE_ACTION ),
			'wpNonce'        => wp_create_nonce( 'wp_rest' ),
			'debounceMs'     => DP_Quick_Order_Config::CART_SYNC_DEBOUNCE_MS,
			'timeoutMs'      => DP_Quick_Order_Config::CART_SYNC_TIMEOUT_MS,
			'placeholderImg' => esc_url( wc_placeholder_img_src() ),
			'i18n'           => [],
		] );
```

  with:

```php
		wp_localize_script( 'dp-quick-order', 'dpQuickOrder', [
			'restUrl'          => esc_url_raw( rest_url( DP_Quick_Order_Config::REST_NAMESPACE . '/' ) ),
			'cartSyncUrl'      => esc_url_raw( rest_url(
				DP_Quick_Order_Config::REST_NAMESPACE . '/' .
				DP_Quick_Order_Config::REST_BASE . '/cart/sync'
			) ),
			'productsUrl'      => esc_url_raw( rest_url(
				DP_Quick_Order_Config::REST_NAMESPACE . '/' .
				DP_Quick_Order_Config::REST_BASE . '/products'
			) ),
			'storeUrl'         => esc_url_raw( rest_url( 'wc/store/v1/' ) ),
			'nonce'            => wp_create_nonce( DP_Quick_Order_Config::NONCE_ACTION ),
			'wpNonce'          => wp_create_nonce( 'wp_rest' ),
			'timeoutMs'        => DP_Quick_Order_Config::CART_SYNC_TIMEOUT_MS,
			'cartSyncMaxBatch' => DP_Quick_Order_Config::CART_SYNC_MAX_BATCH,
			'currency'         => get_woocommerce_currency(),
			'placeholderImg'   => esc_url( wc_placeholder_img_src() ),
			'i18n'             => [
				'skuLabel'          => __( 'Kataloški broj:', 'dp-b2b-quick-order' ),
				'itemsSuffix'       => __( 'artikala', 'dp-b2b-quick-order' ),
				'rowsSuffix'        => __( 'varijacija', 'dp-b2b-quick-order' ),
				'loadingVariations' => __( 'Učitavanje varijacija...', 'dp-b2b-quick-order' ),
				'variationLoadError' => __( 'Greška pri učitavanju varijacija.', 'dp-b2b-quick-order' ),
				'adding'            => __( 'Dodavanje...', 'dp-b2b-quick-order' ),
				'partialFailure'    => __( 'Neki artikli nisu dodani u košaricu — provjerite stanje na skladištu.', 'dp-b2b-quick-order' ),
			],
		] );
```

  (`debounceMs` is removed — nothing debounces anymore; `CART_SYNC_DEBOUNCE_MS`
  in `class-config.php` becomes unused by JS but is left in the PHP config
  class since `class-cart-sync.php`'s doc comments still reference the
  historical debounce model — no PHP constant removal in this task, out of
  scope.)

- [ ] **Verify:** visit `/quick-order/` locally, open browser console, run
  `window.dpQuickOrder` — confirm `currency`, `cartSyncMaxBatch`, and the new
  `i18n` keys are present with correct values (e.g. `currency: "EUR"` or
  whatever the store currency is).

- [ ] **Commit:**

```bash
git add wp-content/plugins/dp-b2b-quick-order/inc/class-assets.php
git commit -m "feat: add currency, batch cap, and i18n strings to Quick Order config"
```

---

## Task 5 — Plugin JS: Local State Model

**Files:**
- Create: `assets/src/quick-order-state.js`

**Interfaces:**
- Produces: `class QuickOrderState` with `setQuantity(rowKey, quantity, meta)`, `getQuantity(rowKey): number`, `getItemCount(): number`, `getRowCount(): number`, `getSubtotal(): number`, `toItems(): {product_id, variation_id, quantity}[]`, `clearKeys(rowKeys: string[])`, `clear()`, `isEmpty(): boolean` — consumed by Tasks 6, 7, 8, 10.

- [ ] **Create the file:**

```javascript
'use strict';

/**
 * Local, ephemeral Quick Order state.
 * Never touches the WooCommerce cart, never persisted (no localStorage/sessionStorage).
 * Row key format: "${productId}_${variationId}" (variationId=0 for simple products) —
 * same format used throughout Quick Order and the reused /cart/sync endpoint.
 */
export class QuickOrderState {
    /** @type {Map<string, {productId:number, variationId:number, quantity:number, unitPrice:number}>} */
    #rows = new Map();

    /**
     * Set or clear a row's quantity. quantity<=0 removes the row.
     * @param {string} rowKey
     * @param {number} quantity
     * @param {{productId:number, variationId:number, unitPrice:number}} meta
     */
    setQuantity(rowKey, quantity, meta) {
        if (quantity <= 0) {
            this.#rows.delete(rowKey);
            return;
        }
        this.#rows.set(rowKey, { productId: meta.productId, variationId: meta.variationId, unitPrice: meta.unitPrice, quantity });
    }

    /** @returns {number} */
    getQuantity(rowKey) {
        return this.#rows.get(rowKey)?.quantity ?? 0;
    }

    /** @returns {number} sum of all quantities — footer "N artikala" */
    getItemCount() {
        let total = 0;
        for (const row of this.#rows.values()) total += row.quantity;
        return total;
    }

    /** @returns {number} count of distinct rows with quantity > 0 — footer "N varijacija" */
    getRowCount() {
        return this.#rows.size;
    }

    /** @returns {number} sum(quantity * unitPrice) — product-only, no VAT/shipping/coupons */
    getSubtotal() {
        let total = 0;
        for (const row of this.#rows.values()) total += row.quantity * row.unitPrice;
        return total;
    }

    /** @returns {{product_id:number, variation_id:number, quantity:number}[]} */
    toItems() {
        return [...this.#rows.values()].map(r => ({
            product_id: r.productId,
            variation_id: r.variationId,
            quantity: r.quantity,
        }));
    }

    /** Remove specific row keys — used after a submit chunk succeeds. */
    clearKeys(rowKeys) {
        for (const key of rowKeys) this.#rows.delete(key);
    }

    /** Discard everything — used after a fully successful submit. */
    clear() {
        this.#rows.clear();
    }

    /** @returns {boolean} */
    isEmpty() {
        return this.#rows.size === 0;
    }
}
```

- [ ] **Verify:** no browser step yet — this module isn't wired in until Task
  11. Confirm the file has no syntax errors by running the plugin's build
  once the entry point imports it (folded into Task 11's verification).

- [ ] **Commit:**

```bash
git add wp-content/plugins/dp-b2b-quick-order/assets/src/quick-order-state.js
git commit -m "feat: add local Quick Order state model"
```

---

## Task 6 — Plugin JS: Footer Controller

**Files:**
- Create: `assets/src/footer-controller.js`

**Interfaces:**
- Consumes: `QuickOrderState` (Task 5) — `getItemCount()`, `getRowCount()`, `getSubtotal()`
- Consumes: `window.dpQuickOrder.currency`, `window.dpQuickOrder.i18n.itemsSuffix`, `window.dpQuickOrder.i18n.rowsSuffix` (Task 4)
- Produces: `class FooterController` with `render(): void`, and `setSubmitEnabled(enabled: boolean): void` — consumed by Tasks 8, 11.
- Consumes DOM: `.dp-qo-footer__items`, `.dp-qo-footer__rows`, `.dp-qo-footer__subtotal-amount`, `.dp-qo-footer__add-to-cart` (Task 9 template markup)

- [ ] **Create the file:**

```javascript
'use strict';

/**
 * Renders the Quick Order footer from local state — never from a WC cart response.
 * See docs/frozen/quick-order-local-state-architecture.md §3.
 */
export class FooterController {
    /** @type {import('./quick-order-state.js').QuickOrderState} */
    #state;
    #itemsEl;
    #rowsEl;
    #subtotalEl;
    #addBtn;

    /**
     * @param {import('./quick-order-state.js').QuickOrderState} state
     */
    constructor(state) {
        this.#state      = state;
        this.#itemsEl    = document.querySelector('.dp-qo-footer__items');
        this.#rowsEl     = document.querySelector('.dp-qo-footer__rows');
        this.#subtotalEl = document.querySelector('.dp-qo-footer__subtotal-amount');
        this.#addBtn     = document.querySelector('.dp-qo-footer__add-to-cart');
        this.render();
    }

    render() {
        const config = window.dpQuickOrder ?? {};
        const items  = this.#state.getItemCount();
        const rows   = this.#state.getRowCount();

        if (this.#itemsEl) this.#itemsEl.textContent = `${items} ${config.i18n?.itemsSuffix ?? 'artikala'}`;
        if (this.#rowsEl)  this.#rowsEl.textContent  = `${rows} ${config.i18n?.rowsSuffix ?? 'varijacija'}`;

        if (this.#subtotalEl) {
            const subtotal = this.#state.getSubtotal();
            try {
                this.#subtotalEl.textContent = new Intl.NumberFormat(navigator.language, {
                    style: 'currency', currency: config.currency ?? 'EUR',
                }).format(subtotal);
            } catch {
                this.#subtotalEl.textContent = subtotal.toFixed(2);
            }
        }

        this.setSubmitEnabled(!this.#state.isEmpty());
    }

    /** @param {boolean} enabled */
    setSubmitEnabled(enabled) {
        if (this.#addBtn) this.#addBtn.disabled = !enabled;
    }
}
```

- [ ] **Verify:** folded into Task 11 (entry point wiring) — this module has
  no standalone visible effect until wired.

- [ ] **Commit:**

```bash
git add wp-content/plugins/dp-b2b-quick-order/assets/src/footer-controller.js
git commit -m "feat: add Quick Order footer controller"
```

---

## Task 7 — Plugin JS: Row Controller (replaces RowSync)

**Files:**
- Create: `assets/src/row-controller.js`
- Delete: `assets/src/row-sync.js` (end of this task, after the replacement is verified working — see Task 11)

**Interfaces:**
- Consumes: `QuickOrderState.setQuantity()`, `QuickOrderState.getQuantity()` (Task 5)
- Consumes: `FooterController.render()` (Task 6)
- Produces: `class RowController` with `hydrateAll(): void` — consumed by Task 11 (also called after `dp:qo:rows-rendered`, dispatched by Task 8)
- Consumes DOM row attributes: `data-row-key`, `data-product-id`, `data-variation-id`, `data-price` (Task 8 markup)

No dropdown/variation-select handling is carried over — variations now
render as independent rows (Task 8), so there is no `.dp-qo-variation`
`change` event to wire up.

- [ ] **Create the file:**

```javascript
'use strict';

/**
 * Row interaction controller.
 * Wires quantity inputs and +/- buttons to local Quick Order state via event
 * delegation. No network calls — WC cart is untouched until explicit
 * "Dodaj u košaricu" submit. See docs/frozen/quick-order-local-state-architecture.md §2.
 */
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

    /**
     * Re-hydrate every currently-rendered row's qty input from existing local
     * state. Called after any (re)render — pagination, sort, or a variation
     * set arriving asynchronously — so a value set before navigating away
     * from a page is still reflected if the user pages back to it.
     */
    hydrateAll() {
        if (!this.#tbody) return;
        this.#tbody.querySelectorAll('[data-row-key]').forEach(row => {
            const input = row.querySelector('.dp-qo-qty');
            if (!input) return;
            input.value = this.#state.getQuantity(row.dataset.rowKey);
        });
    }

    #bindTableEvents() {
        this.#tbody.addEventListener('input', e => {
            if (e.target.matches('.dp-qo-qty')) this.#onQtyInput(e.target);
        });
        this.#tbody.addEventListener('click', e => {
            if (e.target.matches('.dp-qo-qty-minus')) this.#onQtyButton(e.target, -1);
            else if (e.target.matches('.dp-qo-qty-plus'))  this.#onQtyButton(e.target, +1);
        });
    }

    #onQtyInput(input) {
        if (input.disabled) return;
        const row = input.closest('.dp-qo-row');
        const rowKey = input.dataset.rowKey;
        if (!row || !rowKey) return;

        const qty         = Math.max(0, parseInt(input.value, 10) || 0);
        const productId    = parseInt(row.dataset.productId, 10) || 0;
        const variationId  = parseInt(row.dataset.variationId ?? '0', 10) || 0;
        const unitPrice     = parseFloat(row.dataset.price ?? '0') || 0;

        this.#state.setQuantity(rowKey, qty, { productId, variationId, unitPrice });
        this.#footer.render();
    }

    #onQtyButton(btn, delta) {
        if (btn.disabled) return;
        const input = btn.closest('.dp-qo-qty-wrap')?.querySelector('.dp-qo-qty');
        if (!input || input.disabled) return;
        const next = Math.max(0, (parseInt(input.value, 10) || 0) + delta);
        input.value = next;
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }
}
```

- [ ] **Verify:** folded into Task 11.

- [ ] **Commit (creation only — deletion of `row-sync.js` happens in Task 11 once the replacement is confirmed wired and working):**

```bash
git add wp-content/plugins/dp-b2b-quick-order/assets/src/row-controller.js
git commit -m "feat: add Quick Order row controller (local-state based)"
```

---

## Task 8 — Plugin JS: Product List Rendering Changes

**Files:**
- Modify: `assets/src/product-list.js`

**Interfaces:**
- Consumes: `window.dpQuickOrder.i18n.skuLabel`, `.loadingVariations`, `.variationLoadError` (Task 4)
- Produces: dispatches `dp:qo:rows-rendered` custom event on `document` — consumed by Task 11's listener (`rowController.hydrateAll()`)
- Row markup now carries `data-variation-id` and `data-price` in addition to the existing `data-product-id`, `data-type`, `data-row-key` — consumed by Task 7's `RowController`

Changes from the current file: product name/image links removed (brief §5);
SKU label reworded (brief §6); the `.dp-qo-variation` `<select>` is replaced
by rendering every variation as an independent sibling row (brief §7); the
status-icon column is dropped (no more per-keystroke server round trip to
report pending/synced/error against).

- [ ] **Replace the full file** (WOOF integration, pagination, and sorting
  methods are unchanged from the current implementation — only `#rowHTML`,
  variation loading, and the row template are touched; the file is shown in
  full here to keep it copy-pasteable and avoid partial-edit drift):

```javascript
'use strict';

/**
 * Product list renderer.
 *
 * Fetches /products (paginated), renders rows into .dp-qo-tbody. Variable
 * products render a temporary loading row, replaced with one independent
 * row per variation once /products/{id}/variations resolves — no dropdown.
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
    /** @type {Map<number, {name:string, image:string}>} Parent product meta, keyed by product id — used when expanding variation rows. */
    #parentMeta   = new Map();

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
        this.#tbody.innerHTML = `<tr><td colspan="5" class="dp-qo-loading">Učitavanje...</td></tr>`;

        let data;
        try {
            const url = this.#buildProductsUrl(page);
            const res = await fetch(url, { headers: { 'X-WP-Nonce': this.#config.wpNonce } });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            data = await res.json();
        } catch (err) {
            this.#tbody.innerHTML = `<tr><td colspan="5" class="dp-qo-error">Greška pri učitavanju proizvoda.</td></tr>`;
            return;
        }

        this.#totalPages = data.total_pages ?? 1;
        this.#renderRows(data.products ?? []);
        this.#renderPagination();
        document.dispatchEvent(new CustomEvent('dp:qo:rows-rendered'));
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
        if (f.price_min > 0)                           params.set('price_min',    f.price_min);
        if (f.price_max > 0)                           params.set('price_max',    f.price_max);
        if (f.stock_status)                            params.set('stock_status', f.stock_status);
        if (f.category > 0)                            params.set('category',     f.category);
        if (f.brand > 0)                                params.set('brand',        f.brand);
        if (f.attributes && Object.keys(f.attributes).length) {
            params.set('attributes', JSON.stringify(f.attributes));
        }

        return `${this.#config.productsUrl}?${params.toString()}`;
    }

    #renderRows(products) {
        if (!products.length) {
            this.#tbody.innerHTML = `<tr><td colspan="5" class="dp-qo-empty">Nema dostupnih proizvoda.</td></tr>`;
            return;
        }
        this.#parentMeta.clear();
        this.#tbody.innerHTML = products.map(p => this.#rowHTML(p)).join('');
    }

    #rowHTML(product) {
        const isVariable = product.type === 'variable';
        const thumbSrc    = product.image || this.#config.placeholderImg || '';

        if (isVariable) {
            this.#parentMeta.set(Number(product.id), { name: product.name, image: thumbSrc });
            return `
<tr class="dp-qo-row dp-qo-row--loading" data-product-id="${product.id}" data-type="variable">
  <td colspan="5" class="dp-qo-loading">${escHtml(this.#config.i18n?.loadingVariations ?? 'Učitavanje varijacija...')}</td>
</tr>`.trim();
        }

        const rowKey     = `${product.id}_0`;
        const disableQty = product.stock?.status === 'outofstock';
        const stockLabel = { instock: 'Na stanju', outofstock: 'Nema na stanju', onbackorder: 'Po narudžbi' };
        const stockClass = `dp-qo-stock--${escHtml(product.stock?.status ?? 'outofstock')}`;
        const stockText  = stockLabel[product.stock?.status] ?? (product.stock?.status ?? '');
        const thumbCell  = thumbSrc
            ? `<img src="${escHtml(thumbSrc)}" alt="" class="dp-qo-thumb" width="40" height="40" loading="lazy">`
            : '';

        return this.#dataRowHTML({
            rowKey, productId: product.id, variationId: 0,
            name: escHtml(product.name), sku: escHtml(product.sku),
            stockClass, stockText, priceHtml: product.price_html ?? '', price: product.price ?? 0,
            thumbCell, disableQty,
        });
    }

    /**
     * Shared row template for both simple-product rows and expanded variation rows.
     * Product links are intentionally omitted — Quick Order keeps the user on-page (brief §5).
     */
    #dataRowHTML({ rowKey, productId, variationId, name, sku, stockClass, stockText, priceHtml, price, thumbCell, disableQty }) {
        const skuLabel = escHtml(this.#config.i18n?.skuLabel ?? 'Kataloški broj:');
        return `
<tr class="dp-qo-row"
    data-product-id="${productId}"
    data-variation-id="${variationId}"
    data-row-key="${rowKey}"
    data-price="${price}">
  <td class="dp-qo-col-thumb">${thumbCell}</td>
  <td class="dp-qo-col-name">
    <strong class="dp-qo-name">${name}</strong>
    <small class="dp-qo-sku">${skuLabel} ${sku}</small>
  </td>
  <td class="dp-qo-col-stock">
    <span class="dp-qo-stock ${stockClass}">${stockText}</span>
  </td>
  <td class="dp-qo-col-price">${priceHtml}</td>
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
</tr>`.trim();
    }

    /** Kick off parallel variation fetches for all variable rows on current page. */
    #loadAllVariations() {
        const rows = this.#tbody.querySelectorAll('[data-type="variable"]');
        rows.forEach(row => this.#loadVariationOptions(row));
    }

    /**
     * Fetch variation details and replace the loading row with one independent
     * row per variation (brief §7 — no dropdown). Reuses the parent's thumbnail
     * for every variation row; variation-specific images are not fetched
     * (`get_variation_details()` doesn't return them — avoids extra hydration
     * cost per project performance rules).
     */
    async #loadVariationOptions(row) {
        const productId = row.dataset.productId;

        let variations;
        try {
            const url = `${this.#config.productsUrl}/${productId}/variations`;
            const res = await fetch(url, { headers: { 'X-WP-Nonce': this.#config.wpNonce } });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            variations = await res.json();
        } catch {
            row.innerHTML = `<td colspan="5" class="dp-qo-error">${escHtml(this.#config.i18n?.variationLoadError ?? 'Greška pri učitavanju varijacija.')}</td>`;
            return;
        }

        if (!variations.length) {
            row.remove();
            return;
        }

        const meta      = this.#parentMeta.get(Number(productId)) ?? { name: '', image: '' };
        const thumbCell = meta.image
            ? `<img src="${escHtml(meta.image)}" alt="" class="dp-qo-thumb" width="40" height="40" loading="lazy">`
            : '';
        const stockLabel = { instock: 'Na stanju', outofstock: 'Nema na stanju', onbackorder: 'Po narudžbi' };

        const rowsHtml = variations.map(v => {
            const rowKey     = `${productId}_${v.id}`;
            const stockClass = `dp-qo-stock--${escHtml(v.stock_status)}`;
            const stockText  = stockLabel[v.stock_status] ?? v.stock_status;
            return this.#dataRowHTML({
                rowKey, productId: Number(productId), variationId: v.id,
                name: `${escHtml(meta.name)} — ${escHtml(v.label)}`, sku: escHtml(v.sku),
                stockClass, stockText, priceHtml: v.price_html, price: v.price,
                thumbCell, disableQty: v.stock_status === 'outofstock',
            });
        }).join('');

        row.outerHTML = rowsHtml;
        document.dispatchEvent(new CustomEvent('dp:qo:rows-rendered'));
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
     */
    #bindWoofIntegration() {
        this.#woofFilters = this.#extractWoofFilters(new URLSearchParams(window.location.search));

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
     * @param {URLSearchParams} params
     * @returns {{ price_min?: number, price_max?: number, stock_status?: string, category?: number, brand?: number, attributes?: object }}
     */
    #extractWoofFilters(params) {
        const result = {};

        const prMin = parseFloat(params.get('wpf_min_price') ?? '');
        const prMax = parseFloat(params.get('wpf_max_price') ?? '');
        if (!isNaN(prMin) && prMin > 0) result.price_min = prMin;
        if (!isNaN(prMax) && prMax > 0) result.price_max = prMax;

        const stockStatus = params.get('pr_stock');
        if (stockStatus && ['instock', 'outofstock', 'onbackorder'].includes(stockStatus)) {
            result.stock_status = stockStatus;
        }

        for (const [key, val] of params) {
            if (!/^wpf_filter_cat_\d+$/.test(key)) continue;
            const termId = parseInt(val, 10);
            if (termId > 0) { result.category = termId; break; }
        }

        for (const [key, val] of params) {
            if (!/^wpf_filter_product_brand_\d+$/.test(key)) continue;
            const termId = parseInt(val, 10);
            if (termId > 0) { result.brand = termId; break; }
        }

        const attrs = {};
        for (const [key, val] of params) {
            if (!key.startsWith('wpf_filter_pa_')) continue;
            const attrName = key.slice('wpf_filter_pa_'.length);
            if (!attrName) continue;
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

- [ ] **Verify:** folded into Task 11 (needs `RowController`'s
  `dp:qo:rows-rendered` listener wired to actually see hydration take effect,
  though rendering itself can be sanity-checked once Task 11's entry point
  boots the page and shows rows without a `<select>` and without name/image
  links).

- [ ] **Commit:**

```bash
git add wp-content/plugins/dp-b2b-quick-order/assets/src/product-list.js
git commit -m "feat: render variations as independent rows, remove product links"
```

---

## Task 9 — Plugin PHP+Template: Table Header and Footer Markup

**Files:**
- Modify: `templates/quick-order.php`

**Interfaces:**
- Produces DOM targets consumed by Task 6 (`FooterController`) and Task 11 (submit button click handler): `.dp-qo-footer__items`, `.dp-qo-footer__rows`, `.dp-qo-footer__subtotal-amount`, `.dp-qo-footer__add-to-cart`, `.dp-qo-footer__cart-link`

Column count drops from 7 to 5 (Varijacija and status-icon columns removed —
variations are now independent rows, and there's no more per-keystroke
server round trip to show pending/synced/error against).

- [ ] **Replace the file:**

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
		<div class="row">

			<div class="col-lg-3">
				<?php if ( shortcode_exists( 'wpf-filters' ) ) : ?>
				<div class="dp-qo-filter-area">
					<?php echo do_shortcode( '[wpf-filters id="1"]' ); ?>
				</div>
				<?php endif; ?>
			</div>

			<div class="col-lg-9">

				<div class="dp-qo-pagination"></div>

				<div class="dp-qo-table-wrap">
					<table class="dp-qo-table">
						<thead>
							<tr>
								<th class="dp-qo-col-thumb"></th>
								<th data-sort="title"><?php esc_html_e( 'Naziv', 'dp-b2b-quick-order' ); ?><span class="dp-qo-sort-arrow" aria-hidden="true"></span></th>
								<th><?php esc_html_e( 'Stanje', 'dp-b2b-quick-order' ); ?></th>
								<th data-sort="price"><?php esc_html_e( 'Cijena', 'dp-b2b-quick-order' ); ?><span class="dp-qo-sort-arrow" aria-hidden="true"></span></th>
								<th><?php esc_html_e( 'Kol.', 'dp-b2b-quick-order' ); ?></th>
							</tr>
						</thead>
						<tbody class="dp-qo-tbody">
							<tr>
								<td colspan="5" class="dp-qo-loading">
									<?php esc_html_e( 'Učitavanje...', 'dp-b2b-quick-order' ); ?>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

			</div><!-- .col-lg-9 -->

		</div><!-- .row -->
	</div><!-- .container -->

	<div class="dp-qo-footer">
		<div class="dp-qo-footer__summary">
			<i class="icon-shopping-bag dp-qo-footer__icon" aria-hidden="true"></i>
			<span class="dp-qo-footer__items">0 <?php esc_html_e( 'artikala', 'dp-b2b-quick-order' ); ?></span>
			<span class="dp-qo-footer__rows">0 <?php esc_html_e( 'varijacija', 'dp-b2b-quick-order' ); ?></span>
		</div>
		<div class="dp-qo-footer__total">
			<span class="dp-qo-footer__total-label"><?php esc_html_e( 'Ukupno (bez PDV-a)', 'dp-b2b-quick-order' ); ?></span>
			<span class="dp-qo-footer__subtotal-amount"></span>
		</div>
		<div class="dp-qo-footer__actions">
			<button type="button" class="dp-qo-footer__add-to-cart" disabled>
				<?php esc_html_e( 'Dodaj u košaricu', 'dp-b2b-quick-order' ); ?>
			</button>
			<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="dp-qo-footer__cart-link">
				<?php esc_html_e( 'Pregled košarice →', 'dp-b2b-quick-order' ); ?>
			</a>
		</div>
	</div>
</div><!-- #dp-quick-order -->
```

  Note the `.dp-qo-footer` moved outside `.container` — Task 12's CSS fixes
  it to the viewport bottom at full width, which a `.container`-scoped
  element (with its max-width/padding) would fight against.

- [ ] **Verify:** visit `/quick-order/` locally. Table header shows 5 columns
  (thumb has no label). Footer shows "0 artikala", "0 varijacija", an empty
  subtotal, a disabled "Dodaj u košaricu" button, and "Pregled košarice →".
  (Full interactivity arrives in Task 11 — this step only confirms markup/
  column count are correct and nothing is visually broken.)

- [ ] **Commit:**

```bash
git add wp-content/plugins/dp-b2b-quick-order/templates/quick-order.php
git commit -m "feat: rework Quick Order table header and footer markup"
```

---

## Task 10 — Plugin JS: Cart Submit (replaces CartSync)

**Files:**
- Create: `assets/src/cart-submit.js`
- Delete: `assets/src/cart-sync.js`, `assets/src/sync-queue.js` (end of this task, after Task 11 confirms the replacement works)

**Interfaces:**
- Consumes: `QuickOrderState.toItems()` (Task 5)
- Consumes: `window.dpQuickOrder.cartSyncUrl`, `.wpNonce`, `.cartSyncMaxBatch` (Task 4)
- Produces: `class CartSubmit` with `submit(): Promise<{addedKeys: string[], failedItems: object[]}>` — consumed by Task 11
- Produces: dispatches `dp:submit:complete` custom event with `{addedKeys, failedItems, totals}` — consumed by Task 11's WC ecosystem bridge listener

Reuses the existing `/cart/sync` REST route and `class-cart-sync.php` PHP
validation unchanged — see
`docs/frozen/quick-order-local-state-architecture.md` §4.

- [ ] **Create the file:**

```javascript
'use strict';

/**
 * Bulk "Dodaj u košaricu" submit.
 *
 * Reuses the existing /cart/sync REST endpoint (server-side stock/purchasability
 * validation, add_to_cart()) — chunked to respect CART_SYNC_MAX_BATCH, sequential
 * (not parallel) so cart mutation order stays deterministic.
 * See docs/frozen/quick-order-local-state-architecture.md §4.
 */
export class CartSubmit {
    /** @type {import('./quick-order-state.js').QuickOrderState} */
    #state;
    #config;
    #chunkSize;

    /**
     * @param {import('./quick-order-state.js').QuickOrderState} state
     * @param {object} config  window.dpQuickOrder
     */
    constructor(state, config) {
        this.#state     = state;
        this.#config    = config;
        this.#chunkSize = config.cartSyncMaxBatch ?? 50;
    }

    /**
     * @returns {Promise<{addedKeys: string[], failedItems: object[]}>}
     */
    async submit() {
        const items = this.#state.toItems();
        if (!items.length) return { addedKeys: [], failedItems: [] };

        const chunks = [];
        for (let i = 0; i < items.length; i += this.#chunkSize) {
            chunks.push(items.slice(i, i + this.#chunkSize));
        }

        const addedKeys   = [];
        const failedItems = [];
        let lastTotals    = null;

        for (const chunk of chunks) {
            let data;
            try {
                const res = await fetch(this.#config.cartSyncUrl, {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce':   this.#config.wpNonce,
                    },
                    body: JSON.stringify({ items: chunk }),
                });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                data = await res.json();
            } catch {
                // Whole chunk failed at the network/HTTP level — every item in it is unresolved.
                failedItems.push(...chunk.map(i => ({
                    product_id: i.product_id, variation_id: i.variation_id,
                    action: 'failed', error: 'network_error',
                })));
                continue;
            }

            for (const item of data.synced ?? []) {
                const key = `${item.product_id}_${item.variation_id}`;
                if (['added', 'updated', 'removed'].includes(item.action)) {
                    addedKeys.push(key);
                } else {
                    failedItems.push(item);
                }
            }
            if (data.totals) lastTotals = data.totals;
        }

        document.dispatchEvent(new CustomEvent('dp:submit:complete', {
            detail: { addedKeys, failedItems, totals: lastTotals },
        }));

        return { addedKeys, failedItems };
    }
}
```

- [ ] **Verify:** folded into Task 11 (needs the entry point's button
  wiring to trigger it).

- [ ] **Commit (creation only — deletion of `cart-sync.js`/`sync-queue.js`
  happens in Task 11 once the replacement is confirmed working):**

```bash
git add wp-content/plugins/dp-b2b-quick-order/assets/src/cart-submit.js
git commit -m "feat: add chunked bulk cart submit for Quick Order"
```

---

## Task 11 — Plugin JS: Entry Point Rewiring + Delete Superseded Files

**Files:**
- Modify: `assets/src/quick-order.js`
- Delete: `assets/src/cart-sync.js`, `assets/src/sync-queue.js`, `assets/src/row-sync.js`

**Interfaces:**
- Consumes: everything produced in Tasks 5–10.

- [ ] **Replace the file:**

```javascript
'use strict';

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

    // Re-hydrate qty inputs from local state whenever rows (re)render —
    // pagination, sort, or a variation set arriving asynchronously.
    document.addEventListener('dp:qo:rows-rendered', () => rowCtrl.hydrateAll());

    const boot = () => productList.loadPage(1);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }

    const addBtn = document.querySelector('.dp-qo-footer__add-to-cart');

    addBtn?.addEventListener('click', async () => {
        if (state.isEmpty()) return;

        addBtn.disabled = true;
        const originalLabel = addBtn.textContent;
        addBtn.textContent = config.i18n?.adding ?? '...';

        const { addedKeys, failedItems } = await submit.submit();

        // Only rows the server actually processed (added/updated/removed) are
        // cleared. Rows that came back out_of_stock/failed stay in local
        // state so the user can see and correct them.
        state.clearKeys(addedKeys);
        footer.render();
        rowCtrl.hydrateAll();

        addBtn.textContent = originalLabel;
        addBtn.disabled = state.isEmpty();

        if (failedItems.length) {
            window.alert(config.i18n?.partialFailure ?? 'Neki artikli nisu dodani u košaricu.');
        }
    });

    // Reuse the existing WC ecosystem bridge (Toastify, mini-cart HTML, .cart-contents
    // .count), fired once per submit instead of per keystroke.
    document.addEventListener('dp:submit:complete', e => {
        if (typeof jQuery === 'undefined') return;
        if (!e.detail.addedKeys.length) return;
        jQuery(document.body).one('wc_fragments_refreshed wc_fragments_ajax_error', function () {
            jQuery(document.body).trigger('added_to_cart', [[], '', null]);
        });
        jQuery(document.body).trigger('wc_fragment_refresh');
    });

    // Expose internal instances for browser-console inspection (dev/staging only).
    config.state       = state;
    config.productList = productList;
})();
```

- [ ] **Build:**

```powershell
Set-Location "C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order"
npm run build
```

  Expected: `assets/dist/quick-order.js` updated, no esbuild errors (import
  resolution failures here mean a Task 5–10 file wasn't saved where this
  file expects it — check the five `import` paths above against the actual
  filenames first).

- [ ] **Verify — full local-state flow, no network on quantity change:**
  visit `/quick-order/` locally, open DevTools Network tab, change a
  quantity — confirm footer updates immediately and **no** request appears
  in the Network tab.

- [ ] **Verify — variation rows:** find a variable product, confirm it
  renders as multiple independent rows (no dropdown) once variations load,
  each with its own working qty controls.

- [ ] **Verify — pagination hydration:** set a quantity on page 1, navigate
  to page 2, navigate back to page 1 — confirm the quantity is still shown
  (state persisted in-page across pagination, per
  `docs/frozen/quick-order-local-state-architecture.md` §2).

- [ ] **Verify — submit:** set quantities on 2–3 rows, click "Dodaj u
  košaricu" — confirm exactly one `POST` fires in the Network tab (or one
  per 50-row chunk if you have a large test selection), the WooCommerce
  mini-cart updates, and Quick Order rows reset to 0 with the footer back to
  "0 artikala".

- [ ] **Verify — refresh discards state:** set a quantity, refresh the page
  without submitting — confirm it resets to 0 (no persistence, by design).

- [ ] **Delete superseded files** once all the above pass:

```powershell
Set-Location "C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order\assets\src"
Remove-Item cart-sync.js, sync-queue.js, row-sync.js -Confirm:$false
```

- [ ] **Rebuild after deletion** (confirms nothing else still imports the
  deleted files):

```powershell
Set-Location "C:\xampp2\htdocs\dp-b2b\wp-content\plugins\dp-b2b-quick-order"
npm run build
```

- [ ] **Commit:**

```bash
git add wp-content/plugins/dp-b2b-quick-order/assets/src/quick-order.js wp-content/plugins/dp-b2b-quick-order/assets/dist/quick-order.js
git rm wp-content/plugins/dp-b2b-quick-order/assets/src/cart-sync.js wp-content/plugins/dp-b2b-quick-order/assets/src/sync-queue.js wp-content/plugins/dp-b2b-quick-order/assets/src/row-sync.js
git commit -m "feat: wire local-state Quick Order entry point, remove superseded CartSync files"
```

---

## Task 12 — Plugin CSS: Footer, Variation Rows, Cleanup

**Files:**
- Modify: `assets/dist/quick-order.css`

Removes styles for elements that no longer exist (`.dp-qo-variation` select,
row-state classes tied to per-keystroke server round trips, status icon) and
adds the new footer layout.

- [ ] **Replace the file:**

```css
/* ── Layout ─────────────────────────────────────────────────── */
.dp-quick-order { font-size: 14px; padding-bottom: 90px; } /* space for fixed footer */

.dp-qo-table-wrap { overflow-x: auto; margin: 12px 0; }

.dp-qo-table { width: 100%; border-collapse: collapse; }

.dp-qo-table th,
.dp-qo-table td {
    padding: 7px 10px;
    border-bottom: 1px solid #e2e2e2;
    vertical-align: middle;
}

.dp-qo-table th { text-align: left; font-weight: 600; background: #f7f7f7; }

/* ── Thumbnail column ────────────────────────────────────────── */
.dp-qo-col-thumb { width: 50px; padding: 4px 6px; }
.dp-qo-thumb { display: block; width: 40px; height: 40px; object-fit: contain; border-radius: 2px; }

/* ── Product name / SKU ──────────────────────────────────────── */
.dp-qo-name { display: block; font-weight: 500; }
.dp-qo-sku  { display: block; color: #888; font-size: 12px; }

/* ── WPF filter area ─────────────────────────────────────────── */
.dp-qo-filter-area { margin-bottom: 16px; }

/* ── Stock badge ─────────────────────────────────────────────── */
.dp-qo-stock { display: inline-block; font-size: 11px; padding: 2px 6px; border-radius: 3px; white-space: nowrap; }
.dp-qo-stock--instock     { background: #e8f5e9; color: #2e7d32; }
.dp-qo-stock--outofstock  { background: #fce4ec; color: #c62828; }
.dp-qo-stock--onbackorder { background: #fff3e0; color: #e65100; }

/* ── Quantity input ──────────────────────────────────────────── */
.dp-qo-qty { width: 52px; text-align: center; padding: 4px 6px; border: 1px solid #ccc; border-radius: 3px; font-size: 14px; }
.dp-qo-qty:disabled { background: #f3f3f3; opacity: 0.5; cursor: not-allowed; }

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

/* ── Pagination ──────────────────────────────────────────────── */
.dp-qo-pagination { display: flex; align-items: center; gap: 10px; padding: 8px 0; }

.dp-qo-btn { padding: 5px 12px; border: 1px solid #ccc; background: #fff; border-radius: 3px; cursor: pointer; font-size: 13px; }
.dp-qo-btn:hover { background: #f3f3f3; }

.dp-qo-page-info { font-size: 13px; color: #666; }

/* ── Fixed Quick Order footer ─────────────────────────────────── */
.dp-qo-footer {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 50;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    padding: 14px 24px;
    background: #fff;
    border-top: 2px solid #e2e2e2;
    box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.06);
}

.dp-qo-footer__summary { display: flex; align-items: center; gap: 10px; font-size: 13px; color: #444; }
.dp-qo-footer__icon { font-size: 18px; }

.dp-qo-footer__total { display: flex; flex-direction: column; align-items: flex-start; }
.dp-qo-footer__total-label { font-size: 11px; color: #888; }
.dp-qo-footer__subtotal-amount { font-weight: 600; font-size: 17px; }

.dp-qo-footer__actions { display: flex; align-items: center; gap: 12px; }

.dp-qo-footer__add-to-cart {
    padding: 10px 22px;
    background: #222;
    color: #fff;
    border: none;
    border-radius: 3px;
    font-size: 13px;
    cursor: pointer;
}
.dp-qo-footer__add-to-cart:hover:not(:disabled) { background: #444; }
.dp-qo-footer__add-to-cart:disabled { opacity: 0.4; cursor: not-allowed; }

.dp-qo-footer__cart-link {
    display: inline-block;
    color: #222;
    text-decoration: none;
    font-size: 13px;
    border-bottom: 1px solid currentColor;
}
.dp-qo-footer__cart-link:hover { color: #444; }

@media only screen and (max-width: 767.98px) {
    .dp-qo-footer {
        flex-wrap: wrap;
        gap: 10px;
        padding: 10px 14px;
    }
    .dp-qo-footer__actions { width: 100%; justify-content: space-between; }
}

/* ── Loading / empty / error states ─────────────────────────── */
.dp-qo-loading, .dp-qo-empty, .dp-qo-error {
    text-align: center;
    padding: 24px;
    font-style: italic;
    color: #aaa;
}
.dp-qo-error { color: #c62828; }

/* ── Access denied ───────────────────────────────────────────── */
.dp-qo-access-denied { color: #666; font-style: italic; padding: 12px 0; margin: 0; }

/* ── Sortable column headers ─────────────────────────────────── */
.dp-qo-table th[data-sort] { cursor: pointer; user-select: none; }
.dp-qo-table th[data-sort]:hover { background: #efefef; }
.dp-qo-sort-arrow { font-size: 11px; margin-left: 3px; }

/* ── Loading placeholder row (variable product, before variations resolve) ── */
.dp-qo-row--loading td { text-align: left; padding-left: 16px; }
```

  Removed from the previous version (no longer applicable): `.dp-qo-name-link`
  (product links removed), `.dp-qo-variation` (dropdown removed),
  `.dp-qo-stock--neutral` (moot — variations render with their own real
  stock status immediately, no "select a variation first" neutral state),
  `.dp-qo-row.is-pending/is-synced/is-error` and `.dp-qo-status-icon` (no
  more per-keystroke server round trip to represent), `.dp-qo-footer__total`/
  `.dp-qo-footer__cart-link`'s old button-style rules (replaced by the new
  footer block above — `.dp-qo-footer__cart-link` is redefined as a text
  link per the brief's "secondary button" framing, not the old dark-button
  style).

- [ ] **Verify:** visit `/quick-order/` locally — footer is visually fixed to
  the bottom of the viewport and stays there while scrolling the product
  table; last table rows are not obscured by the fixed footer (the
  `padding-bottom` on `.dp-quick-order` handles this — adjust the `90px`
  value if the footer's actual rendered height differs).

- [ ] **Commit:**

```bash
git add wp-content/plugins/dp-b2b-quick-order/assets/dist/quick-order.css
git commit -m "style: fixed Quick Order footer, remove dropdown/row-state styles"
```

---

## Task 13 — Theme SCSS: Minimal Header Layout

**Files:**
- Create: `sass/pages/quick-order.scss`
- Modify: `sass/style.scss`

**Interfaces:**
- Consumes: `$brand`, `$black`, `$white`, `bp()` mixin (existing, `sass/theme/_vars.scss`)
- Styles the markup produced in Task 2 (`.dp-qo-header`)

- [ ] **Create the partial:**

```scss
// Minimal header for the Quick Order page (docs/frozen/quick-order-local-state-architecture.md §7)
.dp-qo-header {
    border-bottom: 1px solid $border-secondary;
    background: $white;

    &__inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 24px;
        gap: 16px;
        @include bp(sm-down) {
            padding: 12px 16px;
        }
    }

    &__left {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    &__logo img {
        display: block;
        height: 32px;
        width: auto;
    }

    &__back {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: $black;
        text-decoration: none;
        font-size: 13px;
        @include bp(sm-down) {
            span { display: none; } // icon-only on mobile — label kept in DOM for accessibility
        }
        &:hover { color: $brand; }
    }

    &__center {
        @include heading-6;
        color: $black;
        white-space: nowrap;
    }

    &__right {
        display: flex;
        align-items: center;
    }
}
```

  Note: the `&:hover { span { display: none; } }` mobile rule assumes the
  "Povratak u katalog" text is wrapped in a `<span>` — Task 2's markup above
  does not currently wrap it. **Before this SCSS ships, either wrap the text
  in `<span>Povratak u katalog</span>` in Task 2's markup, or drop this
  mobile rule and let the label wrap/shrink naturally.** Flagged here instead
  of silently changing Task 2's already-written markup after the fact — pick
  one during implementation and verify at the `sm-down` breakpoint.

- [ ] **Import it in `sass/style.scss`.** Add after the existing component
  imports:

```scss
@import "components/header";
@import "components/content";
@import "components/footer";
@import "components/nav";
@import "pages/quick-order";
```

- [ ] **Build:**

```powershell
Set-Location "C:\xampp2\htdocs\dp-b2b\wp-content\themes\dreampoint-b2b"
npm run build:css
```

  Expected: `style.css` updated, no Sass compile errors.

- [ ] **Verify:** visit `/quick-order/` locally — minimal header matches the
  layout from Task 2 (logo + back link left, "QUICK ORDER" centered, cart
  icon right), no main nav/account menu/mega menu visible. Check at a mobile
  width (`sm-down`, ≤767px) that the header doesn't overflow or wrap badly.

- [ ] **Commit:**

```bash
git add wp-content/themes/dreampoint-b2b/sass/pages/quick-order.scss wp-content/themes/dreampoint-b2b/sass/style.scss wp-content/themes/dreampoint-b2b/style.css
git commit -m "style: add minimal Quick Order header layout"
```

---

## Task 14 — Full Local Verification Pass

**Files:** none (verification only)

- [ ] **Golden path:** log in as a B2B test user with an assigned bucket →
  open Quick Order → set quantities across at least one simple product, one
  variable product's variations, and a second page of results → confirm
  footer counts/subtotal are correct at each step → click "Dodaj u košaricu"
  → confirm cart/mini-cart reflect exactly what was set → confirm Quick
  Order resets to empty.

- [ ] **Partial failure:** set a quantity higher than available stock on a
  managed-stock product, submit — confirm that row's quantity is NOT cleared
  from Quick Order (server returned `out_of_stock`), the alert/message
  appears, and rows that DID succeed are cleared.

- [ ] **Chunking (only if a >50-row test dataset is available):** select
  more than 50 rows, submit, confirm multiple sequential `POST` requests in
  the Network tab (not one oversized request, not parallel requests) and all
  rows end up in the cart.

- [ ] **B2C / logged-out access:** confirm Quick Order page still shows the
  existing access-denied/login-prompt behavior — unaffected by this
  transformation (`class-frontend.php`, `class-rest-api.php::is_b2b_user()`
  are unchanged).

- [ ] **Keyboard navigation:** Tab through a row's quantity input and +/-
  buttons, and through the footer's "Dodaj u košaricu"/"Pregled košarice"
  controls — confirm all are reachable and operable without a mouse
  (accessibility rule — `:focus` must remain visible; if any new element
  suppresses it, add a visible replacement before considering this task
  done).

- [ ] **Record the footer/site-footer visual question from Task 3** — note
  in the PR/handoff whether the full site footer below the fixed Quick Order
  footer needs the same suppression treatment.

- [ ] **Update `docs/frozen/quick-order-local-state-architecture.md` Status
  line** from `APPROVED (2026-07-10) — implementation pending` to `STABLE /
  PRODUCTION-VALIDATED` once all of the above pass locally.

- [ ] **Update `docs/active/status.md`** rows currently marked `PENDING
  IMPLEMENTATION` / `BEING REPLACED` to `ACTIVE` once verified.

---

## Self-Review Notes

- Brief §1 (local state, no cart writes on qty change) → Tasks 5, 7, 11 (verify: no network on qty change)
- Brief §2 (footer: package icon, N artikala, N varijacija) → Tasks 6, 9
- Brief §2 (Ukupno bez PDV-a, product-only subtotal) → Task 6
- Brief §3 (Dodaj u košaricu / Pregled košarice buttons) → Tasks 9, 11
- Brief §4 (bulk add, refresh fragments, clear state) → Tasks 10, 11
- Brief §5 (page not persistent, discard on leave) → Task 5 (no persistence layer by construction), verified Task 11
- Brief §6 (remove product links) → Task 8
- Brief §7 (Kataloški broj: label) → Tasks 4, 8
- Brief §8 (variations as independent rows, no dropdown) → Task 8
- Brief §9 (minimal Quick Order header, conditional not new template) → Tasks 1, 2, 13
- Brief §10 (fixed footer, content bottom spacing) → Task 12
- Brief §11 (desktop layout preserved, responsive) → Task 13 (mobile breakpoint), flagged for visual confirmation against supplied mockups (not available to this plan — confirm during Task 14)
- Brief §12/§13 (reuse existing code, minimal footprint) → File Map's reuse column in the architecture doc §8; no new REST routes, no new PHP validation logic
- Governance reconciliation (frozen doc supersession, status/roadmap docs) → completed before this plan was written; see `docs/frozen/quick-order-sync-architecture.md` Supersession Note and `docs/active/status.md`/`current-phase.md`/`docs/index.md`
- No placeholders: every task has complete, copy-pasteable code — confirmed on this pass
- Type/name consistency check: `QuickOrderState` methods (`setQuantity`, `getQuantity`, `getItemCount`, `getRowCount`, `getSubtotal`, `toItems`, `clearKeys`, `clear`, `isEmpty`) are named identically across Tasks 5–11 wherever consumed — confirmed
- Row key format `"${productId}_${variationId}"` used identically in Tasks 5, 7, 8, 10 — confirmed

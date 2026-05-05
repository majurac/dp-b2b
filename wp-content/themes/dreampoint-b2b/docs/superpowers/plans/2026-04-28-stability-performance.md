# Stability & Performance — Localhost-Only Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Two minimal, locally-verifiable changes — stabilize PDV injection and sanity-check meta_cache vs WooFilter Pro.

**Architecture:** Targeted edits to `functions.php` only. No new files, no new abstractions, no dependencies.

**Tech Stack:** WordPress/WooCommerce, PHP 8.x.

---

## Deferred (not in scope until staging/design/ERP ready)

| Task | Blocked on |
|------|-----------|
| Font preloads | Final design and font choices unknown |
| fetchpriority tuning | Final design unknown |
| LSCache JS defer | Staging only |
| CorvusPay + jquery-migrate | Payment gateway not available |
| GTM snippet | Not requested |

---

## Files Modified

| File | Task | Lines |
|------|------|-------|
| `functions.php` | 1 | 985–1005 |
| `functions.php` | 2 | 867–873 — code change only if WPF is broken |

---

## Task 1 — PDV Filter: Defensive Guards

**Problem:** Three issues in the current implementation:

1. No guard against double-injection (filter could fire twice and render the PDV row twice)
2. No structural guard — if WC changes its block HTML, injection silently corrupts output instead of skipping
3. Text domain is `'woocommerce'` — should be `'dreampoint-b2b'` since "PDV" is a custom label

**The `strrpos` approach is kept** — it targets the outer block wrapper's closing tag which is stable. The improvement is purely defensive guards around the existing logic.

**Can this be verified locally?** Yes — add any product to cart, go to `/checkout/`, confirm the PDV row appears once and the price is correct.

**Current code** (`functions.php:985–1005`):
```php
add_filter(
    'render_block_woocommerce/checkout-order-summary-totals-block',
    function ( string $block_content ): string {
        if ( ! WC()->cart ) return $block_content;

        $pdv = WC()->cart->get_total_tax();

        $custom_row  = '<div class="wp-block-woocommerce-checkout-order-summary-pdv-block wc-block-components-totals-wrapper">';
        $custom_row .= '<div class="wc-block-components-totals-item">';
        $custom_row .= '<span class="wc-block-components-totals-item__label">' . esc_html__( 'PDV', 'woocommerce' ) . '</span>';
        $custom_row .= '<span class="wc-block-components-totals-item__value">' . wp_kses_post( wc_price( $pdv ) ) . '</span>';
        $custom_row .= '</div></div>';

        $pos = strrpos( $block_content, '</div>' );
        if ( $pos !== false ) {
            $block_content = substr_replace( $block_content, $custom_row . '</div>', $pos, strlen( '</div>' ) );
        }

        return $block_content;
    }
);
```

- [ ] **Step 1: Replace the existing PDV filter block**

  Replace the entire block above with:

  ```php
  add_filter(
      'render_block_woocommerce/checkout-order-summary-totals-block',
      function ( string $block_content ): string {
          if ( ! WC()->cart ) return $block_content;

          // Guard: WC block structure must be recognizable before injecting.
          // If WC changes inner class names in a future update, skip rather than corrupt output.
          if ( ! str_contains( $block_content, 'wc-block-components-totals-item' ) ) {
              return $block_content;
          }

          // Guard: prevent double-injection if the filter fires more than once per render.
          if ( str_contains( $block_content, 'wp-block-woocommerce-checkout-order-summary-pdv-block' ) ) {
              return $block_content;
          }

          $pdv = WC()->cart->get_total_tax();

          $custom_row  = '<div class="wp-block-woocommerce-checkout-order-summary-pdv-block wc-block-components-totals-wrapper">';
          $custom_row .= '<div class="wc-block-components-totals-item">';
          $custom_row .= '<span class="wc-block-components-totals-item__label">' . esc_html__( 'PDV', 'dreampoint-b2b' ) . '</span>';
          $custom_row .= '<span class="wc-block-components-totals-item__value">' . wp_kses_post( wc_price( $pdv ) ) . '</span>';
          $custom_row .= '</div></div>';

          $pos = strrpos( $block_content, '</div>' );
          if ( $pos !== false ) {
              $block_content = substr_replace( $block_content, $custom_row . '</div>', $pos, strlen( '</div>' ) );
          }

          return $block_content;
      }
  );
  ```

- [ ] **Step 2: PHP syntax check**

  ```bash
  /c/xampp2/php/php.exe -l /c/xampp2/htdocs/dp-b2b/wp-content/themes/dreampoint-b2b/functions.php
  ```
  Expected: `No syntax errors detected`

- [ ] **Step 3: Verify in browser**

  1. Log in as `vis_full` (password: `TestVis2025!`)
  2. Add product ID 23 to cart: `http://localhost:8080/dp-b2b/shop/?add-to-cart=23`
  3. Open `http://localhost:8080/dp-b2b/checkout/`
  4. In the order summary, confirm:
     - PDV row is visible with a non-zero price
     - PDV row appears **once** (not duplicated)
     - No visual breakage in the totals block

- [ ] **Step 4: Commit**

  ```bash
  git add functions.php
  git commit -m "fix: PDV block filter — add structure guard, duplicate guard, fix text domain"
  ```

---

## Task 2 — update_post_meta_cache: Sanity Check

**Current code** (`functions.php:867–873`):
```php
add_action( 'pre_get_posts', 'dreampoint_b2b_optimize_product_query' );
function dreampoint_b2b_optimize_product_query( WP_Query $query ): void {
    if ( ! is_admin() && $query->is_main_query() && ( is_shop() || is_product_category() || is_product_tag() ) ) {
        $query->set( 'update_post_meta_cache', false );
        $query->set( 'update_post_term_cache', false );
    }
}
```

**Risk:** WooFilter Pro (WPF) builds filter widgets from product terms and meta. If WPF reads the main query's cached terms/meta, setting these to `false` could produce wrong filter counts or missing options.

**Minimum viable test:** WPF filter sidebar visible + at least one clickable filter + result count changes.

**If there is no test data** (WPF filters empty, no attributes assigned to products, etc.): skip code change, mark task as **Deferred — insufficient test data**.

- [ ] **Step 1: Check WPF is active and shows filters**

  Open `http://localhost:8080/dp-b2b/shop/` as `vis_full`.
  Confirm WooFilter Pro sidebar or top-bar shows at least one filter group (attribute, category, brand, price range, or in-stock toggle) with clickable options.

  **If no filters are visible:** mark task as Deferred. Stop here.

- [ ] **Step 2: Apply a filter — verify count is correct**

  Click one filter option (e.g. a brand, attribute, or category).
  Expected: product list narrows, result count updates, no products disappear that should be visible.

  Test on:
  - `http://localhost:8080/dp-b2b/shop/` (main shop)
  - `http://localhost:8080/dp-b2b/product-category/edukativne-igracke/` (category archive)

- [ ] **Step 3: Evaluate result**

  **If filters work correctly:** no code change. Mark task done.

  **If filters produce wrong counts, missing options, or JS errors in console:** revert.

  In `functions.php:870–871`, change:
  ```php
  $query->set( 'update_post_meta_cache', false );
  $query->set( 'update_post_term_cache', false );
  ```
  to:
  ```php
  // Reverted: WPF requires pre-fetched term/meta cache. Re-enable only after confirming WPF compatibility.
  // $query->set( 'update_post_meta_cache', false );
  // $query->set( 'update_post_term_cache', false );
  ```

- [ ] **Step 4: PHP syntax check (only if code was changed)**

  ```bash
  /c/xampp2/php/php.exe -l /c/xampp2/htdocs/dp-b2b/wp-content/themes/dreampoint-b2b/functions.php
  ```

- [ ] **Step 5: Commit (only if code was changed)**

  ```bash
  git add functions.php
  git commit -m "fix: revert update_post_meta_cache — WPF requires pre-fetched cache"
  ```

  **If no code change, no commit.**

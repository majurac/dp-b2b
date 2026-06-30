# Frontend Bundle Integration Review

**Plugin:** `uncledev-product-bundles`
**Phase:** Pre-Implementation (Phase 7)
**Date:** 2026-06-10
**Reviewer:** Claude Code

---

## 1. Existing Rendering Flow

### Template hierarchy

WooCommerce invokes `content-single-product.php` for all single product pages.
No `single-product.php` or wrapper template exists in this theme — WC core handles the page wrapper.

### content-single-product.php — structure

```
woocommerce_before_single_product (hook, line 21)
│
└─ #product-single
   └─ .product-main-info
      └─ .row
         ├─ .col-md-6.pos-sticky           ← GALLERY
         │   Custom PHP — no WC hooks
         │   Slick slider, fancybox, variation images
         │
         └─ .col-md-6                      ← SUMMARY
             .product-summary
             │   SKU, H1, price, short description, stock status
             │
             └─ woocommerce_template_single_add_to_cart()   ← line 236
                 │
                 ├─ simple.php (theme override)
                 │   do_action('woocommerce_before_add_to_cart_form')   ← line 30
                 │   <form>
                 │       do_action('woocommerce_before_add_to_cart_button')  ← line 33  ★
                 │       qty input
                 │       <button> Add to cart
                 │       do_action('woocommerce_after_add_to_cart_button')   ← line 59
                 │   </form>
                 │
                 └─ variable.php (WC core — not overridden)
                     do_action('woocommerce_before_add_to_cart_form')
                     <form>
                         variation-add-to-cart-button.php (theme override)
                             do_action('woocommerce_before_add_to_cart_button')  ← line 15  ★
                             qty input
                             <button>
                             do_action('woocommerce_after_add_to_cart_button')
                     </form>

   Product inquiry button (line 239)
   Social share (line 253)

   .wc-tabs-section
       woocommerce_output_product_data_tabs()    ← TABS (line 323)

#recomended-products                              ← RELATED (lines 333–406)
    Upsells → category fallback
    Renders via content-product.php
```

### Key architectural finding

`content-single-product.php` does **not** call `do_action('woocommerce_single_product_summary')`.
The standard WC hook that most plugins target for product page insertion is **not present**.
Any plugin hooking into `woocommerce_single_product_summary` would produce no output on this theme.

The plugin already accounts for this: `Frontend::register_hooks()` hooks into
`woocommerce_before_add_to_cart_button`, which IS fired by both overridden templates.

### Hook availability summary

| Hook | Fires in theme? | Location |
|------|----------------|----------|
| `woocommerce_before_single_product` | ✅ Yes | `content-single-product.php` line 21 |
| `woocommerce_single_product_summary` | ❌ No | Not called anywhere in theme |
| `woocommerce_before_single_product_summary` | ❌ No | Not called anywhere in theme |
| `woocommerce_after_single_product_summary` | ❌ No | Not called anywhere in theme |
| `woocommerce_before_add_to_cart_form` | ✅ Yes | `simple.php` line 30; WC core `variable.php` |
| `woocommerce_before_add_to_cart_button` | ✅ Yes | `simple.php` line 33; `variation-add-to-cart-button.php` line 15 |
| `woocommerce_after_add_to_cart_button` | ✅ Yes | Both overrides |
| `woocommerce_product_tabs` | ✅ Yes (filter) | WC `woocommerce_output_product_data_tabs()` |

---

## 2. Best Insertion Point

### Requirements

The bundle contents block must:
1. Appear in the product summary area, near the add-to-cart form
2. For **optional** mode: the opt-in checkbox must be inside `<form class="cart">` (it is a POST field)
3. For **required** mode: informational display only — no form interaction required, but same template callback handles both
4. Render before the add-to-cart button so the customer sees included items before committing

### Recommended location

`woocommerce_before_add_to_cart_button` — already in use by the plugin.

Rendered position in the page:
```
.product-summary
    SKU
    H1
    Price
    Short description
    Stock status
    [form.cart]
        ← bundle UI renders here (checkbox + items list) ← woocommerce_before_add_to_cart_button
        qty input
        [Add to Cart button]
    Product inquiry button
    Social share
```

The items list renders inside the form. This is valid HTML and required for optional mode
(the checkbox is a POST field). For required mode, the items list is informational but
co-locating it with the hidden mode input keeps the implementation unified.

### Alternative considered: `woocommerce_before_add_to_cart_form`

Would place required-mode content outside the form (semantically cleaner for required mode).
Not recommended for Phase 7: this hook fires in WC core `variable.php` but its presence in
future template overrides is not guaranteed. Also splits the implementation into two hook
registrations. Single hook is simpler and already works.

---

## 3. Integration Options

### Option A — WooCommerce hook: `woocommerce_before_add_to_cart_button`

| Dimension | Assessment |
|-----------|-----------|
| Complexity | **Low** — hook already registered by the plugin in Phase 6. Phase 7 extends existing render methods only. |
| Maintainability | **High** — standard WC hook, stable across WC versions. Self-contained in plugin. |
| Theme compatibility | **Excellent** — both theme template overrides (`simple.php`, `variation-add-to-cart-button.php`) fire this hook explicitly. |
| Plugin isolation | **High** — plugin owns all rendering. No theme modification needed. Theme is unaware of plugin. |

**Risk:** None. Hook is confirmed present in both simple and variable product paths.

---

### Option B — Custom theme hook

Add `do_action('dreampoint_after_product_summary')` or similar in `content-single-product.php`
at the desired location, and hook the plugin's renderer there.

| Dimension | Assessment |
|-----------|-----------|
| Complexity | **Medium** — requires editing `content-single-product.php` to add the hook. Low-risk change but a theme modification. |
| Maintainability | **Good** — flexible placement, but plugin now depends on a theme-specific hook. If theme is replaced, plugin loses its insertion point. |
| Theme compatibility | **Theme-specific** — only works if this hook is present in the active theme. Not portable. |
| Plugin isolation | **Medium** — plugin is decoupled from theme markup but coupled to the theme's hook API. |

**Risk:** Creates a dependency between the plugin and this specific theme version. Breaks if theme is updated without preserving the hook.

---

### Option C — Shortcode rendered from template

Register `[udp_bundle_contents]` shortcode and call it from `content-single-product.php`.

| Dimension | Assessment |
|-----------|-----------|
| Complexity | **High** — shortcode registration + `content-single-product.php` modification + output buffering for the shortcode. |
| Maintainability | **Low** — shortcode in a template is brittle. Editor could inadvertently remove it if template is ever edited as "content." |
| Theme compatibility | **Requires theme modification** — template must call the shortcode. |
| Plugin isolation | **Low** — theme must explicitly invoke the plugin's shortcode. |

**Risk:** High operational risk. Shortcodes in PHP templates gain nothing over direct function calls and add complexity.

---

### Option D — Dedicated template part

Create `templates/udp-bundle-items.php` in the plugin and call `get_template_part()` from
the theme template or from within `woocommerce_template_single_add_to_cart()`.

| Dimension | Assessment |
|-----------|-----------|
| Complexity | **Medium** — requires theme template modification to call the template part. |
| Maintainability | **OK** — template is overridable, but theme must know to call it. |
| Theme compatibility | **Requires theme modification** — `content-single-product.php` must include a `get_template_part()` call. |
| Plugin isolation | **Low** — theme must explicitly include the plugin template part. Plugin cannot self-insert. |

**Risk:** Breaks portability. Also contradicts the task requirement ("Do NOT create a template override inside the plugin").

---

## 4. Recommendation

**Option A — `woocommerce_before_add_to_cart_button`**

This is the only option that requires no theme modification and is already fully operational
in the current codebase. The plugin hooks there for Phase 6 and Phase 7 extends the same
callback. The insertion point is correct for both modes. Both template overrides confirm the
hook fires reliably.

No other option satisfies the constraint of theme-independent plugin rendering given that
`woocommerce_single_product_summary` is absent from this theme.

---

## 5. Required Plugin API

The plugin already has the correct hook in place. Phase 7 does not need to change the hook registration.

**Existing registration (unchanged):**

```php
// class-bundle-frontend.php — Frontend::register_hooks()
add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'render_bundle_ui' ] );
```

**Hook:** `woocommerce_before_add_to_cart_button`
**Priority:** 10 (default)
**Callback:** `Frontend::render_bundle_ui()`

**Phase 7 change — within existing callbacks only:**

`render_bundle_ui()` dispatches to:
- `render_optional_checkbox( $product_id )` — for optional mode
- `render_required_label( $product_id )` — for required mode

Phase 7 adds a shared private method `render_bundle_items_list( int $product_id )` called
from both methods when `Data::get_items()` returns a non-empty array. No new hook. No new
registration. The insertion point is unchanged.

**No plugin API change is needed for the integration layer.**

---

## 6. Future Compatibility

| Scenario | Compatible? | Notes |
|----------|-------------|-------|
| Non-bundle products (mode=disabled) | ✅ Yes | `render_bundle_ui()` returns early for disabled mode. No output. |
| Required mode | ✅ Yes | Hidden mode input + items list renders inside form. |
| Optional mode | ✅ Yes | Checkbox (POST field) + items list inside form. Checkbox must be in form. |
| Variable product (WC core template) | ✅ Yes | `variation-add-to-cart-button.php` override fires the hook. |
| Future per-item opt-in UI | ✅ Yes | Would add per-item checkboxes inside form — correct layer. |
| Theme template update | ⚠️ Monitor | If `simple.php` or `variation-add-to-cart-button.php` are revised, confirm `woocommerce_before_add_to_cart_button` is preserved at line 33 / line 15 respectively. |
| WooCommerce core update | ✅ Low risk | `woocommerce_before_add_to_cart_button` is a stable WC API hook since WC 2.x. |
| Block-based checkout | ✅ Not affected | Product page templates and cart/checkout are separate concerns. |

---

## Conclusion

Phase 7 should integrate bundle contents through the **WooCommerce hook `woocommerce_before_add_to_cart_button`**.

**Justification:**

1. The hook is already registered by the plugin — Phase 7 is an extension of existing callback methods, not a new insertion point.
2. Both theme template overrides (`simple.php`, `variation-add-to-cart-button.php`) explicitly fire this hook.
3. `woocommerce_single_product_summary` — the standard product summary hook — is not present in this theme. Hook-based alternatives that rely on the standard WC summary hook would produce no output.
4. The optional mode checkbox is a POST field and must render inside `<form class="cart">`. This hook fires inside the form, which is the correct context.
5. No theme modifications are required. The plugin is self-contained.

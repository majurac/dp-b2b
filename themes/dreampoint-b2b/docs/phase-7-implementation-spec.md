# Phase 7 — Implementation Specification

**Plugin:** `uncledev-product-bundles` v1.0.0 → v1.1.0
**Date:** 2026-06-09
**Prerequisite reading:** `docs/display-stand-use-case-analysis.md`
**Status:** Implementation-ready

---

## 1. Executive Summary

Phase 7 activates three placeholders left intentionally open in the foundation commit:

| Placeholder location | Phase 7 action |
|----------------------|----------------|
| `Fields::get_fields()` — comment at line 74 | Add ACF bundle items repeater |
| `Data::get_items()` — `$raw_items = []` at line 82 | Implement ACF read + normalization |
| `Cart::validate_bundle_before_add()` — variation comment at line 136 | Load variation product for stock check |
| `Cart::handle_bundle_after_add()` — variation comment at line 298 | Pass variation attributes when adding to cart |
| `bundle-frontend.js` — stub file | Implement bundle item summary display |
| `uninstall.php` — comment at line 18 | Add repeater meta key to cleanup list |

**What Phase 7 delivers:**

An admin can open any WooCommerce product, configure a bundle of specific items (including variations and quantities), and those items will be automatically added to the cart when a customer purchases the parent product. A frontend summary shows the customer what is included before they add to cart.

**What Phase 7 does NOT change:**

- Cart engine: parent-child meta, quantity sync, removal propagation, anti-merge — unchanged
- Order engine: two-pass meta resolution, `_udp_is_bundle_parent`, `_udp_bundle_parent_item_id` — unchanged
- Plugin API: all public functions in the entry file — unchanged
- All existing filters and actions — unchanged (new ones may be added, documented in sections below)

---

## 2. Admin Configuration Model

### 2.1 ACF Field Group

Field group key: `group_udp_product_bundle` (existing — do not create a new group)
Phase 7 adds two new fields to the existing group: the `_udp_bundle_enabled` checkbox (first field,
feature gate) and the `_udp_bundle_items` repeater (conditional on mode). The existing
`_udp_bundle_mode` select gains conditional logic that hides it when bundle is not enabled.

### 2.2 Field Definitions

#### New field: Bundle Enabled (Phase 7 addition — first field)

| Property | Value |
|----------|-------|
| Key | `field_udp_bundle_enabled` |
| Name | `_udp_bundle_enabled` |
| Label | Enable Bundle |
| Type | `true_false` |
| Default | `0` (false) |
| Message | This product includes a bundle of additional items |
| Purpose | Per-product feature gate. When false, all bundle UI is hidden and bundle logic is skipped at runtime. Prevents bundle configuration fields from appearing on unrelated products. |

**Admin UX effect:** On 99% of products this field is the only visible bundle element (unchecked,
no visual overhead). Bundle Mode and Bundle Items only appear when this is checked.
See conditional logic in Section 2.3.

**Runtime effect:** `Data::is_bundle_product( $product_id )` reads this flag. Cart validation
and handling exit early when false — no ACF reads, no item iteration on non-bundle products.

#### Existing field (conditional logic updated — see 2.3)

| Property | Value |
|----------|-------|
| Key | `field_udp_bundle_mode` |
| Name | `_udp_bundle_mode` |
| Label | Bundle Mode |
| Type | select |
| Choices | `disabled`, `optional`, `required` |
| Default | `disabled` |
| Purpose | Controls bundle behavior for this product |
| **Conditional logic** | **Show when `_udp_bundle_enabled` = 1 (Phase 7 addition)** |

#### New field: Bundle Items Repeater

| Property | Value |
|----------|-------|
| Key | `field_udp_bundle_items` |
| Name | `_udp_bundle_items` |
| Label | Bundle Items |
| Type | `repeater` |
| Required | No |
| Min rows | 0 |
| Max rows | 0 (unlimited) |
| Layout | `table` |
| Button label | Add Bundle Item |
| Conditional logic | Show when `_udp_bundle_mode` ≠ `disabled` |
| Purpose | Defines which products are added with this product |

#### Repeater sub-field: Bundle Product

| Property | Value |
|----------|-------|
| Key | `field_udp_bi_product` |
| Name | `bundle_product` |
| Label | Product |
| Type | `post_object` |
| Post types | `product` |
| Filters | `search` |
| Return format | `id` |
| Required | Yes |
| Allow null | No |
| Multiple | No |
| Purpose | The WooCommerce product to add as a bundle item |

Note: Only `post_type = product` (parent products). Variation is handled by a separate field.
Variable products should be selected here; the specific variation is set in Bundle Variation ID.

#### Repeater sub-field: Bundle Variation ID

| Property | Value |
|----------|-------|
| Key | `field_udp_bi_variation_id` |
| Name | `bundle_variation_id` |
| Label | Variation ID |
| Type | `number` |
| Default value | `0` |
| Min | 0 |
| Required | No |
| Instructions | Enter `0` for simple products. For variable products, enter the WooCommerce variation post ID. Leave `0` to treat the item as the parent product (not recommended for variable products). |
| Purpose | Identifies the specific variation to add. 0 = simple or unspecified. |

**Admin JS enhancement (Phase 7, see Section 5.5):** When the Bundle Product field changes
and the selected product is variable, a small admin JS snippet should replace the manual
number input with a populated select of that product's variations. This does not change
the stored value — only the input presentation.

#### Repeater sub-field: Quantity

| Property | Value |
|----------|-------|
| Key | `field_udp_bi_quantity` |
| Name | `bundle_quantity` |
| Label | Quantity |
| Type | `number` |
| Default value | `1` |
| Min | `1` |
| Step | `1` |
| Required | Yes |
| Instructions | Quantity added per parent product purchased. Automatically scales with parent quantity. |
| Purpose | Per-item quantity in the bundle. Multiplied by stand quantity at cart time. |

#### Repeater sub-field: Required Flag

| Property | Value |
|----------|-------|
| Key | `field_udp_bi_required` |
| Name | `bundle_item_required` |
| Label | Always Included |
| Type | `true_false` |
| Default value | `1` (true) |
| Required | No |
| Message | This item cannot be removed from the bundle |
| Purpose | Frontend display signal. When true, item is shown as "Always included" in the summary. Does not affect Phase 7 cart behavior — all items are added regardless. Reserved for future per-item opt-in UX. |

#### Repeater sub-field: Sort Order

| Property | Value |
|----------|-------|
| Key | `field_udp_bi_sort_order` |
| Name | `bundle_sort_order` |
| Label | Sort Order |
| Type | `number` |
| Default value | `0` |
| Min | 0 |
| Required | No |
| Instructions | Lower values display first. Leave 0 to use repeater row order. |
| Purpose | Explicit display order for the frontend item summary. When all values are 0, repeater row order is used. |

### 2.3 Complete ACF Schema (programmatic registration)

The following is the complete `get_fields()` return value after Phase 7.
Field order: `field_udp_bundle_enabled` (new, first) → `field_udp_bundle_mode` (existing,
now conditional on enabled) → `field_udp_bundle_items` (new repeater, conditional on mode).

```php
[
    // New first field — per-product feature gate
    'key'           => 'field_udp_bundle_enabled',
    'label'         => __( 'Enable Bundle', 'uncledev-product-bundles' ),
    'name'          => '_udp_bundle_enabled',
    'type'          => 'true_false',
    'message'       => __( 'This product includes a bundle of additional items', 'uncledev-product-bundles' ),
    'default_value' => 0,
    'wrapper'       => [ 'width' => '50' ],
],
[
    // Existing field — now conditional on bundle_enabled
    'key'           => 'field_udp_bundle_mode',
    'name'          => '_udp_bundle_mode',
    'conditional_logic' => [
        [
            [
                'field'    => 'field_udp_bundle_enabled',
                'operator' => '==',
                'value'    => '1',
            ],
        ],
    ],
    // ... (all other existing properties unchanged)
],
[
    'key'             => 'field_udp_bundle_items',
    'label'           => __( 'Bundle Items', 'uncledev-product-bundles' ),
    'name'            => '_udp_bundle_items',
    'type'            => 'repeater',
    'instructions'    => __( 'Products automatically added when this product is purchased.', 'uncledev-product-bundles' ),
    'required'        => 0,
    'min'             => 0,
    'max'             => 0,
    'layout'          => 'table',
    'button_label'    => __( 'Add Bundle Item', 'uncledev-product-bundles' ),
    'conditional_logic' => [
        [
            [
                'field'    => 'field_udp_bundle_mode',
                'operator' => '!=',
                'value'    => 'disabled',
            ],
        ],
    ],
    'sub_fields' => [
        [
            'key'           => 'field_udp_bi_product',
            'label'         => __( 'Product', 'uncledev-product-bundles' ),
            'name'          => 'bundle_product',
            'type'          => 'post_object',
            'required'      => 1,
            'post_type'     => [ 'product' ],
            'filters'       => [ 'search' ],
            'return_format' => 'id',
            'allow_null'    => 0,
            'multiple'      => 0,
            'wrapper'       => [ 'width' => '40' ],
        ],
        [
            'key'          => 'field_udp_bi_variation_id',
            'label'        => __( 'Variation ID', 'uncledev-product-bundles' ),
            'name'         => 'bundle_variation_id',
            'type'         => 'number',
            'instructions' => __( '0 for simple products. Enter variation post ID for variable products.', 'uncledev-product-bundles' ),
            'required'     => 0,
            'default_value' => 0,
            'min'          => 0,
            'wrapper'      => [ 'width' => '20' ],
        ],
        [
            'key'           => 'field_udp_bi_quantity',
            'label'         => __( 'Quantity', 'uncledev-product-bundles' ),
            'name'          => 'bundle_quantity',
            'type'          => 'number',
            'required'      => 1,
            'default_value' => 1,
            'min'           => 1,
            'step'          => 1,
            'wrapper'       => [ 'width' => '15' ],
        ],
        [
            'key'           => 'field_udp_bi_required',
            'label'         => __( 'Always Included', 'uncledev-product-bundles' ),
            'name'          => 'bundle_item_required',
            'type'          => 'true_false',
            'default_value' => 1,
            'message'       => __( 'Cannot be removed by customer', 'uncledev-product-bundles' ),
            'wrapper'       => [ 'width' => '15' ],
        ],
        [
            'key'           => 'field_udp_bi_sort_order',
            'label'         => __( 'Sort', 'uncledev-product-bundles' ),
            'name'          => 'bundle_sort_order',
            'type'          => 'number',
            'default_value' => 0,
            'min'           => 0,
            'wrapper'       => [ 'width' => '10' ],
        ],
    ],
],
```

---

## 3. Data Model

### 3.1 Storage

ACF stores repeater data as individual post meta rows.
For a product with 2 bundle items, the following meta is written:

```
_udp_bundle_items             = 2
_udp_bundle_items_0_bundle_product       = 101       (product post ID)
_udp_bundle_items_0_bundle_variation_id  = 0
_udp_bundle_items_0_bundle_quantity      = 12
_udp_bundle_items_0_bundle_item_required = 1
_udp_bundle_items_0_bundle_sort_order    = 0
_udp_bundle_items_1_bundle_product       = 102
_udp_bundle_items_1_bundle_variation_id  = 203       (variation post ID)
_udp_bundle_items_1_bundle_quantity      = 6
_udp_bundle_items_1_bundle_item_required = 1
_udp_bundle_items_1_bundle_sort_order    = 0
```

ACF `get_field( '_udp_bundle_items', $product_id )` returns a PHP array of associative arrays:

```php
[
    [
        'bundle_product'       => 101,   // int (return_format = 'id')
        'bundle_variation_id'  => 0,
        'bundle_quantity'      => 12,
        'bundle_item_required' => true,  // ACF true_false returns bool
        'bundle_sort_order'    => 0,
    ],
    [
        'bundle_product'       => 102,
        'bundle_variation_id'  => 203,
        'bundle_quantity'      => 6,
        'bundle_item_required' => true,
        'bundle_sort_order'    => 0,
    ],
]
```

Returns `false` or `[]` when no rows are configured.

### 3.2 Normalized Output Format

`Data::get_items()` must return items in this format (backward-compatible with existing cart/validation code):

```php
[
    [
        'product_id'   => 101,
        'variation_id' => 0,
        'quantity'     => 12,
        'required'     => true,
        'sort_order'   => 0,
    ],
    [
        'product_id'   => 102,
        'variation_id' => 203,
        'quantity'     => 6,
        'required'     => true,
        'sort_order'   => 0,
    ],
]
```

The existing cart and validation code reads only `product_id`, `variation_id`, and `quantity`.
`required` and `sort_order` are new keys — existing code ignores unknown keys safely.

### 3.3 `Data::is_bundle_product()` Implementation Contract (Phase 7 addition)

```php
public static function is_bundle_product( int $product_id ): bool {
    return (bool) get_field( '_udp_bundle_enabled', $product_id );
}
```

Called in `Cart::validate_bundle_before_add()` and `Cart::handle_bundle_after_add()` before
any other bundle logic. Early return (`return` / `true` with no error) when false — prevents
ACF reads and item iteration on non-bundle products.

### 3.4 `Data::get_items()` Implementation Contract

```php
public static function get_items( int $product_id ): array {
    $rows = get_field( '_udp_bundle_items', $product_id );

    $raw_items = [];

    if ( ! empty( $rows ) && is_array( $rows ) ) {
        // Sort by sort_order if any non-zero value exists.
        usort( $rows, fn( $a, $b ) => ( $a['bundle_sort_order'] ?? 0 ) <=> ( $b['bundle_sort_order'] ?? 0 ) );

        foreach ( $rows as $row ) {
            $product_id_item = (int) ( $row['bundle_product'] ?? 0 );
            if ( $product_id_item < 1 ) {
                continue; // Skip malformed rows.
            }

            $raw_items[] = [
                'product_id'   => $product_id_item,
                'variation_id' => (int) ( $row['bundle_variation_id'] ?? 0 ),
                'quantity'     => max( 1, (int) ( $row['bundle_quantity'] ?? 1 ) ),
                'required'     => (bool) ( $row['bundle_item_required'] ?? true ),
                'sort_order'   => (int) ( $row['bundle_sort_order'] ?? 0 ),
            ];
        }
    }

    // Existing filter fires on already-normalized array.
    return (array) apply_filters( 'uncledev_bundles_items', $raw_items, $product_id );
}
```

### 3.5 Input / Output Examples

**Example A — display stand (simple products only)**

Input (ACF): stand product ID 500, two rows: glasses ×12, case ×2

```php
Data::get_items( 500 )
// Returns:
[
    [ 'product_id' => 201, 'variation_id' => 0, 'quantity' => 12, 'required' => true, 'sort_order' => 0 ],
    [ 'product_id' => 202, 'variation_id' => 0, 'quantity' => 2,  'required' => true, 'sort_order' => 1 ],
]
```

**Example B — stand with specific variation**

Input: row 0 — product 201, variation 305, qty 12

```php
[ 'product_id' => 201, 'variation_id' => 305, 'quantity' => 12, 'required' => true, 'sort_order' => 0 ]
```

**Example C — no bundle configured**

```php
Data::get_items( 999 )
// ACF returns false or empty array.
// Returns: []
```

### 3.6 Edge Cases

| Scenario | Behaviour |
|----------|-----------|
| `get_field()` returns `false` | `$raw_items` stays `[]`, filter fires with `[]` |
| `get_field()` returns `[]` (rows deleted) | Same as above |
| Row with `bundle_product = 0` | Row skipped during normalization |
| Row with `bundle_quantity < 1` | Forced to 1 via `max(1, ...)` |
| `bundle_variation_id` references a variation whose parent ≠ `bundle_product` | Mismatch detected in cart validation (see Section 9) |
| Product deleted from WooCommerce after bundle is configured | `wc_get_product()` returns false; existing validation blocks add-to-cart with existing error notice |
| `uncledev_bundles_items` filter removes all items | Returns `[]`, cart adds stand without children (existing behavior) |
| `uncledev_bundles_items` filter adds items not in ACF | Allowed; validation and cart treat injected items identically |

---

## 4. Bundle Composition Rules

### 4.1 What Already Exists (unchanged in Phase 7)

- Bundle mode: `disabled`, `optional`, `required` — read via `Data::get_mode()`
- Bundle intent resolution: `Cart::resolve_bundle_intent()` — reads POST or defaults to true
- Purchasability check: `wc_get_product()->is_purchasable()`
- Stock check: `managing_stock() && get_stock_quantity() < required_qty`
- OOS behavior: `uncledev_bundles_oos_behavior` filter (`block` or `skip`)
- Item validation filter: `uncledev_bundles_validate_item` (returns `true` or `WP_Error`)
- Quantity scaling: `item['quantity'] * $stand_quantity`
- All items in `get_items()` are added — no per-item selection in Phase 7

### 4.2 What Phase 7 Adds

**Required item behaviour (existing mode = `required` + new ACF data)**
All items in the repeater are added. The `bundle_item_required` flag is informational
and is passed to the frontend summary for display only. No cart behavior change.

**Optional item behaviour (existing mode = `optional` + new ACF data)**
All items in the repeater are added when the customer opts in via the checkbox.
Per-item opt-in/opt-out is NOT in Phase 7 scope. Reserved for future release.

**Quantity validation (new: Phase 7 activates)**
Stock check in `validate_bundle_before_add()` uses the variation product when `variation_id > 0`.
Type and parent checks run first — if the variation is invalid, add-to-cart is blocked immediately
before the stock check runs (no fallback to parent; see Section 7.2 Change 1 and Section 9a).
Stock must be sufficient for `item_quantity × stand_quantity` across all items.

**Variation validation (new: Phase 7 activates)**
Before adding a bundle item with `variation_id > 0`, verify:
1. The variation product exists (`wc_get_product( variation_id )` returns a `WC_Product_Variation`)
2. The variation's parent matches `product_id` (`$variation->get_parent_id() === $item_product_id`)
3. The variation is purchasable

If parent mismatch is detected: block add-to-cart with `wc_add_notice( ..., 'error' )`. Treated
as a configuration error — the bundle cannot be added until the admin corrects the repeater.
The primary check is in `validate_bundle_before_add()`; a secondary guard in
`handle_bundle_after_add()` provides defense-in-depth for programmatic adds.

**Missing product handling**
Product deleted from WooCommerce after bundle is configured:
- `wc_get_product()` returns false
- Existing error notice fires: "Bundle item X is not available for purchase"
- Bundle + stand blocked

**Deleted product in repeater**
Same as missing product. The admin should remove deleted products from the repeater.
No automatic cleanup on product deletion in Phase 7 (future enhancement).

**Out-of-stock handling (existing, activated by Phase 7 data)**
`uncledev_bundles_oos_behavior` filter controls: `block` (default) or `skip`.
When `block`: entire stand + bundle cannot be added. Customer sees which item is OOS.
When `skip`: OOS item is skipped, remaining items are added, stand proceeds.

**Visibility handling (existing filter, project-level)**
`uncledev_bundles_validate_item` filter — B2B project code hooks here to enforce:
- bucket restrictions
- brand/category visibility
- role-based access

Plugin provides no built-in visibility enforcement. This is intentional — visibility
is project-specific and belongs in the theme/project layer.

---

## 5. Admin UX Specification

### 5.1 Editor Experience Overview

Admin opens a product edit page in WooCommerce admin.
The "Product Bundle" field group appears below the product data meta box.

**Flow for creating a display stand bundle:**

1. Check the "Enable Bundle" checkbox — Bundle Mode becomes visible
2. Set Bundle Mode → "Required — bundle always added, no opt-out"
3. The Bundle Items repeater becomes visible (via ACF conditional logic)
4. Click "Add Bundle Item" — a new row appears
5. In the Product column: search by product name or SKU, select the glasses product
6. In the Variation ID column: enter the variation post ID (or use the JS enhancement if implemented)
7. In the Quantity column: enter 12
8. Toggle "Always Included" to on (default)
9. Repeat for additional items
10. Drag rows to reorder (ACF repeater native behavior)
11. Save the product (Update)

### 5.2 Field Layout in Admin (table layout)

```
| Product               | Variation ID | Qty | Always Included | Sort |
|-----------------------|--------------|-----|-----------------|------|
| [Glasses Model A]     | [    0     ] | [12]| [✓]             | [ 0] |
| [Glasses Case]        | [    0     ] | [ 2]| [✓]             | [ 1] |
| [+ Add Bundle Item]
```

### 5.3 Validation Messages (admin-side, ACF native)

| Scenario | Message shown |
|----------|---------------|
| Product field empty on save | "This field is required" (ACF native) |
| Quantity field empty on save | "This field is required" (ACF native) |
| Quantity < 1 | Corrected to 1 on read (server-side guard in `Data::get_items()`) |
| Bundle mode = disabled, items configured | Items are stored but ignored at runtime; no admin warning in Phase 7 |

### 5.4 Success States

| State | Expected outcome |
|-------|-----------------|
| Product saved with bundle items | ACF writes meta, next product page load shows configured items in repeater |
| Bundle item row reordered | Sort order reflected in repeater; `get_items()` returns rows in new order |
| Bundle item row deleted | Row removed, meta updated, cart will not include deleted item |
| Bundle mode changed to `disabled` | Items remain in DB (non-destructive), repeater is hidden, `get_items()` still fires filter but items are not added to cart (mode gate prevents it) |

### 5.5 Admin JS Enhancement — Variation Selector

**This is an enhancement, not MVP.** The MVP (manual Variation ID number field) is
fully functional without JS.

**Goal:** When admin selects a variable product in the Bundle Product field, replace
the static number input for Variation ID with a populated select of that product's
variations.

**Implementation approach:**

- Hook: `acf/render_field/key=field_udp_bi_variation_id` to inject a `data-field-key` attribute
- Admin JS file: `assets/js/bundle-admin.js` (new file, not in current plugin)
- Event: `acf/fields/post_object/change` on `field_udp_bi_product`
- On change: fetch `/wp-json/wc/v3/products/{id}/variations?per_page=100` (authenticated)
- On response: replace number input with `<select>` populated from variation names/SKUs
- On simple product: restore number input with value 0 and disable it

**Enqueue condition:** Admin pages only, specifically the `post.php` and `post-new.php`
screens with `post_type=product`.

**If not implemented:** Admin enters variation post ID manually. Valid but poor UX
for products with many variations.

---

## 6. Frontend UX Specification

> **Design decision — modal over inline list:**
> The validated primary use case is a display stand containing ~50 bundled products.
> Rendering 50 items as an inline list inside the product summary causes unacceptable
> vertical growth that buries the add-to-cart button. Bundle contents are displayed in
> a Fancybox modal instead. The product summary stays compact regardless of bundle size.
> This decision applies to both required and optional modes.

### 6.1 Bundle Summary Display

The product summary shows a compact one-line trigger. The full item list opens in a
Fancybox modal when the customer clicks the CTA.

**Required mode — display stand example:**

```
.product-summary
    [SKU]  [H1 product name]  [Price]  [Short description]  [Stock]

    ┌────────────────────────────────────────────────────────┐
    │  Includes 50 items    [View contents  →]               │
    └────────────────────────────────────────────────────────┘

    [qty]  [Add to cart]
```

Note: `"Includes 50 items"` is the plugin default string. The Dreampoint B2B project
overrides this via `uncledev_bundles_summary_label` filter:
`sprintf( 'Sadrži %d modela naočala', $count )` — project-level customization,
not a plugin default. The filter receives `$count` and `$product_id`.

**Optional mode:**

```
    [✓] Dodaj sadržaj paketa    [Pregled sadržaja  →]
```

Checkbox controls opt-in (POST field). The CTA link opens the modal independently
of checkbox state — customer can preview contents before deciding to opt in.

**Scale invariance:** 10 items, 50 items, 100 items — the product summary renders
identically. Only the modal content height increases. Overflow scroll handles depth.

**Summary count label** is filterable:

```php
/**
 * Filter: customize the item count label shown in the product summary.
 *
 * Plugin default: "Includes N items" (English, context-neutral).
 * Project code overrides per product, category, or context via this filter.
 * Example override: sprintf( 'Sadrži %d modela naočala', $count ) for display stands.
 *
 * @param string $label      Default count label ("Includes N items").
 * @param int    $count      Number of configured bundle items.
 * @param int    $product_id Bundle parent product ID.
 */
apply_filters( 'uncledev_bundles_summary_label', $label, $count, $product_id )
```

Plugin implementation:

```php
$label = sprintf(
    /* translators: %d: number of bundle items */
    __( 'Includes %d items', 'uncledev-product-bundles' ),
    $count
);
$label = apply_filters( 'uncledev_bundles_summary_label', $label, $count, $product_id );
```

**Modal title** is filterable:

```php
/**
 * Filter: customize the modal heading.
 *
 * @param string $title      Default: "Package Contents".
 * @param int    $product_id Bundle parent product ID.
 */
apply_filters( 'uncledev_bundles_modal_title', $title, $product_id )
```

### 6.2 Server-Side Rendering

`Frontend::render_optional_checkbox()` and `Frontend::render_required_label()` each:
1. Render the compact summary trigger (count label + CTA link) — visible in product summary.
2. Call a shared private method `render_bundle_modal( int $product_id, array $items ): void`
   which outputs the hidden modal content div.

The hidden modal div is placed immediately after the visible trigger, inside the same
`woocommerce_before_add_to_cart_button` output. Both elements are inside `<form class="cart">`.
This is valid HTML5 (non-interactive block elements inside a form are permitted).

**If `Data::get_items()` returns empty:** No trigger and no modal div are rendered.
The form renders with no bundle UI, same as pre-Phase-7 behavior.

**Modal content per item:**
- Product thumbnail (48×48, `loading="lazy"` — hidden until modal opens, not an LCP candidate)
- Product name (variation name if `variation_id > 0`, parent product name otherwise)
- SKU / catalog number — rendered only if `get_sku()` returns a non-empty string
- Quantity (`× N`)
- No pricing. No selection. No quantity controls.

**No AJAX.** All modal content is server-rendered into the page HTML.
Fancybox reveals the hidden div as a modal. No HTTP requests on modal open.

### 6.3 HTML Structure

**Visible summary trigger (inside `<form class="cart">`):**

```html
<!-- Required mode -->
<div class="udp-bundle-ui udp-bundle-required"
     data-udp-bundle-mode="required"
     data-product-id="<?php echo esc_attr( $product_id ); ?>">
    <input type="hidden" name="udp_bundle_mode" value="required" />
    <div class="udp-bundle-summary">
        <span class="udp-bundle-count">
            <?php echo esc_html( $summary_label ); ?>
        </span>
        <a class="udp-bundle-view-btn"
           href="#udp-bundle-contents-<?php echo esc_attr( $product_id ); ?>"
           data-fancybox
           data-type="inline"
           aria-label="<?php esc_attr_e( 'View bundle contents', 'uncledev-product-bundles' ); ?>">
            <?php esc_html_e( 'View contents', 'uncledev-product-bundles' ); ?>
        </a>
    </div>
</div>

<!-- Optional mode -->
<div class="udp-bundle-ui udp-bundle-optional"
     data-udp-bundle-mode="optional"
     data-product-id="<?php echo esc_attr( $product_id ); ?>">
    <input type="hidden" name="udp_bundle_mode" value="optional" />
    <div class="udp-bundle-summary">
        <label class="udp-bundle-checkbox-label">
            <input
                type="checkbox"
                name="udp_bundle_opt_in"
                id="udp-bundle-opt-in-<?php echo esc_attr( $product_id ); ?>"
                value="1"
                checked="checked"
                class="udp-bundle-checkbox"
            />
            <span><?php echo esc_html( $checkbox_label ); ?></span>
        </label>
        <a class="udp-bundle-view-btn"
           href="#udp-bundle-contents-<?php echo esc_attr( $product_id ); ?>"
           data-fancybox
           data-type="inline"
           aria-label="<?php esc_attr_e( 'View bundle contents', 'uncledev-product-bundles' ); ?>">
            <?php esc_html_e( 'View contents', 'uncledev-product-bundles' ); ?>
        </a>
    </div>
</div>
```

**Hidden modal content div (immediately follows trigger, inside same form output):**

```html
<div id="udp-bundle-contents-<?php echo esc_attr( $product_id ); ?>"
     class="udp-bundle-modal-content"
     style="display:none;">
    <h2 class="udp-bundle-modal-title">
        <?php echo esc_html( $modal_title ); ?>
    </h2>
    <ul class="udp-bundle-items-list">
        <li class="udp-bundle-item"
            data-product-id="201"
            data-variation-id="305">
            <span class="udp-bundle-item-thumb">
                <img src="..."
                     alt="<?php echo esc_attr( $item_name ); ?>"
                     width="48" height="48"
                     loading="lazy" />
            </span>
            <span class="udp-bundle-item-name">Glasses Model A (Red)</span>
            <span class="udp-bundle-item-sku">SKU-001</span><!-- omitted if SKU empty -->
            <span class="udp-bundle-item-qty">× 12</span>
        </li>
        <!-- ... one <li> per configured item ... -->
    </ul>
</div>
```

### 6.4 Modal Technology — Fancybox (existing dependency)

**No new dependency is introduced.** Fancybox v5 is already loaded on all product pages:

```php
// functions.php — theme
function dreampoint_b2b_needs_fancybox(): bool {
    return is_product() || is_singular( 'post' ) || is_front_page();
}
```

Fancybox is initialized globally in `fancybox-init.js`:

```js
Fancybox.bind( '[data-fancybox]', { ... } );
```

This global binding automatically catches the plugin's `data-fancybox` trigger.
**The plugin emits the attributes; the theme handles the Fancybox initialization.**
No plugin-side Fancybox initialization code is needed.

**Inline modal activation:** The trigger uses `data-type="inline"` and `href="#id"`.
Fancybox opens the hidden div as an inline modal — consistent with existing iframe
modal usage in `content-single-product.php`.

**Fancybox options** (`Thumbs: false`, `compact: false`, `hideScrollbar: false`) are set
by the theme's global init. `hideScrollbar: false` is critical — allows scrolling inside
the modal for bundles with many items.

### 6.5 JS Behavior

**Required mode:** No plugin JS required. Fancybox handles modal open/close.

**Optional mode:** `bundle-frontend.js` may provide UX polish (e.g., visually dim the
CTA when checkbox is unchecked). This is not required for Phase 7 MVP.

**Phase 7 `bundle-frontend.js`:** Stub file only. No functional JS required for the
display stand use case. The file exists to satisfy the enqueue registration; it will
be populated in a future phase when optional mode per-item UX is implemented.

```js
( function () {
    'use strict';
    // Phase 7: bundle modal is handled by Fancybox (theme global init).
    // No plugin JS required for required mode.
    // Optional mode enhancements are deferred to a future phase.
} )();
```

**No AJAX.** Modal content is server-rendered. JS does not fetch product or variation data.

### 6.6 Accessibility

- Modal trigger: `<a>` with `aria-label` describing the action ("View bundle contents")
- Fancybox handles focus trapping while modal is open (built-in behavior)
- Fancybox restores focus to the trigger element on modal close (built-in behavior)
- Modal heading (`<h2>`) provides screen reader context when modal opens
- Item thumbnails: `alt` text set to the product/variation name
- Item list: semantic `<ul>/<li>` structure
- SKU field: rendered as `<span>` — no additional ARIA role needed
- Keyboard: Fancybox responds to Escape (close) and Tab (navigation) natively

### 6.7 Responsive Behavior

- Modal width: Fancybox default (`compact: false`) — sized to content, max-width via CSS
- Tall bundles (50+ items): `max-height` on `.udp-bundle-modal-content` with `overflow-y: auto`
- On mobile: Fancybox fills the viewport (built-in responsive behavior with `compact: false`)
- Item row layout: thumbnail + name + sku + qty — flexbox row, wraps gracefully on narrow screens

### 6.8 Data Contract: Localized JS (if needed)

Phase 7 does not require `wp_localize_script`. All modal data is in the server-rendered DOM.
If future phases require JS access to item data, add:

```php
wp_localize_script( 'udp-bundle-frontend', 'udpBundleData', [
    'items' => [], // populated in future phase
] );
```

### 6.9 Enqueue Condition Update

Current: `is_product() || is_cart()`
Phase 7 change: no change to enqueue condition. CSS remains on product pages and cart.
JS is enqueued only on `is_product()` — not needed on cart.

```php
if ( is_product() ) {
    wp_enqueue_script( 'udp-bundle-frontend', ... );
}
if ( is_product() || is_cart() ) {
    wp_enqueue_style( 'udp-bundle-frontend', ... );
}
```

**Fancybox dependency note:** The plugin does NOT declare `dreampoint-b2b-fancybox` as a
script dependency. The plugin emits `data-fancybox` attributes; the theme's global init
catches them. If deployed to a theme without Fancybox, the trigger link opens as a plain
anchor (the hidden div becomes the click target). A future portability enhancement could
add a lightweight fallback modal, but this is out of scope for Phase 7.

### 6.10 Cart Messaging

Existing behavior: children appear in cart with "— Bundle item" label.
Phase 7 change: the `uncledev_bundles_item_label` filter remains unchanged.
Cart visual grouping (nesting children under parent in cart template) is a
theme-level override — not a plugin responsibility.

---

## 7. Cart Integration Rules

### 7.1 Confirmed Compatibility

All existing cart engine behavior is compatible with Phase 7 data. No structural changes.

| Feature | Status | Notes |
|---------|--------|-------|
| Parent-child meta | Unchanged | `_udp_bundle_parent`, `_udp_bundle_children` |
| Quantity synchronization | Unchanged | `_udp_bundle_configured_qty × stand_qty` |
| Removal propagation | Unchanged | Children removed when parent removed |
| Anti-merge protection | Unchanged | `_udp_bundle_id` prevents row merge |
| Bundle intent (optional checkbox) | Unchanged | POST field read by `resolve_bundle_intent()` |
| Programmatic add (Quick Order) | Unchanged | Defaults to including bundle |

### 7.2 Phase 7 Changes to Cart

**Change 0: Early exit via `Data::is_bundle_product()` (both methods)**

Both `validate_bundle_before_add()` and `handle_bundle_after_add()` must call
`Data::is_bundle_product( $product_id )` before any other bundle logic.

```php
// At the top of both methods, before existing mode check:
if ( ! Data::is_bundle_product( $product_id ) ) {
    return; // Not a bundle product — skip all bundle logic
}
```

This prevents ACF reads and item iteration on products where `_udp_bundle_enabled = false`.
Performance optimization and correctness guard in one.

**Change 1: Variation product for stock check in `validate_bundle_before_add()`**

Location: `class-bundle-cart.php` line ~136

Replace:
```php
// Phase 7: when variation_id > 0, load variation product for stock check.
$wc_product = wc_get_product( $item_product_id );
```

With:
```php
$variation_id = (int) ( $item['variation_id'] ?? 0 );

if ( $variation_id > 0 ) {
    $wc_product = wc_get_product( $variation_id );

    // V5/V16: variation post missing or is not an actual WC_Product_Variation.
    if ( ! $wc_product || ! ( $wc_product instanceof \WC_Product_Variation ) ) {
        wc_add_notice(
            sprintf(
                /* translators: %s: product name */
                __( 'Bundle item "%s": Variation ID does not exist or is not a valid variation.', 'uncledev-product-bundles' ),
                get_the_title( $item_product_id )
            ),
            'error'
        );
        $has_error = true;
        continue;
    }

    // V6: variation belongs to a different parent product.
    if ( $wc_product->get_parent_id() !== $item_product_id ) {
        wc_add_notice(
            sprintf(
                /* translators: %s: product name */
                __( 'Bundle item "%s": the selected variation does not belong to the selected product.', 'uncledev-product-bundles' ),
                get_the_title( $item_product_id )
            ),
            'error'
        );
        $has_error = true;
        continue;
    }
} else {
    $wc_product = wc_get_product( $item_product_id );

    // V14: variable product configured without a variation ID.
    if ( $wc_product instanceof \WC_Product_Variable ) {
        wc_add_notice(
            sprintf(
                /* translators: %s: product name */
                __( 'Bundle item "%s" is a variable product — a Variation ID is required.', 'uncledev-product-bundles' ),
                get_the_title( $item_product_id )
            ),
            'error'
        );
        $has_error = true;
        continue;
    }
}
```

**Ordering note:** Type and parent checks run BEFORE the purchasable and stock checks that follow.
Running stock checks against a mismatched or non-variation product would validate the wrong product.
There is no fallback to parent — if variation_id > 0 but the variation is invalid, add-to-cart is blocked.

**Change 2: Variation attributes in `handle_bundle_after_add()`**

Location: `class-bundle-cart.php` line ~298

Replace:
```php
// Phase 7: when variation_id > 0, retrieve and pass variation attributes.
$child_key = WC()->cart->add_to_cart(
    $item_product_id,
    $item_qty,
    $item_variation_id,
    [], // variation attributes — Phase 7: populate for variation_id > 0
    $child_cart_data
);
```

With:
```php
$variation_attributes = [];
if ( $item_variation_id > 0 ) {
    $variation_product = wc_get_product( $item_variation_id );
    if ( $variation_product instanceof \WC_Product_Variation ) {
        $variation_attributes = $variation_product->get_variation_attributes();
    }
}

$child_key = WC()->cart->add_to_cart(
    $item_product_id,
    $item_qty,
    $item_variation_id,
    $variation_attributes,
    $child_cart_data
);
```

**Change 3: Variation parent mismatch guard (defense-in-depth)**

The primary mismatch check is in `validate_bundle_before_add()` (see Change 1 above).
This guard in `handle_bundle_after_add()` provides defense-in-depth for items that bypass
pre-cart validation (e.g., programmatic adds via `uncledev_bundles_items` filter injections).
If mismatch detected: skip item, log to PHP error log. No customer-facing notice — the stand
is already in cart at this point, so blocking is not possible here.

### 7.3 No Changes Required For

- `prevent_merge()` — already operates on the parent product, no change needed
- `sync_children_quantity()` — already reads `_udp_bundle_configured_qty`, unchanged
- `propagate_removal()` — already iterates cart_contents by parent key, unchanged
- `resolve_bundle_intent()` — POST/programmatic logic unchanged

---

## 8. Order Integration Rules

### 8.1 Confirmed Compatibility

The Order class requires no changes in Phase 7. The two-pass meta system is complete.

| Meta key | Written by | Phase 7 impact |
|----------|-----------|----------------|
| `_udp_is_bundle_parent` | `Order::collect_meta()` | No change — fires for all bundle parents |
| `_udp_is_bundle_item` | `Order::collect_meta()` | No change — fires for all bundle children |
| `_udp_bundle_parent_item_id` | `Order::resolve_parent_ids()` | No change — resolves cart → item ID |
| `_udp_bundle_cart_key` | Temporary, cleaned up | No change |
| `_udp_bundle_parent_cart_key` | Temporary, cleaned up | No change |

### 8.2 Expected Order Item Structure

For a display stand order with 2 bundle items:

**Order items:**
```
Line item 1: Display Stand (qty 1)
  meta: _udp_is_bundle_parent = true

Line item 2: Glasses Model A (qty 12)
  meta: _udp_is_bundle_item = true
        _udp_bundle_parent_item_id = <item ID of Line item 1>

Line item 3: Glasses Carrying Case (qty 2)
  meta: _udp_is_bundle_item = true
        _udp_bundle_parent_item_id = <item ID of Line item 1>
```

### 8.3 Reporting Behaviour

No reporting UI is part of Phase 7. The order meta structure supports:
- Admin order detail page: children appear as separate line items (standard WC behavior)
- Order export / ERP integration: reads `_udp_bundle_parent_item_id` to group items
- Refund logic: standard WC refund UI applies to each line item independently

### 8.4 Uninstall — Phase 7 Completion

Add `_udp_bundle_enabled` and `_udp_bundle_items` to `$meta_keys` in `uninstall.php`:

```php
$meta_keys = [
    '_udp_bundle_mode',
    '_udp_bundle_enabled', // Phase 7: per-product feature gate
    '_udp_bundle_items',   // Phase 7: ACF repeater rows
];
```

ACF repeater also writes indexed sub-keys (`_udp_bundle_items_0_bundle_product`, etc.).
`delete_post_meta_by_key()` deletes only the count row. Full repeater cleanup requires
either `delete_post_meta_by_key()` for each possible sub-key pattern, or a custom
`$wpdb` query. Document this as a known limitation; implement full cleanup in a
follow-up if needed.

---

## 9. Validation Matrix

| # | Trigger | Expected Behaviour | Error Message | Recovery Path |
|---|---------|-------------------|--------------|---------------|
| V1 | Bundle item product does not exist | Block add-to-cart | "Bundle item [name] is not available for purchase." | Admin removes deleted product from repeater |
| V2 | Bundle item product not purchasable | Block add-to-cart | "Bundle item [name] is not available for purchase." | Admin investigates product status |
| V3 | Bundle item out of stock, mode=block | Block add-to-cart | "Bundle item [name] does not have sufficient stock." | Customer waits for restock; or `uncledev_bundles_oos_behavior` filter → 'skip' |
| V4 | Bundle item out of stock, mode=skip | Skip item, add stand + other items | No customer notice (silent skip) | Order placed without OOS item |
| V5 | variation_id > 0 but variation post not found, OR post exists but is not WC_Product_Variation | Block add-to-cart | "Bundle item [name]: Variation ID does not exist or is not a valid variation." | Admin corrects variation_id in repeater |
| V6 | variation_id > 0, variation is WC_Product_Variation but parent ≠ item product_id | Block add-to-cart (check in validate_bundle_before_add) | "Bundle item [name]: the selected variation does not belong to the selected product." | Admin corrects product/variation pairing in repeater |
| V7 | `uncledev_bundles_validate_item` returns WP_Error | Block specific item, surface error message | WP_Error message text | Project-level code determines recovery (visibility, role, bucket) |
| V8 | `uncledev_bundles_validate_item` returns WP_Error for all items | All items blocked | One notice per blocked item | Same as V7 |
| V9 | Bundle items empty (no rows configured, mode≠disabled) | Stand added without children | No error | Admin adds items to repeater |
| V10 | Stand quantity increased (e.g. 1→2) | Children quantity syncs: `configured_qty × 2` | No notice | Expected behavior |
| V11 | Stand quantity exceeds available stock of a child | Not validated on quantity update (only on add-to-cart) | WC handles at checkout | Customer sees stock error at checkout |
| V12 | Repeater row with quantity = 0 (edge case) | Normalized to 1 by `max(1, ...)` in `get_items()` | No notice | Item added with qty 1 |
| V13 | Admin configures bundle_mode=disabled but items are configured | Items ignored at runtime (mode gate) | No notice | Expected — non-destructive |
| V14 | Bundle item is a variable product, variation_id = 0 | Block add-to-cart | "Bundle item [name] is a variable product — a Variation ID is required." | Admin enters correct variation ID in repeater |

---

### Manual Variation ID Validation

`bundle_variation_id` is a free-entry number field. ACF validates type (number, min 0) but does
not verify that the entered value is a variation post belonging to the selected `bundle_product`.
Runtime validation in `validate_bundle_before_add()` is the only enforcement layer.

**Validation sequence per bundle item (Phase 7, validate_bundle_before_add):**

When `variation_id > 0`:
1. `wc_get_product( $variation_id )` — if returns false OR result is not `WC_Product_Variation` → **Block** (V5)
2. `$variation->get_parent_id() !== $item_product_id` → **Block** (V6)
3. `! $wc_product->is_purchasable()` → **Block** (V2)
4. Stock insufficient → **Block** or skip per `uncledev_bundles_oos_behavior` (V3/V4)
5. `uncledev_bundles_validate_item` filter (V7/V8)

When `variation_id = 0`:
1. `wc_get_product( $item_product_id )` returns `WC_Product_Variable` → **Block** (V14)
2. `! $wc_product->is_purchasable()` → **Block** (V2)
3. Stock insufficient → **Block** or skip per `uncledev_bundles_oos_behavior` (V3/V4)
4. `uncledev_bundles_validate_item` filter (V7/V8)

**Ordering constraint:** Steps 1–2 must execute before steps 3–4. Running stock checks against
a mismatched or invalid product validates the wrong product's stock.

**Simple product with variation_id > 0:** Covered by V5 or V6 depending on what the ID refers to.
No separate scenario required — type and parent checks catch all sub-cases.

| Scenario | Error message | Layer | Recovery |
|----------|--------------|-------|----------|
| V5: variation not found or not WC_Product_Variation | `Bundle item "X": Variation ID does not exist or is not a valid variation.` | `validate_bundle_before_add` | Admin corrects variation_id |
| V6: parent mismatch | `Bundle item "X": the selected variation does not belong to the selected product.` | `validate_bundle_before_add` | Admin corrects product/variation pair |
| V14: variable product, variation_id = 0 | `Bundle item "X" is a variable product — a Variation ID is required.` | `validate_bundle_before_add` | Admin adds variation ID |
| V2 (variation): not purchasable or invalid status | `Bundle item "X" is not available for purchase.` | `validate_bundle_before_add` | Admin investigates product status |

All errors: `wc_add_notice( ..., 'error' )` + `$has_error = true` + `continue` → add-to-cart blocked.

**Admin-side visibility:** No admin-side validation of variation IDs in Phase 7. Errors surface
only when a customer (or admin) attempts to add the product to cart. The optional admin JS
enhancement (Section 5.5) reduces misconfiguration risk by replacing the number input with a
populated select of valid variations.

---

## 10. Implementation Tasks

### Task 1 — Fields: Add ACF Fields (bundle_enabled + repeater)

**File:** `includes/class-bundle-fields.php`
**Description:**
  (a) Add `field_udp_bundle_enabled` as the first field in `get_fields()` per Section 2.2 and 2.3.
  (b) Add conditional logic to `field_udp_bundle_mode` to show only when `_udp_bundle_enabled = 1`.
  (c) Replace the Phase 7 placeholder comment with the repeater field definition from Section 2.3.
**Dependencies:** None.
**Risk:** Low. ACF programmatic fields are non-destructive. Existing `_udp_bundle_mode` field gains conditional logic but its stored value and behavior are unaffected.
**Effort:** 1–2 hours.
**Order:** First — all other tasks depend on field existence.

---

### Task 2 — Data: Add `is_bundle_product()` + Implement `get_items()` Normalization

**File:** `includes/class-bundle-data.php`
**Description:**
  (a) Add `is_bundle_product( int $product_id ): bool` static helper per Section 3.2.
  (b) Replace `$raw_items = [];` with ACF repeater read, sort, and normalization per Section 3.4.
**Dependencies:** Task 1 (both `_udp_bundle_enabled` and repeater field must exist).
**Risk:** Low. Method already wired into cart and validation. Empty return is the current state.
**Effort:** 1–2 hours.
**Order:** Second.

---

### Task 3 — Cart: Activate Variation Support

**File:** `includes/class-bundle-cart.php`
**Description:** Four targeted changes per Section 7.2:
  (a) Add `Data::is_bundle_product()` early exit to both `validate_bundle_before_add()` and `handle_bundle_after_add()` (Change 0)
  (b) Load variation product for stock check in `validate_bundle_before_add()` (Change 1)
  (c) Retrieve and pass variation attributes in `handle_bundle_after_add()` (Change 2)
  (d) Add variation parent mismatch guard in `handle_bundle_after_add()` as defense-in-depth (Change 3)
**Dependencies:** Task 2 (meaningful items must exist for this to activate).
**Risk:** Medium. Cart is a production code path. Must test simple products, variable products with valid variation, and variable products with missing variation_id.
**Effort:** 2–3 hours including testing.
**Order:** Third.

---

### Task 4 — Uninstall: Add Meta Keys

**File:** `uninstall.php`
**Description:** Add `_udp_bundle_enabled` and `_udp_bundle_items` to the `$meta_keys` array.
**Dependencies:** None (standalone cleanup change).
**Risk:** None (only fires on plugin uninstall).
**Effort:** 15 minutes.
**Order:** Can be done in parallel with any other task.

---

### Task 5 — Frontend: Server-Side Modal Rendering

**File:** `includes/class-bundle-frontend.php`
**Description:**
  - Replace `render_bundle_items_list()` stub plan with new private methods per Section 6.2–6.3:
    - `render_bundle_summary_trigger( int $product_id, array $items ): void`
      Renders the compact count label + CTA link (`data-fancybox data-type="inline"`).
    - `render_bundle_modal( int $product_id, array $items ): void`
      Renders the hidden `<div id="udp-bundle-contents-{id}">` with the full item list.
  - Call both methods from `render_optional_checkbox()` and `render_required_label()`
    when `Data::get_items()` returns non-empty.
  - Apply `uncledev_bundles_summary_label` filter for count text.
  - Apply `uncledev_bundles_modal_title` filter for modal heading.
  - Update enqueue: JS only on `is_product()`, CSS on `is_product() || is_cart()`.
**Dependencies:** Task 2.
**Risk:** Low. Additive rendering change. Existing hidden inputs and mode detection unchanged.
**Effort:** 2–3 hours.
**Order:** After Task 2.

---

### Task 6 — JS: bundle-frontend.js (stub, no functional change)

**File:** `assets/js/bundle-frontend.js`
**Description:** Replace current stub content with a minimal IIFE comment block.
The Fancybox modal is handled by the theme's global `fancybox-init.js` — no plugin JS
is needed for the required mode display stand use case.
The file must still be registered/enqueued so the handle exists for future phases.
**Dependencies:** Task 5 (file enqueued from Frontend::enqueue_assets).
**Risk:** None. Empty IIFE.
**Effort:** 15 minutes.
**Order:** After Task 5.

---

### Task 7 — CSS: Modal and Summary Styles

**File:** `assets/css/bundle-frontend.css`
**Description:** Add styles for:
  - `.udp-bundle-summary` — flex row, aligns count label and CTA
  - `.udp-bundle-count` — count text style
  - `.udp-bundle-view-btn` — CTA link style (button-like or text link per design)
  - `.udp-bundle-modal-content` — modal inner container, `max-height` + `overflow-y: auto`
  - `.udp-bundle-modal-title` — modal heading
  - `.udp-bundle-items-list` — item list layout (no bullets)
  - `.udp-bundle-item` — flex row: thumb | name + sku | qty
  - `.udp-bundle-item-thumb img` — 48×48 constrained
  - `.udp-bundle-item-name`, `.udp-bundle-item-sku`, `.udp-bundle-item-qty` — text sizing
**Dependencies:** Task 5.
**Risk:** None. Additive CSS. No WooCommerce or theme selectors modified.
**Effort:** 1–2 hours.
**Order:** After Task 5, can run in parallel with Task 6.

---

### Task 8 — Admin JS: Variation Selector (Optional Enhancement)

**File:** `assets/js/bundle-admin.js` (new file)
**Description:** When Bundle Product field changes to a variable product, replace the
Variation ID number field with a dynamically populated select.
**Dependencies:** Task 1.
**Risk:** Low (admin-only JS, does not affect frontend or cart).
**Effort:** 3–4 hours (includes WC REST API integration and ACF hook).
**Order:** Can be done after Task 1, independently of Tasks 2–7.
**Note:** MVP is functional without this task. Defer if timebox is tight.

---

### Task 9 — Version Bump

**Files:** `uncledev-product-bundles.php`, `UDB_VERSION` constant
**Description:** Bump version from `1.0.0` to `1.1.0`.
**Dependencies:** All other tasks complete.
**Risk:** None.
**Effort:** 5 minutes.
**Order:** Last.

---

### Recommended Implementation Order

```
Task 1 (Fields)
  ↓
Task 2 (Data)
  ↓
Task 3 (Cart) ───────── Task 4 (Uninstall) — anytime
  ↓
Task 5 (Frontend PHP)
  ↓
Task 6 (JS) ──────────── Task 7 (CSS) — parallel
  ↓
Task 8 (Admin JS) — optional, after Task 1
  ↓
Task 9 (Version bump)
```

---

## 11. Definition of Done

Phase 7 is complete when all of the following are confirmed:

**Configuration**
- [ ] `_udp_bundle_enabled` checkbox is visible on all product edit screens (unchecked by default)
- [ ] When `_udp_bundle_enabled` is unchecked, Bundle Mode and Bundle Items are hidden
- [ ] When `_udp_bundle_enabled` is checked, Bundle Mode becomes visible
- [ ] Bundle items repeater is visible when bundle mode ≠ disabled AND bundle enabled = true
- [ ] Admin can add, reorder, and delete bundle item rows
- [ ] Product, Variation ID, Quantity, Always Included, and Sort fields save and reload correctly
- [ ] Non-bundle products show only the unchecked `_udp_bundle_enabled` checkbox — no other bundle UI

**Data layer**
- [ ] `Data::get_items()` returns normalized items from ACF repeater
- [ ] `Data::get_items()` returns `[]` when no rows are configured
- [ ] `uncledev_bundles_items` filter fires with normalized data
- [ ] Sort order is respected (explicit sort_order > repeater row order)

**Cart behavior**
- [ ] Adding a stand product with required bundle adds all configured items to cart
- [ ] Adding a stand product with optional bundle + checkbox checked adds all configured items
- [ ] Adding a stand product with optional bundle + checkbox unchecked adds stand only
- [ ] Variation products (variation_id > 0) added to cart with correct variation attributes
- [ ] Stock check uses variation product when variation_id > 0
- [ ] Quantity sync: stand qty × 2 → all child qtys × 2
- [ ] Removal propagation: removing stand removes all children

**Validation**
- [ ] OOS bundle item (mode=block): stand cannot be added, customer sees error notice
- [ ] OOS bundle item (mode=skip): stand added, OOS item skipped silently
- [ ] Non-purchasable bundle item: stand blocked, error notice surfaced
- [ ] Missing variation product (variation_id > 0 but not WC_Product_Variation): blocked with error notice
- [ ] Parent mismatch (variation belongs to different product): blocked with error notice
- [ ] Variable product without variation_id: blocked with error notice

**Frontend**
- [ ] Required mode: product summary shows count label and "View contents" CTA
- [ ] Optional mode: product summary shows checkbox and "View contents" CTA
- [ ] Clicking CTA opens Fancybox inline modal with full item list
- [ ] Modal shows: thumbnail, product name, SKU (if present), quantity per item
- [ ] Modal does not show pricing, selection controls, or quantity inputs
- [ ] Modal scrolls correctly for 50+ items
- [ ] Modal opens and closes with keyboard (Fancybox native: Enter/Space, Escape)
- [ ] Product summary layout unchanged for non-bundle products (disabled mode)
- [ ] CSS and JS load only on product pages (JS) and product/cart pages (CSS)

**Order**
- [ ] Order items contain stand with `_udp_is_bundle_parent = true`
- [ ] Order items contain children with `_udp_is_bundle_item = true` and `_udp_bundle_parent_item_id` pointing to stand item ID

**Display stand use case**
- [ ] A display stand product can be configured with glasses (simple or variation) and quantity
- [ ] Customer adding display stand to cart receives stand + all configured glasses
- [ ] Changing stand quantity correctly updates glasses quantity in cart
- [ ] Removing stand from cart removes all glasses

**Cleanup**
- [ ] `uninstall.php` includes `_udp_bundle_enabled` in cleanup meta keys
- [ ] `uninstall.php` includes `_udp_bundle_items` in cleanup meta keys
- [ ] Plugin version bumped to `1.1.0`

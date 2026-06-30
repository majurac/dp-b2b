# Display Stand Bundles — Use Case Analysis

**Datum:** 2026-06-09
**Scope:** `uncledev-product-bundles` v1.0.0 (foundation commit)
**Trigger:** bundle-products-transfer-note.md — validated business requirement

---

## Use Case

A display stand for glasses cannot be purchased independently.
It must be ordered together with a predefined quantity of glasses.

This maps to the canonical bundle model:
- Parent product: the display stand
- Bundle items: specific glass products in specific quantities
- Mode: `required` (no opt-out — customer cannot remove glasses from the order)

---

## Analysis by Dimension

---

### 1. Bundle Composition

**Requirement:** Stand product contains N units of product X, M units of product Y (specific products, specific quantities).

**Architecture:**
`Data::get_items()` returns normalized `[ product_id, variation_id, quantity ]` per item.
This format is the exact model needed:

```
Display stand → [
  { product_id: X, variation_id: 0, quantity: 12 },
  { product_id: Y, variation_id: 47, quantity: 6 },
]
```

The parent-child assignment is arbitrary — any product can be a parent (stand), any product can be a child (glasses). The engine imposes no type constraints.

**Gap:** `Data::get_items()` always returns an empty array. The ACF repeater field and normalization path are placeholders marked Phase 7. Bundle composition model is designed but not yet stored.

**Classification: REQUIRES NEW COMPONENT**
The ACF repeater field and `Data::get_items()` normalization path — Phase 7 implementation already planned.

---

### 2. Product Selection UX

**Requirement:** Customer ordering a display stand must understand what comes with it. No opt-out (required mode).

**Architecture:**
`Frontend::render_required_label()` renders an informational paragraph. The `uncledev_bundles_required_label` filter allows the label text to be customized per project without touching the plugin.

**What is missing:** The current label is static text — it cannot enumerate the actual products included in the stand. A customer ordering the stand has no visible list of what glasses are included. This is the Phase 7 JS "Dynamic bundle item summary" placeholder.

The data to build that summary already exists in the normalized items array — once Phase 7 is implemented, the JS can receive items via `wp_localize_script` and render them inline.

**Classification: REQUIRES MODIFICATION**
The bundle item summary display — Phase 7 JS enhancement already planned.

---

### 3. Validation Rules

**Requirement:** Cannot add a stand if the required glasses are unavailable or restricted.

**Architecture:**
`Cart::validate_bundle_before_add()` already enforces:

- purchasability check per bundle item (`is_purchasable()`)
- stock quantity check against required quantity (bundle_qty × stand_qty)
- `uncledev_bundles_oos_behavior` filter: 'block' or 'skip' per item on stock failure
- `uncledev_bundles_validate_item` filter: injectable WP_Error for any project-specific rule

The B2B visibility system (bucket rules, brand/category restrictions) can hook into
`uncledev_bundles_validate_item` to block stands whose glass items the user cannot access.
No plugin changes required.

Quantity proportionality is already modeled: `item['quantity'] × stand_qty` is computed
inside the validator and passed to the stock check. Ordering 2 stands correctly requires
2 × configured_qty of each glass product.

**Classification: SUPPORTED BY CURRENT ARCHITECTURE**

---

### 4. Cart Representation

**Requirement:** Stand and its glasses appear in cart in a way that the buyer understands the relationship.

**Architecture:**
The engine creates:
- One cart row for the stand (parent), with `_udp_has_bundle`, `_udp_bundle_children`
- One cart row per glass product (child), with `_udp_bundle_parent`, `_is_bundle_item`
- Children display "— Bundle item" label (filterable via `uncledev_bundles_item_label`)

Quantity sync: if buyer changes stand qty to 2, each glass product's cart quantity
updates automatically (`sync_children_quantity()`).

Removal propagation: removing the stand removes all glass rows from the cart.

Anti-merge: `_udp_bundle_id` (unique per add-to-cart) prevents WooCommerce from
collapsing two separate stand+glasses sets into a single stand row. A buyer ordering
3 stands gets 3 independent stand rows, each with their own glass children.

**No architecture change needed.** Visual grouping (visually nesting glasses under
their stand in the cart template) is a theme-level CSS/template concern — the data
structure already provides the parent key on each child.

**Classification: SUPPORTED BY CURRENT ARCHITECTURE**

---

### 5. Order Representation

**Requirement:** Order must record: which stand was ordered, which glasses came with it, in what quantity.

**Architecture:**
`Order::collect_meta()` and `Order::resolve_parent_ids()` implement a two-pass approach:

Pass 1 — stores temporary cart key references as order item meta during line item creation.
Pass 2 — after `$order->save()`, resolves cart keys to stable WC order item IDs and writes:
- Stand item: `_udp_is_bundle_parent = true`
- Glass items: `_udp_is_bundle_item = true`, `_udp_bundle_parent_item_id = <stand item ID>`

The resulting order is fully self-describing: each glass line item references its stand by item ID. This supports order display, admin review, and any downstream processing.

**Classification: SUPPORTED BY CURRENT ARCHITECTURE**

---

## Summary Table

| Dimension              | Classification                     | Gap / Action                              |
|------------------------|------------------------------------|-------------------------------------------|
| Bundle composition     | REQUIRES NEW COMPONENT             | Phase 7: ACF repeater + `get_items()` fetch |
| Product selection UX   | REQUIRES MODIFICATION              | Phase 7: JS bundle item summary display   |
| Validation rules       | SUPPORTED BY CURRENT ARCHITECTURE  | B2B visibility hooks via `uncledev_bundles_validate_item` |
| Cart representation    | SUPPORTED BY CURRENT ARCHITECTURE  | Theme: CSS grouping (optional)            |
| Order representation   | SUPPORTED BY CURRENT ARCHITECTURE  | —                                         |

---

## Phase 7 Design Assumption Validity

Phase 7 planned the following storage model (documented in `Fields.php` placeholder):

```
ACF repeater → [ product_id, variation_id (0 = simple or specific), quantity ] per row
```

**The display stand use case validates these assumptions:**

1. **Specific product + specific variation per row** — a stand contains particular glasses
   (specific SKU, specific variant), not "any variant of a product family". The
   `variation_id > 0` model is the correct one for this use case. The "all variations of
   parent" storage model option raised in the placeholder comment is NOT needed here.

2. **Mixed simple and variation items** — a stand could contain both simple products
   (accessories) and specific variations (glasses). The normalized format handles this
   natively: `variation_id = 0` for simple, `variation_id > 0` for specific variant.

3. **Required mode is the primary mode for stands** — display stands are never optional
   (the stand cannot be sold without glasses). The `required` mode path is the
   primary path for this use case. The optional/checkbox path remains valid for other
   products.

**Conclusion:** Phase 7 design assumptions remain valid after the display stand business
requirement is applied. No Phase 7 redesign is needed. Implementation can proceed as planned.

---

## Possible Simplifications

One previously-open question is now answered:

**"All variations of parent" storage model** — the display stand case requires specific
products at specific quantities. This confirms that the "all variations of parent" option
identified in the Phase 7 placeholder comment is a future-use model, not required for
this implementation. The repeater can be implemented with specific product + specific
variation per row. No simplification in plugin structure — this removes a design question.

No other simplifications are unlocked by this use case analysis. The architecture remains
clean and no over-engineering is present in the current foundation.

---

## Open Questions

From `bundle-products-transfer-note.md` — still unresolved:

1. How many bundle rules will exist? Is glasses + stand the only case, or is this a repeating pattern across multiple stand types?
2. Is the bundle rule defined by CMS admin, or does it originate from an Apros product attribute?
3. Expected UX when a glass product is added to cart independently (without its stand)?
4. Is the stand itself a purchasable product in WooCommerce, or does it require a special product type?

These questions do not block Phase 7 implementation. The normalized item model handles
all plausible answers. They do affect label copy and possibly the standalone-add UX.

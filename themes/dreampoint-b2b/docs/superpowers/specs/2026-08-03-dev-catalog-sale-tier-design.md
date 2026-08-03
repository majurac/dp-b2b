# Dev Catalog Generator — Sale Price Tier — Design

**Date:** 2026-08-03
**Status:** Approved, pending implementation

## Problem

The synthetic B2B catalog generator (`inc/dev/class-dev-catalog-generator.php`) produces
no products with a valid `sale_price`. This blocks meaningful local testing of the
Discounted Products block (`blocks/templates/discounted-products.php`), which queries
`wc_get_product_ids_on_sale()` directly.

## Constraints

- Do not delete, regenerate, or alter any existing synthetic catalog data or behavior.
- Localhost only. No production changes. No Playwright testing.
- Extend the existing generator — no new phase, no new WP-CLI flag, no new file.
- Deterministic and idempotent, consistent with every other tier already in the file
  (stock, price, total_sales, publish-date offset, variation price delta).
- Native WooCommerce pricing only (`regular_price` / `sale_price`) — no custom sale
  logic, no custom visibility.
- Cover both product types the generator already creates: simple (Phase 2) and
  variable (Phase 3).
- Functional target: the deterministic sale tier is intentionally sparse. Under the
  default synthetic catalog configuration (200 simple + 10 variable) it should
  produce approximately 6–8 discounted products in total, with realistic discounts
  of 10–40%. The exact mechanism used to hit that sparsity (hashing, thresholds,
  etc.) is an implementation detail — the outcome, not the internal hash-distribution
  mechanics, is the design requirement.

## Design

The sale tier becomes another deterministic product attribute alongside price,
stock, and total sales — assigned the same way, at generation time, from the
same per-SKU seeding technique already used throughout this generator.

### Phase 2 — simple products (`generate_simple_products()`)

New private method, following the existing seeded-tier pattern
(`deterministic_price()`, `deterministic_variation_price()`):

```php
private function deterministic_sale_price( string $sku, string $regular_price ): ?string {
    mt_srand( abs( crc32( $sku . '_sale' ) ) );
    if ( mt_rand( 1, 100 ) > 3 ) { // sparse deterministic threshold — tuned to the functional target in Constraints
        return null;
    }

    mt_srand( abs( crc32( $sku . '_sale_pct' ) ) );
    $discount_pct = mt_rand( 10, 40 );

    $sale = (float) $regular_price * ( 1 - $discount_pct / 100 );

    return number_format( max( 0.01, $sale ), 2, '.', '' );
}
```

Called once per SKU in the existing product loop, immediately after
`deterministic_price()`. If non-null, `$product->set_sale_price( $sale_price )` is
called before `$product->save()`. No other product field is touched.

The seed is derived from the SKU with a distinct suffix (`_sale`, `_sale_pct`),
matching the isolation technique already used by `deterministic_variation_price()`
(`crc32( $var_sku . '_price' )`) — this keeps the sale outcome independent from the
stock/price/total_sales values already derived for the same SKU: identical input
(the SKU) always produces identical output (the same sale/no-sale outcome and
discount depth), but each attribute is hashed from a distinct suffixed input so
none of them move together.

### Phase 3 — variable products (`generate_variable_products()`)

New private methods:

```php
private function deterministic_variable_on_sale( string $sku ): bool {
    mt_srand( abs( crc32( $sku . '_sale' ) ) );
    return mt_rand( 1, 100 ) <= 20; // sparse deterministic threshold — tuned to the functional target in Constraints
}

private function deterministic_variation_sale_price( string $var_sku, string $var_price ): string {
    mt_srand( abs( crc32( $var_sku . '_sale_pct' ) ) );
    $discount_pct = mt_rand( 10, 40 );
    $sale = (float) $var_price * ( 1 - $discount_pct / 100 );
    return number_format( max( 0.01, $sale ), 2, '.', '' );
}
```

Per parent product, `deterministic_variable_on_sale( $sku )` is evaluated once. If
true, only the first variation in the combination loop (`$j === 0`) receives
`$variation->set_sale_price(...)` before `$variation->save()`.

**Design principle — variable products rely entirely on native WooCommerce sale
handling:**

- Sale prices are assigned only at the variation level — never on the parent.
- Parent pricing is synchronized exclusively through WooCommerce's own mechanism:
  the existing `WC_Product_Variable::sync( $product_id )` call (already present,
  unchanged), which recomputes the parent's price range from its variations.
- No custom parent pricing logic is introduced.
- No custom sale-visibility logic is introduced.
- No standalone variation visibility is introduced — variations are not shown or
  queried individually in catalog loops.
- The parent product remains the only catalog entity shown in loops; it is also
  the only entity `wc_get_product_ids_on_sale()` needs to surface, which it does
  natively once a child variation is on sale and synced.

### Reporting

Both `generate_simple_products()` and `generate_variable_products()` return arrays;
each gains an `on_sale` (or equivalent) count, surfaced in the existing WP_CLI summary
output lines for `run_products()` / `run_variables()`.

### Idempotency

Unaffected. Both phases already skip any SKU that exists (`wc_get_product_id_by_sku()`
check) before generating — a discounted product, once created, is never
re-evaluated or duplicated on subsequent runs. `--refresh-metadata` is untouched:
it explicitly refreshes only `total_sales` and `date_created`, and price fields
(including the new sale price) remain generation-time-only, consistent with how
`regular_price` already behaves today.

### Out of scope

- `--phase=ugly` (Phase "ugly") is not touched — no sale-price edge cases requested.
- `reset-catalog` / `--refresh-metadata` logic — no changes needed (see above).
- No new WP-CLI flags, phases, or files.

## Files touched

- `inc/dev/class-dev-catalog-generator.php` — four new private methods, small
  additions inside `generate_simple_products()` and `generate_variable_products()`,
  updated summary output in `run_products()` and `run_variables()`.
- `docs/historical/synthetic-b2b-catalog.md` — document the new sale tier alongside
  the existing tier documentation (stock, price, total_sales, publish-date, variation
  price delta), consistent with the file's existing level of detail.

## Validation plan

Localhost only, WP-CLI, no Playwright:

1. `wp dp-b2b generate-catalog --phase=products` (or verify against the currently
   existing 200 if already generated).
2. `wp dp-b2b generate-catalog --phase=variables`.
3. `wp eval` check: `wc_get_product_ids_on_sale()` returns a non-empty array; count
   and cross-check IDs against the SKUs the CLI output marked as discounted.
4. Confirm every returned ID resolves to a `publish` product with
   `catalog_visibility = visible` (already guaranteed by existing generator code —
   no change needed, but part of the sanity check).
5. Report to the user: exact count of discounted simple products, exact count of
   discounted variable (parent) products, and the full `wc_get_product_ids_on_sale()`
   result count.

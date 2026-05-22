# Quick Order — Ugly Dataset Extension Design

**Date:** 2026-05-13
**Status:** Approved

---

## Goal

Extend the existing `Dreampoint_B2B_Dev_Catalog_Generator` with a minimal `--phase=ugly` capability.
Purpose: regression-test Quick Order against ERP-style edge cases before real product imports begin.

---

## Scope

**In scope:**
- Add `--phase=ugly` to the existing `generate_catalog` WP-CLI command
- ~10 curated, deterministic edge-case products (no random generation)
- Full compatibility with existing lifecycle (tag, batch, reset-catalog)
- Playwright-based regression verification after generation

**Out of scope:**
- New CLI commands or entry points
- New classes or files
- Abstract test frameworks or config matrices
- Separate cleanup logic

---

## Architecture

### Files modified

| File | Change |
|------|--------|
| `inc/dev/class-dev-catalog-generator.php` | Add `case 'ugly'` to switch + `run_ugly()` + `generate_ugly_products()` |

No other files touched.

### Pattern

Follows the existing `run_products()` / `generate_simple_products()` split:

```
generate_catalog() switch
  └── case 'ugly' → run_ugly()
        └── generate_ugly_products()
              ├── WC_Product_Simple() × 8
              └── WC_Product_Variable() × 2
```

All products:
- Tagged `_dp_generated = 1` and `_dp_generation_batch`
- Assigned to first available generated category and brand (uses existing `get_generated_child_cat_ids()` / `get_generated_brand_ids()`)
- Cleaned up by existing `reset-catalog`

---

## Curated Product Set

| SKU | Type | Edge Case |
|-----|------|-----------|
| `DEV-UGLY-001` | simple | 160-char name (layout overflow test) |
| `DEV-UGLY-002` | simple | Croatian unicode in name: Č Ć Š Đ Ž |
| `DEV-UGLY-003` | simple | HTML-ish chars: `< > " '` (XSS escape test) |
| `DEV-UGLY-004` | simple | Price 0.01 EUR — edge low |
| `DEV-UGLY-005` | simple | Price 9999.99 EUR — edge high |
| `DEV-UGLY-006` | simple | outofstock + backorder, no image meta |
| `DEV-UGLY-007` | simple | No description, no image (thumbnail fallback) |
| `DEV-UGLY-008` | variable | Solo variation (1 var: Pack Size=1pc only) |
| `DEV-UGLY-009` | variable | Dense attributes: Size×Color×Material = 27 vars |
| `DEV-UGLY-010` | variable | Mixed stock: 3 vars → instock / outofstock / onbackorder |

---

## Execution Sequence

```
# Step 1 — taxonomies (idempotent)
wp dp-b2b generate-catalog --phase=taxonomies

# Step 2 — 700 simple products
wp dp-b2b generate-catalog --phase=products --count=700

# Step 3 — 40 variable products (20 small / 12 medium / 8 stress)
wp dp-b2b generate-catalog --phase=variables --count=40

# Step 4 — 10 ugly edge-case products
wp dp-b2b generate-catalog --phase=ugly
```

Total dataset: ~750 products + ~732 variations (40 vars) + ~30 ugly-product variations.

---

## Regression Verification Areas

After generation, Playwright tests verify these areas on the Quick Order page:

| Area | Ugly products tested | Pass criteria |
|------|----------------------|---------------|
| Layout stability — long names | DEV-UGLY-001 | No table overflow, text wraps or truncates cleanly |
| Special char rendering | DEV-UGLY-002, 003 | Characters display correctly, no HTML escaping visible |
| Thumbnail fallback | DEV-UGLY-006, 007 | Placeholder image shows, no broken img tag |
| Price display | DEV-UGLY-004, 005 | Prices format correctly at both extremes |
| Solo variation CartSync | DEV-UGLY-008 | Add to cart works, no variation selector error |
| Dense variation rendering | DEV-UGLY-009 | Variation selector renders, CartSync stable |
| Mixed stock display | DEV-UGLY-010 | Stock badges correct per-variation |
| Pagination integrity | all | Page N+1 loads without duplicate or missing rows |
| Mobile overflow | DEV-UGLY-001, 002 | No horizontal scroll on 375px viewport |
| Filter compatibility | all ugly products | Brand/category/price filters include/exclude correctly |
| Empty state | filter to zero results | Empty row shows, correct colspan, no JS error |
| Console cleanliness | all | Zero unhandled errors in browser console |

---

## Cleanup

```
wp dp-b2b reset-catalog
```

Removes all generated data including ugly products. No additional cleanup needed.

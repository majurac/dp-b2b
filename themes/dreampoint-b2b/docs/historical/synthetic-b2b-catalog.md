# Synthetic B2B Catalog — Development Tool

Development-only stress-test data generator. Exercises visibility engine, Quick Order, filtering, variation sync, and pagination with realistic fake catalog data.

**Never run in production.** Hard-fails if `WP_ENVIRONMENT_TYPE === 'production'`.

---

## WP-CLI Usage

```bash
# Phase 1 — taxonomies
wp dp-b2b generate-catalog --phase=taxonomies

# Phase 2 — simple products (default 200, cap 1000)
wp dp-b2b generate-catalog --phase=products
wp dp-b2b generate-catalog --phase=products --count=500

# Phase 3 — variable products (default 10, cap 50)
wp dp-b2b generate-catalog --phase=variables
wp dp-b2b generate-catalog --phase=variables --count=10
```

---

## Generated Structures

### Phase 1 — Taxonomies (implemented)

**product_cat — 24 terms total**

| Parent | Children |
|--------|----------|
| [DEV] Elektronika | Kabeli i Konektori, Baterije i Napajanje, LED Rasvjeta |
| [DEV] Alati i Oprema | Ručni Alati, Električni Alati, Mjerni Uređaji |
| [DEV] Kemikalije | Čistila i Detergenti, Maziva i Ulja, Zaštitni Premazi |
| [DEV] Zaštitna Oprema | Radna Odjeća, Zaštita Glave i Lica, Zaštita Ruku |
| [DEV] Uredski Materijal | Papir i Karton, Pisaći Pribor, Toneri i Tinte |
| [DEV] Prehrambeni Dodaci | Vitamini i Minerali, Proteini, Energetski Napitci |

**product_brand — 30 terms (flat)**
AluTech, BestForce, CargoLine, DuraPro, EcoShield, FlexDrive, GripMax, HiTech Pro, IsoCore,
JetFlow, KraftBau, LumiTech, MaxiForce, NanoCoat, OptiChem, ProGard, QuickBuild, RigidForm,
SafeGuard, TechBase, UniPower, VoltEdge, WeldMaster, XtraWeld, YieldMax, ZenoBuild, AlphaGrade,
BetaWorks, ClearFlow, DeltaForge.

### Phase 2 — Simple Products (implemented)

200 simple products by default (cap 1000). SKU: `DEV-0001` → `DEV-N`.
Each product is assigned a child category and brand by cycling through all generated terms.
Deterministic stock (60% instock / 20% low stock / 10% outofstock / 10% backorder) and price (3 tiers: 2.99–19.99 / 20–99.99 / 100–499.99 EUR).

### Phase 3 — Variable Products (implemented)

10 variable products by default (cap 50, hard-fail). SKU: `DEV-VAR-001` → `DEV-VAR-N`.

**Variation tiers (3 sizes):**

| Tier | Attributes | Variation count | % of run |
|------|-----------|----------------|---------|
| small | Pack Size (5 values) | 5 | first 50% of products |
| medium | Size × Color (5×4) | 20 | next 30% |
| stress | Size × Color (7×7) | 49 | last 20% |

Attributes are **local** (not global taxonomy) — no `pa_*` taxonomy pollution.
All variations have individual SKUs (`DEV-VAR-001-01` etc.), deterministic price (±15% delta from parent base), and variation-level stock states matching Phase 2 distribution.
`WC_Product_Variable::sync()` is called after each product to update min/max price range.

---

## Data Markers

Every generated term includes:

| Meta key | Value | Purpose |
|----------|-------|---------|
| `_dp_generated` | `1` | Cleanup targeting |
| `_dp_generation_batch` | `YYYYMMDD_HHmm` | Batch tracing / partial reset |

Names carry `[DEV]` prefix for visual identification in WP Admin.

---

## Idempotency

All phases use slug-based existence checks before inserting. Re-running the same phase skips existing terms/products. Safe for repeated execution — no duplicates created.

---

## Cleanup / Reset

```bash
# Full reset — removes all generated products, variations, categories, brands
wp dp-b2b reset-catalog

# Batch reset — removes only products/variations from one specific batch
# (terms are NOT deleted in batch mode — they are shared across batches)
wp dp-b2b reset-catalog --batch=20260511_1430
```

**Deletion order (hard-coded):** variations → product parents → categories → brands.
Category children are deleted before parents via `ORDER BY tt.parent DESC` in the ID query.
`wp_delete_post($id, true)` is used for posts; `wp_delete_term()` for terms.

---

## Testing Purpose

| Scenario | Covered by |
|----------|-----------|
| Category-restricted visibility | 6 parent + 18 child categories |
| Brand-restricted visibility | 30 brands, flat |
| Quick Order category filter | product_cat tax_query |
| Quick Order brand filter | product_brand tax_query |
| Pagination with real catalog | Phases 2–3 |
| Variation sync stress | Phase 3 — stress tier (49 vars/product) |
| Optimistic rollback on out-of-stock | Phase 3 — variation-level stock mix |
| Variation add_to_cart validation | Phase 3 — all stock states per product |

---

## File Locations

```
inc/dev/dev-tools.php                  — WP-CLI bootstrap (loaded only in CLI context)
inc/dev/class-dev-catalog-generator.php  — generator class
```

Loaded from `functions.php` only when `defined('WP_CLI') && WP_CLI`.

---

## Limitations

- Generated data is not assigned to visibility buckets automatically — bucket rules must be set manually after generation to exercise specific visibility scenarios
- Generated data is not assigned to visibility buckets automatically — bucket rules must be set manually after generation to exercise specific visibility scenarios

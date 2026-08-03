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

**total_sales tiers** — built around the LIVE `DP_Quick_Order_Config::BEST_SELLER_MIN_SALES` threshold (falls back to `10` only if the Quick Order plugin is inactive), so a retuned threshold stays correct here without a second edit:

| Tier | % of run | Value range | Relative to threshold |
|------|---------|-------------|-----------------------|
| high | 15% | threshold×5 – threshold×50 | clearly above |
| exact | 5% | = threshold | boundary-inclusive |
| moderate | 30% | 1 – threshold−1 | clearly below |
| zero | 50% | 0 | clearly below |

**Publish-date (post_date) offset tiers** — built around the LIVE `DP_Quick_Order_Config::NEW_PRODUCT_MAX_AGE_DAYS` window (falls back to `30` only if the Quick Order plugin is inactive). Computed with `current_time('timestamp')` (site-local "now") formatted via `gmdate()` — this pairing is deliberate: `WC_Data::set_date_prop()` treats a plain datetime string with no timezone marker as SITE-LOCAL, so using `time()` (UTC) there would silently shift the stored date by the site's UTC offset:

| Tier | % of run | Offset range (days ago) | Relative to "New" window boundary |
|------|---------|--------------------------|-----------------------------------|
| clearly inside | 20% | 0 – threshold/2 | well within the window |
| near boundary, inside | 5% | threshold/2+1 – threshold−1 | just inside |
| near boundary, outside | 5% | threshold+1 – threshold+14 | just outside |
| clearly outside | 70% | threshold+15 – threshold×4 | well outside the window |

Both tiers are seeded from the same per-SKU `$seed = abs(crc32($sku))` used by `deterministic_stock()`/`deterministic_price()` (the publish-date tier uses `$seed + 1` so it draws an independent value from the total_sales tier for the same SKU).

**Sale price tier** — another deterministic product attribute alongside price, stock, and total_sales, added so the Discounted Products block (`wc_get_product_ids_on_sale()`) has real fixture data to query. Intentionally sparse: seeded independently via `crc32($sku . '_sale')` / `crc32($sku . '_sale_pct')` (same isolation technique as `deterministic_variation_price()`) so it does not correlate with the stock/price/total_sales draws for the same SKU. The exact threshold is an implementation detail — the functional target is approximately 6–8 discounted products across a default full generation run (200 simple + 10 variable). Discount depth is 10–40% off `regular_price`, applied via native WooCommerce `set_sale_price()`.

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

**Sale price tier (variable)** — same sparse deterministic attribute as the Phase 2 sale tier, evaluated once per parent SKU (`deterministic_variable_on_sale()`, ~2 of the default 10 parents). Variable products rely entirely on native WooCommerce sale handling: only the **first variation** of an on-sale parent receives a `sale_price` (`deterministic_variation_sale_price()`, 10–40% off that variation's price); the parent's own price range is synchronized exclusively through the existing `WC_Product_Variable::sync()` call — no custom parent pricing logic, no custom sale-visibility logic, and no variation is exposed as a standalone catalog product. The parent product ID is what `wc_get_product_ids_on_sale()` surfaces once a child variation is on sale and synced.

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

## Metadata Refresh

Products created by an older generator run do not retroactively receive
newly-added deterministic metadata (e.g. `total_sales` tiers, publish-date
tiers) — the default `--phase=products` run skips any SKU that already
exists. `--refresh-metadata` closes that idempotency gap without creating
new products or touching anything else.

```bash
# Report + apply
wp dp-b2b generate-catalog --refresh-metadata

# Report only, no writes
wp dp-b2b generate-catalog --refresh-metadata --dry-run
```

**Scope:** only products with `_dp_generated=1` whose SKU matches the
Phase 2 `DEV-####` pattern (e.g. `DEV-0001`) — this excludes `DEV-VAR-*`
and `DEV-UGLY-*` products by SKU length, not by name matching. `--phase`
is ignored when `--refresh-metadata` is set — it is a standalone mode, not
a fifth phase value.

**Fields refreshed (only these two):**

| Field | Recomputed from |
|-------|------------------|
| `total_sales` | `deterministic_total_sales(seed)` — same tiers as generation |
| `date_created` (post_date) | `deterministic_publish_offset_days(seed)` relative to **refresh execution time**, not original generation time |

**Fields deliberately preserved** (never written by refresh): name,
description, images, stock (manual or generated), price (manual or
generated), categories, brands, attributes, variations, visibility, and
any other meta not listed above. This is what makes refresh safe to run
against a catalog with manually-prepared QA scenarios.

**Why refresh is time-relative:** the New-product filter compares
`date_created` against "now" at query time. A catalog generated weeks ago
drifts out of the New window even though the fixture's *intent* (some
products clearly new, some clearly not) hasn't changed. Refresh
re-anchors the same deterministic tier logic to the current moment so the
New-filter fixtures stay meaningful without a full `reset-catalog` +
regenerate cycle.

**Idempotent:** running refresh twice in a row reproduces the same
`total_sales` per SKU (seed is derived from the SKU, not from time) and
recomputes `date_created` relative to whichever run executed last —
running it repeatedly never creates, duplicates, or deletes anything.

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

## Staging Persistence

The staging synthetic catalog is persistent QA fixture data, not
disposable scratch data. `reset-catalog` must not be run against staging
without explicit instruction — it deletes generated products, variations,
and (in non-batch mode) categories and brands. Use `--refresh-metadata`
to bring time-relative fields up to date instead of resetting and
regenerating.

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
| Quick Order New/Best Seller filters | Backdated post_date + total_sales tiers (Phase 2), thresholds sourced live from DP_Quick_Order_Config |

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

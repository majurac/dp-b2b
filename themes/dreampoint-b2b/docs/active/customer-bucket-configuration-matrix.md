# Customer Bucket Configuration Matrix (IZIPIZI wholesale)

**Status:** PREPARATION ONLY — nothing configured, no bucket records created, awaiting client answers (2026-11-09).
**Engine:** existing `dp_bucket` CPT + visibility engine (`inc/visibility/`); see `CLAUDE.md`, ADR-009, ADR-018 (Finding B). No new roles, taxonomy or engine are required.
**Source of requirements:** client workbook `docs/client-input/customer-buckets/` (sheet "Opis bucketa B2B" + image). Not tracked in Git.

Evidence labels: **VERIFIED** (staging data / code), **STRONGLY SUPPORTED** (consistent product evidence, no explicit APROS-to-client mapping), **PENDING** (client decision), **CONDITIONAL** (configuration choice that depends on a pending decision).

---

## 1. Verified technical facts

- Bucket types: `rule_based` (brands, categories, explicit include, explicit exclude), `full_access`, `custom_offer` (per-user table `wp_dp_product_access`, admin screen *WooCommerce → Custom Offer Products*). User assignment: user meta `dp_bucket_id`. No bucket, a bucket that is unpublished/invalid, or a `rule_based` bucket with no rules → `no_access` (fail-closed). VERIFIED (`class-visibility-engine.php`).
- Rule semantics: allow = brand match **OR** category match **OR** explicit include; then explicit exclude is subtracted. No category-level deny. Category match is by exact term ID, **descendants are not expanded** (`class-query-filter.php`). VERIFIED.
- All 72 published IZIPIZI products have exactly one category, always a leaf (807–816); none is attached to both a parent and a child. Therefore selecting leaf categories is sufficient; selecting parent 806/813 would match nothing. VERIFIED (staging, 2026-11).
- Categories 806–816 contain IZIPIZI products only. Other brands (379 of 451 published products) live in other categories. VERIFIED.
- Enforcement already covers catalog, search, direct URL, REST/Store API, Quick Order, add-to-cart, cart-quantity increase and checkout (ADR-018 addenda 2026-10-08). Eligibility is evaluated on the parent product of a variation. VERIFIED.
- Reassigning a user changes the cache key (`dp_vis_ctx_{user}_{bucket_ver}_{override_ver}` includes the current `dp_bucket_id`), editing a bucket bumps its version. VERIFIED by code reading; not runtime-tested for this feature.
- Existing staging buckets (130 Full range, 131 demo, 134 NUUNA only, 135/136 empty, 137 test custom offer) are fixtures and do not correspond to the client's five buckets. Do not reuse or rename them; create new records. VERIFIED.

## 2. Category mapping used (none of the inferred mappings is client-approved)

| Client term | WooCommerce category | Products | Status |
|---|---|---|---|
| SUN ADULT | 810 `suncane` | 16 | INFERRED (all `pa_dob` = adult) |
| ACTIVE LINE | 809 `sportske` | 10 | STRONGLY SUPPORTED (adult sport models; Excel note on a #Journey buyer is consistent) |
| SUN CHILD | 814 `djecje-suncane-naocale` | 18 | STRONGLY SUPPORTED |
| ACCESSORIZE ("whole") | 812 `dodaci-za-naocale` | 12 | STRONGLY SUPPORTED (all IZIPIZI accessories are in 812, none elsewhere) |
| CHILD | 814, 815, 816 (children's node `002003`) | 18 + 0 + 1 | PENDING — see decision 4 |
| Junior Snow | 816 `djecje-sportske-naocale` | 1 | PENDING — see decision 3 |
| CHILD ACCESSORIZE | no category; 4 products inside 812 by title | 4 | PENDING — see decision 5 |
| Reading / Screen / Sleeping | 807 / 808 / 811 | 6 / 7 / 2 | VERIFIED by name; named in no restricted bucket → FULL-RANGE only |

APROS side (ADR-013): the original per-product classification is not preserved on the product; only the mapped category remains. In the category mapping, adults are `002002…`, children `002003…`, accessories a separate node `002007` without an age split. The four candidate child accessories (Child Rope Cord, Child Hard Case, Child Pearl Cord Candy Drops, Pouch Junior) are identifiable only by product title (one carries `pa_dob` = junior).

## 3. Unresolved decisions (do not configure around them silently)

1. **Other brands for restricted buckets.** Does SUN ADULT & CHILD / SPORT / CHILD restrict only IZIPIZI or the whole shared catalog? The client text ("samo kategorije…") is written for IZIPIZI lines.
2. **FULL-RANGE scope.** All brands, or the complete IZIPIZI range only?
3. **Junior Snow (816).** ACTIVE LINE, CHILD, or both?
4. **CHILD scope.** Children's sunglasses only (= 814) or all children's eyewear (814 + 815 + 816)?
5. **CHILD ACCESSORIZE.** Confirm the four products above.
6. **CUSTOM OFFER.** Product/category list per customer (7 customers).
7. **Future products.** Within approved categories: automatic or after separate approval?

Also open, not a bucket decision: policy for approved customers without a bucket is currently "no access" (engine behavior); keep unless the client objects.

## 4. Representation in the existing engine

All rows are `rule_based` unless stated. "Categories" = leaf term IDs (no descendant expansion needed for the current catalog).

| Bucket | Type | Configuration | Conditional parts |
|---|---|---|---|
| SUN ADULT & CHILD | `rule_based` | categories 810, 809, 814, 812 | add 816 if Junior Snow belongs to ACTIVE LINE (decision 3) |
| SPORT | `rule_based` | category 809 | add 816 if decision 3 says so |
| CHILD | `rule_based` | categories 814 (+815, +816 per decisions 3/4); **explicit include** = the four confirmed child accessories (`dp_explicit_include`) | 812 must NOT be added (would expose adult accessories); neutral accessories only if the client says so, then via explicit include |
| FULL-RANGE | `full_access` | none | If decision 2 = IZIPIZI only: `rule_based` with brand IZIPIZI (term 257) instead of `full_access` (brand rule is acceptable here because the whole brand is the intent) |
| CUSTOM OFFER | `custom_offer` | per-user rows in `wp_dp_product_access` via the admin screen | Category-agreed lists must be expanded to product IDs manually; no category ceiling and no auto-add exist today |

Rules for the restricted buckets:

- Do not add a brand rule to the three restricted buckets: brand OR category would open the entire IZIPIZI range.
- If decision 1 = "restricted buckets also limit other brands", the category rules above already exclude them (fail-closed). If decision 1 = "other brands stay available", the engine has no "category-restricted for one brand, open for others" mode; that would need either brand rules for all other brands (maintenance burden) or a small engine extension. Decide after the answer; do not implement now.
- Explicit include is a static product list: new child accessories must be added by hand (decision 7 covers whether that is acceptable). Variations are covered through the parent.
- A product in two categories is visible if any one matches (OR). Currently only 3 demo products have two categories.

## 5. Configuration gaps (development, independent of client answers)

- No UI/bulk path to assign `dp_bucket_id` to users (see `customer-bucket-assignment-plan.md`).
- Optional hardening: expand category descendants in `resolve_bucket()`; not needed for the current catalog, relevant only if parent categories are ever selected.

## 6. Not to be done before client answers

Creating the five bucket records on staging/production, assigning customers, or treating INFERRED/STRONGLY SUPPORTED mappings as approved.

# Customer Bucket Configuration Matrix (IZIPIZI wholesale)

**Status:** PREPARATION ONLY — nothing configured, no bucket records created. Client answers to decisions 1-7 RECEIVED 2026-11-24 (section 3a); configuration still not performed and one engine question is open.
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

### 3a. Client answers 2026-11-24 (source `docs/reference/client-apros/2026-11-24/B2B Bucket.docx`, red = client; tentative where stated)

1. Restrictions apply ONLY to IZIPIZI products. 2. FULL-RANGE = the complete IZIPIZI assortment. If the other brands stay open to every bucket (the consistent reading of answer 1), FULL-RANGE is `full_access`; the `rule_based` brand-257 variant would hide all other brands and fits only if the owner wants FULL-RANGE customers limited to IZIPIZI (to be confirmed). 3. Junior Snow = ACTIVE LINE only (add 816 to ACTIVE LINE / SPORT and to SUN ADULT & CHILD where ACTIVE LINE is included; not to CHILD); ACTIVE LINE and SPORT are the same category. 4. CHILD = children sunglasses only (814). 5. CHILD ACCESSORIZE = the four listed products (Child Pearl Cord Candy Drops out of stock until further notice). 6. CUSTOM OFFER lists: the client will send them and asked for the deadline (answer owed by us). 7. New products are NOT automatic: separate approval (compatible with per-user product rows; a category answer must be expanded to current product IDs).

### 3b. A14 assessment - restrictions only on IZIPIZI (2026-11-24, from the existing documentation and a read-only reading of `inc/visibility/`; nothing configured or modified)

**Engine facts that decide it:** visibility comes ONLY from the bucket (`dp_access_type`: `full_access`, `rule_based`, `custom_offer`); no separate per-brand access rules exist, so "other brands stay governed by their own rules" can only be satisfied if the bucket itself lets them through. `rule_based` allow = brand OR category OR explicit include, minus explicit exclude (no category deny, no per-brand scoping); when a bucket has brand rules, `filter_brand_terms()` limits the `product_brand` term lists (brand lists, filters, archives) to those brands.

| | A. Configuration only | B. Minimal targeted engine extension |
|---|---|---|
| How | each restricted bucket = categories of the allowed IZIPIZI lines + a brand rule for EVERY other brand + explicit include for CHILD ACCESSORIZE; FULL-RANGE = `full_access`; CUSTOM OFFER unchanged | a new bucket option "limit brand X to these categories / products, leave other brands open" (changes in the bucket field group, `resolve_bucket()`, the context object, `build_rule_based_where()`, `filter_brand_terms()` and any guard reusing the same clause) |
| New brand in the catalog | not visible to the 3 restricted buckets until added to each one (fail-closed, silent hiding) | visible automatically |
| Unintended exposure | low: IZIPIZI stays whitelist-only (new IZIPIZI products in allowed leaf categories appear; others need explicit approval, which matches the client's answer 7) | needs care: a mistake in the new branch could open all of IZIPIZI; mitigated by tests |
| Unintended hiding | brand rules make `filter_brand_terms()` drop the IZIPIZI term from brand lists / filters / archive for the restricted buckets, although some IZIPIZI products are allowed (functional defect, not just maintenance) | none expected if the brand-term filter is adapted too |
| Effort | records: 4 h (in the register); the brand-term side effect needs its own check | NOT supported by existing evidence: requires a focused read-only investigation of every enforcement point (catalog, search, direct URL, REST / Store API, Quick Order term scope, add-to-cart, cart, checkout, WBW compat) and the five `vis_*` test users, then an estimate; the visibility system stays frozen until the owner approves |

**Owner direction 2026-11-24:** the preferred long-term direction is the minimal targeted extension (B) rather than maintaining a whitelist of every unrelated brand in each restricted bucket. It is NOT an approved implementation: the business rule must first be confirmed by the client, no engine modification and no bucket configuration is approved, and any other independently applicable access restriction must be preserved.

**Open question that decides between them:** do restricted-bucket customers - and FULL-RANGE and CUSTOM OFFER customers - see ALL other brands, including brands added later? (Client confirmation requested, see roadmap 7c.)

**Open consequence (owner decision):** answer 1 means the restricted buckets still see the other brands, which the engine cannot express (section 4: brand rules for all other brands, or a small extension of the frozen visibility system). Owner confirmation of that reading is required before any configuration.

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

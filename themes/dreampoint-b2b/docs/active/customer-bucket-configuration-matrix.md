# Customer Bucket Configuration Matrix (IZIPIZI wholesale)

**Status (2026-11-26):** the five business buckets are CONFIGURED on STAGING, verified with the A14 engine and through the rendered admin UI, and OWNER ACCEPTED: COMPLETE / VERIFIED WITHIN APPROVED SCOPE (section 7). A14 as a whole is NOT complete (DreamPoint confirmations, CUSTOM OFFER lists / granularity, customer assignments and CUSTOM OFFER assignments are open). No customer is assigned, no CUSTOM OFFER list received, CUSTOM OFFER granularity deferred. Sections 1-6 keep the original preparation text (superseded where section 7 says otherwise; the engine question of section 3b was resolved by the accepted A14 core engine, ADR-046).
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

## 7. Actual staging configuration (2026-11-26; COMPLETE / VERIFIED WITHIN APPROVED SCOPE / OWNER ACCEPTED)

Created as NEW records (staging only, `dp_bucket` posts, status publish, NO customer assigned). Existing fixture buckets 130 / 131 / 134 / 135 / 136 / 137 were not reused or renamed: each is either assigned to a `vis_*` test customer or a demo record, and section 1 forbids repurposing them. Backup before the change: `/home/dreampoint.b2b/backups/a14-buckets-20261126-094721/` (`dp_bucket_config.json` = bucket posts, postmeta and the `dp_bucket_id` / override usermeta; `dp_product_access.sql`).

| Bucket (ID) | Access type | Scope Brand | Allowed categories | Explicit include |
|---|---|---|---|---|
| FULL-RANGE (23614) | Full Access | none | - | - |
| SUN ADULT & CHILD (23615) | Rule Based | Izipizi (257) | 810 Sunčane, 809 Sportske (ACTIVE LINE), 814 Dječje sunčane, 812 Dodaci (whole ACCESSORIZE), 816 Dječje sportske (Junior Snow) | - |
| SPORT (23616) | Rule Based | Izipizi (257) | 809 Sportske (ACTIVE LINE = SPORT), 816 Dječje sportske (Junior Snow) | - |
| CHILD (23617) | Rule Based | Izipizi (257) | 814 Dječje sunčane (children sunglasses only) | 17522 `#CHILD Rope Cord` (P-57564), 17532 `CHILD Hard Case` (P-57565), 17458 `CHILD Pearl Cord Candy Drops` (P-57500, out of stock, kept), 12773 `#POUCH JUNIOR` (P-55623) |
| CUSTOM OFFER (23618) | Custom Offer | Izipizi (257) | - | no allow records (list awaited) |

No explicit excludes are configured. 812 is NOT part of CHILD; 815 (children's screen, 0 published products) and 807 / 808 / 811 are in no restricted bucket (FULL-RANGE only).

**Verified eligible published parents (engine, in-memory bucket injection, no persistent fixture):** FULL-RANGE 72 IZIPIZI + 379 other = 451; SUN ADULT & CHILD 57 + 379 = 436; SPORT 11 + 379 = 390; CHILD 22 + 379 = 401; CUSTOM OFFER 0 + 379 = 379. For every bucket SQL == PHP parity holds, the IZIPIZI set equals the documented mapping computed independently from the term tables, all 379 non-IZIPIZI parents are eligible, and variations of published parents follow their parent (1284 of 1284 consistent). IZIPIZI only in FULL-RANGE (15): #B / #C / #D / #E / #L READING, #OFFICE READING, six SCREEN products, two SLEEPING products, #CRUISE SCREEN.

**Evidence labels.** Client-confirmed (2026-11-24, section 3a): restrictions only on IZIPIZI; FULL-RANGE = complete IZIPIZI assortment; Junior Snow = ACTIVE LINE only; ACTIVE LINE = SPORT; CHILD = children sunglasses only; CHILD ACCESSORIZE = the four listed products; CUSTOM OFFER lists to be sent. Working interpretations (NOT client-confirmed, awaiting DreamPoint): the category-to-client-term mapping of section 2 (810 = SUN ADULT, 809 = ACTIVE LINE, 814 = SUN CHILD, 812 = whole ACCESSORIZE) is INFERRED / STRONGLY SUPPORTED; new IZIPIZI products are automatically available in FULL-RANGE and in the category-based buckets (within their configured categories) while CUSTOM OFFER needs separate approval - the client's "zasebno odobriti" appears only in the CUSTOM OFFER section; the identification of the four CHILD accessories by title (unambiguous in the current catalog). Still open: customer assignments (none performed), CUSTOM OFFER lists (DreamPoint), CUSTOM OFFER granularity (parent vs variation, deferred), the 5 h CUSTOM OFFER assignment estimate (provisional, unapproved).

**Unchanged (verified against the backup):** customer-to-bucket assignments (`dp_bucket_id` usermeta, 4 rows, identical hash), `wp_dp_product_access` (5 rows of user 6, identical hash), users 9 / customers 8, published products 451, brand terms 61. No application code, ACF definition, schema or importer file was changed.

**Acceptance record (2026-11-26).** Technical verification: SQL == PHP parity for all five buckets; IZIPIZI sets equal to the documented mapping computed from the term tables; 1284 of 1284 variations of published parent products consistent (variations of the 7 unpublished parents appear only in raw mixed queries, no customer path). Administrative UI QA through a headed Playwright browser (owner authenticated manually, no credentials collected; read-only, nothing saved): 6/6 PASS - all five buckets rendered with the expected Access Type, Scope Brand and categories / includes (CHILD: exactly 814 + the four accessories, 812 not selected; SUN ADULT & CHILD: exactly 810, 809, 814, 812, 816; SPORT: exactly 809, 816; FULL-RANGE: Scope Brand hidden; CUSTOM OFFER: Scope Brand Izipizi, no allow records), 11 bucket records (5 new + the 6 preserved demonstration / test buckets), and the Users screen "Bucket" column shows no customer on any of the five new buckets (only the existing `vis_*` test customers keep their old buckets); the five Custom Offer records of the existing `vis_offer` customer are preserved, all other customers have 0. The bucket list and bucket editor do not display assigned customers; that was verified on the Users screen instead. The TinyMCE "unload" permissions-policy console message is a WordPress core / browser warning, unrelated.

**Deployment and assignment guidance (staging IDs are NOT portable).** The IDs above (buckets 23614-23618, categories 806-816, brand term 257, products 17522 / 17532 / 17458 / 12773) exist on STAGING only; this project has no production environment, and any other installation must re-resolve every ID by name before configuring: brand `Izipizi` (slug `izipizi`); categories by slug `suncane`, `sportske`, `djecje-suncane-naocale`, `dodaci-za-naocale`, `djecje-sportske-naocale`; the four CHILD accessories by exact title (`#CHILD Rope Cord` P-57564, `CHILD Hard Case` P-57565, `CHILD Pearl Cord Candy Drops` P-57500, `#POUCH JUNIOR` P-55623) after confirming each is unambiguous in that catalog. Customer assignment is a separate, unapproved step (user meta `dp_bucket_id`; there is no bulk UI, see `customer-bucket-assignment-plan.md`). Staging backup of the pre-configuration state: `/home/dreampoint.b2b/backups/a14-buckets-20261126-094721/` (`dp_bucket_config.json`, `dp_product_access.sql`).

**Outstanding (not part of the accepted technical configuration):** DreamPoint confirmation of the category-to-client-term mapping and of the working interpretation that new IZIPIZI products are automatically available in FULL-RANGE and the category-based buckets (CUSTOM OFFER = separate approval); the seven customer-specific CUSTOM OFFER lists; the final CUSTOM OFFER access granularity (DEFERRED until the lists are reviewed - the implemented parent-product model is a currently supported capability, NOT a client-confirmed requirement; individual variation-level access is not implemented); actual customer-to-bucket assignments; CUSTOM OFFER assignments (provisional 5 h estimate, unapproved).

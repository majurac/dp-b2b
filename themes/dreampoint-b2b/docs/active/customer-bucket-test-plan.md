# Customer Bucket Functional Test Plan

**Status:** PLAN ONLY — not executed. Run on staging after the five buckets are configured and the client decisions in `customer-bucket-configuration-matrix.md` §3 are closed.
**Architecture under test:** existing visibility engine and enforcement (ADR-009, ADR-018 addenda 2026-10-08). This plan verifies the new bucket **configuration**, not the engine.
**Rules:** staging only, real HTTP/browser (User Switching), one runner at a time (`rules/playwright.md`), no production, no new orders unless explicitly approved (use cart + checkout-validation responses; a positive checkout creates a real order). Set `DP_BYPASS_APPROVAL` awareness: with bypass `true` pending-user tests cannot be run (record as NOT TESTED).

Notation: `[C]` = expected result is conditional on a pending client decision (matrix §3); record the decision used.

## 0. Preconditions

- Five bucket records configured exactly per the approved matrix; their IDs recorded.
- Disposable test customers, one per bucket, plus: no-bucket, pending, shop manager. Administrator available. Test accounts only, never real customers.
- Reference products identified by ID before testing: adult sunglasses (810), adult sports (809), children's sunglasses (814), Junior Snow (816), general accessory (812), the four child accessories, reading (807), screen (808), sleeping (811), one product of another brand, one CUSTOM OFFER allowed and one disallowed product.
- Baseline per bucket: expected product count in the shop list (from the matrix, not from the result).

## 1. Expected access by bucket (catalog)

| Product group | SUN ADULT & CHILD | SPORT | CHILD | FULL-RANGE | CUSTOM OFFER |
|---|---|---|---|---|---|
| Adult sunglasses (810) | yes | no | no | yes | only if listed |
| Adult sports (809) | yes | yes | no | yes | only if listed |
| Children's sunglasses (814) | yes | no | yes | yes | only if listed |
| Junior Snow (816) | [C] 3 | [C] 3 | [C] 3/4 | yes | only if listed |
| General accessories (812) | yes | no | no [C] | yes | only if listed |
| Four child accessories | yes (inside 812) | no | [C] 5 | yes | only if listed |
| Reading / screen / sleeping (807/808/811) | no | no | no | yes | only if listed |
| Other brands | [C] 1 | [C] 1 | [C] 1 | [C] 2 | only if listed |

[C] refs: 1 other brands for restricted buckets, 2 FULL-RANGE scope, 3 Junior Snow, 4 CHILD scope, 5 child-accessory confirmation (matrix §3).

## 2. Scenarios

For each scenario: precondition = logged in as the named user; evidence = HTTP status, response snippet (no personal data), product counts, screenshot where UI.

### 2.1 Bucket assignment states
| ID | Scenario | Expected | Pass/fail |
|---|---|---|---|
| B1–B5 | One user per bucket opens `/shop/` | list equals the bucket's product set from §1 | counts equal the matrix; any extra or missing product = fail |
| B6 | Approved user, no `dp_bucket_id` | no products, direct URLs 404, Store API empty/403, add-to-cart rejected | any visible product = fail |
| B7 | Pending user (needs `DP_BYPASS_APPROVAL=false`) | redirected to `/approval-pending`, REST/Quick Order expose nothing | else fail; NOT TESTED if bypass cannot be turned off |
| B8 | Administrator and shop manager | full catalog, add-to-cart works | restriction applied to staff = fail |
| B9 | Bucket published → draft, then restored | users of that bucket see nothing while draft, set restored afterward | any residual access while draft = fail |

### 2.2 Access surfaces (repeat for at least SPORT, CHILD, CUSTOM OFFER, plus FULL-RANGE for completeness)
| ID | Surface | Test | Expected |
|---|---|---|---|
| S1 | Shop + category archives | open 809/810/812/814/816 archives, brand archive | only allowed products; disallowed categories empty/not shown |
| S2 | Search | search a disallowed product name/SKU/barcode | no result |
| S3 | Product page | direct URL of allowed and disallowed product | allowed 200; disallowed 404 (no existence disclosure) |
| S4 | WP REST `wp/v2` and WC REST | product item/list of disallowed product | scrubbed or error, no price/data |
| S5 | Store API | `GET /wc/store/v1/products`, single product, add-item of disallowed ID | list limited; single 404; add-item 400/404; cart unchanged |
| S6 | Quick Order | list, search, variations of disallowed parent, import/validate, `cart/sync` add and increase | disallowed absent / `product_unavailable`; variation request 404 `not_variable` |
| S7 | Add-to-cart (classic `?add-to-cart=`, wc-ajax, Store API) | disallowed ID | rejected, cart empty |
| S8 | Variations | allowed parent: all variations purchasable; disallowed parent: none | per parent eligibility |
| S9 | Filters (WooFilter) | injected filter values for disallowed brand/category | no chip, no leaked term, no extra results |

### 2.3 Cart and checkout after bucket changes
| ID | Scenario | Expected |
|---|---|---|
| C1 | User with allowed items in cart; reassign to a narrower bucket | existing lines kept; quantity increase rejected (400 `dp_b2b_product_not_available`); same/lower quantity and removal allowed |
| C2 | Same cart, `POST /wc/store/v1/checkout` | 409 `dp_b2b_product_not_available`, no order created (order count and max ID unchanged) |
| C3 | Reassign back | checkout validation passes (do not place a real order unless approved) |
| C4 | Quick Order sync on an ineligible existing line | increase `failed/product_unavailable`; quantity 0 removes |
| C5 | Bundle containing an out-of-bucket child (only if bundles are used) | document behavior; ADR-018 notes silent child skip |

### 2.4 Cache invalidation
| ID | Scenario | Expected |
|---|---|---|
| K1 | Reassign user A→B, reload within seconds | new product set immediately, no stale list |
| K2 | Edit the bucket's category rule, reload | change visible without manual purge |
| K3 | Add a product to an allowed category | visible on next request |
| K4 | Edit `dp_explicit_include` (child accessories) | change visible to CHILD user |
Evidence: before/after counts, timestamps. Do not purge caches during the test.

### 2.5 Product-specific
| ID | Scenario | Expected |
|---|---|---|
| P1 | Adult sunglasses vs CHILD/SPORT users | CHILD, SPORT: not visible |
| P2 | Adult sports vs SPORT, SUN ADULT & CHILD, CHILD | yes, yes, no |
| P3 | Children's sunglasses vs CHILD, SUN ADULT & CHILD, SPORT | yes, yes, no |
| P4 | Junior Snow vs all restricted buckets | [C] 3 |
| P5 | General accessory (e.g. adult pouch) vs CHILD | no [C] 5 |
| P6 | Each of the four child accessories vs CHILD and SUN ADULT & CHILD | [C] 5; confirm adult accessories stay hidden from CHILD |
| P7 | Reading, screen, sleeping vs restricted buckets | not visible; visible to FULL-RANGE |
| P8 | Product of another brand vs every bucket | [C] 1, 2 |
| P9 | CUSTOM OFFER allowed vs disallowed product | allowed visible and purchasable; disallowed 404 on every surface in §2.2 |
| P10 | CUSTOM OFFER with no product rows | sees nothing |
| P11 | Product in two categories (use a demo product) | visible if any category allowed; document |

## 3. Pass/fail and evidence

- A bucket passes only if **every** scenario above matches the expected result and no product outside its set is reachable by any surface.
- Any disclosure of a disallowed product's existence, price or stock through any surface is a fail, even if purchase is blocked.
- For each run record: environment, commit hash of staging, test-account IDs, bucket IDs, decisions used for `[C]` rows, per-scenario HTTP status/snippet, order count before/after.
- Out of scope: pricing, ERP export, performance, full regression. Known untested areas from ADR-018 (classic checkout form, external gateways, shop manager on staging) remain separately listed.

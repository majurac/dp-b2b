# Architectural Decisions — Dreampoint B2B

Log arhitekturalnih odluka (ADR — Architecture Decision Record) za projekat. Svaka odluka dokumentuje kontekst, samu odluku, i posledice. Odluke se ne brišu kad zastare — označavaju se kao Superseded uz referencu na odluku koja ih zamenjuje.

**Format:** Status / Context / Decision / Consequences / Related

---

## ADR-001 — Pricing Architecture (Model A domaći + Model C strani)

**Datum:** 2026-07-02
**Status:** Accepted (arhitektura) — payload finalizacija u toku
**Vlasnik:** ERP integracija (Apros)

### Context

Semantika `wholesalePrice` polja u Apros `articleList/get` endpointu je bila nerazriješena od Maja 2026 — dva nezavisna iskustvena izvora (Leo Benkek email, integrator; Milenko Stojaković, developer B2C iskustva) davala su direktno konfliktne interpretacije:

- **Leo Benkek:** `wholesalePrice` = bazna/katalog cijena, ista za sve partnere; Rabat 1 je obavezan per-partner mehanizam za finalnu cijenu (Model A)
- **Milenko Stojaković:** `wholesalePrice` = generalno već finalna B2B cijena; Rabat 1 je rijedak opcionalni sloj (Model B)

Ovaj konflikt (V-01 u `docs/erp-discovery-findings.md`) je bio najveći arhitekturalni bloker projekta (BL-03, P0) — svaki model zahtijeva potpuno drugačiju pricing engine arhitekturu (storage, runtime kalkulacija, caching).

Dodatno, tretman stranih kupaca (bez Rabat 1 mehanizma, cjenik po državama) je zahtijevao zaseban paralelni model (Model C), nezavisno od domaćeg pricing pitanja.

### Decision

Nakon direktnog Apros odgovora (2026-07-02):

- **Domaći kupci → Model A.** `wholesalePrice` je bazna veleprodajna cijena. Rabat 1 je postotni popust po partneru i brandu (iz `partnerBrandDiscountList` / ugovorni uvjeti endpointa). Finalna cijena = `wholesalePrice − Rabat 1 (%)`.
- **Strani kupci → Model C.** Nema Rabat 1 mehanizma. Finalna neto cijena dolazi direktno iz `countryPriceList`. PDV tretman ovisi o pravnoj/poreznoj kategoriji partnera.
- **Model B se ne implementira.** Dokumentiran je u `docs/b2b-erp-adaptation-blueprint.md` Sekciji 5 isključivo radi povijesnog konteksta konflikta — ne predstavlja aktivnu arhitekturalnu opciju.
- Modeli A i C su **paralelni slojevi po tax profilu korisnika** (`domestic` / `foreign` / `tax_exempt`), ne alternative koje se biraju globalno.

### Consequences

- Pricing engine (Faza 4 / Korak 11) može biti arhitekturalno dizajniran i implementiran (filter hook, storage struktura, caching strategija) bez čekanja na dodatnu Apros validaciju modela.
- **Payload primjer i dalje nedostaje** — egzaktni field nazivi i realne vrijednosti za `partnerBrandDiscountList` i `countryPriceList` nisu dostavljeni. Finalna implementacija i QA (potvrda field mappinga, edge case-ovi) ostaju blokirani do payload primjera — vidi `docs/apros-session-final-pack.md` → "Still Required From Apros".
- BL-03 downgraded: HIGH (arhitekturalni rizik) → MEDIUM (payload finalizacija).
- AP-01 u `docs/apros-question-resolution-matrix.md`: PARTIALLY RESOLVED.
- Storage model (Model A: `_wholesale_price` + `_b2b_brand_rabat` user meta; Model C: `_wholesale_price_country` JSON) je zaključan — promjena nakon implementacije Faze 4 bi zahtijevala rework.

### Related

- `docs/erp-discovery-findings.md` — Discovery Revision — Apros Response Integration Update (NC-06, NC-07)
- `docs/apros-question-resolution-matrix.md` — AP-01
- `docs/b2b-erp-adaptation-blueprint.md` — Sekcija 5 (Pricing Architecture), Sekcija 9 (AP-01)
- `docs/b2b-erp-migration-plan.md` — Korak 11

---

## ADR-002 — Partner Approval Architecture (polling/import, ne webhook)

**Datum:** 2026-07-02
**Status:** Accepted
**Vlasnik:** ERP integracija (Apros) / Customer-Partner Architecture

### Context

Raniji arhitekturalni dizajn (`docs/b2b-erp-adaptation-blueprint.md`, planirano prije 2026-07-02) je pretpostavljao da Apros po odobrenju partnera poziva WP inbound webhook (`POST /wp-json/dreampoint-b2b/v1/approve-partner`) koji nosi `sif_kup`, ugovorne uvjete i/ili `advance_only`/`free_shipping` flagove. Ova pretpostavka je bila nevalidirana (NC-05 u discovery findings — potvrđen samo konceptualni tok, ne tehnički mehanizam) i predstavljala je P1 bloker (ranije AP-05 u internoj blueprint numeraciji; AP-03 u kanonskoj `apros-question-resolution-matrix.md`).

### Decision

Apros je direktno potvrdio (2026-07-02): **approval webhook ne postoji.** Potvrđeni tok:

```
web registracija → email notifikacija (Točka sna) → ručno kreiranje partnera u Apros-u
  → Apros postavlja atribut B2B KUPAC = DA
  → partner se pojavljuje na partner list endpointu (nema signala prema WP)
```

Partner sinkronizacija se implementira kao **cron-based polling/import job** — WP periodično poziva partner list endpoint, detektira nove/promijenjene `B2B KUPAC = DA` zapise, i za svaki pokreće partner data fetch + B2B rola dodjelu. Inbound REST endpoint receiver (`approve-partner`) se **ne implementira** u ranije planiranom obliku.

Kao direktna posledica, `advance_only`/`free_shipping` propagacija (ranije AP-08) je reklasificirana kao **OUT OF SCOPE** za inicijalnu implementaciju — pitanje se temeljilo isključivo na webhook mehanizmu koji ne postoji.

### Consequences

- `docs/b2b-erp-adaptation-blueprint.md` Sekcija 4 (Approval Lifecycle) je prepravljena — koraci [5]-[6] (webhook) zamijenjeni koracima [5]-[7] (polling job detekcija i fetch).
- `docs/b2b-erp-migration-plan.md` Korak 10 preimenovan iz "Approval webhook endpoint" u "Partner polling/import job" — može startati **odmah**, bez daljnje Apros validacije (prethodno: 🔴 NE, sada: ✅ DA).
- Korak 9 (partner sync adapter) prelazi iz potpuno blokiranog u djelomično odblokiran — arhitektura poznata, ostaje payload finalizacija za delivery locations (AP-07) i partner list format (PL-01, bez kanonskog AP ekvivalenta).
- Operativna implikacija: onboarding kašnjenje sada ovisi o cron frekvenciji polling joba, ne o webhook pouzdanosti — frekvencija nije arhitekturalni bloker, samo operativna optimizacija.
- Deaktivacijski mehanizam (ranije pretpostavljen kao mogući budući webhook) je sada konzistentno tretiran kao dio istog polling modela — nema posebnog deaktivacijskog kanala.
- AP-03 u `docs/apros-question-resolution-matrix.md`: RECLASSIFIED. AP-08: OUT OF SCOPE.

### Related

- `docs/erp-discovery-findings.md` — Discovery Revision — Apros Response Integration Update (NC-10)
- `docs/apros-question-resolution-matrix.md` — AP-03, AP-08
- `docs/b2b-erp-adaptation-blueprint.md` — Sekcija 4 (Customer/Partner Architecture), Sekcija 9 (AP-03)
- `docs/b2b-erp-migration-plan.md` — Korak 9, Korak 10
- `docs/project-status-matrix.md` — Sekcija 1.7 (Registracija i onboarding)

---

## ADR-003 — WBW Product Filter Multi-Type Search Compatibility Layer

**Datum:** 2026-07-23
**Status:** Accepted
**Vlasnik:** Catalog Filters (WBW Product Filter integration)

### Context

WBW Product Filter (Free/PRO, version 3.1.8 as verified) does not provide native search-box support for Category or Brand filter blocks when their Frontend Type is set to "Multi" (multi-select checkboxes) — the admin "Show search" option is not even exposed for that display type, and no search markup is rendered. This affects `[wpf-filters id=1]` (main shop archive) and `[wpf-filters id=3]` (Quick Order), both of which use Multi-type Category/Brand blocks.

### Decision

The theme implements a compatibility layer at `inc/wbw-multi-search-compat.php` that injects the missing search `<input>` markup server-side, reusing WBW's own existing frontend JavaScript and CSS unchanged. The layer intentionally hooks the official, documented WBW extension point `wpf_addHtmlAfterFilter` (via `DOMDocument`/`DOMXPath` post-processing) instead of patching, subclassing, or modifying any vendor plugin file. It is scoped only to filter ids 1 and 3.

### Consequences

- Zero vendor modifications — WBW/WBW-PRO can be updated freely without merge conflicts.
- The compatibility layer includes a built-in duplicate-prevention check: if a `.wpfSearchWrapper` is already present on a block, injection is skipped automatically.
- **Before modifying or removing `inc/wbw-multi-search-compat.php` in the future, first verify whether the installed WBW version now provides native Multi-type search support.** If native support exists and is functionally equivalent (including hierarchical unfolding/collapse behavior for Category), remove the compatibility layer instead of maintaining it further.

### Related

- `inc/wbw-multi-search-compat.php` — implementation and inline maintenance documentation (vendor line references, removal criteria)

---

## ADR-004 — WBW Product Filter Price-Index Coverage Gap (`woocommerce_new_product`)

**Datum:** 2026-08-04
**Status:** Accepted
**Vlasnik:** Catalog / Shop Archive Sorting (WBW Product Filter integration)

### Context

`orderby=price` on `/shop/` (native WooCommerce "Sort by price" dropdown) was reported to sort incorrectly during Akcija page validation. Investigation traced ownership to WBW Product Filter, not the theme or the visibility engine: `woo-product-filter/modules/woofilters/mod.php` → `forceProductFilter()` (hooked `pre_get_posts`, priority 9999) detects the native `orderby` GET parameter via `isFiltered()` and registers its own `posts_clauses` callback (`addPriceOrder()` / `addPriceOrderDesc()`, priority 99999) — this runs after, and unconditionally overwrites, WooCommerce's own native price-ordering `posts_clauses` callback (`WC_Query::order_by_price_asc_post_clauses()` / `..._desc_post_clauses()`, registered at default priority 10 inside `get_catalog_ordering_args()`).

WBW's price ordering is driven by its own denormalized index table (`{$wpdb->prefix}wpf_meta_data`, `key_id` for `_price`), not WooCommerce's native `wc_product_meta_lookup`. Empirical SQL/data comparison (WP-CLI, read-only, cross-checked against `wc_get_product()->get_price()` as ground truth) confirmed:

- WBW's index values, where present, were **never stale** (0 mismatches against live `_price` postmeta).
- The index was **incomplete**: 220 of 427 published products (~51%) had no row at all for the `_price` key. WBW's `LEFT JOIN` treats a missing row as SQL `NULL`, and MySQL sorts `NULL` before every real value in `ASC` order — so unindexed products (regardless of actual price) always appeared first, ahead of genuinely cheap products. One concrete example: a product priced 27.47 ranked #1 (cheapest) ahead of a product priced 3.29.

Root cause of the incompleteness: WBW's incremental index maintenance (`modules/meta/mod.php:27` — WBW 3.2.0 path; see the 2026-09-01 re-verification note below for the 3.4.0 file/class rename) hooks **`woocommerce_update_product` only** —

```php
add_action( 'woocommerce_update_product', array( $this, 'recalcProductMetaValues' ), 99999, 1 );
```

— and does not hook `woocommerce_new_product` anywhere in either the Free or PRO plugin (confirmed by full-text search across both plugin directories). WooCommerce fires `woocommerce_new_product` the first time a product is created (`WC_Product::save()` on an object with no ID → `WC_Product_Data_Store_CPT::create()`) and only fires `woocommerce_update_product` on a later save of an already-existing product ID (`...::update()`). A product created once via the standard, WooCommerce-native `WC_Product::save()` API and never re-saved is therefore never indexed by WBW's automatic path — confirmed directly against this theme's own `inc/dev/class-dev-catalog-generator.php`, which creates products via the fully correct `new WC_Product_Simple(); ...; $product->save();` pattern and still produced the gap. Products that happened to be indexed had all been re-saved at least once after creation (e.g. via the generator's separate `refresh-metadata` phase, or `WC_Product_Variable::sync()`, both of which perform a genuine update on an existing ID).

WBW ships an official remediation path for this class of problem: a manual "Start indexing product parameters" admin button (`modules/meta/mod.php:79-90`, explicitly documented for post-import scenarios) and an optional hourly background reindex (`wp_cron` event `wpf_calc_meta_indexing_shedule`, gated by an admin toggle). Neither had ever been used on this installation (`wp cron event list` showed no `wpf_calc_meta_*` job scheduled; the plugin's own options table had no row at all for `start_indexing`/`indexing_schedule`). Because the automatic incremental path structurally cannot cover product creation, relying solely on the manual/scheduled mechanism leaves a real-time correctness window open for every future product creation (WP admin, and eventually ERP sync in Phase 4) — this is a gap in WBW's own hook coverage, not a data-entry mistake.

### Decision

Two-part remediation, no vendor files modified:

1. **One-time index rebuild** — WBW's own official full-recalculation API was invoked directly (not reproduced manually): `FrameWpf::_()->getModule('meta')->getModel()->recalcMetaValues();` (no arguments → full recalc branch in `MetaModelWpf::doRecalcMetaValues()`). This is the exact call WBW's own scheduled reindex (`recalcMetaIndexingShedule()`, `modules/meta/mod.php:283`) uses internally.
2. **Permanent compatibility hook** — `inc/wbw-price-indexing-compat.php` (new file, same architectural pattern as `inc/wbw-multi-search-compat.php` / ADR-003) hooks `woocommerce_new_product` and calls WBW's own supported per-product entry point, `MetaWpf::recalcProductMetaValues( $product_id )` — the identical method `woocommerce_update_product` already calls, obtained via `FrameWpf::_()->getModule('meta')->recalcProductMetaValues( $product_id )`. No indexing SQL or model logic is duplicated; this is a thin wiring layer. `woocommerce_update_product` (WBW's existing hook) is untouched — only the creation-time gap is closed. Variable products/variations are handled the same way WBW's own existing hook already handles them: `doRecalcMetaValues()` expands a variable parent ID to its `get_children()` internally, and variation-only changes are already covered (in both WBW's model and this project's dev-catalog generator) via `WC_Product_Variable::sync()` re-saving the parent, which fires `woocommerce_update_product`. WBW's own static de-dupe guard (`MetaWpf::$wpfPreviousProductId`) — inherited for free by calling WBW's own method — prevents redundant recalculation if multiple creation-related hooks fire for the same product ID within one request.

Bypassing or disabling WBW's price-ordering override was explicitly out of scope for this remediation — the goal was to fix the confirmed incremental-indexing gap while preserving the existing WBW integration, not to route around it.

### Consequences

- Index coverage: 207/427 → 407/427 published products after the one-time rebuild. The remaining 20 are simple products with **no `_price` postmeta row at all** (confirmed directly against `wp_postmeta`) — a pre-existing dev-fixture data-quality edge case unrelated to WBW's indexing pipeline; nothing for WBW (or this fix) to index.
- Post-rebuild, native WooCommerce (`wc_product_meta_lookup`) and WBW ordering were verified in full agreement: 0 differing positions across the entire real-priced catalog (407/407) in both ASC and DESC order.
- Validated live: a throwaway product created via `WC_Product::save()` (create path) was immediately present in WBW's index with a matching price, with no manual edit, no `refresh-metadata` run, no full rebuild, and no cron wait — confirming the creation-time gap is closed going forward.
- Zero vendor modifications — WBW/WBW-PRO can be updated freely.
- `inc/visibility/class-query-filter.php` and all other visibility-engine code are untouched; visibility hooks were confirmed still registered post-change.
- **Before modifying or removing `inc/wbw-price-indexing-compat.php` in the future, first verify whether the installed WBW version now hooks `woocommerce_new_product` itself.** If it does, this file's own de-dupe reuse makes it harmless to leave in place, but it should be reviewed and removed once confirmed redundant.

### Re-verification — 2026-09-01 (WBW Free + PRO 3.4.0)

WBW Free and PRO were updated locally 3.2.0 → 3.4.0. This ADR's remediation was re-verified against 3.4.0:

- **Gap unchanged.** WBW 3.4.0 still does **not** hook `woocommerce_new_product` in either Free or PRO (re-confirmed by full-text search + changelog review). The 3.4.0 meta module still registers `woocommerce_update_product` only (plus new-in-3.4.0 `acf/save_post` and stock-status hooks, none of which cover product creation). The compatibility hook is still required.
- **Vendor class prefix refactor (3.4.0).** WBW 3.4.0 prefixed every PHP class with `WooBeWoo_PF_` and renamed the class files to `class-woobewoo-pf-*.php` (changelog: *"Prefixed PHP class names"*, *"Prefixed class file names"*). Old → new mapping for the references in this ADR:
  - `FrameWpf` → `WooBeWoo_PF_Frame`
  - `MetaWpf` → `WooBeWoo_PF_Meta` — `recalcProductMetaValues( $product_id )` and the de-dupe guard `$wpfPreviousProductId` are unchanged in signature and behavior
  - `MetaModelWpf` → `WooBeWoo_PF_Meta_Model` — `doRecalcMetaValues()` unchanged
  - `modules/meta/mod.php` → `modules/meta/class-woobewoo-pf-meta.php` (hook registration at line 29; "Start indexing" option definition; `recalcMetaIndexingShedule()`)
  - `modules/woofilters/mod.php` → `modules/woofilters/class-woobewoo-pf-woofilters.php`
- **Compat layer broke silently on 3.4.0.** `inc/wbw-price-indexing-compat.php` guarded with `class_exists( 'FrameWpf' )` and accessed `FrameWpf::_()` — both gone in 3.4.0 — so the handler returned early and indexed nothing. A controlled create-path test (throwaway `WC_Product_Simple::save()`, WBW framework booted) confirmed the new product was **absent** from the WBW `_price` index: neither WBW-native nor the (dead) compat hook indexed it. `woocommerce_new_product` was confirmed as the only hook firing on that path; `woocommerce_update_product` did not fire.
- **Fix applied (localhost).** `inc/wbw-price-indexing-compat.php` updated: `class_exists( 'FrameWpf' )` → `class_exists( 'WooBeWoo_PF_Frame' )` and `FrameWpf::_()->getModule( 'meta' )` → `WooBeWoo_PF_Frame::_()->getModule( 'meta' )`. No change to hook priority, `woocommerce_new_product` registration, the `WPF_VERSION` guard, the `method_exists()` protection, or indexing logic. No vendor files modified.
- **Post-fix create-path test passed.** After the fix, a throwaway product created via `WC_Product_Simple::save()` was present in the WBW `_price` index immediately with the correct value — no manual `recalcProductMetaValues()` call, no rebuild, no cron wait. Attribution confirmed by an A/B test: with `dreampoint_b2b_wbw_index_new_product` registered the new product was indexed; with it removed via `remove_action()` an identically created product was **not** indexed. `$wp_filter['woocommerce_new_product']` was enumerated — no `WooBeWoo_PF_*` (vendor) callback is registered on that hook; the only WBW-index-related callback is the theme's compat hook. Both throwaway products hard-deleted, all their `wpf_meta_data` / `wc_product_meta_lookup` rows removed, no residue.
- **Post-update index integrity.** The automatic full reindex triggered by the plugin update completed successfully: 407/407 publish/private products with non-empty `_price` are present in the WBW `_price` index, 0 missing, 0 orphan/duplicate rows.

### Related

- `inc/wbw-price-indexing-compat.php` — implementation and inline maintenance documentation (vendor line references, removal criteria)
- `docs/active/status.md` — Staging TODOs item 11 (Akcija validation — original bug report)
- `docs/dev-context.md` → "Akcija (Discounted Products) Page" — original symptom note
- `docs/decisions.md` ADR-006 — 2026-09-02 reuse of this same `recalcMetaValues()` full rebuild to clear orphan `wp_wpf_meta_data` rows after synthetic-product deletion, plus the disproven "16 products with duplicate `_price`" corruption hypothesis

---

## ADR-005 — Malformed `faq-category` Terms from Numeric-String Term IDs

**Datum:** 2026-08-08
**Status:** Accepted — repaired on localhost and staging
**Vlasnik:** FAQ CPT/taxonomy (`faq` / `faq-category`, ACF-registered — `acf-json/taxonomy_683068b151d25.json`)

### Context

During localhost → staging FAQ content synchronization, all 9 `faq` CPT posts (seeded 2026-07-28, commit `1f7bf33`, alongside the FAQ CPT/taxonomy infrastructure itself — no seeding script was committed) were found assigned to `faq-category` terms whose `name` and `slug` were literal numeric strings (`"230"`, `"231"`, `"232"`), while three legitimate, correctly-named terms with those exact numeric term IDs already existed unused (`count = 0`): term 230 "Naručivanje", term 231 "Dostava", term 232 "Plaćanje".

Root cause confirmed directly against the installed WordPress core (`wp-includes/taxonomy.php`), not assumed: `wp_set_object_terms()` calls `term_exists( $term, $taxonomy )`, which only performs an ID-based lookup when `is_int( $term )` is `true` — a numeric **string** (e.g. `"230"`) fails that check and falls through to a slug/name string search instead. When no term with that literal name/slug exists yet, `wp_insert_term( $term, $taxonomy )` silently creates a new term named `"230"` rather than attaching to existing term ID 230. This exactly reproduces the observed data: the intended category IDs (230/231/232) were evidently passed as strings (e.g. from `$_POST`, a JSON-decoded import payload, or similar) during the original 2026-07-28 seeding, instead of as PHP integers.

Evidence for the specific malformed→legitimate mapping (content semantics, term creation order, and 1:1 count correspondence — full investigation not reproduced here): malformed term 233 (`"230"`) held all 3 ordering-question posts → legitimate term 230 "Naručivanje"; malformed term 234 (`"231"`) held all 3 delivery-question posts → legitimate term 231 "Dostava"; malformed term 235 (`"232"`) held all 3 payment-question posts → legitimate term 232 "Plaćanje".

### Decision

Reassigned all 9 FAQ posts (both localhost and staging) from the malformed numeric-name terms to the correct legitimate `faq-category` terms via `wp post term set <id> faq-category <term_id> --by=id` (passes a genuine WP-CLI–resolved term ID, not a string bypassing `is_int()`). Localhost's 3 now-orphaned malformed terms (233/234/235, `count = 0` post-reassignment, confirmed no other object references — `faq-category`'s `object_type` is scoped to `faq` only) were deleted via `wp term delete`. Staging had zero `faq-category` terms of any kind (expected — taxonomy content isn't git-synced); the 3 legitimate terms were created fresh there by stable slug identity (`narucivanje`, `dostava`, `placanje` — new staging term IDs, not copied from localhost) and the 9 already-synced staging FAQ posts (mapped by slug identity, not post ID) were assigned accordingly. No malformed terms were ever created on staging.

`faq.php` does not read `faq-category` anywhere in its query or render logic — this taxonomy was, and remains, purely latent/unused data with no effect on current frontend behavior. The repair is a data-integrity fix, not a functional fix.

### Consequences

- Localhost: exactly 3 `faq-category` terms remain (230/231/232), each `count = 3`. No numeric-name terms remain.
- Staging: 3 fresh legitimate terms created (own IDs), each `count = 3`. No numeric-name terms exist there.
- FAQ post titles, `post_content`, `faq_answer`, and dates were not touched by this repair.
- No code changes were required — this was a pure content/taxonomy-relationship fix via standard WP-CLI taxonomy commands.

### Related

- `docs/decisions.md` ADR-004 — same investigation pattern (RCA against confirmed installed-core behavior before applying a fix).

---

## ADR-006 — Synthetic Catalog Removal from Staging + WBW Orphan Cleanup + Disproven `_price` Corruption Hypothesis

**Datum:** 2026-09-02
**Status:** Accepted — investigation complete, `NO REMEDIATION REQUIRED`
**Vlasnik:** Staging data integrity / Catalog (WBW Product Filter integration, Apros ERP import)

### Context

Staging (`dreampoint.b2b.uncledev.cloud`) carried two distinct product populations that must never be conflated:

- **Legacy synthetic/dummy catalog** generated by the theme's own `wp dp-b2b generate-catalog` tool (`inc/dev/class-dev-catalog-generator.php`) — batch `20260713_1138`: 210 parent products (`DEV-0001`..`DEV-0200` simple + `DEV-VAR-001`..`DEV-VAR-010` variable) + 183 variations, each marked `_dp_generated = 1` + `_dp_generation_batch = 20260713_1138`.
- **Legitimate ERP-imported products** from **Uncle Dev Importer (Apros)** (`wp-content/plugins/uncle-dev-importer`, active on staging, absent on localhost) — ~10,186 `product` posts marked `_erp_provider = AprosProvider` + `_erp_id`, imported 2026-08-19. Not reflected on localhost or in pre-2026-09 theme docs.

Two staging-only problems needed resolving:
1. Remove the now-obsolete synthetic products (real ERP data supersedes them as the QA dataset).
2. A prior session (2026-09-01 — `.claude/session.md`, and the server artifact `/home/dreampoint.b2b/wbw-3.4.0-deployment-20260901/index-rebuild-outcome.txt`) had flagged **16 products with more than one `_price` postmeta row** as corrupted metadata ("duplicate `_price` rows … `_regular_price`/`_sale_price` missing … bulk edit 2026-08-19 14:51"), pending authorized remediation.

### Actions taken (2026-09-02, staging only; read-only except the two authorized operations below)

**1. Synthetic catalog cleanup** — via the generator's own canonical batch-scoped path:
`wp dp-b2b reset-catalog --batch=20260713_1138` (run as site user `dream9399`). The generator's `guard_production()` aborts when `wp_get_environment_type() === 'production'`; on staging that function returns `production` only because the `WP_ENVIRONMENT_TYPE` constant/env var is unset (WordPress default), so the guard was satisfied for the single invocation with a transient `WP_ENVIRONMENT_TYPE=staging` env var — **no `wp-config.php` change, no source change**.
- Deleted: **210 parent products + 183 variations = 393 objects** (`wp_delete_post($id, true)`).
- **Preserved** (batch mode never deletes terms): 24 `_dp_generated` `[DEV]` product categories, 30 `_dp_generated` `[DEV]` brands, all 11 `_dp_brand_fixture` Brand Fixture terms, 3 `faq-category` terms + 9 `faq` posts, 16 pages, 6 nav menu items, 6061 attachments.
- **Zero Apros products affected.** `SYNTHETIC DELETE SET ∩ PROTECTED ERP SET = ∅` proven: no post carries both `_dp_generated` and `_erp_*`; the 16 flagged products all have `_dp_generated IS NULL` + `_erp_provider = AprosProvider`.
- `wp_wc_product_meta_lookup`: 11878 → 11485 rows, 0 orphans (WooCommerce auto-cleaned the 393 rows on delete).

**2. WBW index cleanup** — WBW 3.4.0 (Free + PRO) hooks no product-deletion event, so deleting the 393 synthetic posts left **1998 orphan rows in `wp_wpf_meta_data`** across 393 non-existent `product_id`s. Cleared with WBW's own official full rebuild:
`WooBeWoo_PF_Frame::_()->getModule('meta')->getModel()->recalcMetaValues()` (CLI bootstrap: WBW gates its entire framework on `$_SERVER['REQUEST_URI']` via `woobewoo_pf_request()` in `woo-product-filter.php`; the value was injected through a WP-CLI `--require` file loaded before plugins).
- `WooBeWoo_PF_Meta_Model::doRecalcMetaValues()` full-recalc branch = `dropIndexes()` → `delete('')`, and `WooBeWoo_PF_Table::delete('')` with an empty WHERE is **`TRUNCATE TABLE wp_wpf_meta_data`** → rebuild from a temp-table scan of `wp_posts` for currently-existing `publish`/`private` `product` / `product_variation` posts only. It replaces, never appends; orphans for deleted posts cannot survive a full recalc.
- Result: `recalcMetaValues()` returned `true` (0.47 s, no errors). `wp_wpf_meta_data` **13606 → 11608 rows**; **orphan rows 1998 → 0**; orphan `product_id`s 1998 → 0. `_price` coverage 1748 / 1748 (source `_price` postmeta vs WBW index, 1:1). All 15 `wp_wpf_meta_keys` `status = 1`; `start_indexing` idle, no lock. Synthetic-only WBW attribute keys `attribute_color` / `attribute_pack-size` / `attribute_size` now hold 0 data rows (key rows retained, harmless).
- **Only WBW-derived tables written** (`wp_wpf_meta_data`, `wp_wpf_meta_values`, `wp_wpf_meta_keys`, WBW `start_indexing` option). `wp_posts`, `wp_postmeta`, `wp_wc_product_meta_lookup`, WooCommerce and Apros data: read-only, unchanged.

**3. The 16-product `_price` investigation — corruption hypothesis DISPROVEN.**

All 16 flagged products are **Apros-owned `variable` parents** (`_erp_provider = AprosProvider`, `_erp_id` 55625–57090, SKU `P-<erp_id>`), not simple products. For each, the parent's set of `_price` postmeta rows is **exactly** the numerically-sorted set of distinct `_price` values across its published child variations:

| parent | ERP id | child variations (all publish) | distinct child `_price` | parent `_price` rows | WC lookup min/max | WBW `_price` | class |
|---|---|---|---|---|---|---|---|
| 12803 | 55625 | 7 | 12, 16, 20 | 12, 16, 20 | 12.00 / 20.00 | 12, 16, 20 | EXACT MATCH |
| 12821 | 55626 | 8 | 21.6, 28, 36 | 21.6, 28, 36 | 21.60 / 36.00 | 21.6, 28, 36 | EXACT MATCH |
| 12849 | 55627 | 5 | 23.2, 29.6 | 23.2, 29.6 | 23.20 / 29.60 | 23.2, 29.6 | EXACT MATCH |
| 12864 | 55628 | 16 | 16, 20 | 16, 20 | 16.00 / 20.00 | 16, 20 | EXACT MATCH |
| 12985 | 55631 | 7 | 21.6, 28, 36 | 21.6, 28, 36 | 21.60 / 36.00 | 21.6, 28, 36 | EXACT MATCH |
| 13031 | 55634 | 11 | 16, 20 | 16, 20 | 16.00 / 20.00 | 16, 20 | EXACT MATCH |
| 13068 | 55635 | 21 | 21.6, 28, 36 | 21.6, 28, 36 | 21.60 / 36.00 | 21.6, 28, 36 | EXACT MATCH |
| 13206 | 55639 | 24 | 13.6, 20 | 13.6, 20 | 13.60 / 20.00 | 13.6, 20 | EXACT MATCH |
| 13477 | 55651 | 6 | 87.2, 95.2 | 87.2, 95.2 | 87.20 / 95.20 | 87.2, 95.2 | EXACT MATCH |
| 13557 | 55653 | 9 | 55.2, 63.2 | 55.2, 63.2 | 55.20 / 63.20 | 55.2, 63.2 | EXACT MATCH |
| 13808 | 55673 | 5 | 15.96, 31.96 | 15.96, 31.96 | 15.96 / 31.96 | 15.96, 31.96 | EXACT MATCH |
| 13847 | 55675 | 25 | 22, 23.96 | 22, 23.96 | 22.00 / 23.96 | 22, 23.96 | EXACT MATCH |
| 14006 | 55676 | 17 | 24, 27.2 | 24, 27.2 | 24.00 / 27.20 | 24, 27.2 | EXACT MATCH |
| 14459 | 55703 | 2 | 120, 136 | 120, 136 | 120.00 / 136.00 | 120, 136 | EXACT MATCH |
| 15344 | 56386 | 6 | 13.56, 14.36 | 13.56, 14.36 | 13.56 / 14.36 | 13.56, 14.36 | EXACT MATCH |
| 16646 | 57090 | 3 | 23.2, 29.6 | 23.2, 29.6 | 23.20 / 29.60 | 23.2, 29.6 | EXACT MATCH |

**16 EXACT MATCH, 0 MISMATCH, 0 AMBIGUOUS.** All four layers (parent `_price` postmeta / distinct child prices / `wc_product_meta_lookup` min-max / WBW `_price` index) internally consistent for all 16. No variation carries a `_sale_price`; every variation has `_price == _regular_price`. Catalog-wide: exactly these 16 `product` posts have `COUNT(_price) > 1` (all `variable`); 0 `product_variation` posts and 0 simple products do.

### The WooCommerce semantic that explains the finding

Deployed **WooCommerce 11.0.1**, `wp-content/plugins/woocommerce/includes/data-stores/class-wc-product-variable-data-store-cpt.php` → `WC_Product_Variable_Data_Store_CPT::sync_price()` (≈ lines 865–901), invoked from `WC_Product_Variable::sync()` on every variable-product / variation save:

```php
$prices = array_unique( /* SELECT meta_value FROM postmeta WHERE meta_key='_price' AND post_id IN (visible children) */ );
delete_post_meta( $parent, '_price' );
delete_post_meta( $parent, '_sale_price' );
delete_post_meta( $parent, '_regular_price' );
sort( $prices, SORT_NUMERIC );
foreach ( $prices as $price ) {
    add_post_meta( $parent, '_price', $price, false );   // $unique = false → one row per distinct child price
}
```

WooCommerce's own inline comment: *"To allow sorting and filtering by multiple values, we have no choice but to store child prices in this manner."*

Therefore, for a variable parent:
- `_price` postmeta is a **price index** — one row per distinct visible-variation price, numerically sorted. `get_post_meta($parent,'_price',true)` returns the lowest ("from") price.
- There is **no** parent `_regular_price` / `_sale_price` — `sync_price()` deletes them and never restores them for variable products.
- `wc_product_meta_lookup.min_price` / `max_price` = min / max of that same set.
- WBW's `_price` index mirrors every one of those rows — which is why the multiple values reappear after any WBW rebuild.

### Why the previous investigation produced a false positive

The 2026-09-01 session:
1. Queried for products with `COUNT(_price) > 1` → found 16, but **did not check `product_type`**, and treated them as simple products (for which multiple `_price` rows *would* be anomalous).
2. Read the shared `post_modified` timestamp (all 16 within 2026-08-19 14:51:46–47) as a targeted corrupting bulk operation. In fact **all 10,186 Apros products** have `post_modified` on 2026-08-19, with 66 / 542 / 534 products modified in the minutes 14:49 / 14:50 / 14:51 — the 16 are the tail of the ERP import run, not a targeted edit.
3. Read the absent parent `_regular_price` / `_sale_price` as data loss — it is intentionally deleted by `sync_price()`.

Its one correct conclusion — that WBW was not the cause and a WBW rebuild would not change the values — stands; it simply assumed there was something to fix.

### Apros (Uncle Dev Importer) — not proven faulty

No independent evidence of any Apros defect exists. The 16 products are fully explained by native WooCommerce variable-product behavior plus a normal import run. Per scope, Apros was **not** inspected further, **not** modified, executed, reconfigured, or remediated; no Apros-owned product or metadata was changed.

### Diagnostic invariant (for future audits)

**`COUNT(_price) > 1` on a product post is NOT corruption by itself — check `product_type` first.**
- **Variable parent:** multiple `_price` rows are expected whenever visible variations have multiple distinct effective prices; an absent parent `_regular_price` / `_sale_price` is also expected.
- **Simple product:** multiple `_price` rows may be anomalous and warrant investigation.

### Consequences

- Staging synthetic products/variations removed; `[DEV]` taxonomy terms and Brand Fixtures retained. `.claude/session.md`, `docs/active/status.md`, and `docs/historical/synthetic-b2b-catalog.md` updated to match.
- **Final decision: `NO REMEDIATION REQUIRED`** — for the 16 products and the wider catalog.
- The server artifact `/home/dreampoint.b2b/wbw-3.4.0-deployment-20260901/index-rebuild-outcome.txt` still records the superseded hypothesis; it is a deployment-provenance file and was intentionally left untouched. This ADR supersedes its "ROOT CAUSE" and "RECOMMENDED NEXT STEP" sections.
- Three product populations remain strictly distinct and must not be conflated: (a) DreamPoint synthetic generator — `_dp_generated` / `_dp_generation_batch`, cleaned by `reset-catalog`; (b) Brand Fixtures — `_dp_brand_fixture`, entirely outside `reset-catalog`; (c) Uncle Dev Importer / Apros — `_erp_provider` / `_erp_id`, legitimate ERP data, never touched by catalog tooling.

### Related

- `docs/decisions.md` ADR-004 — WBW `_price` index coverage + `recalcMetaValues()` full-rebuild semantics (same subsystem)
- `docs/historical/synthetic-b2b-catalog.md` — generator ownership markers, `reset-catalog` batch mode, Brand Fixtures exclusion
- `wp-content/plugins/woocommerce/includes/data-stores/class-wc-product-variable-data-store-cpt.php` — `sync_price()`
- `wp-content/plugins/woo-product-filter/modules/meta/models/class-woobewoo-pf-meta-model.php` — `doRecalcMetaValues()`
- `.claude/session.md` — 2026-09-01 session that raised the (now disproven) hypothesis

---

## Review Note — 2026-07-03 (Documentation Reconciliation)

**Pregledano:** ADR-001 (Pricing Architecture) i ADR-002 (Partner Approval Architecture) pregledani nakon internog workshopa i dokumentacijske rekonsolidacije.

**Zaključak: Nema izmjena ni na jednom ADR-u.** Nove stavke iz workshopa (invoice splitting eksplicitno označen kao PARTIALLY RESOLVED s dokumentiranom pretpostavkom da Apros vrši interno segmentiranje faktura; ponovno otvaranje stock reservation pitanja kao DP-B06) ne mijenjaju pricing ni partner approval arhitekturu — riječ je o zasebnim, nepovezanim stavkama:

- Invoice splitting je aspekt AP-06 (order export), ne pricing (ADR-001) ni partner approval (ADR-002). Nije formalizirano kao ADR jer nije donesena arhitekturalna odluka — samo dokumentirana radna pretpostavka koja čeka Apros potvrdu. Vidi `docs/project-status-matrix.md` Sekciju 1.8.
- Stock reservation (DP-B06) je WooCommerce cart-level UX odluka koja ne zahtijeva ERP arhitekturalnu promjenu niti Apros input. Nije formalizirano kao ADR jer odluka nije donesena — status je OTVORENO. Vidi `docs/project-status-matrix.md` Sekciju 3.B.

Oba će biti formalizirana kao novi ADR-ovi tek kad budu stvarno odlučeni (invoice splitting nakon Apros potvrde; stock reservation nakon Dream Point odluke).

---

## ADR-007 — Stock Reservation (DP-B06): Poslovna odluka zatvorena, tehnički scope otvoren

**Datum:** 2026-09-21
**Status:** Accepted (poslovna odluka) — tehnička implementacija NIJE dizajnirana ni implementirana
**Vlasnik:** Cart/Checkout UX

### Context

DP-B06 je ranije (workshop Lipanj 2026) tretiran kao zatvoren s native WC defaultom, zatim ponovo otvoren 2026-07-03 kao poslovna odluka koja čeka eksplicitnu Dream Point potvrdu (`docs/project-status-matrix.md` §3.B). Timski prijedlog u tom trenutku je bio **zadržati native WooCommerce ponašanje** i eksplicitno izbjeći cart-level rezervaciju zbog dodane kompleksnosti (expiry/lock mehanizam, race conditions, cron cleanup, UI countdown).

Klijent je putem `B2B odgovori na pitanja.docx` sada eksplicitno potvrdio: 1-satna cart-level rezervacija je **mandatory poslovni zahtjev**, klijent prihvaća trošak eventualnog plaćenog plugina, i korisnik mora vidjeti koliko dugo je artikal rezerviran specifično za njega. Očekivani konceptualni model (preferenca, ne dokazana tehnička činjenica): JEDNA rezervacija po cart/session-u = jedan 60-minutni prozor, ne nezavisni tajmeri po stavci.

### Investigation — plugin research

Pretraga cjelokupne kanonske projektne dokumentacije (`docs/*.md`, uključujući `project-status-matrix.md`, `b2b-erp-migration-plan.md`, `decisions.md`) **ne sadrži nijedan konkretan naziv stock-reservation plugina** (npr. "Reserve Stock for WooCommerce") ni bilo kakvu prethodnu evaluaciju takvog plugina. Jedini prethodno dokumentirani stav tima bio je suprotan — eksplicitna preporuka da se cart-level rezervacija IZBJEGNE. Prethodna pretpostavka da je specifičan plugin već razmatran nije potvrđena postojećom dokumentacijom — ako takva evaluacija postoji, nije zapisana u ovom projektu.

### Plugin odabir — Reserved Stock Pro (Puri.io), potvrđeno 2026-09-21

Klijent je finalizirao odabir konkretnog mehanizma: **Reserved Stock Pro for WooCommerce** (Puri.io, `puri.io/plugin/reserved-stock-pro-for-woocommerce/`). Klijent će kupiti plugin — cijena nije bloker.

**Arhitekturalni princip (obavezujući za implementaciju):**

- **Reserved Stock Pro = reservation engine i source of truth.** Sva stvarna rezervacija, expiry i release logika živi unutar plugina.
- **DreamPoint B2B tema = presentation/UI slot oko tog stanja**, samo gdje native plugin prikaz ne odgovara traženom dizajnu.
- **Zabranjeno:** paralelni reservation engine, nezavisan autoritativan JS tajmer, drugi source of truth za expiry, frontend logika koja produžava/restartuje rezervacije nezavisno od plugina. Custom UI je dozvoljen, ali mora PRIKAZATI stvarno stanje plugina, ne izmišljati vlastito.

**Verified plugin capability (WebSearch/WebFetch, `puri.io/docs/reserved-stock-pro/`, 2026-09-21):**

- **Jedan unificiran cart-level tajmer je NATIVE ponašanje**, ne custom rad: *"All stock-managed products are synced and will expire at the same time from the cart."* — direktno odgovara klijentskom zahtjevu za jednu koherentnu rezervaciju po cart-u umjesto per-item tajmera. Native shortcode `[rsp_countdown]` + filteri `rsp_countdown_options` / `rsp_default_countdown_css` / `rsp_default_countdown_location` postoje za customizaciju/repozicioniranje prikaza.
- **Integracija:** paralelna custom DB tabela `rsp_reserved_stock` — NE modifikuje product meta direktno; stvarno smanjenje WC zaliha se dešava tek na promjenu WC order statusa (on-hold/processing/completed). Ovo je usklađeno s "ne dirati native WC stock logiku bez razloga".
- **Release/cleanup — tri putanje:** (1) validacija na sledećem page load-u nakon isteka, (2) early cleanup na cart akcijama (removal, qty change, purchase), (3) WP-Cron `rsp_reserved_stock_twice_daily` (svakih 12h).
- **Nuansa relevantna za Quick Order:** plugin **PRODUŽAVA (resetuje na puni interval) cijeli cart-level tajmer kad se dodaje NOVI proizvod** u već-rezervisanu košaricu; ne produžava se na promjenu količine ili uklanjanje. Pošto Quick Order chunk-uje submit u sekvencijalne `/cart/sync` pozive (`quick-order-local-state-architecture.md` §4), višestruki chunk-ovi u jednom logičkom submit-u mogu okidati ovo "extend on add" ponašanje više puta zaredom — vjerovatno bezopasno (korisnik doživljava jedan submit), ali **REQUIRES PLUGIN VERIFICATION** nakon instalacije da potvrdi da rezultat ostaje jedan koherentan tajmer, ne artefakt od resetovanja.
- **Cache interakcija:** plugin eksplicitno izlaže `rsp_enable_page_cache_plugin_integrations` i `reserved_stock_pro_disable_object_cache` filtere — **relevantno za ovaj projekat** (LiteSpeed Cache + Redis Object Cache su u stack-u, `CLAUDE.md`). Cache konfiguracija za ove filtere nije verifikovana i mora biti dio implementacionog plana, ne pretpostavljena.

### UX specifikacija — 4 stanja (designer reference, 2026-09-21)

Klijent/designer je isporučio namjeravani UX kao tekstualni opis stanja (screenshots nisu fizički priloženi ovoj sesiji — ako budu dostavljeni kao fajlovi, treba ih dodati kao design evidence uz ovaj odlomak). Klasifikacija po zahtjevu:

| Stanje | Opis | Klasifikacija |
|---|---|---|
| 1 — Aktivna rezervacija | "Proizvodi u vašoj košarici su rezervisani." + prominentni countdown (npr. `59:42`) + apsolutno vrijeme isteka (npr. `Vrijedi do 13:07`) + instrukcija | FINAL BUSINESS/UX REQUIREMENT (jedan cart-level tajmer) — **VERIFIED PLUGIN CAPABILITY** (native, vidi gore) |
| 2 — Rezervacija uskoro ističe | Warning state, countdown (npr. `09:58`), apsolutno vrijeme, jača instrukcija, CTA "Idi na naplatu" | DESIGN REFERENCE — prag (≈10 min u primjeru) **NIJE finalno poslovno pravilo**, ne hard-kodirati bez potvrde; da li plugin native izlaže konfigurabilan warning-threshold nije potvrđeno iz dokumentacije — **REQUIRES PLUGIN VERIFICATION** |
| 3 — Rezervacija istekla / provjera dostupnosti | "Rezervacija je istekla." + "Provjeravamo dostupnost..." — privremeni processing state | DESIGN REFERENCE. Plugin **VERIFIED** da radi validaciju na page-load nakon isteka; native postojanje ODVOJENOG "processing/checking" UI koraka (a ne trenutne tihe validacije) **REQUIRES PLUGIN VERIFICATION** |
| 4 — Košarica ažurirana nakon provjere, novi `60:00` | "Korpa je ažurirana... Neke količine su prilagođene..." + svježa puna rezervacija | DESIGN REFERENCE. **REQUIRES PLUGIN VERIFICATION** — eksplicitno NE pretpostavljati. Dostupna dokumentacija kaže samo da plugin *"compares the stock quantity and the reserved quantity to check what's available"* nakon isteka — ovo je audit/comparison korak, NE potvrđena garancija da automatski re-rezerviše preostale dostupne količine i pokreće nov pun 60-minutni period. Ako plugin to native ne radi, potrebna je custom orkestracija — **mora biti eksplicitno prijavljeno prije implementacije**, ne tiho pretpostavljeno. |

**Cart-level prezentacija:** jedan koherentan countdown za cijelu rezervaciju/cart sesiju — ne nezavisni tajmeri po proizvodu (osim ako budući eksplicitno potvrđen zahtjev to zatraži). Ovo NE zahtijeva izmjenu kako plugin internally čuva rezervacije po proizvodu/varijaciji/kupcu — samo user-facing prezentacija mora biti jedinstvena, izvedena iz plugin-ovog autoritativnog stanja (verifikovati siguran način izvođenja pre implementacije — direktna funkcija za cart-level expiry timestamp nije pronađena u javnoj developer dokumentaciji; `[rsp_countdown]`/`rsp_countdown_options` je najbliži native put).

### Quick Order compatibility — CONFIRMED

`docs/frozen/quick-order-local-state-architecture.md` §4–§5: Quick Order stavke ulaze u pravu WC košaricu isključivo na eksplicitni "Dodaj u košaricu" submit (`/cart/sync` REST ruta). Prije submit-a, lokalno stanje se nigdje ne perzistira (§5 — refresh/navigacija briše sve nesubmitovano). Posljedica: bilo koji mehanizam rezervacije vezan za "artikal je u WC košarici" će se prirodno pokrenuti tek na Quick Order submit trenutku — **nema potrebe mijenjati frozen local-state arhitekturu** niti umjetno pokretati rezervaciju ranije, osim ako se naknadno eksplicitno zatraži drugačije poslovno ponašanje specifično za Quick Order.

### Napomena o razlici od AP-10

AP-10 (kada Apros interno rezervira stanje na svojoj strani — checkout vs. ERP potvrda) ostaje **zasebno, nepromijenjeno otvoreno** Apros pitanje. Ovaj ADR zatvara isključivo WooCommerce-stranu poslovnu odluku (DP-B06).

### Decision

1-satna cart-level rezervacija zaliha je **CONFIRMED — BUSINESS DECISION**, mandatory. **Plugin je odabran: Reserved Stock Pro (Puri.io)** — CONFIRMED — ARCHITECTURE, klijent kupuje. Reserved Stock Pro je engine/source-of-truth; tema je presentation-layer, uz obavezujući princip "nema paralelnog engine-a" (vidi gore). Jedan koherentan cart-level countdown je FINAL zahtjev i potvrđen kao native plugin ponašanje. UX 4-stanja specifikacija je zabilježena (vidi tabelu gore) sa eksplicitnom distinkcijom šta je verifikovano naspram šta zahtijeva provjeru nakon instalacije. **Nijedna implementacija nije izvršena** — plugin nije instaliran ni na jednom environmentu u ovoj sesiji.

### Consequences

- DP-B06 se briše sa liste otvorenih poslovnih odluka; ostaje kao otvorena TEHNIČKA implementacija (instalacija, konfiguracija threshold-a za State 2, verifikacija State 3/4 ponašanja, cache integracija).
- Timski prijedlog "zadrži native WC default" iz `project-status-matrix.md` §3.B je **superseded** ovim ADR-om — treba ažurirati taj odlomak da ne prikazuje stariju preporuku kao trenutno važeću.
- **Pre implementacije, obavezna verifikacija na stvarno instaliranom pluginu** (ne samo javna dokumentacija): (a) State 4 post-expiry re-reservation ponašanje, (b) da li postoji native konfigurabilan "expiring soon" threshold, (c) ponašanje "extend on add" tajmera tokom Quick Order chunk-ovanog submit-a, (d) LiteSpeed/Redis cache integracija filteri.
- Ako verifikacija pokaže da plugin nativno NE radi State 4 re-rezervaciju kako je dizajnirano, potrebna je custom orkestracija — mora biti prijavljena i odobrena kao zaseban plan prije koda, ne tiho implementirana.

### Related

- `docs/project-status-matrix.md` §3.B (DP-B06, zahtijeva ažuriranje statusa)
- `docs/frozen/quick-order-local-state-architecture.md` §4–§5
- AP-10 (Apros-side rezervacija, zasebno otvoreno)

---

## ADR-008 — TEST Apros ERP pristup uspostavljen; Read-Only nalazi implementacije

**Datum:** 2026-09-21
**Status:** Accepted — investigacija kompletna, BEZ izmjena plugin/DB/ERP stanja
**Vlasnik:** ERP integracija (Apros) — Discovery

### Context

BL-01 (Apros API/sandbox pristup ne postoji) je bio rangiran kao #1 kritični bloker (`project-status-matrix.md` §0.3) koji blokira svaku payload validaciju. Drugi developer je uspostavio TEST Apros ERP pristup i instalirao/konfigurisao tri plugina na stagingu (`dreampoint.b2b.uncledev.cloud`):

- `apros-pricing` — B2B pricing (fiksne country cijene, brend rabati, dostavne lokacije)
- `uncle-dev-importer` (već referenciran u ADR-006) — katalog + order export prema Apros-u
- `b2b-partner-importer` — **prethodno nedokumentiran u ovom projektu**, otkriven ovom investigacijom

Sva tri su tretirana kao STRICT PROTECTED BOUNDARY — isključivo read-only inspekcija koda (bez SQL upita nad bazom, bez izvršavanja importa/sync-a, bez izmjena konfiguracije), izvršena preko SSH read-only pristupa (`ssh hetzner`, `find`/`grep`/`sed -n` nad plugin PHP fajlovima).

### BL-01 status

**Efektivno RESOLVED za TEST/sandbox svrhe** — konekcija i kredencijali postoje, plugin kod je funkcionalan na stagingu. Produkcijski/finalni Apros pristup (izvan TEST okruženja) nije potvrđen ovim nalazom.

### Nalazi — CONFIRMED — CURRENT TEST IMPLEMENTATION (za razliku od CONFIRMED — APROS SPECIFICATION)

**AP-01 (pricing):** `apros-pricing.php` implementira TAČNO ADR-001 prioritet: (1) fiksna country cijena — konačna, rabat se ne primjenjuje; (2) brend rabat na wholesale cijenu; (3) wholesale cijena. Brend rabati se čuvaju po partneru u custom tabeli `{prefix}apros_brand_discounts` (`partner_code`, `brand_id`, `discount_percent`), **ručno konfigurisani kroz wp-admin UI** (`apros-pricing-admin.php`, sekcija "Brend rabati" / "Rabat (%)") — nije uočen live sync iz Apros `partnerBrandDiscountList`-a. Sam kod eksplicitno komentariše: *"Prioritet (potvrđeno sa korisnikom, čeka i pismenu potvrdu klijenta/Apros)"* — i implementacija sama priznaje da AP-01 nije formalno zatvoren. `countryPriceListCode` (ne sirovi ISO kod) je stvarno polje korišteno za country pricing lookup.

**AP-07 (delivery locations):** CONFIRMED šema — custom tabela `{prefix}apros_delivery_locations` (`recipient_code`, `name`, `address`, `city`, `postal_code`, `email`), po `partner_code`, podržava više redova po partneru (`apros_get_partner_delivery_locations()`). Order payload šalje `partnerDeliveryLocationId` (= `recipient_code`), sa fallback-om na prvu lokaciju ako order nema eksplicitno postavljenu (`order.php`) — ovaj fallback je safety-net na nivou slanja narudžbe ERP-u, **nije potvrđeno** da odražava checkout UI pre-fill ponašanje; "nema pamćenja zadnje lokacije" zahtjev nije verifikovan naspram stvarnog checkout template koda (izvan scope-a protected plugina).

**DP-01 (sif_kup kardinalitet):** CONFIRMED na nivou šeme — trenutna arhitektura već podržava OBA klijentska scenarija bez daljnjeg redizajna: jedan WP korisnik ima tačno jedan `apros_partner_code` (singularni user meta), a taj partner_code može imati N redova u `apros_delivery_locations` (Scenarij A — jedan nalog, više lokacija). Zasebni WP korisnici sa zasebnim partner_code vrijednostima prirodno pokrivaju Scenarij B. Status podignut sa PARTIALLY RESOLVED na suštinski riješeno na nivou šeme — preostaje potvrditi da Apros uvijek izdaje zaseban sif_kup po branch-u/nalogu u Scenariju B.

**AP-06 (order export), pronađeno opportunistički u `uncle-dev-importer/order.php`:** Pun payload oblik: `number`, `date`, `partnerCode`, `partnerDeliveryLocationId`, `paymentTypeId`, `shippingMethodId`, billing/shipping polja, `items`. **Idempotency CONFIRMED** — endpoint je idempotentan po `number`; odgovor "already been imported" se tretira kao uspjeh, ne kao duplikat. Response format: niz dokumenata `{numberErp, warehouseId}` — Apros može vratiti više ERP brojeva narudžbe, po jedan per skladište, spremljeno u order meta `_erp_documents` i kao order note. Ovo zatvara na nivou TEST implementacije historijski najveći finansijski rizik (duplirane narudžbe) — pismena Apros potvrda tog ponašanja i dalje nije dokumentovana.

**Warehouse splitting — nijansa naspram klijentskog odgovora:** `{numberErp, warehouseId}` niz je direktan dokaz da Apros VRAĆA webshopu itemizirane per-warehouse dokumente — ovo je nijansa naspram klijentske izjave "webshop ne prima split-rezultat notifikaciju": importer kod DE FACTO prima i bilježi per-warehouse split rezultat (za order-note/referencu), iako to možda nije izloženo krajnjem kupcu u UI-ju. Zabilježeno kao otvorena nijansa, ne tiho razriješeno.

**PL-01 (partner list format): NIJE razriješeno ovom inspekcijom.** `b2b-partner-importer` je ručni Excel/CSV upload alat s admin-triggered importom i automatskim matchanjem komercijalista — ne poziva live Apros partner-list API endpoint. ADR-002-ova cron-polling arhitektura trenutno NIJE ono što je u pogonu; partneri se danas ručno seed-uju. PL-01 (stvarni Apros partner-list endpoint format) ostaje otvoreno.

**PL-01 — ažurirano 2026-09-22 (zvanična ZGData API dokumentacija primljena):** API capability sada **RESOLVED/zvanično potvrđeno** — `partnerList/get` postoji, puna šema dokumentovana (`partnerCode`, `name`, `address`, `city`, `postalCode`, `taxId`, `email`, `partnerLegalFormCode`). **Konzumacija od strane trenutne integracije ostaje NEPROMIJENJENA** — `b2b-partner-importer` je i dalje isključivo ručni Excel/CSV alat; nema dokaza da automatski poziva ovaj endpoint. Ne miješati "endpoint postoji" sa "naš kod ga koristi".

**WH-01 (warehouse stock payload): NIJE razriješeno.** `AprosProvider.php` mapira jedan flattened `stock` integer (`$raw['stock']`) — nije uočena per-warehouse struktura u ovoj kodnoj putanji. Nije potvrđeno da li Apros-ov sirovi payload ima per-warehouse detalj koji se agregira uzvodno, ili TEST integracija to jednostavno još ne izlaže.

**WH-01 — ažurirano 2026-09-22 (zvanična ZGData API dokumentacija primljena):** Zvanična dokumentacija potvrđuje da `articleList/get` izlaže jedno flattened `stock` decimal polje ("Raspoloživa količina na zalihi") — konzistentno sa postojećim kodom. **Ovo NE dokazuje da per-warehouse endpoint/capability ne postoji negdje drugdje** — dokument jednostavno ne dokumentuje takav endpoint u ovoj verziji. Per-warehouse dostupnost ostaje not established by this document, ne opovrgnuta.

**DP-02/BL-06 (sales location routing) — provenance provjerena:** Potvrđeno kao stvaran, ne zastario bloker — konzistentno referenciran kroz 6+ nezavisnih dokumenata (`b2b-erp-adaptation-blueprint.md`, `apros-session-final-pack.md`, `b2b-architecture-validation-audit.md` [EG-07, HIGH severity], `apros-question-resolution-matrix.md`, `erp-discovery-findings.md`, `project-status-matrix.md`) kao zavisan o "Josip / stari B2B sustav (ZGData)" — stvaran, imenovan izvor institucionalnog znanja iz legacy sistema, ne dokumentaciona greška. Uočeno numeričko poklapanje: salesLocationId kodovi (3=Igračke/Toys, 5=Lifestyle) tačno odgovaraju ranije potvrđenim ID-jevima skladišta za iste kategorije (memory: 4 skladišta — 1 Glavno, 3 Igračke, 4 Naočale, 5 Lifestyle). Ovo je cirkumstancijalni dokaz (INFERRED, ne potvrđeno) da "sales location routing" i "warehouse splitting" mogu biti isti Apros mehanizam — što bi, u kombinaciji sa novim klijentskim odgovorom da Apros automatski dijeli po skladištu, značajno smanjilo ovaj bloker. Nije pronađeno u pregledanom kodu (nijedno `salesLocationId` polje nije uočeno ni u jednom od tri plugina). Preporuka: eksplicitno potvrditi prije nego se Josip-zavisnost povuče sa liste blokera.

### Zvanična ZGData API dokumentacija primljena (2026-09-22)

Klijent/integrator je dostavio zvaničnu tehničku dokumentaciju: **"API DOKUMENTACIJA — Dreampoint - B2B integracija", verzija 1.0, Zagreb Data d.o.o., Zagreb 2026.** Dokument je pisana Apros/ZGData referenca za `https://tockasna-b2b-api.zgdata.hr/api3/{API-KEY}/` (API ključ redigovan — nikad se ne upisuje u dokumentaciju).

**Ovo je viša evidencijska razina od prethodnih izvora** (usmeni odgovori, email korespondencija, kod-inspekcija) jer je formalna, pisana, endpoint-po-endpoint API referenca — ali pokriva ISKLJUČIVO katalog/partner/pricing GET endpointe: `classificationList`, `brandList`, `articleList`, `articleImageList`, `articleVariationList`, `articleVariationImageList`, `attributeList`, `attributeItemList`, `articleAttributeList`, `articleVariationAttributeList`, `partnerBrandDiscountList`, `countryPriceList`, `partnerList`, `partnerDeliveryLocationList`, `partnerLegalFormCodes`.

**Eksplicitno NE dokumentuje** (odsustvo ≠ dokaz nepostojanja, samo "not established by this document"): `order/create`, outbound order payload, `partnerDeliveryLocationId` unutar tog payload-a, response format, idempotency, shipping-address precedence, null delivery-location semantika, per-warehouse stock breakdown, niti bilo kakvu **environment designaciju** (TEST/sandbox/staging/production) za sam endpoint.

Detaljna rekonsilijacija po AP-ID stavci: `docs/project-status-matrix.md` (AP-01, AP-06, AP-07) i PL-01/WH-01 bullet-i iznad u ovom ADR-u.

**Apros TEST/sandbox safety pitanje ostaje POTPUNO NEPROMIJENJENO** — dokument ne sadrži nijednu izjavu koja bi identifikovala konfigurisan endpoint kao izolovano test okruženje odvojeno od produkcijskih posljedica. Vidi zaseban E2E safety gate nalaz (2026-09-22): `APROS TEST ORDER E2E BLOCKED — TEST ENDPOINT NOT CONCLUSIVELY VERIFIED`.

### Consequences

- Nijedna plugin/config/data izmjena nije napravljena. Nijedan SQL upit nije izvršen direktno nad bazom — nazivi tabela/kolona su pročitani iz PHP source koda, ne upitani uživo.
- `docs/project-status-matrix.md` §0/§5 zahtijeva ažuriranje statusa BL-01, AP-01, AP-06, AP-07, DP-01 (vidi taj dokument).
- Novootkriveni `b2b-partner-importer` plugin treba biti dodan u sve buduće reference protected boundary liste uz `apros-pricing` i `uncle-dev-importer`.

### Related

- ADR-001 (Pricing Architecture), ADR-002 (Partner Approval Architecture), ADR-006 (uncle-dev-importer prvi put dokumentovan)
- `docs/project-status-matrix.md` §0, §5
- `docs/erp-discovery-findings.md`

---

## ADR-009 — Homepage vs. Segment Landing vidljivost: identifikovan arhitekturalni gap (NIJE implementirano)

**Datum:** 2026-09-21
**Status:** Accepted (dokumentovan gap i preporučen pravac) — **implementacija NIJE odobrena niti izvršena**
**Vlasnik:** Vidljivost engine (frozen) / Homepage-Segment Landing arhitektura

### Context

Klijent je finalizirao (`B2B odgovori na pitanja.docx`, §5.1 EDIT superseduje raniji prijedlog personalizovanog homepage-a): Homepage i tri Segment Landing stranice (Lifestyle/Toys/Outdoor) moraju prikazivati kompletan sadržaj, neograničen customer-bucket pravilima. Segment Landing MORA biti filtriran PO SEGMENTU (ne po customer bucket-u) — segment filtering ≠ customer/bucket filtering. Customer-specifična vidljivost počinje tek dublje u katalogu.

### Investigation — CONFIRMED empirijski (lokalno, Playwright, `vis_none` test korisnik, `TestVis2025!`)

Prijava kao `vis_none` (nulta catalog vidljivost) na trenutnu homepage stranicu, upoređeno sa admin sesijom:

- `blocks/templates/latest-products.php`, `blocks/templates/bestseller.php`, `blocks/templates/discounted-products.php` — svaki pokreće `new WP_Query(['post_type' => 'product', ...])` direktno. `inc/visibility/class-query-filter.php` → `should_filter()` presreće SVAKI WP_Query s eksplicitnim `post_type => product`, bezuslovno — nema page-context izuzetka. **CONFIRMED**: sekcije "Novo u ponudi" i "Akcija" su prikazale prazne poruke ("Nisu pronađeni...") za `vis_none`, dok su za admin bile pune.
- `blocks/featured-products.php` (ACF `selected_products` relationship polje) — **CONFIRMED**: cijeli blok "Istaknuti proizvodi" je nestao za `vis_none` (ACF-ovo razrešavanje relationship polja prolazi kroz isti filtrirani query put).
- `blocks/templates/brands.php` — **CONFIRMED**: koristi `get_terms(['taxonomy' => 'product_brand', ...])`, presretnuto od `filter_brand_terms()` (registrovan na `get_terms` hook); cijeli "Naša zastupništva" brand carousel je nestao za `vis_none`.
- `blocks/company-features.php`, `blocks/templates/featured-brand.php`, `blocks/templates/featured-categories.php` — **CONFIRMED neosjetljivi**: render isključivo ACF tekst/slika/link polja, bez product ili `product_brand` upita — vidljivost engine ih ne može dotaći bez obzira na kontekst stranice. `featured-categories.php` linkuje na `product_cat` termine ali nikad ne poziva `get_terms()` sam, a enginov `get_terms` filter je scoped isključivo na `product_brand` — `product_cat` nije presretnut.
- Sales Representative sekcija: **već implementirana** (`inc/myaccount-komercijalist.php` → `display_commercialist_contact_info()`, pozvana iz `functions.php`), nezavisna od vidljivost engine-a — čita per-user `assigned_komercijalist` meta koji pokazuje na "Komercijalist" CPT, s gracioznim fallback-om ("Partner nema dodeljenog komercijalistu"). Već prisutna u trenutnom homepage "Tu smo za vas" bloku i na My Account "Komercijalist" tabu; **nije potvrđeno** da je prisutna na Contact/Kontakt stranici (novi klijentski zahtjev).

### Decision — finalizovano nakon tri kruga refinement-a (2026-09-21)

**Nijedna izmjena koda nije napravljena — ADR ostaje na nivou odobrenog dizajna.** ADR bilježi potvrđen arhitekturalni gap (isto kao gore) i propisuje **isključivo generičku primitivu**, ne konkretnu Homepage/Segment-Landing aktivaciju:

**Naziv:** `dp_visibility_context` = string vrijednost `'shared_surface'` (NE `'homepage_shared'` — preimenovano jer isti mehanizam koristi i Homepage i Segment Landing; `shared_surface` opisuje SEMANTIKU upita, ne konkretnu stranicu).

**Puna odgovornost ADR-009 (i ništa više od ovoga):**
> Eksplicitno postavljen `dp_visibility_context = 'shared_surface'` na jednom `WP_Query`/`get_terms()` pozivu znači da customer-specifična bucket/custom-offer vidljivost ne smije ograničiti rezultat TOG upita. Odsustvo tog eksplicitnog konteksta znači da se postojeće ponašanje ne mijenja.

**Eksplicitno ODBAČENO (namjerno, ne previđeno):**
- **Page-ID registar/allowlist** (npr. `dreampoint_b2b_shared_visibility_page_ids`) — vidljivost primitiva ne smije biti vezana za identitet WP stranice; Homepage/Segment Landing još nisu u finalnom obliku i page ID-jevi se razlikuju po environment-u. Semantika pripada UPITU, ne stranici koja ga sadrži.
- `is_front_page()`/slug/title detekcija — implicitna, širokopojasna, odbačena iz istog razloga kao i prije.
- Bilo kakva segment-filtering logika (brand_segment reuse, Lifestyle/Toys/Outdoor query grane, nova šema) — eksplicitno VAN SCOPE-a ovog ADR-a. `brand_segment` (`acf-json/group_675053191eac4.json`) je potvrđeno vlasništvo Brands-page navigacije, NIJE dokazano dovoljan kao autoritativni product-segment model — ta odluka čeka zasebnu buduću fazu.
- Masovna izmjena postojećih blokova "za svaki slučaj" — reusable blok mora ostati neutralan po defaultu; kontekst mu mora eksplicitno proslijediti njegov POZIVALAC (buduća Homepage/Segment-Landing render logika), ne obrnuto.

**Fazna podjela (namjerna, potvrđena kao tehnički zvučna):**

- **Faza A (ovaj ADR, odobreno za implementaciju):** SAMO `inc/visibility/class-query-filter.php` — `should_filter()` prepoznaje `dp_visibility_context = 'shared_surface'` na `WP_Query`-ju (rani `return false`, odmah posle postojeće `dp_skip_visibility` provjere); `filter_brand_terms()` prepoznaje ekvivalentan ključ u `$args` trećem parametru koji `get_terms()` već prosljeđuje (bez izmjene poziva). Nijedan blok se ne mijenja. Pošto nijedan trenutni pozivalac ne postavlja ovaj flag, Faza A je u produkciji potpuno dormant/no-op — nulti vidljiv efekat, testabilna izolovano.
- **Faza B (buduća, van scope-a ovog ADR-a):** kad Homepage/Segment-Landing rendering arhitektura stvarno postoji, njen pozivalac eksplicitno prosljeđuje `shared_surface` semantiku relevantnim blokovima/upitima. Za `featured-products.php` konkretno: konverzija sa `get_field('selected_products')` (formatted, prolazi kroz ACF-ov `acf_get_posts()` → filtrirani `WP_Query`, potvrđeno u `class-acf-field-relationship.php:764-769` + `api-helpers.php:1165-1205`) na `get_field('selected_products', false, false)` (sirovi ID niz, potvrđeno u `api-template.php:26-75` da preskače cijeli `acf_format_value()` lanac) + sopstveni `WP_Query` — **potrebna je TEK kad blok stvarno treba konzumirati `shared_surface`**, ne prije. Do tada `featured-products.php` ostaje nepromijenjen. Ovo je namjerno stateless rešenje (nema global/static markera, nema ACF vendor hook-a) — razmotren i odbačen `acf/acf_get_posts/args` hook jer prima samo `$args` bez field-identity konteksta.

**Napomena o testiranju:** Projekat trenutno **nema PHPUnit/WP_UnitTestCase infrastrukturu** (nema `phpunit.xml`, `tests/` foldera, niti composer PHPUnit zavisnosti — provjereno u `composer.json`). Uvođenje PHPUnit-a bi bila NOVA zavisnost koja zahtijeva eksplicitno odobrenje. Za Fazu A, preporučena verifikacija bez novih alata: privremena, jednokratna `wp eval` provjera (read-only, deterministička, u skladu sa postojećom `wp eval` konvencijom projekta) koja konstruiše `WP_Query`/`get_terms()` sa i bez `dp_visibility_context` argumenta i potvrđuje očekivano ponašanje — ne ostavlja trag u kodu.

### Consequences

- Segment Landing (i bilo koja buduća shared površina) NE MOGU sigurno ponovno koristiti trenutne homepage blokove doslovno bez Faze B rada — ali sama Faza A ne blokira ništa niti zahtijeva da to bude riješeno sada.
- Bilo kakva izmena `inc/visibility/class-query-filter.php` (uključujući Fazu A) zahtijeva eksplicitno odobren implementacioni plan prije koda (frozen sistem pravilo, `docs/active/current-phase.md`) — Faza A je OVIM ADR-om odobrena za implementaciju; Faza B zahtijeva NOVU odluku kad Homepage/Segment-Landing arhitektura bude definisana.
- FINALNA homepage/segment-landing struktura i sadržajna specifikacija dokumentovane su zasebno: `docs/active/homepage-segment-landing-architecture.md` (ta specifikacija i dalje važi za SADRŽAJ; ovaj ADR pokriva samo vidljivost-primitivu).
- Nijedan trenutni blok se ne mijenja kao dio ovog ADR-a.

### Related

- `inc/visibility/class-query-filter.php`
- `docs/frozen/*` (Frozen Systems tabela, `docs/active/current-phase.md`)
- `docs/active/homepage-segment-landing-architecture.md` (nova, FINAL struktura)
- ADR-008 (ERP boundary, ista sesija)

---

## ADR-010 — Delivery-Location Checkout: potvrđen NON-COMPLIANT gap + odobrena hibridna remediation arhitektura (implementacija NIJE izvršena)

**Datum:** 2026-09-22 (revidirano isti dan — vidi Revizija ispod; implementirano i djelomično validirano na stagingu isti dan — vidi Staging Acceptance ispod)
**Status:** Accepted — implementirano (`inc/checkout-delivery-location.php`, commit `8577565`) i deployovano na staging. 2+ grana validirana na realnim staging Apros podacima. 0/1-lokacija grane i finalna `_apros_delivery_location_id` persistencija na stvarno kompletiranoj narudžbi ostaju code-review + izolovana lokalna simulacija, bez live staging dokaza (vidi Staging Acceptance). Woo Blocks browser-side restoration nijansa, otvorena istim testom, razriješena je zasebnom WC 11.1.1 source-tracing istragom istog dana — SATISFIED BY DESIGN, live E2E potvrda i dalje pending — vidi ispod.
**Vlasnik:** Checkout / Delivery Locations (AP-07)

### Revizija (isti dan, 2026-09-22)

Originalna verzija ovog ADR-a predlagala je UNIVERZALAN eksplicitan Apros delivery-location selektor za svaki checkout, bez obzira na broj dostupnih lokacija partnera. Naknadna istraga (ista sesija — poređenje `apros_get_partner_delivery_locations()` šeme, native Woo billing/shipping polja, i kompletnog outgoing `uncle-dev-importer/order.php` payload-a) pokazala je da:

- payload već šalje punu billing I shipping adresu kao potpuno odvojena polja, nezavisno od `partnerDeliveryLocationId`;
- `recipient_code` nema deterministički, sigurno izvodiv odnos prema Woo adresnim poljima (nema zajedničkog ključa, ERP tabela nema `country` kolonu, šema ne garantuje jedinstvenost adrese po `recipient_code`) — mapiranje adrese → `recipient_code` NIJE pouzdano izvodivo bez fuzzy matchinga, koji je eksplicitno odbačen;
- za partnera s **0 ili tačno 1** dostavnom lokacijom, ovaj problem uopšte ne postoji — nema šta da se mapira niti bira, pa prisiljavanje kupca da vidi ERP-specifičan selektor u tom slučaju nepotrebno izlaže internu integracionu terminologiju.

Ovo je promijenilo odluku iz "univerzalan selektor" u **hibridnu arhitekturu** ispod — selektor se prikazuje ISKLJUČIVO kada je stvarno neophodan (2+ lokacije).

### Context

Finalizovano poslovno pravilo (klijent, ova sesija): za B2B partnere s više dostavnih lokacija, svaka NOVA narudžba mora početi bez unaprijed izabrane lokacije — kupac mora eksplicitno izabrati lokaciju za tu narudžbu; prethodni izbor se ne smije ponovo koristiti.

Read-only istraga ove sesije (lokalno + staging, `ssh hetzner` — isključivo `find`/`grep`/`sed -n`) potvrdila je **NON-COMPLIANT** stanje:

- Theme kod (project-owned) ne sadrži nikakav delivery-location selector, ni u klasičnom ni u Block checkout-u — potvrđeno grep-om kroz `inc/`, root PHP i sve `.js` fajlove (nula pogodaka za `partnerDeliveryLocationId`, `apros_delivery_locations`, `recipient_code`, `delivery_location`, `apros_get_partner_delivery_locations`).
- Protected `apros-pricing` plugin (staging, `apros-pricing.php:727-730`) sluša `$_POST['apros_delivery_location']` na `woocommerce_checkout_create_order` i čuva `_apros_delivery_location_id` order meta — ali sam **ne renderuje, ne enqueue-uje niti validira** ijedno UI polje koje bi tu vrijednost popunilo (potvrđeno: nula `woocommerce_checkout_fields`/`woocommerce_form_field`/block-checkout field registracija i nula enqueue poziva u cijelom plugin folderu).
- Posljedica: `uncle-dev-importer/order.php:91-99` danas **uvijek** aktivira fallback na prvu dostavnu lokaciju partnera (`recipient_code ASC`) pri ERP exportu narudžbe — ovo pogađa SVAKU narudžbu koja danas prolazi kroz sistem, ne rubni slučaj. Fallback se izvršava isključivo u trenutku ERP sync-a (`woocommerce_thankyou`/`payment_complete`/`status_processing`), nikad u toku renderovanja checkout-a — kupac ga nikad ne vidi.
- Ovo krši osnovni poslovni zahtjev suštinski, ne samo formalni "no-reuse" tekst pravila — kupac trenutno nikad eksplicitno ne bira dostavnu lokaciju.

Ovaj nalaz razrešava nesigurnost koju je ADR-008 prvi zabilježio ("nije potvrđeno da [fallback] odražava checkout UI pre-fill ponašanje") — sada je potvrđeno da UI ne postoji uopšte.

### Potvrđen izvor partner_code-a

`apros_partner_code` user meta, ručno postavljen kroz WP Admin → Users → Edit User ("Apros Pricing" sekcija), sačuvan funkcijom `apros_pricing_save_user_fields()` u protected `apros-pricing` plugin-u. Ovo je jedini postojeći izvor partner_code-a za ulogovanog korisnika — buduća implementacija ga mora ponovo koristiti, ne kreirati paralelni mapping.

### Decision — odobrena HIBRIDNA remediation arhitektura (implementacija NIJE izvršena)

Cijela implementacija ostaje u project-owned theme kodu; protected plugin-ovi (`apros-pricing`, `uncle-dev-importer`, `b2b-partner-importer`) se NE mijenjaju. Woo billing/shipping ostaje autoritativan, kupcu vidljiv delivery-address workflow u svim slučajevima — `partnerDeliveryLocationId` je interna integraciona vrijednost koja se dodaje SAMO kada je stvarno potrebna.

**Grananje po broju dostavnih lokacija partnera** (`apros_get_partner_delivery_locations($partner_code)`, `$partner_code` iz postojećeg `apros_partner_code` user meta — jedini izvor, ponovo se koristi, ne kreira se paralelan mapping):

1. **0 lokacija:** Checkout ostaje potpuno Woo-native. Nema ERP-specifičnog selektora. `_apros_delivery_location_id` se ne postavlja — `partnerDeliveryLocationId` ostaje `null` u payload-u, isto kao i danas. Apros-strana obrada `null` vrijednosti je `REQUIRES APROS CONFIRMATION` (vidi ispod) — ovo NIJE bloker za implementaciju 2+ grane.
2. **Tačno 1 lokacija:** Checkout ostaje potpuno Woo-native — nema dodatnog selektora. Theme kod tiho postavlja tu jedinu `recipient_code` vrijednost kao `_apros_delivery_location_id`, bez ikakvog adresnog poklapanja (nije potrebno — postoji samo jedna moguća vrijednost). Ovo NIJE "zapamćen/default" izbor kupca — to je jedini mogući ERP recipient za tog partnera, strukturno identičan pri svakoj narudžbi, pa ne krši "no-reuse" pravilo (nema prethodnog izbora koji bi bio "reuse-ovan").
3. **2+ lokacije:** Eksplicitan izbor kupca je obavezan za SVAKU novu narudžbu, prazno initial stanje, nikad prethodni izbor. Prikazuje se korisniku-razumljiva informacija (naziv/adresa/grad) — ERP terminologija (`recipient_code`, `partnerDeliveryLocationId`) se NIKAD ne izlaže kupcu. Server-side validacija obavezna (vidi ispod).

**Eksplicitno odbačeno (namjerno, ne previđeno):** mapiranje proizvoljnog Woo billing/shipping adresnog stringa na `recipient_code` za 2+ slučaj. Istraga (ista sesija) pokazala je da ne postoji zajednički deterministički ključ između Woo adresnih polja i `apros_delivery_locations` šeme (ERP tabela nema `country` kolonu, nema garancije jedinstvenosti adrese po `recipient_code`, `address` je slobodan tekst uvezen iz Apros-a nezavisno od kupčevog unosa) — fuzzy matching je eksplicitno odbačen kao rješenje.

**Implementacioni mehanizam (za 2+ granu):**

1. WooCommerce native "Additional Checkout Fields" API (`woocommerce_register_additional_checkout_field()`, dostupno od WC 8.9+; instalirana verzija 11.1.1), `location => 'order'` (cijela narudžba, ne adresa) — jedini registruje se SAMO kada partner ima 2+ lokacije (uslovna registracija na osnovu `apros_get_partner_delivery_locations()` rezultata za trenutnog korisnika).
2. **Nema potrebe za dual classic/Blocks hook obrascem** koji `inc/checkout-logic.php` koristi za payment-rule validaciju — taj obrazac je bio nužan specifično zato što je `woocommerce_checkout_process` (klasičan, pre-order-creation validacijski hook) classic-only i ne okida se u Store API toku. Additional Checkout Fields API je, za razliku od toga, dizajniran kao JEDINSTVEN mehanizam preko oba checkout tipa — `woocommerce_validate_additional_field` (validacija) i storage/persist put rade preko istog, zajedničkog WC core order-creation puta (`WC_Checkout::create_order()`) koji koriste i klasični checkout i Store API/Blocks ruta. Pošto projekat koristi Block-based Checkout (potvrđeno), plan koristi TAČNO JEDAN integracioni hook, bez redundantne classic-only kompatibilnosti — tačan hook/meta-key naziv za WC 11.1.1 treba potvrditi čitanjem instaliranog core koda neposredno prije implementacije (nije blokirajuće, implementacioni detalj).
3. **Bridging na postojeći contract:** theme kod kopira validiranu vrijednost iz WC-ovog native additional-field storage-a u tačno isti meta ključ koji `uncle-dev-importer/order.php` već čita — `_apros_delivery_location_id`. Time se ta vrijednost uvijek eksplicitno postavlja (za 1-lokaciju granu direktno, za 2+ granu nakon validiranog izbora) prije nego što `order.php` fallback ikad dobije priliku da se aktivira — fallback ostaje netaknut kao isključivo legacy/exception safety-net (npr. buduće edge-case scenarije van ove tri grane).
4. **Validacija (2+ grana):** server-side, preko `woocommerce_validate_additional_field` — odbacuje bilo koji `recipient_code` koji nije u trenutnom rezultatu `apros_get_partner_delivery_locations($partner_code)` za PRIJAVLJENOG korisnika (sprječava proizvoljne/stale/tuđe ID-jeve). Frontend validacija sama nije dovoljna.
5. **Isti-checkout preservation (2+ grana):** obezbjeđuje native WC Blocks checkout store automatski — eksplicitan izbor preživljava AJAX/shipping-rate recalculation u ISTOJ sesiji, bez custom localStorage-a napisanog od strane ove implementacije. **Ispravka (2026-09-22, Staging Acceptance):** tvrdnja "cross-order reuse ostaje nemoguć" je bila TAČNA na server-side nivou (potvrđeno — nema customer-meta niti order-meta persistencije, vidi Staging Acceptance), ali NETAČNA/nepotpuna na browser-UX nivou — WooCommerce Blocks-ov VLASTITI `localStorage`-based cart cache (nezavisan od ove implementacije) vizuelno vraća prethodni izbor pri reload-u checkout-a unutar iste browser sesije. Ova nijansa je otvorena kao neriješena stavka, ne kao tiho razriješena — vidi Staging Acceptance sekciju ispod.
6. **UI (2+ grana):** ponovo koristi postojeće checkout stilove (`sass/pages/checkout.scss`, isti obrazac kao `js/checkout-b2b-info.js` / `.dp-b2b-billing-info` sekcije) — prazno/placeholder initial stanje, korisniku-razumljiv prikaz (naziv/adresa/grad), error state kroz native WC Blocks validation UI.

### Preostalo pitanje koje zahtijeva Apros/klijent potvrdu

- **`partnerDeliveryLocationId = null` semantika na Apros strani** — payload strukturno već podržava `null` (postojeći kod, 0-lokacija slučaj i historijski svaki dosadašnji red koda prije ove ADR), ali nema dokaza kako Apros interno obrađuje/interpretira tu vrijednost. Označeno `REQUIRES APROS CONFIRMATION` — NIJE bloker za implementaciju 1-lokacija i 2+ grana, koje ne zavise od ovog odgovora. I dalje neriješeno nakon Staging Acceptance prolaza (2026-09-22) — namjerno nije testirano jer bi zahtijevalo slanje narudžbe Apros-u. Nezavisno pitanje, ne miješati sa Woo Blocks browser-side restoration nijansom ispod.
- **Proizvoljna/nova jednokratna Woo shipping adresa** (koncept ranije neformalno pominjan kao `+ Dodaj novu adresu`) — ostaje van scope-a ove implementacije. Nije potvrđen kao poslovni zahtjev (zasebna istraga, ista sesija) i ne smije se tretirati kao autoritativan zahtjev na osnovu bilo kojeg Figma koncepta. Odluka o ovome čeka Apros odgovor o `shippingAddress` vs. `partnerDeliveryLocationId` semantici (zasebno pitanje, van scope-a ovog ADR-a).

### Staging Acceptance (2026-09-22)

Implementacija (`inc/checkout-delivery-location.php`) je napisana, lokalno simulirana (izolovani `wp eval-file` testovi, sve tri grane: 0/1/2+), commit-ovana (`8577565`), pushed i deployovana na staging (`git pull`, staging HEAD potvrđen na `8577565`) u istoj sesiji. Nakon deploya izvršen je READ-ONLY staging acceptance pass koristeći stvarnog partnera (partner_code 2870, 8 realnih Apros dostavnih lokacija) preko `User Switching` plugina (vidi ispod).

**Implementirano i validirano na realnim staging podacima (browser, 2+ grana):**
- Selektor se renderuje unutar "Additional order information" sekcije native WC Blocks checkout-a
- Svih 8 stvarnih Apros lokacija partnera 2870 prikazano, ispravno formatirano (`{name} — {address}, {postal_code} {city}`)
- Nula ERP terminologije vidljivo kupcu (nema `recipient_code`, nema internih ID-jeva)
- Initial stanje prazno pri prvom učitavanju checkout-a
- Native required-validacija blokira submit bez izbora (potvrđeno: pokušaj submit-a bez izbora NIJE kreirao narudžbu — DB provjereno prije/poslije, broj narudžbi na stagingu nepromijenjen)
- Izbor stvarne lokacije uklanja validacionu grešku
- No-partner-code regresija (admin nalog): selektor se ispravno NE renderuje, checkout nepromijenjen, 0 novih console grešaka

**Implementirano, ali samo code-review + izolovana lokalna simulacija (NIJE live staging dokaz):**
- 0-lokacija fail-closed grana (`RouteException`) — ne postoji prirodan 0-lokacija partner na stagingu za live test
- 1-lokacija tiha persistencija — ne postoji prirodan 1-lokacija partner na stagingu za live test
- Finalna `_apros_delivery_location_id` persistencija u STVARNO kompletiranoj narudžbi — namjerno netestirano jer bi kompletiranje narudžbe (bacs/cod) odmah okinulo `woocommerce_thankyou` ERP sync pokušaj u `uncle-dev-importer/order.php`, što je eksplicitno zabranjeno za ovaj prolaz

**Browser-side restoration nijansa — RAZRIJEŠENO izvornom istragom (2026-09-22, zaseban WC 11.1.1 source-tracing prolaz):**

WooCommerce Blocks-ov vlastiti `localStorage` cart cache (`storeApiCartData`/`storeApiCartHash`, WC-nativan mehanizam, nezavisan od ove implementacije) je vizuelno vratio prethodno izabranu lokaciju pri reload-u checkout stranice tokom staging testa — čak i nakon uklanjanja/ponovnog dodavanja stavke u korpu. Ciljana istraga instaliranog WC 11.1.1 izvornog koda (`wc-blocks-data.js`, `class-wc-cart-session.php`, `class-wc-cart.php`, `wc-cart-functions.php`) je utvrdila TAČAN mehanizam i razriješila ranije otvoreno pitanje:

1. **Zašto opservacija nije predstavljala stvarno novu narudžbu:** staging test je uklonio pa ponovo dodao IDENTIČAN proizvod/količinu — `WC_Cart::get_cart_hash()` je čist funkcionalni hash sadržaja korpe (`md5(cart_session + total)`), pa je rezultujući hash bio identičan prethodnom. Ovo je bio isti, nezavršeni cart lifecycle sa slučajno poklopljenim hash-em — ne novi, kompletiran pa ponovo započet, ciklus narudžbe.
2. **Zašto se keš uopšte mogao restaurirati:** WC Blocks-ova client-side `Wi()` funkcija čita `storeApiCartData` iz `localStorage` ISKLJUČIVO ako (a) `woocommerce_items_in_cart` kolačić postoji I (b) lokalno keširan `storeApiCartHash` poklapa trenutni `woocommerce_cart_hash` kolačić. Identičan sadržaj korpe → identičan hash → oba uslova zadovoljena → keš (uključujući stari izbor) restauriran.
3. **Zašto Woo-ov uspješan-checkout lifecycle sprječava ovo za stvarno novu narudžbu:** `wc_clear_cart_after_payment()` (`template_redirect`, prioritet 20) prazni korpu na order-received stranici; u ISTOM request-u, kasnije hook-ovan `WC_Cart_Session::maybe_set_cart_cookies()` (`wp` prio 99 / `shutdown` prio 0) detektuje praznu korpu i BRIŠE oba relevantna kolačića (`woocommerce_items_in_cart`, `woocommerce_cart_hash`). Bez tih kolačića, `Wi()`-jev prvi uslov odmah ne prolazi — keš se nikad ne čita, WC Blocks vrši svjež `/wc/store/v1/cart` fetch koji odražava zaista novu, praznu korpu/draft narudžbu.
4. **Nikakav custom localStorage-brisanje workaround nije potreban** na osnovu trenutnog dokaza — mehanizam je već strukturno riješen native WC lifecycle-om.

**Razlika arhitektura/izvor vs. live dokaz:** Ponašanje opisano u tačkama 1-3 je **SATISFIED BY DESIGN** — potvrđeno direktnim čitanjem instaliranog WC 11.1.1 koda (client JS + server PHP, tri nezavisna sloja koja se moraju sva poklopiti). **Live E2E potvrda i dalje NIJE izvedena** — stvarno kompletiranje TEST narudžbe, praćeno otvaranjem nove narudžbe i provjerom da je selektor prazan, bi zatvorilo posljednju empirijsku prazninu, ali NIJE potrebno sada niti je izvedeno u ovom ili prethodnom prolazu (namjerno izbjegnuto — kompletiranje narudžbe bi okinulo ERP sync pokušaj). Status: **SATISFIED BY DESIGN — LIVE E2E CONFIRMATION STILL PENDING** (ne miješati sa "riješeno i live-potvrđeno").

**User Switching (staging operational tooling, ne aplikacioni kod):** Za browser acceptance test korišten je `User Switching` plugin (John Blackbourn, slug `user-switching`, v1.12.2 pri instalaciji) — instaliran i aktiviran isključivo na DreamPoint B2B stagingu radi impersoniranja TEST partner naloga bez potrebe za njihovim lozinkama. Pristup ograničen na `edit_users` capability (admin-only po defaultu; potvrđeno da customer role tog capability nema). Lozinka partner naloga nije mijenjana. Plugin namjerno ostaje instaliran/aktivan na stagingu za buduće acceptance testove — dokumentovan u `~/.claude/docs/server-runbook.md` (dp-b2b sekcija), ne u ovom theme repo-u (nije aplikacioni/theme kod).

### Consequences

- Nijedna izmjena `apros-pricing`, `uncle-dev-importer` ili `b2b-partner-importer` nije potrebna niti planirana.
- Predložen nov, samostalan theme fajl (`inc/checkout-delivery-location.php`), uključen u `functions.php` pored postojećeg `inc/checkout-logic.php` (isti WooCommerce-conditional include blok) — `inc/checkout-logic.php` se sam NE modifikuje (frozen fajl, izbjegava se dodatni approval gate za nepovezanu funkcionalnost; i nije mu ni potrebna dual-hook logika koju taj fajl koristi za drugu svrhu).
- Implementacija može startovati odmah za sve tri grane (0/1/2+) — nijedna od preostalih "BUSINESS DECISION REQUIRED" stavki iz prethodne verzije ovog ADR-a više ne blokira implementaciju: hibridna arhitektura ih je razriješila arhitekturalno (0 i 1 lokacija više ne zahtijevaju nikakvu poslovnu odluku o auto-selekciji — ponašanje je determinističko po definiciji).
- Tačan WC 11.1.1 interni format order-meta ključa za native additional-field storage i tačan naziv jedinstvenog order-creation hook-a treba potvrditi čitanjem instaliranog WC core koda neposredno prije implementacije — nije blokirajuće, samo implementacioni detalj za potvrdu.
- Nijedan kod nije mijenjan ovom odlukom — ADR dokumentuje samo potvrđeni gap i odobrenu hibridnu arhitekturu plana.

### Related

- ADR-008 (read-only nalazi protected plugin implementacije, ista sesija) — ovaj ADR razrešava njegovu preostalu nesigurnost o checkout UI pre-fill ponašanju
- `docs/project-status-matrix.md` AP-07 (ažuriran ovom sesijom)
- `inc/checkout-logic.php` / `docs/frozen/checkout-logic.md` (referentni primjer classic+Blocks razlike u hook ponašanju — razlog zašto TA specifična dual-hook potreba ovdje NE postoji, vidi Decision iznad)
- Woo-native delivery mapping istraga (ista sesija) — poređenje `apros_get_partner_delivery_locations()` šeme, Woo adresnih polja i `uncle-dev-importer/order.php` payload-a; osnova za odbacivanje adresa→recipient_code mapiranja i za hibridnu 0/1/2+ granu

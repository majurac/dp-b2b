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

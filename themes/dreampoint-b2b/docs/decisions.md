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

> **Addendum 2026-10-07 (see ADR-018):** the client formally confirmed this Apros-driven flow in `B2B odgovori na pitanja.docx` §3. The cron-based polling described above is still **NOT implemented** (only a manual, unscheduled partner sync exists). "B2B rola dodjela" never named a concrete role; none exists today. Historical text above is unchanged.

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
`wp dp-b2b reset-catalog --batch=20260713_1138` (run as site user `dream9399`). The generator's `guard_production()` was *intended* to abort in a production environment; on staging `wp_get_environment_type()` returns `production` only because the `WP_ENVIRONMENT_TYPE` constant/env var is unset (WordPress default). A transient `WP_ENVIRONMENT_TYPE=staging` env var was supplied for the single invocation — **no `wp-config.php` change, no source change**. *Correction (2026-10-01):* the guard of that time did NOT use `wp_get_environment_type()`; it checked `defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'production'` on the raw constant, so it did not actually enforce the effective-environment rule, and the transient env var was not what satisfied it. The guard was replaced with a `wp_get_environment_type()` allow-list (`local`/`development`/`staging`) — see ADR-012 Update 2026-10-01 (fixture generator hardening). With the new guard, a transient env var is exactly the supported opt-in.
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

**Ažurirano 2026-09-22 (Apros Sandbox Live E2E Confirmation, `docs/decisions.md` ADR-010):** Apros je eksterno, eksplicitno potvrdio da je konfigurisan staging endpoint njihov sandbox/staging environment (ne izvedeno iz zvanične ZGData API PDF dokumentacije, koja tu designaciju sama ne sadrži — zaseban, kasniji eksterni nalaz). Sandbox status je time u potpunosti RESOLVED, ne samo "efektivno". Live E2E narudžba (#23358) je uspješno prihvaćena na tom endpointu (ERP broj 4244). Produkcijski pristup i dalje nije adresiran ovim nalazom — nezavisno pitanje, nije relevantno za TEST/sandbox rad.

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
- **Addendum 2026-10-07 (see ADR-018):** this inspection did not record that `apros-pricing` already contains a manual partner sync (`apros_pricing_sync_partners`, run via `wp importer partners` from `uncle-dev-importer`). It reads `partnerList/get`, links/creates WP users and sets `apros_partner_code`; it is unscheduled and does not activate users. The statement above that partners are "ručno seed-ovani" refers to the unrelated Excel/CSV `b2b-partner-importer`.

### Related

- ADR-001 (Pricing Architecture), ADR-002 (Partner Approval Architecture), ADR-006 (uncle-dev-importer prvi put dokumentovan)
- `docs/project-status-matrix.md` §0, §5
- `docs/erp-discovery-findings.md`

---

## ADR-009 — Homepage vs. Segment Landing vidljivost: identifikovan arhitekturalni gap → implementirano i deployovano

**Datum:** 2026-09-21 (odluka) / 2026-09-23 (implementacija, deploy, closure)
**Status:** Accepted → **IMPLEMENTIRANO I DEPLOYOVANO** (staging, `dreampoint.b2b.uncledev.cloud`, commit `c4dc61d4f5674a3eb6da59490210f243019fee1e`)
**Vlasnik:** Vidljivost engine (frozen, nepromijenjen) / Homepage-Segment Landing arhitektura

### Context

Klijent je finalizirao (`B2B odgovori na pitanja.docx`, §5.1 EDIT superseduje raniji prijedlog personalizovanog homepage-a): Homepage i tri Segment Landing stranice (Lifestyle/Toys/Outdoor) moraju prikazivati kompletan sadržaj, neograničen customer-bucket pravilima. Segment Landing MORA biti filtriran PO SEGMENTU (ne po customer bucket-u) — segment filtering ≠ customer/bucket filtering. Customer-specifična vidljivost počinje tek dublje u katalogu.

### Investigation — CONFIRMED empirijski (lokalno, Playwright, `vis_none` test korisnik, `TestVis2025!`)

Prijava kao `vis_none` (nulta catalog vidljivost) na trenutnu homepage stranicu, upoređeno sa admin sesijom:

- `blocks/templates/latest-products.php`, `blocks/templates/bestseller.php`, `blocks/templates/discounted-products.php` — svaki pokreće `new WP_Query(['post_type' => 'product', ...])` direktno. `inc/visibility/class-query-filter.php` → `should_filter()` presreće SVAKI WP_Query s eksplicitnim `post_type => product`, bezuslovno — nema page-context izuzetka. **CONFIRMED**: sekcije "Novo u ponudi" i "Akcija" su prikazale prazne poruke ("Nisu pronađeni...") za `vis_none`, dok su za admin bile pune.
- `blocks/featured-products.php` (ACF `selected_products` relationship polje) — **CONFIRMED**: cijeli blok "Istaknuti proizvodi" je nestao za `vis_none` (ACF-ovo razrešavanje relationship polja prolazi kroz isti filtrirani query put).
- `blocks/templates/brands.php` — **CONFIRMED**: koristi `get_terms(['taxonomy' => 'product_brand', ...])`, presretnuto od `filter_brand_terms()` (registrovan na `get_terms` hook); cijeli "Naša zastupništva" brand carousel je nestao za `vis_none`.
- `blocks/company-features.php`, `blocks/templates/featured-brand.php`, `blocks/templates/featured-categories.php` — **CONFIRMED neosjetljivi**: render isključivo ACF tekst/slika/link polja, bez product ili `product_brand` upita — vidljivost engine ih ne može dotaći bez obzira na kontekst stranice. `featured-categories.php` linkuje na `product_cat` termine ali nikad ne poziva `get_terms()` sam, a enginov `get_terms` filter je scoped isključivo na `product_brand` — `product_cat` nije presretnut.
- Sales Representative sekcija: **već implementirana** (`inc/myaccount-komercijalist.php` → `display_commercialist_contact_info()`, pozvana iz `functions.php`), nezavisna od vidljivost engine-a — čita per-user `assigned_komercijalist` meta koji pokazuje na "Komercijalist" CPT, s gracioznim fallback-om ("Partner nema dodeljenog komercijalistu"). Već prisutna u trenutnom homepage "Tu smo za vas" bloku i na My Account "Komercijalist" tabu; **nije potvrđeno** da je prisutna na Contact/Kontakt stranici (novi klijentski zahtjev).

  **Update (2026-09-23) — RESOLVED / CONFIRMED PRESENT:** Ranija neizvjesnost oko prisustva na Contact stranici je razriješena, bez ikakve izmjene koda. `display_commercialist_contact_info()` se doseže preko `dreampoint_b2b_contact_info_shortcode()` (shortcode `[contact_info]`, `functions.php`), koji renderuje dijeljeni template `blocks/templates/contact-info.php` — isti put koji koristi i Homepage (ACF blok `acf/contact-info-section` u post_content) i `contact.php` (direktan `get_template_part('blocks/templates/contact-info')` poziv, bez uslovljavanja po stranici). Ovo dijeljenje postoji od refaktora `2d843df` (2026-08-03, "refactor Contact Info into reusable shortcode"). Ciljana lokalna Playwright provera (2026-09-23, korisnik `admin` s `assigned_komercijalist` postavljenim) potvrdila je da Homepage i Kontakt renderuju identičan blok ("Tu smo za vas" → ime, telefon, email istog dodijeljenog komercijalista) za istog korisnika. Zaključak: postojeća implementacija je otkrivena i verifikovana ovom provjerom — nije implementirana u ovoj sesiji.

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

### Update (2026-09-23) — Faza A deterministički POTVRĐENA

Preporučena verifikacija iznad je izvršena: privremeni, read-only `wp eval-file` skript (nije ostavljen u repo-u), lokalno, za svih pet postojećih test korisnika. Ground truth (ukupan broj `product` postova i `product_brand` termina) dobijen direktnim `$wpdb` upitom, van vidljivost hook-ova: **427** proizvoda, **51** brand termina.

| Korisnik (access type) | default upit | `shared_surface` upit | isolation (default #2) | nepoznat kontekst (`bogus_value`) | brand termini default | brand termini `shared_surface` |
|---|---|---|---|---|---|---|
| vis_none (no_access) | 0 | 427 | 0 | 0 | 0 | 51 |
| vis_rule_brand (rule_based) | 2 | 427 | 2 | 2 | 1 | 51 |
| vis_rule_cat (rule_based) | 2 | 427 | 2 | 2 | 51† | 51 |
| vis_full (full_access) | 427 | 427 | 427 | 427 | 51 | 51 |
| vis_offer (custom_offer) | 4 | 427 | 4 | 4 | 3 | 51 |

† `vis_rule_cat` nema brand pravila → `filter_brand_terms()` namjerno ne filtrira brand listu kad je `allowed_brand_ids` prazan (postojeće, dokumentovano ponašanje u kodu — vidi komentar u `filter_brand_terms()` — nije regresija ni dataset anomalija).

Potvrđeno za sve access tipove: (1) default upit i dalje primjenjuje customer/bucket vidljivost; (2) eksplicitan `dp_visibility_context = 'shared_surface'` bypass-uje i vraća pun katalog; (3) nakon shared upita, sljedeći default upit se vraća na restriktovano stanje — nema perzistentnog/globalnog leakage-a; (4) nepoznata vrijednost konteksta NE bypass-uje — fail-closed potvrđen; (5) `get_terms('product_brand')` integration point ponaša se identično WP_Query putanji. `inc/visibility/class-query-filter.php` nije mijenjan ovom verifikacijom — Faza A ostaje dormant do prvog stvarnog pozivaoca (Faza B).

### Update (2026-09-23) — Segment membership model istraga: `brand_segment` odobren, `product_cat` odbačen

Prije Faze B implementacije, sproveden je poseban, isključivo read-only knowledge-first + staging-investigation krug da se utvrdi KOJI postojeći podatak treba biti izvor istine za "kom segmentu (Lifestyle/Toys/Outdoor) pripada proizvod" na Segment Landingu. Dva kandidata su postojala u projektu; oba investigirana do korena.

**`product_cat` termini "Lifestyle"/"Toys"/"Outdoor" — ODBAČENI kao source of truth.**
Ovi termini postoje i lokalno i na stagingu, ali su **van ERP mapiranja**: `Importer::get_categories()` (uncle-dev-importer, `src/Importer.php`) gradi mapu Apros `classification` → lokalni `product_cat` term ISKLJUČIVO preko ACF `remote_category_id` polja na terminu. Direktna staging provera (read-only, `wp eval` preko SSH): sva tri termina (staging term_id 254/255/256) imaju `remote_category_id = null` i `count = 0` — importer ih nikad ne dotiče, ne populiše, ne održava. Nisu ni traceable do lokalnog dev-catalog-generatora (nema literalnog kreiranja ovih naziva u `inc/dev/class-dev-catalog-generator.php`). Poreklo ostaje neutvrđeno, ali potvrđeno van ERP dosega — ne smiju postati Segment Landing izvor istine.

**`brand_segment` (ACF select na `product_brand`, `acf-json/group_675053191eac4.json`) — ODOBREN kao B2B-owned/manual segment membership layer.**
Direktno pročitan izvorni kod potvrđuje: `brand_segment` string se **ne pojavljuje nigdje** u `uncle-dev-importer` ni `apros-pricing` (SSH grep, staging, protected plugins, read-only). Polje je 100% lokalna WordPress/ACF ekstenzija — ERP ga ne poznaje, ne piše, ne briše. Vrijednosti na `product_brand` (ERP-sync taksonomija, 61/62 termina na stagingu ima `remote_category_id`) su nezavisan, ručno održavan sloj preko ERP-sinkronizovanih brendova — potpuno konzistentno sa poznatim workshop nalazom (`docs/erp-discovery-findings.md`, `docs/stakeholder-question-matrix.md`): legacy B2B sistem radi **brand→matično-skladište** automatsko mapiranje (Igračke=3, Lifestyle=5), a taj mehanizam TAKOĐE ne postoji tehnički implementiran nigdje u trenutnoj Apros integraciji (potvrđeno: nula pogodaka za `warehouse`/`skladi`/`lokacij`/`mjesto` u cijelom importer i pricing kodu) — samo kao institucionalno znanje. `brand_segment` je stoga ispravan nosilac tog znanja u trenutnoj arhitekturi, ne duplikacija niti izmišljena taksonomija.

**Arhitekturalna odluka (closed):** `product → product_brand → brand_segment`. Ne uvoditi novu taksonomiju, mapping tabelu, ni sync mehanizam.

**Poznat, namjerno neriješen content gap:** `Outdoor` nema NIGDJE dokumentovanu vezu ni sa jednim od 4 legacy skladišta (Glavno/Igračke/Naočale/Lifestyle) — ni u dokumentaciji, ni u kodu, ni na stagingu. Ovo je content/business pitanje (koji brendovi pripadaju Outdoor segmentu), NE arhitekturalni bloker — ne rešava se pogađanjem, čeka validno poslovno znanje (Josip/klijent). DP-02/BL-06 ostaje formalno otvoren kao P1 blocker (order-routing kontekst), nezavisno od ove Segment Landing odluke.

### Update (2026-09-23) — Faza B implementirana

Faza B (ADR-009 §Decision, "kad Homepage/Segment-Landing rendering arhitektura stvarno postoji, njen pozivalac eksplicitno prosljeđuje `shared_surface` semantiku") implementirana je isključivo na pozivaocima — **`inc/visibility/class-query-filter.php` nije mijenjan**.

**Novi generički primitiv:** `inc/homepage-segments.php` — čita `dp_page_segment_context` ACF polje (novo, `acf-json/group_dp_shared_surface.json`, location: `post_type == page`, default prazno = bez promjene ponašanja) sa vrijednostima `''|homepage|lifestyle|toys|outdoor`. Izlaže: `dreampoint_b2b_shared_surface_query_args()` (dodaje `dp_visibility_context=shared_surface` na `WP_Query`/`get_terms` args), `dreampoint_b2b_apply_segment_tax_query()` (dodaje `product_brand` tax_query po segmentu, koristi `dreampoint_b2b_get_brand_ids_for_segment()` — `brand_segment` meta_query preko `get_terms`, sama sa `shared_surface`).

**Pozivaoci ožičeni:** `blocks/templates/latest-products.php`, `discounted-products.php` (shared_surface + segment tax_query), `featured-products.php` (Faza B raw-ID konverzija `get_field('selected_products', false, false)` + shared_surface, BEZ segment tax_query — ručno kurirano po stranici), `blocks/templates/brands.php` (carousel: shared_surface uvijek; na segment stranicama dinamički `brand_segment`-based `include` umjesto `selected_brands`).

**Deterministička verifikacija (lokalno, `wp eval-file`, read-only + privremena reverzibilna mutacija):** korišten je **[DEV] fixture brend** (term 214, sintetički test entitet, NE realan/ERP brend), privremeno markiran `brand_segment=lifestyle`, testiran preko stvarnih helper funkcija kao `vis_none` (no_access test korisnik), pa **odmah vraćen na prazno**. Rezultati: default upit (bez konteksta) = 0; `lifestyle` segment kontekst = 13 (= ground-truth broj proizvoda tog brenda); `toys` kontekst (isti brend, pogrešan segment) = 0 (nema cross-segment curenja); `homepage` kontekst = 427 (pun katalog, bez segment filtera); naredni default upit poslije = 0 (nema perzistentnog leakage-a). Sve u skladu sa Faza A garantovanim fail-closed ponašanjem.

**Browser verifikacija (lokalno, Playwright, stvarni HTTP):** Homepage kao `vis_none` prikazuje pun "Naša zastupništva" carousel (identično adminu) — shared_surface bypass potvrđen u realnom rendering kontekstu. `/shop/` kao isti korisnik i dalje vraća "No products were found" — dublji katalog ostaje ograničen. Lifestyle Segment Landing (`/lifestyle/`) renderuje bez PHP grešaka za oba korisnička konteksta; "Novo u ponudi"/"Akcija" sekcije ispravno prazne (segment filter aktivan, ali nijedan REALAN brend još nema `lifestyle` vrijednost — očekivano, content gap, ne bug).

**Implementacija:** Homepage (post ID 17) rebuild — Segmenti tile-ovi (`featured-categories-section`, prošireno sa `show_custom_link`/`custom_url` poljima koja je PHP kod već očekivao ali ACF field group nikad nije definisao) sada linkuju na nove stranice umjesto na prazne `product_cat` arhive; legacy Homepage sekcije koje Figma više ne sadrži (hero slider, Latest/Discounted/Featured Products, Featured Brand) uklonjene. Tri nove Segment Landing stranice (`lifestyle`/`toys`/`outdoor`, template `page-segment-landing.php`, `dp_page_segment_context` postavljen po stranici) kreirane sa istim reusable block kompozicijom.

**Poznat, dokumentovan gap:** "Badge" tekst iznad hero naslova (Figma, Segment Landing) nije implementiran — polje ne postoji u `featured-section`/`featured-brand` field grupi, koja je DB-only (nije u `acf-json/`, pre-postojeći governance gap, van scope-a ove faze). Hero slika je generička (reused, ne Figma-specifična — `get_screenshot` MCP alat je bio rate-limited tokom ove sesije).

### Update (2026-09-23) — Deploy + focused frontend fix pass (closure)

**Finalni deployovan commit:** `c4dc61d4f5674a3eb6da59490210f243019fee1e` (staging `dreampoint.b2b.uncledev.cloud`, hash-potvrđen preko `git rev-parse HEAD`), gradi se na `feef07b61b59bbaec50a39b6ec306126bad51457` (originalni Homepage/Segment Landing/Faza B commit). Oba pushovana na `origin/master`.

**Focused frontend fix (`c4dc61d`), otkriveno tokom staging acceptance-a:**

1. **Brands slider JS nedostajao na Segment Landing stranicama.** Root cause: `dreampoint_b2b_needs_slick()` (gate za `slick.min.js`/`slick-init.js` enqueue) je bio hardkodovan na `is_front_page() || is_singular('post') || is_home() || is_product()` — obične `page` stranice (Lifestyle/Toys/Outdoor) nikad nisu prolazile uslov, pa se Slick nikad nije učitavao iako je Brands carousel markup postojao. Fix: funkcija sada, za svaku `is_singular()` stranicu koja ne prolazi postojeće uslove, parsira `post_content` (isti `parse_blocks()`/`dreampoint_b2b_collect_block_names()` mehanizam koji već koristi CSS enqueue, `inc/enqueue-block-styles.php`) i traži bilo koji ACF blok koji renderuje Slick-slider markup. Block-driven, ne page-ID/template provera — bez dupliranja slider JS-a.
2. **Company Features se ponašao kao slider svuda gdje se pojavljuje (uključujući Homepage).** Root cause: shortcode je renderovao `class="features-content company-features-slider"` — druga klasa nije imala nijedan drugi legitiman kontekst u projektu (grep potvrdio). Fix: klasa i odgovarajući Slick init blok uklonjeni generički (ne per-page hack); dodat static `display:flex;flex-wrap:wrap;justify-content:center` u `sass/blocks/company-features.scss` (rebuild preko `npm run build:blocks`), pošto je horizontalni raspored ranije u potpunosti zavisio od Slick-ovog runtime flex-a.

**Verifikacija — eksplicitna distinkcija lokalno/staging:**
- **Lokalno (Playwright, browser):** potpuno verifikovano — Brands slider inicijalizovan (`slick-initialized` klasa) na sve 4 stranice, Company Features NEMA `slick-initialized` nigde, 4 stavke poravnate u jednom redu na desktop širini, responsive wrap radi, 0 console grešaka/upozorenja.
- **Staging:** HEAD potvrđen (`c4dc61d...`), server-side/statička provera potvrđena (`dreampoint_b2b_needs_slick()` vraća `YES` za sve tri Segment Landing stranice, `brands-slider` markup prisutan, deployovan `slick-init.js` više ne inicijalizuje Company Features, deployovan CSS sadrži static flex layout) — **ali stvarna browser/JS inicijalizacija (`slick-initialized`, console stanje) NIJE direktno posmatrana na stagingu**, jer sajt globalno redirektuje neautentifikovane posetioce na `/my-account`, a staging test-korisnički kredencijali nisu bili dostupni u ovoj sesiji. Deployovan kod je hash-identičan lokalno-verifikovanom kodu, ali ova distinkcija se ne sme brisati.

**Hero badge — klasifikacija (istorijsko usklađivanje, ne nova odluka):** Namjerno odloženo (nije slučajno izostavljeno) — eksplicitno identifikovano u Phase 1 Figma mappingu i eksplicitno zabilježeno u prethodnom Update-u iznad u trenutku implementacije. Uzrok odlaganja: blokirano postojećom ACF arhitekturom — `featured-section`/`featured-brand` field grupa je DB-only (nije u `acf-json/`), i dodavanje polja usred ove faze bi zahtijevalo DB-autoritativnu izmjenu van projektnog ACF Field Group Creation Doctrine-a bez sigurnog JSON mirror-a. Ostaje otvoren follow-up, ne redefinisan kao van scope-a.

**Figma vernost — konačan, precizan status:** Strukturni/sadržajni Figma metadata za Homepage i Segment Landing su uspješno pročitani i sekcijska arhitektura/redoslijed implementirani iz tog izvora. Direktno pixel poređenje sa Figma screenshot-om je bilo blokirano `get_screenshot` MCP rate limit-om (Figma "View seat") tokom cijele sesije i nikad nije izvršeno — **rezultat se ne smije opisivati kao pixel-perfect Figma-complete**. Trenutne Segment Landing hero slike su reused/generičke, ne potvrđeni finalni Figma asset-i. Pixel-level vizuelno poređenje ostaje vizuelni acceptance/polish follow-up.

**Cache-busting nalaz (zabilježeno, NIJE riješeno u ovom pass-u):** Pojedinačni block CSS fajlovi (`css/blocks/*.css`) dijele globalni `_S_VERSION` cache-bust query string sa `style.css`/`theme.min.js` (`_S_VERSION = max(filemtime(style.css), filemtime(theme.min.js))`) — promjena SAMO u block CSS-u ne mijenja `?ver=` vrijednost, pa postojeći posjetioci mogu zadržati stale keširanu verziju do prirodnog isteka keša. Otkriveno tokom lokalne verifikacije ovog fix-a (zaobiđeno samo za potrebe testiranja, cache-bypass fetch). Pun zapis: `docs/active/block-css-cache-busting-followup.md`.

**Update 2026-10-09 — cache-busting RESOLVED (`fff3d37`, staging-verified):** theme page CSS (7), `woocommerce.css` and the 11 block CSS files are now versioned per file through `dreampoint_b2b_asset_ver()` instead of the shared `_S_VERSION`; `_S_VERSION` and the helper are unchanged. Triggered by a stale `cart.css` after the cart remove button deploy. `brands.css` keeps its own equivalent `filemtime()`; vendor CSS and some JS still use `_S_VERSION`. Record: `docs/active/block-css-cache-busting-followup.md` (Resolution).

**Content-population follow-up (NIJE implementacioni defekt, konsolidovano sa prethodnim update-om):**
- Realni ERP-sync brendovi na stagingu i dalje trebaju ručnu `brand_segment` populaciju (61/62 brand termina na stagingu je ERP-mapirano preko `product_brand`, ali segment vrijednost mora ostati ručni content unos — ne smije se izmišljati).
- Outdoor membership posebno ostaje business/content knowledge gap — čeka validno institucionalno znanje (Josip/klijent), ne smije se pogađati.
- "Istaknuti proizvodi" (Featured Products) na sve tri Segment Landing stranice čeka finalnu ručnu kuraciju gdje je primjenjivo.
- **Novi nalaz iz finalnog staging acceptance-a:** `features_items` ACF Options polje (Company Features sadržaj) je `NULL`/nekonfigurisano na stagingu — blok trenutno ne renderuje nijednu stavku tamo. Pre-postojeći staging content/configuration gap, potpuno nezavisan od static-layout fix-a (potvrđeno da je kod ispravan preko istog, već popunjenog polja lokalno). Nije popunjavano u ovom pass-u.

**Protected boundaries (potvrđeno tokom cijele implementacije/deploya/fix pass-a):** nijedan ERP sync/import nije pokrenut; nijedan protected Apros plugin (`uncle-dev-importer`, `apros-pricing`) nije mijenjan; nijedna realna `brand_segment` vrijednost nije izmišljena; sintetička katalog generacija nije pokretana na stagingu.

### Update (2026-09-30) — Brand data ownership model (staging brand audit)

Izvor: read-only staging audit `product_brand` termova (62 terma, 10 192 proizvoda) + read-only čitanje protected `uncle-dev-importer` koda + jedan read-only GET `brandList/get` prema verifikovanom Apros sandboxu (66 ERP brendova). Nijedna mutacija, import ni sync nisu izvršeni.

**ERP/Apros posjeduje:** kanonski identitet branda — `GET /brandList/get` (`brandId`, `title`) — i vezu proizvod → brand kroz `articleList.brandId`.

**Ponašanje importera (CONFIRMED BY CODE, `AprosProvider.php` + `Importer::assign_taxonomy_term_to_product()`):**
- gradi mapu `brandId → title`; `product_brand` term nastaje SAMO ako je brend referenciran uvezenim artiklom (ERP brend bez artikala nema WP term);
- postojeći term traži po IMENU (`get_term_by('name', ...)`), a kreira ga ako imena nema;
- brand proizvoda dodjeljuje sa `wp_set_object_terms` (zamjena prethodne relacije, ne dodavanje);
- ERP `brandId` upisuje na term kroz ACF `remote_category_id` (repeater, `value` = brandId kao string);
- na proizvodu upisuje `_BRAND_ID` (int) za Apros pricing / rabat lookup; varijacije `_BRAND_ID` nemaju;
- NIKAD ne briše zastarjele `product_brand` termove.

**Arhitekturalna posljedica (rizik, NE observirani produkcijski kvar):** pošto identitet term-a počinje od IMENA, a ne od sačuvanog ERP `brandId`, preimenovanje brenda u ERP-u može stvoriti duplikat WordPress terma umjesto ažuriranja postojećeg kanonskog.

**Fixture marker:** `_dp_brand_fixture` NE znači da je brend disposable test podatak. Devet trenutno označenih termova (24Bottles, A Fan Of, Design Letters ApS, Fresk, Leatherman, Ledlenser, Leuchtturm1917, NUUNA, Printworks) su validni ERP-backed brendovi čiji `remote_category_id` odgovara trenutnom sandbox inventaru. Fixture status se nikad ne smije koristiti kao kriterij brisanja.

**DreamPoint (lokalni WordPress) posjeduje prezentacijske/enrichment podatke koje ERP ne isporučuje:**
1. `brand_segment` (ACF select, `acf-json/group_675053191eac4.json`);
2. Featured Image — ACF polje `brand_image`, field key `field_68302324d99a1`, pohranjeno kao attachment ID u term meta;
3. Brand logo — nativni WooCommerce term meta `thumbnail_id`, attachment ID.

**HARD invarijanta:** odsustvo segment/slika/logo informacije u ERP-u NIJE dokaz da su odgovarajući lokalni podaci zastarjeli. ERP reconciliation, brand cleanup, rukovanje preimenovanjem i konsolidacija duplikata NE smiju automatski: brisati ili mijenjati `brand_segment`; brisati `brand_image` ili `thumbnail_id`; brisati Media Library attachmente; mijenjati attachment metapodatke. Za svaki zadržani brend ova lokalna polja ostaju netaknuta osim ako eksplicitan zadatak namjerno kaže drugačije.

**Pravilo sigurne konsolidacije:** prije brisanja ili spajanja bilo kojeg `product_brand` terma obavezno uporediti ERP identitet, legitimne relacije proizvoda, `brand_segment`, `brand_image`, `thumbnail_id` i ostale ne-ERP term meta. Ako duplikat/ručni term nosi lokalno kurirane podatke koji trebaju preživjeti, oni se PRIJE brisanja namjerno migriraju na zadržani kanonski term. Lokalno kurirani brand podaci se nikad ne odbacuju tiho tokom ERP normalizacije.

**Slučaj Chilly's — RIJEŠEN 2026-10-01 (Phase 2B; zapis u ADR-012, Update 2026-10-01).** Izvorno stanje (zadržano kao evidencija):
- term 16 `Chilly's` (slug `chillys`): ručni/ne-ERP term, bez ERP brand ID-a, objavljeni proizvodi #23 i #113 (bez `_erp_id`/`_BRAND_ID`), `thumbnail_id = 108`, bez `brand_image`;
- term 283 `CHILLYS` (slug `chillys-2`): ERP-backed, `brandId = 28`, bio odvojen od terma 16, bez `thumbnail_id` i bez `brand_image`.
Ishod: logo 108 je prije brisanja sačuvan kao `thumbnail_id` terma 283, a term 16 je obrisan. Slug `chillys-2` je namjerno nepromijenjen (zasebna URL/SEO odluka).

**Ostalo (strukturni nalazi, ne popravljano):** 5 ERP brendova nema WP term (11, 29, 30, 69, 76 — najvjerovatnije bez artikala); jedan draft proizvod (#23350) ima `_BRAND_ID` bez `product_brand` relacije.

**Životni ciklus importera (referenca):** normalni puni import zadržava lokalna brand polja (`brand_segment`, `brand_image`, `thumbnail_id`) i attachmente na zadržanim termovima — verifikovano stvarnim punim importom na stagingu. Detalji importer životnog ciklusa i destruktivnih putanja su u ADR-012. Identitet branda (preimenovanje, duplikati, brisanje/rekreiranje terma) i dalje podliježe pravilima očuvanja iz ovog ADR-a.

### Update (2026-10-01) — Visibility/Quick Order correctness invariants (commit `648b3c9`)

Two defects found during staging acceptance of the real-ERP demo configuration were fixed and verified with real authenticated HTTP on staging (via User Switching).

**Invariant 1 — brand-term filtering touches `product_brand` only.** `Dreampoint_B2B_Query_Filter::filter_brand_terms()` (`get_terms` hook) previously filtered the ENTIRE returned collection whenever `product_brand` was among the requested taxonomies. WordPress/WooCommerce prime several product taxonomies in one `get_terms()` call, so `product_type` was dropped and a variable product was reported as `simple` for restricted users (confirmed: product 13206, `vis_offer`). Now `restrict_brand_terms()` filters only `WP_Term` objects whose `taxonomy === 'product_brand'`; terms of any other taxonomy pass through unchanged. Scalar results (IDs/names) carry no taxonomy identity and are filtered only when the query is scoped to `product_brand` alone. Semantics for genuine brand-only queries, the `shared_surface` bypass and admin bypass are unchanged. Note: callers that use `WP_Term_Query` directly (e.g. WooCommerce Store API `/products/brands`) never reach the `get_terms` filter — this is existing behavior, not part of this invariant.

**Invariant 2 — variation data requires parent access.** `DP_Quick_Order_Rest_Api::get_variations()` (`/quick-order/products/{id}/variations`) now calls the canonical `dp_b2b_product_accessible` filter for the parent before returning anything. An inaccessible parent returns the same 404 `not_variable` as a non-variable product (no existence/type disclosure). Previously any B2B-eligible user could read price, stock and attributes of all variations of a parent they could not otherwise see. Sibling routes were checked: product list uses the visibility engine, `cart/sync` already used `dp_b2b_product_accessible`.

**Staging acceptance (2026-10-01, product 13206 / ERP 55639, 24 variations):**

| User | list | search | variations | PDP |
|---|---|---|---|---|
| vis_full | variable | variable | 200 × 24 | 200 |
| vis_offer | variable | variable | 200 × 24 | — |
| vis_rule_brand | no | no | 404 | 404 |
| vis_rule_cat | no | no | 404 | 404 |
| vis_none | 403 | 403 | 403 | 404 |

List totals confirmed per rule: full 457, NUUNA-only 68, category-800 3, custom offer 5. For `vis_offer` the Store API product data (type, brands, attribute terms 1/12/2, 24 variations) matched the admin baseline.

**Staging-only fixture:** product category 800 `Demo — Category Access` (bucket 131, products 9339/18893/6180) exists only on staging as a demo fixture for rule-based category access. It is not ERP-owned and not part of any production data model; it must not be treated as a canonical category or migrated.

### Update (2026-10-01) — Store API / REST product visibility finding: CLOSED (commit `5891b7e`)

`STORE API VISIBILITY FIX VERIFIED — DEFECT CLOSED` — the Store API gate no longer blocks Phase 2 cleanup.

**Finding (previously recorded here as OPEN).** WooCommerce Store API `GET /wc/store/v1/products/{id}` returned parent data (identity, public price, type, variation IDs) to restricted and anonymous users whose list/search/PDP access was denied (observed: `vis_rule_brand` → #13206, HTTP 200). Classified as a DreamPoint B2B integration/access-control defect, not an upstream WooCommerce vulnerability: upstream normally exposes published products; DreamPoint did not apply its per-user boundary to these paths. No customer data and no partner-specific prices were exposed (generic catalog price only).

**Root cause.** Direct Store API product reads (`ProductsById`, `ProductsBySlug`) use `wc_get_product()` and direct WP REST reads (`wp/v2/product/{id}`) use `get_post()`, so they never pass through the `WP_Query` filters (`pre_get_posts` / `posts_clauses`) that protect catalog collections. Variations had the same gap: direct variation retrieval, and Store API `type=variation` collections (`post_type=product_variation`, outside `should_filter()`), bypassed parent-product visibility.

**Fix.**
- `Dreampoint_B2B_Access_Guard::guard_rest_product_item()` on `rest_request_after_callbacks`: for Store API by-ID / by-slug and wp/v2 product single reads it calls the canonical `dp_b2b_product_accessible` and replaces an inaccessible result with the route's own 404 error (no existence disclosure). Routes are recognized by controller instance, not URL parsing. A variation is authorized by its PARENT. The client-supplied `dp_skip_visibility` param is deliberately not honoured on these public routes.
- `Dreampoint_B2B_Query_Filter`: REST queries for `product_variation` only are restricted to variations of parents visible to the current user (visible parents come from the regular product query; full access is not restricted).
- Normal Store API product collections (list/search/include/sku/slug/`collection-data`) are unchanged — still protected by the existing `WP_Query` filtering. Authorization logic is not duplicated.

**Verified acceptance (staging, real HTTP via User Switching, 2026-10-01).** #13206: `vis_full` and `vis_offer` 200 (by ID, slug, variation, wp/v2; `type=variation&parent` = 24); `vis_rule_brand`, `vis_rule_cat`, `vis_none` and anonymous 404 / 0 results with no product data in the error body. Allowed controls still 200 (NUUNA #7613, category-800 #9339, offer #5874). `type=variation` without parent returns only variations of accessible parents (vis_offer 24 from #13206; vis_full 1284 = admin; others 0). Collection counts unchanged: 457 / 68 / 5 / 3 / 0. Quick Order (list, search, variations) and PDP unchanged; admin retains access; unrelated wp/v2 objects unaffected.

**Testing caveat (not a confirmed defect).** During acceptance, browser HTTP caching reused an earlier anonymous Store API collection response for requests such as `/products?per_page=5` and `search=Urban`. Repeating them with cache disabled (`no-store`) returned the correct per-user results. Those collection responses carried `Last-Modified` but no explicit `Cache-Control` preventing browser reuse. This is recorded only as a testing note: acceptance of user-specific REST responses must disable the browser cache. No cross-user server/CDN cache leakage was observed or claimed, and no cache change was made.

### Consequences

- Segment Landing (i bilo koja buduća shared površina) NE MOGU sigurno ponovno koristiti trenutne homepage blokove doslovno bez Faze B rada — ali sama Faza A ne blokira ništa niti zahtijeva da to bude riješeno sada.
- Bilo kakva izmena `inc/visibility/class-query-filter.php` (uključujući Fazu A) zahtijeva eksplicitno odobren implementacioni plan prije koda (frozen sistem pravilo, `docs/active/current-phase.md`) — Faza A je OVIM ADR-om odobrena za implementaciju; Faza B je implementirana isključivo na pozivaocima, bez izmjene ovog fajla (vidi Update iznad).
- FINALNA homepage/segment-landing struktura i sadržajna specifikacija dokumentovane su zasebno: `docs/active/homepage-segment-landing-architecture.md` (ta specifikacija i dalje važi za SADRŽAJ; ovaj ADR pokriva samo vidljivost-primitivu).
- Realne `brand_segment` vrijednosti (posebno Outdoor) ostaju otvoren content-population zadatak — ne smiju biti izmišljene u kodu ni u ovoj dokumentaciji.

### Related

- `inc/visibility/class-query-filter.php` (nepromijenjen)
- `inc/homepage-segments.php` (novo, Faza B primitiv)
- `docs/frozen/*` (Frozen Systems tabela, `docs/active/current-phase.md`)
- `docs/active/homepage-segment-landing-architecture.md` (FINAL struktura, status ažuriran)
- `docs/active/block-css-cache-busting-followup.md` (novo — cache-busting nalaz)
- `uncle-dev-importer/src/Importer.php` (`get_categories()`, `assign_taxonomy_term_to_product()` — ERP category/brand mapping evidencija)
- ADR-008 (ERP boundary, ista sesija)
- ADR-012 (ERP importer lifecycle safety — životni ciklus importera i očuvanje lokalnih brand polja pri punom importu)

---

## ADR-010 — Delivery-Location Checkout: potvrđen NON-COMPLIANT gap + odobrena hibridna remediation arhitektura (implementacija NIJE izvršena)

**Datum:** 2026-09-22 (revidirano isti dan — vidi Revizija ispod; implementirano i djelomično validirano na stagingu isti dan — vidi Staging Acceptance ispod)
**Status:** Accepted — implementirano (`inc/checkout-delivery-location.php`, commit `8577565`) i deployovano na staging. 2+ grana validirana na realnim staging Apros podacima. **Finalna `_apros_delivery_location_id` persistencija i new-order empty-selection reset su sada LIVE E2E CONFIRMED preko stvarno kompletirane Apros sandbox narudžbe (#23358, 2026-09-22) — vidi "Apros Sandbox Live E2E Confirmation" ispod.** 0/1-lokacija grane ostaju code-review + izolovana lokalna simulacija (nema prirodnih 0/1-lokacija partnera na stagingu).
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

> **Addendum 2026-10-07 (see ADR-018):** the sentence above ("jedini postojeći izvor") is incomplete. `apros_pricing_sync_partners` (`wp importer partners`, manual, unscheduled) also writes `apros_partner_code`, matching by email. Manual profile entry documents what was operationally used at the time; it did NOT supersede the ADR-002 intent that the Apros synchronization discovers the partner code. Historical text left unchanged.

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

- **`partnerDeliveryLocationId = null` semantika na Apros strani** — payload strukturno već podržava `null` (postojeći kod, 0-lokacija slučaj i historijski svaki dosadašnji red koda prije ove ADR), ali nema dokaza kako Apros interno obrađuje/interpretira tu vrijednost. Označeno `REQUIRES APROS CONFIRMATION` — NIJE bloker za implementaciju 1-lokacija i 2+ grana, koje ne zavise od ovog odgovora. **I dalje neriješeno i nakon Apros Sandbox Live E2E Confirmation-a (2026-09-22, vidi ispod)** — taj test je namjerno poslao stvarni `recipientCode` (1), ne `null`; null-scenario ostaje namjerno netestiran (zahtijevao bi zaseban test sa 0-lokacija partnerom, van scope-a ovog prolaza). Nezavisno pitanje, ne miješati sa Woo Blocks browser-side restoration nijansom niti sa sandbox environment potvrdom ispod.
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
- ~~Finalna `_apros_delivery_location_id` persistencija u STVARNO kompletiranoj narudžbi — namjerno netestirano...~~ **→ RAZRIJEŠENO (Apros Sandbox Live E2E Confirmation, 2026-09-22, isti dan) — nakon eksterne Apros potvrde sandbox statusa, ovo je live testirano preko narudžbe #23358. Vidi sekciju ispod.**

**Browser-side restoration nijansa — RAZRIJEŠENO izvornom istragom (2026-09-22, zaseban WC 11.1.1 source-tracing prolaz):**

WooCommerce Blocks-ov vlastiti `localStorage` cart cache (`storeApiCartData`/`storeApiCartHash`, WC-nativan mehanizam, nezavisan od ove implementacije) je vizuelno vratio prethodno izabranu lokaciju pri reload-u checkout stranice tokom staging testa — čak i nakon uklanjanja/ponovnog dodavanja stavke u korpu. Ciljana istraga instaliranog WC 11.1.1 izvornog koda (`wc-blocks-data.js`, `class-wc-cart-session.php`, `class-wc-cart.php`, `wc-cart-functions.php`) je utvrdila TAČAN mehanizam i razriješila ranije otvoreno pitanje:

1. **Zašto opservacija nije predstavljala stvarno novu narudžbu:** staging test je uklonio pa ponovo dodao IDENTIČAN proizvod/količinu — `WC_Cart::get_cart_hash()` je čist funkcionalni hash sadržaja korpe (`md5(cart_session + total)`), pa je rezultujući hash bio identičan prethodnom. Ovo je bio isti, nezavršeni cart lifecycle sa slučajno poklopljenim hash-em — ne novi, kompletiran pa ponovo započet, ciklus narudžbe.
2. **Zašto se keš uopšte mogao restaurirati:** WC Blocks-ova client-side `Wi()` funkcija čita `storeApiCartData` iz `localStorage` ISKLJUČIVO ako (a) `woocommerce_items_in_cart` kolačić postoji I (b) lokalno keširan `storeApiCartHash` poklapa trenutni `woocommerce_cart_hash` kolačić. Identičan sadržaj korpe → identičan hash → oba uslova zadovoljena → keš (uključujući stari izbor) restauriran.
3. **Zašto Woo-ov uspješan-checkout lifecycle sprječava ovo za stvarno novu narudžbu:** `wc_clear_cart_after_payment()` (`template_redirect`, prioritet 20) prazni korpu na order-received stranici; u ISTOM request-u, kasnije hook-ovan `WC_Cart_Session::maybe_set_cart_cookies()` (`wp` prio 99 / `shutdown` prio 0) detektuje praznu korpu i BRIŠE oba relevantna kolačića (`woocommerce_items_in_cart`, `woocommerce_cart_hash`). Bez tih kolačića, `Wi()`-jev prvi uslov odmah ne prolazi — keš se nikad ne čita, WC Blocks vrši svjež `/wc/store/v1/cart` fetch koji odražava zaista novu, praznu korpu/draft narudžbu.
4. **Nikakav custom localStorage-brisanje workaround nije potreban** na osnovu trenutnog dokaza — mehanizam je već strukturno riješen native WC lifecycle-om.

**Razlika arhitektura/izvor vs. live dokaz:** Ponašanje opisano u tačkama 1-3 je **SATISFIED BY DESIGN** — potvrđeno direktnim čitanjem instaliranog WC 11.1.1 koda (client JS + server PHP, tri nezavisna sloja koja se moraju sva poklopiti). **→ AŽURIRANO (Apros Sandbox Live E2E Confirmation, 2026-09-22, isti dan):** live E2E potvrda je naknadno izvedena (narudžba #23358) nakon eksterne Apros potvrde sandbox statusa. Status: **SATISFIED BY DESIGN — LIVE E2E CONFIRMED.** Detalji u sekciji ispod.

**User Switching (staging operational tooling, ne aplikacioni kod):** Za browser acceptance test korišten je `User Switching` plugin (John Blackbourn, slug `user-switching`, v1.12.2 pri instalaciji) — instaliran i aktiviran isključivo na DreamPoint B2B stagingu radi impersoniranja TEST partner naloga bez potrebe za njihovim lozinkama. Pristup ograničen na `edit_users` capability (admin-only po defaultu; potvrđeno da customer role tog capability nema). Lozinka partner naloga nije mijenjana. Plugin namjerno ostaje instaliran/aktivan na stagingu za buduće acceptance testove — dokumentovan u `~/.claude/docs/server-runbook.md` (dp-b2b sekcija), ne u ovom theme repo-u (nije aplikacioni/theme kod).

### Apros Sandbox Live E2E Confirmation (2026-09-22, isti dan, nakon Staging Acceptance-a)

**Provenance eksternog environment fakta:** Apros je eksterno, eksplicitno potvrdio (2026-09-22, van ovog dokumenta — usmena/pisana komunikacija sa klijentom, ne sam PDF) da je konfigurisan `https://tockasna-b2b-api.zgdata.hr/api3/{API-KEY}/` njihov sandbox/staging endpoint. **Napomena o provenance-u:** zvanična ZGData API PDF dokumentacija (v1.0, primljena i rekonsilovana ranije istog dana) SAMA NE sadrži environment designaciju (potvrđeno u toj rekonsilijaciji) — sandbox potvrda je zaseban, kasniji, eksterni Apros nalaz, ne izvedena iz PDF-a. Ovo razrješava ranije zabilježen blocker `APROS TEST ORDER E2E BLOCKED — TEST ENDPOINT NOT CONCLUSIVELY VERIFIED`.

**Live sandbox test (jedna kontrolisana narudžba):**
- Partner: `svijet-medija-d-o-o` (user_id 7, `apros_partner_code` 2870), impersoniran preko User Switching, lozinka nepromijenjena
- Proizvod: CLEANSING CLOTH (post ID 5874, šifra `P-51136`), količina 1, redovna cijena, na zalihi
- Odabrana lokacija: "Kaptol - Centar Kaptol — Nova Ves 17, 10000 Zagreb" (`recipientCode` = 1)
- Woo narudžba **#23358**, status On hold (BACS), kreirana 2026-09-22 12:34:06

**Lanac persistencije — empirijski potvrđeno:**
- Native Woo Additional Checkout Field: `_wc_other/dreampoint-b2b/delivery-location = 1`
- Integration bridge: `_apros_delivery_location_id = 1`
- Oba se poklapaju sa odabranim `recipientCode = 1`
- Apros sandbox je prihvatio narudžbu: order note *"ERP Synced. ERP broj: 4244 (skladište 0)"*, `_erp_documents` = `{numberErp: 4244, warehouseId: 0}`, `_erp_sync_status = success:4244`

**Napomena o preciznosti dokaza (outbound payload):** Perzistencioni lanac je empirijski verifikovan preko `_apros_delivery_location_id = 1`; već verifikovan integracioni kod (`order.php`) deterministički mapira tu vrijednost u outbound `partnerDeliveryLocationId`, a sandbox je prihvatio narudžbu. **Sirovo outbound HTTP request body NIJE nezavisno uhvaćeno/logovano tokom ovog testa** — `partnerDeliveryLocationId = 1` se navodi kao deterministički izveden zaključak iz koda + uspješnog sandbox prihvatanja, ne kao direktno posmatrana HTTP request evidencija.

**New-order reset — sada LIVE E2E CONFIRMED:** Nakon uspješnog kompletiranja #23358, WooCommerce je prirodno ispraznio korpu (potvrđeno: "No products in the cart" na order-received stranici). U ISTOJ autentifikovanoj browser sesiji, nova korpa je započeta (bez ručnog brisanja cookies/localStorage), novi Block Checkout otvoren — selektor je prikazao prazan placeholder ("Select a dostavna lokacija"), Kaptol NIJE bio predizabran. Ovo je live dokaz koji je nedostajao WC 11.1.1 source-tracing zaključku.

**Šta OVAJ test NE razrješava (namjerno ostaje otvoreno):** opšta AP-06 order API specifikacija (URL/response šema van ovog jednog primjera), formalna `order/create` request/response šema, opšta idempotency semantika, shipping-address precedence između `partnerDeliveryLocationId` i `shipping*` polja (shipping adresa u #23358 je namjerno poklopljena sa Kaptol adresom — nema dokaza o conflicting-address ponašanju), null `partnerDeliveryLocationId` semantika, stabilnost `recipientCode`-a između sync ciklusa, per-warehouse stock dostupnost. 0-lokacija i 1-lokacija grane ostaju code-review + lokalna simulacija (nema prirodnih staging partnera za te slučajeve).

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

---

## ADR-011 — PDP "Neobvezujuća MPC": manually maintained product-level ACF field

**Date:** 2026-09-30
**Status:** Implemented locally (not committed, not deployed to staging)

### Context

The business wants a non-binding recommended retail price (MPC) shown above the B2B price on the Product Single Page only. A read-only request against the Apros sandbox `articleList/get` (10,186 articles, 2026-09-30) showed the payload contains exactly: `articleId, code, title, barcode, wholesalePrice, vatRate, stock, classification, virtualArticle, visible, brandId, isNew, isSpecialOffer, description` — no MPC-like field. This finding is scoped to the current B2B `articleList/get` payload only; it does not claim Apros has no MPC anywhere.

### Decision

- MPC is a theme-owned, manually maintained ACF `number` field `dp_non_binding_mpc` (min 0, step 0.01, optional) in a small dedicated group `group_dp_product_reference_price` (`acf-json/group_dp_product_reference_price.json`, location `post_type == product`). No existing theme field group targets products, so an existing group could not be extended.
- The stored value is the final display amount entered by the client. It is never derived from `wholesalePrice`, VAT, brand discounts or country prices, and never participates in any price calculation.
- Rendering: `dreampoint_b2b_get_non_binding_mpc()` (`inc/woocommerce.php`) + a conditional block in `woocommerce/content-single-product.php`, placed immediately above the existing price block but with its own independent condition (valid positive MPC only). The existing price guard (`get_price() && in stock`) is unchanged, so MPC still renders when the Woo price block is hidden (e.g. out of stock). Formatted with `wc_price()`. Empty, non-numeric or `<= 0` values render nothing. Variable products use the parent's single value. No JS.
- The existing Woo price output (`get_price_html()`, regular/sale filters, `apros-pricing` resolver) is untouched. No other surface (archive, Quick Order, cart, checkout, emails) renders MPC.

### Consequences

- The importer (`uncle-dev-importer`) does not write `dp_non_binding_mpc`, so imports cannot overwrite it.
- If MPC must later come from Apros, that is a separate investigation.

---

## ADR-012 — ERP importer lifecycle safety

**Date:** 2026-09-30
**Status:** Documented (behavior of the protected `uncle-dev-importer` plugin as inspected and exercised on staging; no code change)

### Context

Catalog cleanup work (manual legacy products, duplicate brand terms) depends on what a normal ERP refresh will and will not touch. The importer is a protected plugin (`uncle-dev-importer`, provider `AprosProvider`); its lifecycle was read from source and then confirmed by one real full import on staging. This ADR records the durable behavior, not the audit that found it. Statements about exact line-level behavior are implementation details of the current importer version and must be re-verified if the plugin changes.

### Decision — recorded invariants

**1. Execution path.** The canonical refresh is `wp importer import` (`ImporterWPClient::import` → `Importer::import`). It is a full import: all Apros GET endpoints are read every run and there is no separate incremental mode. A per-product response hash that includes the business date skips unchanged products only within the same business day.

**2. Product identity and SKU namespace.** For an ERP parent product `_erp_id` is the ERP `articleId`, and the generated SKU is `'P-' + articleId`, so a normal ERP-managed parent satisfies `SKU == 'P-' + _erp_id`. Lookup is by `_erp_id` through a direct SQL query (post types/statuses publish, draft, pending, private, future), deliberately independent of storefront visibility hooks. Lookup happens before SKU assignment.

**3. SKU collision is destructive.** If `set_sku()` fails because another product owns the target SKU, the importer resolves the holder and calls `wp_delete_post( $holder, true )`. The holder is **not required to be ERP-managed**: a manual WooCommerce product that owns an incoming `P-<articleId>` SKU would be permanently deleted. Consequence: before any import that follows manual catalog work, migrations, or bulk SKU edits, the `P-<articleId>` namespace must be checked against non-ERP products. A collision-free result is an observed state at the time of the check, not an architectural guarantee.

**4. Variation lifecycle.** Stale ERP variations (existing under an ERP parent but absent from the incoming variation state) are hard-deleted (`wp_delete_post( ..., true )`) before new ones are created. Variation SKU collisions are scoped: a holder is deleted only if it is a variation of the same parent; other holders are left alone and the SKU assignment is logged as a duplicate. This lifecycle is not a general cleanup of unrelated products or Media Library attachments.

**5. Missing-product lifecycle.** `trash_missing_products` selects only products carrying the ERP provider marker (`_erp_provider` = provider name, plus `_erp_id` present) and moves those absent from the incoming article list to trash with `wp_trash_post` (recoverable, not permanent deletion). Manual/non-ERP products have no provider marker and are outside this lifecycle, so they survive ERP refreshes indefinitely. Defensive guard: if the incoming list contains fewer than 20 article IDs (current implementation value) trash is skipped, protecting against a failed or truncated ERP response; a partial response above the threshold is not detected.

**6. Visibility and status.** ERP `visible` drives publish/draft for ERP-managed products. It is not a one-to-one mapping: the provider forces `visible = false` for a virtual (variation-driven) article that has no usable variations, so `ERP visible = true` does not universally mean WordPress `publish`. Product images are imported only for visible products; draft products keep no imported images. This proves ERP-driven image handling only; it does not make manually uploaded product images protected enrichment and does not imply they must be migrated to ERP products.

**7. Categories are not a working ERP channel.** `AprosProvider::categories()` returns an empty collection, so the provider creates no categories. The importer can map an article `classification` to a `product_cat` term only through a term-level `remote_category_id` mapping, and no such mapping is currently established. A full ERP refresh therefore does not reconstruct WordPress product categories. If category integration is implemented later, this ADR must be revised.

**8. Brand enrichment boundary (extends ADR-009).** For `product_brand`, the importer resolves terms by NAME (`get_term_by('name', ...)`), creates a term when no name matches, assigns the product brand with `wp_set_object_terms` (replacing the previous relation), and writes only the ERP mapping field `remote_category_id` on the term. It does not write `brand_segment`, `brand_image` or `thumbnail_id`, and it never deletes terms or Media Library attachments. A real full import preserved these fields unchanged on retained terms. This holds for the normal full-import lifecycle of retained terms only. Because identity resolution starts from the brand name, an ERP rename, capitalization/name mismatch, duplicate term, term deletion/recreation or importer change is a reconciliation case that must explicitly preserve DreamPoint-owned enrichment per ADR-009.

### Consequences

- Manual/non-ERP products are not cleaned up by the ERP lifecycle; retiring them is an explicit, separately approved catalog operation.
- Any operation that could give a non-ERP product a `P-<articleId>` SKU, or that recreates ERP parents with new IDs, requires a SKU-namespace pre-flight before the next full import.
- A full import is safe for retained brand terms' presentation fields under current behavior, but is not a substitute for brand identity reconciliation.
- Category structure must be maintained outside the importer until a category mapping is deliberately introduced.

### Update (2026-10-01) — Phase 2: core legacy catalog cleanup CLOSED (staging)

`PHASE 2 CORE LEGACY CATALOG CLEANUP DOCUMENTED AND CLOSED`. Staging only; runtime code was not changed (HEAD `587a5bf` before and after). This is the explicit, separately approved retirement operation that the Consequences above require.

**Six legacy products.** Manual staging development/test fixtures #23, #113, #114, #119, #124, #127 were independently verified before cleanup: `publish`, `simple`, no `_erp_id` / `_erp_provider` / `_BRAND_ID` / ERP GTIN / lock metadata, outside `dp_product_access` and every demo bucket. Only #114 had a local SKU (`F300BLPNK`); no ERP-style `P-*` SKU collision existed. Action: all six moved `publish → trash` with `wp_trash_post()` (reversible). They were **not** permanently deleted and remain in Trash (`EMPTY_TRASH_DAYS = 30`). Their probable ERP counterparts #9416, #6150, #19836, #15602, #21142, #9470 were untouched; those mappings are PROBABLE, not hard-identity duplicates, and the decision did not depend on them.

**Chilly's.** Manual term 16 `Chilly's` (slug `chillys`) was non-ERP legacy/test brand data; ERP-backed term 283 `CHILLYS` (slug `chillys-2`, `remote_category_id` 28) is the canonical brand. Before deleting term 16, attachment 108 (`chillys.jpg`, md5 `fa413177f5095a5945c747d787c0c344`) was assigned as term 283 `thumbnail_id = 108`; the attachment and file were unchanged. After the six products were trashed, term 16 was deleted. Term 283 remained present, ERP-backed (28), slug `chillys-2`, `thumbnail_id` 108, and `/brand/chillys-2/` kept rendering the logo. No product-level data from #23/#113 was migrated. Legacy #23 had been assigned to manual term 16, but its probable ERP counterpart (#9416) belongs to 24Bottles — it was NOT mapped to CHILLYS. Attachment 108 is not orphaned: it is now term 283's WooCommerce brand logo.

**Recovery artifacts** (`/home/dreampoint.b2b/backups/`, do not move or modify): `pre-phase2b-20261001-113307.sql.gz` (5,709,264 B, `dream9399:dream9399`, `gzip -t` PASS); `pre-phase2b-chillys-logo-20261001-113307.tgz` (384,429 B, 10 files, `gzip -t` PASS); `pre-phase2b-manifest-20261001-113307.txt`.

**Real ERP demo intact.** Demo products #5874, #7613, #7505, #9339, #18893, #6180, #13206, #15287, #14250 stayed `publish` / ERP-owned. Buckets unchanged: 130 → `vis_full`; 131 → category 800 → `vis_rule_cat`; 134 → NUUNA (273) → `vis_rule_brand`; 137 custom offer → `vis_offer` (exactly 5874, 6180, 9339, 13206, 18893); `vis_none` no access. Category 800 stayed assigned exactly to 6180, 9339, 18893.

**Acceptance (real HTTP, User Switching, cache disabled).** Catalog counts `vis_full` / `vis_rule_brand` / `vis_offer` / `vis_rule_cat` / `vis_none` = 451 / 68 / 5 / 3 / 0. The six trashed products are absent from catalog, search, Quick Order and Store API collections, and direct access no longer exposes them as catalog products. Quick Order for #13206 unchanged (`vis_full`/`vis_offer`: visible, variable, 24 variations; `vis_rule_brand`/`vis_rule_cat`: not visible, variations protected; `vis_none`: no-access behavior). Store API fix remains closed (#13206 authorized 200, unauthorized 404). Navigation and shop render.

**Persistent-cart observation (side effect, not a failure).** Before Phase 2B the persistent cart of `vis_full` (user 3) contained legacy #113, #119, #127. During post-cleanup acceptance WooCommerce loaded that user's context after those products had become unavailable and the persistent cart became empty (`[]`). It was not cleared manually and no cart cleanup command was run; no rollback is needed because the entries were test products intentionally removed from the active catalog.

**Intentionally retained (separate decisions).**
- Legacy/manual `product_cat` terms (20, 23, 25, 27, 30, 31, 32, 34 and their trees): `inc/nav-categories.php` builds the desktop/mobile navigation from `product_cat` with `hide_empty => false` and ERP does not supply categories, so they are part of current navigation. Category 800 is protected demo infrastructure.
- Checkout-draft orders 31, 142, 146: reference some legacy products but keep their historical item names.
- The 13 product attachments of the six products: may now be orphaned.
- Buckets 135 and 136: empty development/test remnants, no users, no legacy-product rules.
- Term 283 slug stays `chillys-2` (not renamed to `chillys`; changing the ERP brand archive URL is a URL/SEO decision, not a requirement).

**Fixture-generator residual — RESOLVED 2026-10-01 (see 'Fixture generator hardening' below).** Originally recorded: `inc/dev/class-dev-catalog-generator.php` still defined a `chillys` brand fixture and `guard_production()` checked only the raw `WP_ENVIRONMENT_TYPE` constant, so it did not protect staging while the constant was undefined although `wp_get_environment_type()` reports `production`.

**Follow-up register (none is a blocker for the closed core cleanup):**
1. Legacy/manual product categories — separate decision; currently KEEP.
2. Checkout-draft orders 31/142/146 — **DONE 2026-10-01** (permanently deleted on explicit user decision; see 'Legacy test order cleanup' below).
3. Buckets 135/136 — separate decision.
4. Orphan attachments of the six products — separate decision.
5. Persistent cart — `vis_full` already emptied naturally; no cleanup required unless broader stale carts are later investigated.
6. Fixture generator — stale `chillys` fixture + environment-guard hardening — **DONE 2026-10-01** (see below).
7. CHILLYS slug `chillys-2` → `chillys` — separate URL/SEO decision.
8. Six trashed products — eventual permanent deletion/empty-trash; separate explicit decision, not to be bundled into another cleanup.

### Update (2026-10-01) — Fixture generator hardening (follow-up register item 6: CLOSED)

Code/repository-only fix to `inc/dev/class-dev-catalog-generator.php` and `dev-fixtures/`; no staging data was touched, the generator was not run, and no configuration was changed (no `WP_ENVIRONMENT_TYPE` definition, no `wp-config.php` change).

- **Environment guard.** `guard_production()` now uses `wp_get_environment_type()` with an explicit fail-closed allow-list: only `local`, `development` and `staging` are allowed. `production`, unset and invalid values (WordPress normalizes them to `production`) are blocked with an error naming the effective environment and the allowed values. No bypass flag, no hostname exceptions, no automatic override. The guard covers both `generate-catalog` (all phases and `--refresh-metadata`) and `reset-catalog`. Historical truth: the previous guard checked only the raw constant and never enforced this rule (see ADR-006 correction).
- **Stale fixtures removed.** The `chillys` (canonical ERP-backed term is 283 `CHILLYS` / `chillys-2`), `flow-amsterdam` and `go-baby-go` (terms 267/269 were deliberately deleted from staging earlier) fixture definitions were removed, along with `dev-fixtures/brands/{chillys,flow-amsterdam,go-baby-go}/`. The canonical fixture inventory is now 18. `djeco` and `janod` are retained (their slug-matching ERP-backed terms 17/18 exist, so they skip). Fixture identity (slug matching) and `_dp_brand_fixture` semantics (provenance only, no runtime meaning) are unchanged; existing markers were not touched.
- **No data migration.** Term 283, attachment 108 and all Media Library data are untouched.
- **Operational consequence.** Both the local installation and staging currently resolve `wp_get_environment_type() === 'production'` unless an environment is explicitly configured, so the generator is intentionally blocked there by default. Future intentional fixture work must explicitly configure an allowed environment (`local`, `development` or `staging`) through the canonical WordPress mechanism (constant or environment variable, e.g. a transient `WP_ENVIRONMENT_TYPE=staging` for a single invocation).
- **Validation.** `php -l`; static assertion of 18 unique fixture slugs (no `chillys`/`flow-amsterdam`/`go-baby-go`, `djeco`/`janod` present); isolated harness using the real `wp_get_environment_type()` and the real guard method confirming `local`/`development`/`staging` allowed and `production`/unset/invalid blocked — no WordPress bootstrap, no DB.

### Update (2026-10-01) — Legacy test order cleanup (follow-up register item 2: CLOSED)

Staging data operation only; no repository/runtime code or configuration changed (staging HEAD `9f882ba` before and after, `git status` clean).

- **Decision.** The user explicitly decided that orders #31, #142 and #146 are not needed for business, historical, development, debugging or audit purposes and may be permanently removed.
- **Pre-delete classification (re-verified).** All three were HPOS `shop_order` records in status `checkout-draft`, `created_via = store-api`, payment method `bacs`, customer user 1, dated 2026-04-23 / 2026-04-29 / 2026-06-18. No transaction ID, no refunds, no order notes, no ERP/integration meta (only WooCommerce internal hash/index keys and `is_vat_exempt`). Items referenced only legacy test products (#23, #114, #119, #127 — the trashed fixtures). Evidence supported: legacy development/test order.
- **Backup.** `/home/dreampoint.b2b/backups/pre-delete-test-orders-31-142-146-20261001-143320.sql.gz` (5,710,742 B, `dream9399:dream9399`, `gzip -t` PASS, full dump). Do not move or modify.
- **Mechanism.** WooCommerce API `WC_Order::delete( true )` per ID from a hard allow-list (31, 142, 146), with a precondition (status `checkout-draft`, `created_via = store-api`). No manual SQL deletes, no bulk/status/date-based cleanup.
- **Verification.** Order set before = {31, 142, 146, 147, 23358}; after = {147, 23358}; exactly the three allow-listed IDs were removed. `wc_get_order()` returns nothing for each. No residual rows for those IDs in HPOS tables, `posts`/`postmeta`, order items or `wc_order_stats`; no global orphan order items. Unrelated orders #147 and #23358 remain readable; WooCommerce 11.1.2 queries normally; no `debug.log`.
- **Scope.** No other order, product, attachment, user, category, brand or cart was touched.

### Related

- ADR-009 (Update 2026-09-30, Brand data ownership model; Chilly's case resolved by the 2026-10-01 update above)
- ADR-008 (ERP boundary, protected Apros plugins)
- `uncle-dev-importer/src/Importer.php` (`import()`, `get_product_id_by_erp_id()`, `trash_missing_products()`, `assign_taxonomy_term_to_product()`), `Providers/AprosProvider.php` (`fetch()`, `categories()`)

---

## ADR-013 — Product categories: manual `remote_category_id` mapping model (inherited from JekaaStore) and fixture provenance

**Date:** 2026-10-02
**Status:** Accepted. No importer change is required or made; this record documents facts and the decision to keep the existing model.

### Context

The question was whether DreamPoint B2B should import Apros `classificationList` into `product_cat` automatically. A Phase A importer proposal (`AprosProvider::categories()`, hardened `import_remote_categories()`, `sync_categories`/`reconcile_categories`, Uncategorized change, navigation exclusion) was prepared as a local diff and then **rejected and removed**; it was never installed, committed or deployed.

### Decision

Keep the existing importer unchanged and use the existing manual mapping model.

- `uncle-dev-importer` on B2B was forked from the JekaaStore (Apros B2C) importer. Category code is byte-identical to JekaaStore (`get_categories()`, `import_remote_categories()`, `assign_taxonomy_term_to_product()`, the `product_cat` assignment block, the `fetch_categories` gate and the `AprosProvider::categories()` stub). The same code is present in older Jekaa snapshots (2026-03), so it was not introduced by B2B work. B2B-specific importer changes exist elsewhere (pricing/partners, etc.).
- WordPress/storefront categories are locally curated. Apros classifications are associated through the ACF repeater `remote_category_id` on the term.
- **Meaning of `remote_category_id`:** "this local category is mapped to one or more ERP classifications". It does **not** mean the category was created or is owned by the ERP. Real storefront categories are expected to carry it.
- `AprosProvider::categories()` stays an unchanged stub; automatic `classificationList` → `product_cat` creation is not introduced; `fetch_categories` stays absent/NULL for Apros. No importer category-code change is needed for the current B2B requirement.
- Future production storefront taxonomy and the exact ERP mappings are a separate content/business decision (client input).

### Reference evidence (JekaaStore, read-only, 2026-10-02)

- JekaaStore is the reference Apros B2C implementation: 80 `product_cat` terms, 75 with `remote_category_id`, no automatic import of `classificationList`.
- Names and hierarchy are curated and often differ from the ERP taxonomy.
- One ERP classification can map to several WP terms (11 ERP ids do). The repeater also allows several ERP ids on one term; that direction is supported by code but was not observed in the reference data.
- Manual terms without a remote id (e.g. "Noviteti") are preserved by the importer; the default term 15 carries a dummy remote value so it is dropped from mapped products.
- Classifications without a mapping leave products in Uncategorized.
- Note: other importer instances (Cotra, TASK ERP) do implement `categories()` and enable `fetch_categories`; that is a different provider and was not adopted for Apros.

### DreamPoint B2B category terms (provenance, established by the project owner)

- `product_cat` terms 20–36 and 254–256 were created manually by the developer for development/testing and for designing the navigation dropdown. They are not client business taxonomy and not ERP-generated taxonomy, and must not be mapped to ERP classifications merely because names match. This supersedes the "origin undetermined" statement in the ADR-009 update of 2026-09-23.
- They stay temporarily because the developer needs populated navigation while designing the dropdown; removal needs explicit approval.
- Term 800 `Demo — Category Access` is a separate staging access-control fixture. Term 15 `Uncategorized` is WooCommerce default-category infrastructure.
- Currently no B2B `product_cat` term has a `remote_category_id`, so ERP products remain in Uncategorized until a curated mapping is entered.

### Update (2026-10-02) — Navigation UX source of truth is the Figma design, not the copied Jekaa code

The current `inc/nav-categories.php` (top-level groups with a subcategory grid and 200×200 thumbnails, one shared 6-hour transient) was copied from JekaaStore as a temporary placeholder and must not be treated as the DreamPoint UX specification. Statements earlier in this ADR's discussion that the menu "has two levels with thumbnails" described that placeholder only.

Source of truth: Figma file `lZvGxdZfmaLJgAMo4NBgpp`, node `11022:51566` "Katalog proizvoda menu" (https://www.figma.com/design/lZvGxdZfmaLJgAMo4NBgpp/DreamPoint-B2B?node-id=11022-51566). What it shows:

- "Katalog proizvoda" (header item with chevron) opens a panel: left column of category rows, each with a chevron-down (children revealed on expand; the expanded/hover state is not drawn), and a "Prikaži sve kategorije" link under the list; right column "Popularni proizvodi" with 4 compact product cards (image, name, price). The 9 row labels ("Kategorija 1…5") are arbitrary placeholders: Figma defines the UI pattern only, not the taxonomy or the number of categories (7, 8, 9 … are equally acceptable).
- "Popularni proizvodi" is independent of the selected/active category. It must not be modelled as category-dependent content.
- Category rows carry no thumbnails; category thumbnails are not a menu requirement.
- Lifestyle / Toys / Outdoor are separate homepage segment blocks (ADR-009), not menu entries.
- "Brandovi" and "Akcija" are separate header items.

Implications for later work (not implemented): the menu needs a curated, ordered selection of categories (with a link to all categories), a target for "Prikaži sve kategorije", a separately curated "Popularni proizvodi" list, and that list must respect per-customer B2B visibility and prices, so it cannot live in the shared global menu transient. Interaction details (expand vs flyout, hover vs click, depth beyond one expand level, mobile) are not defined by this node and need the designer.

### B2B storefront taxonomy — INITIAL STAGING taxonomy (2026-10-02; created on staging, editable after client feedback)

Derived from Apros `classificationList` + `articleList` (snapshot 2026-10-02: 210 classifications, 10,186 articles, 471 ERP-visible) and the JekaaStore manual `remote_category_id` precedent; the Figma pattern fits it (category rows with expandable children). Compact, 2 levels, 5 groups / 26 children, covering all 471 visible products. ERP model-line nodes (12-digit) are folded into their parent child-term (N:1); every used ERP node needs its own `remote_category_id` row (58 rows) because the importer matches the exact classification only (no parent propagation).

| Group (ERP code) | Child ← ERP subtree (nodes; articles / visible) |
|---|---|
| Boce i posude za hranu (002001; 1 direct article) | Termo boce ← 002001005 (6; 290/19) · Termo šalice ← 002001004 (4; 66/4) · Posude za hranu ← 002001002 (4; 17/2) · Dodaci za boce ← 002001001 (3; 48/1) |
| Naočale (002002) | Za čitanje ← 002002001 (1; 990/7) · Zaštita od ekrana ← 002002002 (2; 182/9) · Sportske ← 002002003 (8; 129/11) · Sunčane ← 002002004 (3; 183/22) · Sleeping ← 002002005 (1; 2/2) · Dodaci za naočale ← 002007 (1; 19/12) |
| Djeca (002003) | Dječje sunčane naočale ← 002003003 (8; 162/20) · Dječje naočale za zaštitu od ekrana ← 002003001 (1; 17/1) · Dječje sportske naočale ← 002003002 (1; 1/1) · Dječje boce i posude za užinu ← 002003004 (1; 8/3) · Dječji ručnici ← 002003005 (1; 2/2) |
| Lifestyle (002005; 5 direct articles) | Bilježnice i planeri ← 002005009 (1; 414/84) · Šalice ← 002005010 (1; 31/11) · Ručnici ← 002005003 (1; 36/7) · Dodaci ← 002005006 (1; 34/7) · Poklon setovi ← 002005011 (1; 7/7) · Foto albumi ← 002005012 (1; 3/3) |
| Modni dodaci (002006) | Dodaci za mobitele ← 002006005 (1; 293/103) · Novčanici ← 002006003 (1; 106/48) · Ruksaci i torbe ← 002006004 (1; 145/42) · Lepeze ← 002006002 (1; 35/26) · Čarape ← 002006006 (1; 18/17) |

Visible totals: Boce 26, Naočale 63, Djeca 27, Lifestyle 119, Modni dodaci 236 = 471.

Decisions taken (low risk, name/data-only, reversible): groups map to ERP level-2 codes, also mapped at group level; kids' eyewear appears only under Djeca (mirrors ERP 002003; no 1:N); duplicate names across groups are disambiguated by name only ("Termo šalice" vs Lifestyle "Šalice", "Dodaci za boce" vs Lifestyle "Dodaci"); Croatian names follow the JekaaStore precedent; "Lifestyle" group name coincides with the Lifestyle segment (`brand_segment`) and may be renamed without any mapping change; WooCommerce default term 15 is to be mapped to a dummy remote value (`UNCATEGORIZED`) as on JekaaStore so it is dropped from mapped products; developer fixtures 20–36, 254–256 and term 800 are not part of it.

Intentionally unmapped for now (8 used ERP nodes, 270 articles, 0 visible): 002 root direct (17), promo nodes 002001003 / 002004 / 002005004, 002001006001, Svijeće 002005005, Kućni ljubimci 002005008, Nakit 002006001. Also unmapped: 001 HARDWARE (775 articles) and 003 DJEČJE IGRAČKE I OPREMA (5,897), all currently not visible. Mapping them is additive data work once they become visible.

Genuine client decisions remaining: (1) confirmation of the group/child names and the kids' eyewear placement; (2) storefront categories for HARDWARE and toys (and Nakit / Svijeće / Kućni ljubimci / promo) when they become visible; (3) who curates "Popularni proizvodi" (not a taxonomy item).

### Initial staging taxonomy — created 2026-10-02 (staging data only)

This is an INITIAL STAGING taxonomy. It was created without prior client approval because it is reversible data: names, slugs, hierarchy, order and `remote_category_id` rows stay editable in WP admin and are expected to change after client feedback. It is not a final production taxonomy.

- Created via WP-CLI (WP/ACF APIs, no importer code, no SQL) on DreamPoint B2B staging: 5 groups + 26 children = 31 `product_cat` terms, IDs 801–831 (801 Boce i posude za hranu, 806 Naočale, 813 Djeca, 819 Lifestyle, 826 Modni dodaci; children in between, in the order of the table above). `order` term meta set per sibling group.
- `remote_category_id` rows: 61 (58 used ERP nodes + group-level codes 002001, 002002, 002003, 002005, 002006 — three of the group codes have no direct articles today) and term 15 `Uncategorized` carries the dummy value `UNCATEGORIZED` (JekaaStore convention: it makes the importer drop the default category from mapped products).
- Slug collisions with the developer fixtures were resolved deterministically by suffixing, e.g. group Lifestyle → `lifestyle-kategorija` (the fixture 254 "Lifestyle" keeps `lifestyle`; two top-level "Lifestyle" entries coexist until the fixtures are removed), Termo boce → `termo-boce-boce-i-posude-za-hranu`, Posude za hranu → `posude-za-hranu-boce-i-posude-za-hranu`, Dodaci (Lifestyle) → `dodaci-lifestyle-kategorija`.
- Developer fixtures 20–36 and 254–256 and staging fixture 800 were left untouched (verified unchanged); they stay for navigation design and are not part of this taxonomy. The copied placeholder navigation lists all top-level terms, so the new groups appear next to the fixtures on staging.
- Product assignment: done afterwards by a one-off, category-only WP-CLI migration (see below), not by the importer.
- DB checkpoint before the change: `/home/dreampoint.b2b/backups/pre-b2b-taxonomy-20261002-124022.sql.gz` (site-user owned, `gzip -t` OK). Do not move or modify.
- Read-only simulation of the unchanged importer's assignment: all 451 published ERP products have a mapped classification; 6,942 (hidden hardware/toys etc.) have none.

### Category-only migration of existing ERP products — executed 2026-10-02 (staging)

A one-off WP-CLI script (not part of the repo, not importer code) assigned the new terms to the existing ERP products, applying the importer's own rule (current terms minus ERP-owned terms, plus mapped terms), with one deliberate difference: products whose classification has NO mapping keep Uncategorized as a safe fallback. Manual terms (e.g. fixture 800) are preserved. Variations received their parent's `product_cat` set, as the importer does. The full importer was not run.

- Dry-run first, then a fresh checkpoint `/home/dreampoint.b2b/backups/pre-cat-migration-20261002-124523.sql.gz` (`gzip -t` OK), then apply guarded by the dry-run plan key (apply aborts if the plan differs). Run log: `/home/dreampoint.b2b/backups/cat-migration-apply-20261002-124523.log`.
- Before → after (ERP parent products 10,186): 3,244 matched products moved from Uncategorized to their mapped child term (1,291 variations updated); 6,942 products with no mapped classification (hidden hardware/toys/promo etc.) keep Uncategorized; Uncategorized published count 451 → 0 (all 451 published products are mapped); products in no `product_cat`: 0; fixture 800 keeps its 3 products. Published (cumulative) counts: Boce i posude za hranu 25, Naočale 53, Djeca 24, Lifestyle 119, Modni dodaci 230 = 451.
- Verified: all 28 child terms hold exactly the expected number of products; posts, postmeta of products/variations and all non-`product_cat` term relationships are byte-for-byte unchanged by checksum; fixtures 20–36, 254–256, 800 unchanged.
- User Switching acceptance (real HTTP): shop catalog `vis_full` 451, `vis_rule_brand` 68, `vis_offer` 5, `vis_rule_cat` 3, `vis_none` 0 (identical to ADR-012); category 800 shows 3 for `vis_full`/`vis_offer`/`vis_rule_cat` and none for `vis_rule_brand`/`vis_none`; admin identity restored. Category archives render (Naočale 53, Boce 25, Lifestyle 119, Modni dodaci 230) without errors.
- Still true: this is an INITIAL STAGING taxonomy, editable after client feedback. A later full importer run will converge to the same assignment (term 15 is mapped to the dummy value), except that on the importer's own rule unmapped products lose Uncategorized unless WooCommerce re-applies the default (as observed on JekaaStore); they are hidden, so this has no visible effect.

### Katalog proizvoda header menu — CLOSED (2026-10-02, staging)

Implemented from the Figma node (11022:51566) and accepted on staging. Final code HEAD: `1b5e642` (documentation commits follow it). Commits: `801ff60` (menu, ACF group "Katalog meni"), `578c11a` (render menu-1 only, deterministic item marking, chevron), `15e535a` (mobile drill-down: `menu-item-has-children`, removed the old mobile `.cat-toggler` hide rule), `28e5b9c` (64px thumbnails in the mobile menu), `1b5e642` (user change: dropped the fixed min-width on the `#main-header` container; only those two compiled rules were removed).

- **Mechanism:** `header.php` renders the primary menu by `theme_location => menu-1` only (the hardcoded `menu => Main Menu` and the temporary `display:none` on the nav were removed). The catalog item is identified by `dreampoint_b2b_mark_catalog_menu_item()` (`inc/nav-categories.php`): first top-level custom-link item with URL `#` in `menu-1`; it gets `cat-toggler`, `menu-item-parent-proizvodi` and `menu-item-has-children` (a manually entered class is still honoured). No manual CSS class is needed.
- **Staging data:** the existing "Products Menu" (term 263) is assigned to `menu-1` (menus are DB content and are not deployed by git; any other environment needs the same assignment and a first top-level custom `#` item). ACF group "Katalog meni" is synced to the DB without rewriting the JSON (import with the full field tree via `acf_get_fields()` and `acf_update_setting('json', false)`; an import through `acf_get_local_fields()` drops repeater sub-fields and overwrites `acf-json`). Configuration: categories 801, 806, 813, 819, 826 (explicit, no fallback) and a temporary "Popularni proizvodi" selection of products 7613, 6180, 5874, 14250 — a staging placeholder to be replaced by the client's choice.
- **Behaviour (assumptions where Figma is silent):** desktop panel opens on hover/focus, rows expand inline (one at a time), Escape dismisses and returns focus, "#" link does not jump; "Prikaži sve kategorije" → Shop unless the admin sets a link; "Popularni proizvodi" are independent of categories, rendered per request (B2B visibility and prices apply) and hidden when the customer may see none; mobile reuses the existing drill-down with the popular cards at the end of the "Katalog proizvoda" level.
- **Staging acceptance:** desktop 1440 and 1920 (panel 531×407, columns 175/36/272), accordion, Escape, 35 links all HTTP 200; User Switching — popular products vis_full 4, vis_offer 2, vis_rule_cat 1, vis_rule_brand 1, vis_none 0 (section hidden), admin 4; mobile 390px — no horizontal overflow, "Katalog proizvoda" first, 5 categories, "Prikaži sve kategorije", 4 popular products (64px thumbnails), category drill-down and back. Focused regression after the header CSS change: header layout, menu and mobile unchanged.
- **Known notes:** long real labels wrap in the 175px column (e.g. "Boce i posude za hranu"); per-customer price differences were not demonstrable because the test customers share the same prices; "Brandovi" has no dropdown (Figma shows one, out of scope); the header logo is not shown on staging (pre-existing). The browser tab used for testing reported `visibilityState: hidden`, so CSS transitions were disabled in the test page only.

---

## ADR-014 — Product search: native WordPress/WooCommerce search + small identifier extension; header search UI; Select2 on variable PDPs

**Date:** 2026-10-05
**Status:** Accepted and CLOSED on staging. Code commit `b5ce399cd183c648dd273492709f4ceaa89c1f7e` (local = `origin/master` = DreamPoint staging, working tree clean). Production deployment is NOT APPLICABLE (no production environment is provisioned for this project). The final DreamPoint Figma design for search is still expected; the current presentation is an accepted staging implementation that may receive later visual (CSS) refinement.

### Context

Header search had to be brought in line with the intended UX (default/focus panel, compact live results) and with the product data model (ERP identifiers). A requirements audit of the canonical documentation and a read-only data-model inspection were done first.

**Requirements audit** (what the project documentation actually requires):

| Topic | Status | Source |
|---|---|---|
| Product name/title search | implied, implemented (native) | — |
| SKU / catalog number ("SKU-first for B2B") | recommended direction of an INTERNAL decision (INT-01), not client-confirmed | `docs/project-status-matrix.md` INT-01, `docs/stakeholder-question-matrix.md` INT-01, `Dreampoint-B2B-Workshop-Pack.docx` |
| Autocomplete shows SKU + name | same recommendation (INT-01) | same |
| EAN / barcode | data confirmed (`barcode` → `global_unique_id`, AP-11); barcode search documented as a possibility, not a requirement | `docs/apros-question-resolution-matrix.md` AP-11, `docs/apros-session-final-pack.md` |
| Typo tolerance / "did you mean" | only a question in INT-01; no decision, not confirmed | INT-01 |
| Popular / recent searches | implied by the approved (temporary) default-state design; "Recent searches?" was also an INT-01 question | design + INT-01 |
| B2B visibility / access | confirmed, mandatory | `inc/visibility/`, CLAUDE.md |
| Client acceptance criteria for search | none exist | — |

**Product data model** (verified read-only on staging: 10,192 products, 1,291 variations): parent `_sku` = `P-<articleId>` (internal form), `_ARTICLE_CODE` = ERP catalog number ("Kataloški broj"), `_global_unique_id` = EAN (on simple products; ERP variable parents carry none); variations carry their own `_sku` (= variationId), `_ARTICLE_CODE` and `_global_unique_id`.

### Corrections to earlier documentation (Relevanssi)

Several documents stated that Relevanssi is installed/active ("Relevanssi je aktivan", the CLAUDE.md stack list, the dev-context plugin map). **This was wrong.** Verified 2026-10-05: Relevanssi is NOT installed locally and NOT installed on DreamPoint staging (no plugin directory, no `relevanssi_*` options, no `relevanssi_*` functions). There is no executable Relevanssi dependency in the DreamPoint PHP/JS (the old `'relevanssi' => true` query argument and the `did_you_mean()` wrapper were dead code and were removed). The only remaining mentions are historical/workshop text and an inert `relevanssi_exclude` key in some `acf-json` field settings (written by ACF while the plugin existed on some dev machine; harmless without the plugin).

Historical documents that propose Relevanssi (INT-01 rows, workshop `.docx` artifacts) are preserved as history. Their current-state conclusion is **superseded by this ADR**: Relevanssi is not part of the active stack, and the INT-01 recommendation "Relevanssi for name search" was not implemented. The `.docx` workshop files were intentionally not edited.

### Decision

Keep search on native WordPress/WooCommerce and add one small, opt-in extension. No dedicated search plugin, index, table or ranking layer is introduced. If typo tolerance or fuzzy matching ever becomes a confirmed requirement, evaluate an existing search plugin then; do not build a custom search engine.

**Identifier search** (`inc/product-search.php`):
- Opt-in through the query var `dp_search_extended` (set by the AJAX handler and, for the frontend main query, by `pre_get_posts` when `is_search()` and `post_type === 'product'`). Admin, REST/Store API and Quick Order queries are not affected.
- A `posts_search` filter widens the native clause with `OR ID IN (matching ids)`; the WordPress `post_password` clause stays outside the `OR`.
- Searchable values: native title/content (unchanged), `_sku`, `_ARTICLE_CODE` (catalog number), `_global_unique_id` (EAN). A match on a **published variation** returns the **parent** product. Owners of the matched meta must be published (`publish`); this is intended.
- `MIN_CHARS = 2` (JS threshold, server threshold and extension threshold are identical).
- The identifier lookup collects at most **200** matching ids; `ORDER BY pid ASC` makes that set deterministic (the lowest 200 ids). This is not relevance ranking. For unusually broad identifier terms (e.g. `P-`, which matches ~every parent SKU) the cap can omit matches. **Accepted limitation, not a blocker.**
- The term used is the WordPress-parsed `s` (no extra `stripslashes`).
- There is **no SKU-first ranking**. Final result ordering remains native WordPress/WooCommerce behavior. "SKU-first" remains an unconfirmed internal recommendation.
- Typo tolerance, fuzzy matching and "did you mean" are **not implemented** and are not part of the architecture.

**AJAX handler** (`inc/ajax-handlers.php`): nonce-protected `search_products`; 5 compact rows (image, title, catalog number, price/stock) + footer ("Odustani", "Vidi sve rezultate"); no-results block. See the security correction below.

### UI sources of truth (split)

- **Default / focus state before typing:** temporary DreamPoint screenshot/Figma-derived design: "Popularne pretrage" (configurable chips), "Nedavno pretraženo" (up to 4 client-side recent searches, history icon + individual ×), empty-state hint "Počnite kucati za prikaz rezultata".
- **Live AJAX result state after typing:** the current DreamPoint Figma does not define it. **Cotra production search UI is the temporary presentation/interaction reference.** Cotra is UI/UX reference ONLY: no Cotra search, indexing, backend, ranking, normalization or SKU/EAN engine (`ud_ls_*`, `ud2_dym_suggest`, …) was ported. Cotra's own `ajax-search.js` contains the iOS DOM-removal bug and its behavior was deliberately not copied.
- The final DreamPoint Figma design is expected later; visual changes should be primarily CSS (default panel markup is in `header.php` + a `<template>`; styles in `sass/components/_header.scss`).

**Popular searches:** ACF group `group_dp_search_panel` (JSON in `acf-json/`, DB post created through ACF's native import), field `search_popular_terms` (repeater, sub-field `term`, max 6), stored on the existing `theme-settings` options page. No terms are hardcoded in PHP. Initial staging values: `IZIPIZI`, `Notabag`, `Termos boca`. Deployment: the group was synced **by key only** (`wp acf json sync --key=group_dp_search_panel`, after a dry-run for that exact key showed only this group); the **21 other pending ACF groups on staging were not touched** — never run an unscoped `wp acf json sync` there. Values were written with `update_field()` and read back exactly (3 rows). ACF data/options are DB content: any other environment needs the same group sync and values.

**Recent searches:** `localStorage` key `dpRecentSearches`; max 4; newest first; case-insensitive de-duplication; individual removal (×, a separate `<button>`, never inside the link); all storage access is wrapped so unavailable/blocked storage degrades to "no history" without breaking search. No server-side or user-identifying storage is introduced.

**iOS/WebKit-safe interaction rules** (keep when changing the UI): no click/tap handler may remove or empty result/default DOM that contains the link or form being activated; closing (Escape, outside tap) only sets `hidden`; panels are re-rendered only after the "×" `<button>` is activated; the document-level "outside click" handler ignores targets that were detached from the DOM.

### Security / B2B visibility correction (important)

`admin-ajax.php` runs `WP_Query` with `is_admin = true`, and without an explicit status WordPress then adds protected statuses (`draft`, `pending`, `future`) for every user. The legacy handler therefore could expose non-published products to ordinary B2B users. **The AJAX product search now explicitly sets `'post_status' => 'publish'`.** Keep it.

- Reproduced locally before the fix (an ordinary B2B user saw draft/pending/future products and a draft variable parent through a published variation's SKU); fixed and re-verified.
- Staging proof (staging has 9,735 draft products): known draft #23350 returned **0** by title, catalog number, SKU and EAN; published variations belonging to draft parents returned **0** (catalog number, EAN, SKU); published control products remained searchable. Tested as admin and `vis_full`.
- Existing B2B visibility filtering (`pre_get_posts` + `posts_clauses`) is unchanged and remains applied to title search, SKU, catalog number, EAN, variation identifiers, AJAX results and the normal search-results page. Staging matrix: `vis_full` finds everything tested; `vis_rule_cat` finds only products of its category rule (positive control found, other product not); `vis_none` finds nothing. No visibility leakage in the tested access contexts.

### Search result cache removed

The old shared `dp_search_*` rendered-HTML transient cache (and its clearing hooks) was **removed**: search results depend on the user's B2B visibility/access context, and the old cache key was only the search term, so one user's HTML could be served to another. **Do not reintroduce a shared search-result HTML cache keyed only by the search term.** Staging check: 0 legacy `dp_search_*` transient rows; staging has no external object cache (no `object-cache.php`), so nothing needed cleanup and no cache was flushed.

### Select2 on variable PDPs

- Select2 now loads on single product pages (`dreampoint_b2b_needs_select2()` includes `is_product()`), so variable-product attribute selects and the mobile tabs dropdown are Select2-enhanced. Coverage was aligned with the relevant Suplementi (staging) behavior; features DreamPoint does not have (e.g. blog post sorting) were not copied.
- On `woocommerce_update_variation_values` the variation selects are defensively re-synced (`destroy` + re-init), as in Suplementi.
- **The original Suplementi stale-Select2 bug was NOT reproduced on DreamPoint** (the A→B→A scenario passed locally even with the handler removed). The resync is retained defensively and for parity with the proven Suplementi solution.
- Directly enqueued JS (`ajax-search.js`, `select2-init.js`, `product-single.js`, `variation-stock.js`, `tabs-dropdown.js`) is versioned per file by mtime through `dreampoint_b2b_asset_ver()`; `_S_VERSION` still tracks `style.css` / `theme.min.js`. Staging `?ver=` values matched the server mtimes.

### Acceptance (2026-10-05)

- **Local:** focused backend suite (names, SKU, catalog number, EAN, partial identifiers, variations, `vis_full`/`vis_rule_cat`/`vis_none`, results page, nonce 403) and UI suite 196/196 across WebKit (iPhone 13, iPad landscape, desktop) and Chromium/Edge (mobile emulation, desktop, blocked localStorage).
- **Staging, Chromium:** default panel, chips, recent searches, transitions, results, "Vidi sve rezultate", no-results, Escape/outside close, blocked storage, mobile overlay with admin bar, identifier searches, N1 proof, visibility matrix via User Switching, Select2 (three variable products, A→B→A, reset), shop sort and account selects. The controlled Chrome window was occluded (`visibilityState: hidden`), so interactions used dispatched DOM events and geometric hit-target checks instead of real pointer events.
- **Staging, WebKit with real Playwright touchscreen taps** (temporary storage state, deleted afterwards): iPhone 13 **41/41** search + **6/6** PDP Select2; iPad Pro 11 portrait **46/46**; iPad Pro 11 landscape **40/40**. Popular chip, recent item, "×" (removes only that item, no navigation), product result and "Vidi sve rezultate" all navigate/submit correctly; a document-level probe confirmed that after every such tap the target was still connected and the default action was not prevented. **No equivalent of the known Suplementi iOS AJAX-search DOM-removal bug was found.**
- **Physical iPhone/iPad hardware was NOT tested** — this was Playwright WebKit emulation.

### Performance (staging measurements, not guarantees)

- Server-side identifier metadata lookup: ~50–56 ms median (scans ~33k identifier meta rows).
- Browser AJAX, representative queries: ~196–232 ms median (about 160 ms of that is the baseline PHP/network cost of a rejected admin-ajax request); p90 ≤ 245 ms in the measured run (12 samples per query, admin and `vis_full`). One isolated 486 ms outlier.
- No need for Relevanssi, custom indexing, custom tables or additional search caching was demonstrated.

### Known / out-of-scope items (not blockers)

1. **Mobile tabs dropdown:** the Select2 wrapper exists, but the product tabs section is hidden by existing template behavior (`woocommerce/content-single-product.php`: `.wc-tabs-section` has `display: none`) on all 12 sampled staging PDPs, so it could not be interactively tested. Pre-existing, not a regression.
2. **Variation image switching** was not meaningfully verified: the tested product variations share the same image.
3. **A single typed backslash** is lost upstream (core/site layer) before the extension sees the term; not addressed.
4. **Broad identifier searches:** the 200-id cap (above) is accepted.
5. **Final DreamPoint Figma design** for search is pending; visual refinements may follow.
6. Pre-existing and unrelated: a third-party TI Wishlist script throws when `localStorage` is blocked; staging has 21 unsynced ACF groups; the CLAUDE.md stack list names "Redis Object Cache" but staging has no external object cache (not changed here).

### Consequences

- Search stays small and dependency-free; any future typo tolerance or ranking needs a new, separate decision.
- Any change to the AJAX handler must keep `post_status => publish`, keep the visibility filters on the query, and stay cache-free for result HTML.
- Staging is the only deployment target; ACF group sync and options values are an environment step (git does not carry DB content).

### Addendum 2026-10-05 — Quick Order reuses the ADR-014 identifier semantics (narrow opt-in)

Quick Order (plugin `dp-b2b-quick-order`) search opts into the same extension; the ADR-014 decision itself is unchanged.

- QO sets the `dp_search_extended` query var on its own WP_Query, so `posts_search` matches title/content + `_sku` + `_ARTICLE_CODE` + `_global_unique_id`, variation hit returns the parent, `MIN_CHARS` 2 (QO mirrors it as `searchMinChars`), 200-ID cap. No QO-specific search backend, index, ranking or typo tolerance. URL param: `qo_search`.
- Catalog number (`_ARTICLE_CODE`) is exposed in the QO REST contract as `catalog_number` (parent, and per variation — future ERP per-variation catalog-number model; keep it visible below the variation option).
- Stock is binary only (in stock / out of stock) in the QO REST contract and UI — numeric stock quantities are never sent to the client.
- "Popularne pretrage" reuse `search_popular_terms` through the `dp_qo_popular_searches` filter (helper in `inc/product-search.php`); QO is visibility-filtered like every other query (`pre_get_posts` + `posts_clauses`).
- Search results are never cached by term (consistent with "Search result cache removed").

### Related

- Code: commit `b5ce399` — `inc/product-search.php`, `inc/ajax-handlers.php`, `header.php`, `js/ajax-search.js`, `js/select2-init.js`, `functions.php`, `sass/components/_header.scss`, `sass/components/_content.scss`, `acf-json/group_dp_search_panel.json`.
- Superseded statements (kept as history, annotated): INT-01 rows in `docs/project-status-matrix.md`, `docs/stakeholder-question-matrix.md`, `docs/client-workshop-questions.md`.
- ADR-003 (WBW filter search compatibility — separate topic), ADR-012 (visibility/test users).

---

## ADR-015 — Quick Order filter sidebar: native-first filtering and a visibility-safe term vocabulary

**Status:** accepted and closed on staging 2026-10-06 (plugin `dp-b2b-quick-order` v1.0.27). **Scope:** Quick Order desktop filter sidebar (Slice 4). Production does not exist for this project.

### Governing rule

> Quick Order filtering is native-first. WooCommerce global attributes remain native `pa_*` taxonomies and use the existing WBW/WooCommerce filtering pipeline. Custom Quick Order code exists only where necessary to enforce B2B visibility/security or integrate the native filter UI.

Model, Boja and Dob are native WBW `wpfAttribute` blocks over `pa_model`, `pa_boja`, `pa_dob`; Brand is the native `wpfBrand` block over `product_brand`; Dostupnost is the native `wpfInStock` block. Filtering, URL state (`wpf_filter_<attr>`, `product_brand_list`, `pr_stock`), selected-parameter chips and reset are WBW / QO Slice 2 behavior, unchanged. The design (PNG/Figma) guided hierarchy and spacing only; no custom attribute filtering, counts, show-more, search-in-filter or accordion was built to match it.

### Finding that forced custom code: the global vocabulary is not B2B-safe

WBW builds option lists with `get_terms()`; `hide_empty` and term counts are global (not user-visibility-aware). Measured on staging before the fix:

- `vis_rule_cat` (3 visible products) received **all 61** `product_brand` terms in the rendered Brand block (the theme's brand `get_terms` filter does nothing for rule-based users without brand rules); `vis_full` received 61 although only 13 brands have a visible published product.
- There is no visibility filter at all for `pa_*`: raw `pa_boja` (365 terms), `pa_model` (77), `pa_dob` (13) would have exposed every term to every restricted user. `hide_empty=true` still returned e.g. 276 `pa_boja` terms to a user who can see none.
- WBW view 3 runs with "remove actions" on: its AJAX handler calls `remove_all_filters( 'pre_get_posts' )`, which removes the visibility engine from the queries behind its result count, product HTML and `filter_state.exists`. A restricted user filtering by a permitted brand received **26 products** (3 are visible to them). QO discards that HTML, but it was delivered to the browser.

### Visibility-safe term invariant (enforced by `DP_Quick_Order_Term_Scope`, `inc/class-term-scope.php`)

Inside the Quick Order scope a `product_brand` / `pa_*` term is returned by `get_terms()` only if at least one **published product the current user may see** carries it.

- **Permitted universe:** `WP_Query` (published `product`, `suppress_filters=false`, same base args as the QO product query). The canonical engine (`pre_get_posts` + `posts_clauses`) decides; no rule is re-implemented. Terms come from the permitted PARENTS' term relationships in chunked SQL (no per-term queries); ancestors of hierarchical brands are added.
- **Data model:** WooCommerce attaches `pa_*` terms to the parent (simple and variable); `product_variation` posts carry no term relationships, only `attribute_pa_*` meta (verified on staging: 0 variation posts with `pa_*` relationships, 0 variation-meta terms missing from the parent). An inaccessible parent therefore cannot contribute vocabulary through its variations.
- **Scope:** active only while the QO template renders (`enter()`/`leave()` in `DP_Quick_Order_Frontend`) and for the WBW AJAX action `woobewoo_pf_filters_frontend` when the request's `currenturl` path is the Quick Order page. Inert for wp-admin, archives, REST, cron, CLI and other WBW views. `get_terms` result shapes without a term id (`fields=names|slugs|tt_ids`) fail closed to empty. Admins are also restricted to terms of published products inside QO scope (they bypass visibility for products, not for this vocabulary).
- **AJAX:** the permitted universe is primed on `wp_loaded` (before WBW removes the engine) and the engine's own `pre_get_posts` callback (`Dreampoint_B2B_Query_Filter::filter_product_query`) is re-attached through WBW's `wpf_checkBeforeFiltersFrontendArgs` / `wpf_beforeFilterExistsTerms` filters. If the engine class is unavailable the WBW queries are pinned to `post__in=[0]`. A first attempt that pinned `post__in` to parent ids broke WBW's variation-aware result set and was replaced (commit `0a48a01`).
- **Cache:** none shared; memoized per request and per user id. **Cost on staging (451 published products):** all four taxonomies resolved in 0-4 ms; re-measure on a production-size catalog.

### Counts policy

Term/filter counts stay **OFF** (`f_show_count=false`; `f_hide_empty=false`, because hide_empty is not a visibility mechanism). Do not expose `term->count` and do not build a global `get_terms()` count. The mockup's "(34)" is not reproduced.

### Accepted WBW view 3 configuration (`wp_wpf_filters.id=3`, "Quick order filter")

| # | Block | Source | Type / settings |
|---|-------|--------|-----------------|
| 1 | `wpfBrand` "Brendovi" | `product_brand` | `multi`, WBW's native "Search brands" input, list scroll `f_max_height=200`, counts off |
| 2 | `wpfAttribute` "Model" | `f_list=1` (`pa_model`) | `list`, `f_query_logic=or`, native search input on (`f_show_search_input`), scroll 200px, counts off |
| 3 | `wpfAttribute` "Boja" | `f_list=5` (`pa_boja`) | same as Model (276 terms: native scroll + native search) |
| 4 | `wpfAttribute` "Dob" | `f_list=3` (`pa_dob`) | same, search off (9 terms) |
| 5 | `wpfInStock` "Dostupnost" | `pr_stock` | `f_options[]=instock,outofstock`, `f_status_names=on`, labels "Na stanju" / "Nema na stanju" ("Po narudžbi" unused) |

`f_description` is empty on every block (it duplicated the title). The stale unbound "Pakovanje" block was removed (`pa_pakovanje` does not exist). No view-level setting changed (128 top-level settings identical). A term-less block (e.g. a restricted user without any permitted `pa_boja` term) is simply not rendered.

- **BEFORE** (4 blocks: Dostupnost, Brendovi, Boja `f_list=null`, Pakovanje `f_list=null`): `setting_data` length 25624, sha256 `639b8a0cacad1d21ff93351d030a4c3c676f543567ee76e1b2bf5bac783f37d4`. Verbatim backup on the staging server: `/root/wpf_view3_setting_data.BEFORE.202610061050.bak` (the PHP-serialized `setting_data` column value). Restore: `UPDATE wp_wpf_filters SET setting_data = <file contents> WHERE id = 3`, then flush the object cache.
- **AFTER** sha256 `066bb51457ee53723b3282faa1b116ac13139d0c2f1e00226df73fd570658378`. Applied programmatically (guarded by the BEFORE sha) in three small steps (blocks, native search on Model/Boja, empty descriptions); intermediate shas `32f16cef...` and `cb8a2ccd...`.

### Single stock dimension

Dostupnost (`wpfInStock`, `pr_stock`) is the **only** stock filter. The mockup's "Na stanju" under Popularno is not a second filter; QO-owned Popularno stays Već naručeno / Novo / Best seller. Numeric stock stays out of the client contract.

### Long lists: native behavior accepted

WBW's scroll box (200px) plus its native client-side search input is sufficient for `pa_boja` (276 terms for a full-access user). The search filters only DOM terms that already passed the visibility-safe vocabulary. No custom "Prikaži više", custom search or accordion was added; WBW's own +/- block collapse is retained. The search placeholder is WBW's English "Search ..." (a WBW translation, not a QO string).

### Integration fix (the only custom JS)

WBW 3.4.5 `getFilterParam()` does `JSON.parse()` on a `filters.order` that its own settings parser had already turned into an array, so the change handler of every checkbox-list attribute block threw. Selecting a term still filtered, but WBW's selected-parameter x, which re-triggers `change` through jQuery, aborted: attribute chips could not clear their filter (Brand/Stock chips worked). `assets/src/wbw-compat.js` replaces that single lookup on the `window.wpfFrontendPage` instance with an equivalent accepting both shapes (commit `53bfd9d`). WBW source is untouched; remove the shim if a WBW update fixes the lookup.

### Acceptance (real staging browser, User Switching, 2026-10-06)

- **Vocabulary per user** (DOM and raw HTML; no non-permitted term name in the page source outside the editorial "Popularne pretrage" chips): `vis_full` Brand 13 / Model 55 / Boja 276 / Dob 9; `vis_rule_cat` 3 / 2 / none / none; `vis_rule_brand` 1 / none / none / none; `vis_offer` 5 / 3 / 12 / 1; `vis_none` access denied, no filter markup. Server-side simulation of the same counts (0 leaks in 20 user x taxonomy checks) was supplementary only.
- **AJAX:** after the fix no WBW response carried more products than the user can see and `filter_state.exists` ids were always a subset of the rendered terms. WBW's own (discarded) product/exists result is under-inclusive or empty for some filters (variable products under the engine's EXISTS clause; also empty for admin). QO never uses it.
- **Injected URL values** (`product_brand_list=izipizi,nuuna`; `wpf_filter_boja=abyss&wpf_filter_model=clima&wpf_filter_dob=adult`, as `vis_rule_cat`): no chip, no checked input, no term name in the DOM (only the user's own URL echo), 0 products; indistinguishable from a non-existent slug.
- **Native combinations** (as `vis_full`): Boja; Model + Dob; Brand + Model; Boja + Dostupnost; several Boja values (OR); search + Brand; search + Model + Dob (+ Boja); Popularno + attribute. Each produces the native selected parameter / QO chip, each chip x clears exactly its filter (Brand, Model, Boja, Dob, Dostupnost), "Poništi filtere" clears all (the search term is kept by design), and local quantities/footer counts are unchanged throughout. Zero console errors.
- Slice 1-3 smoke (catalog-number search, no-results state, popular chips, columns, binary stock, footer, native cart link) unchanged. At 390px: no page-level overflow.

### Known / out of scope (unchanged backlog)

- WBW drops `orderby` when a WBW filter is applied; Excel Import; row-level cart error UX; mobile QO design.
- Mobile: the sidebar is now about 1,350px tall on a narrow screen, so the search field sits even lower than before (known limitation, no mobile design exists).
- Outside Quick Order the theme's brand `get_terms` filter still returns all brands to rule-based users without brand rules (e.g. shop brand widgets). Not touched here; candidate follow-up.
- The ACF "Popularne pretrage" chips are editorial and global (they may name brands a restricted user cannot browse).
- `pa_dob` has 4 empty duplicate terms (legacy); not cleaned.
- A rule-based user with both brand and category rules gets a brand list limited to the brand rules by the theme filter (under-inclusive; not testable with the current demo users).

### Related

- Commits: `d994580` (term vocabulary), `b4939da` then `0a48a01` (WBW AJAX visibility), `53bfd9d` (chip compat), `50e19a8` (CSS). Plugin `readme.md` ("Native-first filter sidebar"); `docs/frozen/quick-order-local-state-architecture.md` Addendum 2026-10-06; ADR-014 (search), ADR-003 (WBW search compat).


---

## ADR-016 — Quick Order cart submit: normalized, non-sensitive `/cart/sync` failure contract and row-level error UX (Slice 5)

**Status:** accepted on staging 2026-10-06 (plugin v1.0.28 + v1.0.29). The local-state model, the additive/chunked CartSync and ADR-014/015 are unchanged; this record only adds to them.

### Context

Discovery (2026-10-06) found: failures reached the client as an unstructured mix (`action:'out_of_stock'`, `action:'failed'` + `error:'invalid_product'|'access_denied'|'add_failed'`, `skipped`); the UI only showed a blocking `window.alert` ("provjerite stanje na skladištu") for every kind of failure including network errors; `timeoutMs` existed in config but was never enforced. The server had three correctness/oracle gaps: (1) `product_id` was access-checked but a variation's stock was read before verifying that the variation belongs to that parent (stock-status oracle for a hidden product's variation); (2) `invalid_product` vs `access_denied` let a client tell a hidden product from a nonexistent one; (3) an already-existing cart line could still grow after its product went out of stock (no `is_in_stock()` check on that path). A submitted item with `product_id=0` was silently dropped.

### Decision

**Per-item contract** (`inc/class-cart-sync.php`) — one result per submitted item, never dropped:
`{product_id, variation_id, action:'added'|'updated'|'removed'|'skipped'}` or `{product_id, variation_id, action:'failed', error:<code>}` with `error` one of:

- `out_of_stock` — the unit itself is not in stock;
- `quantity_unavailable` — in stock, but the resulting quantity cannot be fulfilled;
- `product_unavailable` — nonexistent / unpublished / not purchasable / inaccessible / variation not belonging to the submitted parent / invalid variation / `product_id=0` / malformed item. **One** code on purpose;
- `not_addable` — WooCommerce rejected `add_to_cart` for a reason that is not classified further.

There is no `invalid_variation` code (WooCommerce does not distinguish it reliably at this boundary). `request_failed` exists only client-side.

**Stock privacy invariant:** no numeric stock, "allowed/remaining quantity", `stock_quantity`, `managed` flag or `quantity_allowed` is ever part of any Quick Order client contract. Catalog stock stays binary. The residual, inherent oracle (a user who may add can probe quantities by trying) is unchanged and cannot be removed without removing server validation.

**Oracle protection / validation order:** `resolve_unit()` verifies existence, that a variation is a `WC_Product_Variation` whose `get_parent_id()` equals the submitted `product_id` (and that a variation id is not submitted as a parent id), and `is_purchasable()` — all before any stock is read. The visibility gate (`dp_b2b_product_accessible`, applied to the verified parent) maps to the same `product_unavailable`. Existing-line path: still no access re-check (intentional, "no retroactive revalidation"), but it now respects current stock: `!is_in_stock()` → `out_of_stock`; WooCommerce's `has_enough_stock(<resulting total>)` → `quantity_unavailable`. `has_enough_stock()` replaces the earlier hand-rolled `get_manage_stock()` + `get_stock_quantity()` comparison, so it now also honours backorders and parent-managed variations like WooCommerce itself (no backorder product exists on staging today). `sync()` restores the WooCommerce notice queue to its pre-request state so `add_to_cart()` rejection notices do not resurface on `/cart/`.

**Confirmed failure vs. ambiguous request failure (client, `cart-submit.js`):** a chunk with no usable response — fetch error, any non-2xx (403 nonce, 500, 400), unparsable/malformed body, or the existing `CART_SYNC_TIMEOUT_MS` (10 s, now enforced with `AbortController`) — is **ambiguous**: because the sync is additive, the server may have applied it. Its rows are not marked failed; they keep their quantity and get no row error; one global message is shown ("Nismo mogli potvrditi je li dodano. Provjerite košaricu prije ponovnog pokušaja."). No automatic retry. Rows with no result inside an otherwise good response are treated as ambiguous too.

**Row error state:** `QuickOrderState` has a separate `rowErrors` Map (not part of the quantity row object), in memory only. Confirmed-failed rows keep their quantity and get their error; successful rows are cleared together with their error; the error is dropped when that row's quantity changes (including to 0), replaced by a newer confirmed failure, or cleared by a successful retry; ambiguous rows have any old error cleared. Search, filter, pagination and reset do **not** clear errors (re-applied by `RowController.hydrateAll()` when the row renders again). Never persisted.

**Presentation / accessibility:** `.dp-qo-line__error` inside the row's own `.dp-qo-line` grid (`grid-column: 1 / -1`, text + "!" icon, red input border via `:has()`); `aria-invalid="true"` + `aria-describedby="dp-qo-err-<rowKey>"` on the quantity input, removed together with the node. One `role="status"` polite region (`.dp-qo-footer__status`) replaces `window.alert`: "Dodano: N. Nije dodano: M — pogledajte označene retke." / "Dodano: N." / "Nije dodano: M — …" (counts are Quick Order rows). A confirmed-result summary is cleared when a quantity is edited; the ambiguous warning stays until the next submit. No `role="alert"` per row, no focus movement, keyboard stepper unchanged.

**Croatian row messages:** `out_of_stock` "Trenutno nije na stanju." · `quantity_unavailable` "Količina nije dostupna." · `product_unavailable` "Proizvod trenutno nije dostupan." · `not_addable` "Proizvod nije moguće dodati u košaricu."

### Explicitly not added

No idempotency token, no preflight or per-row requests, no polling, no stock reservation (ADR-007 stays a business decision), no change to chunking (sequential, 50), no change to the B2B visibility engine.

### Residual risks / known limitations

- **Duplicate-add ambiguity after a lost response remains:** if the server applied a chunk but the response never arrived, the quantities stay in the UI and a manual retry is additive (the quantity would be added twice). Mitigated only by the wording of the ambiguous message and the native "Pregled košarice" link; a real fix needs request idempotency (out of scope).
- The 10 s timeout is the pre-existing configured value; a 50-row chunk took about 1 s on staging, so there is large headroom, but a slow server could turn a slow success into an ambiguous result.
- Failures WooCommerce raises inside `add_to_cart()` after our pre-checks (e.g. cumulative stock across sibling variations, held stock) surface as `not_addable`, not `quantity_unavailable`.
- On a narrow screen the product table already scrolls horizontally (no approved mobile design); the row error sits directly under the quantity stepper and is visible whenever the stepper is, but not at scroll position 0.

### Acceptance (staging, `dreampoint.b2b.uncledev.cloud`, 2026-10-06)

Backend contract exercised through `/cart/sync` as admin and as `vis_rule_cat`: valid simple/variation, simple and variation OOS, excessive quantity (fresh line and existing line), existing line whose product became OOS (product 18896 stock temporarily set to 0, restored to 18), nonexistent, draft, hidden, variation of another parent, invalid variation, variable parent without variation (`not_addable`), `product_id=0`/malformed items, mixed batch, 51 rows (chunks 50 + 1, a failure in each chunk). Hidden-existing, nonexistent, hidden+OOS, hidden variation, invalid variation, unpublished draft, "visible parent + hidden product's variation" and "visible parent + hidden OOS variation" all returned the identical `failed/product_unavailable`. Browser (real UI, User Switching `vis_full` / `vis_rule_cat`): all-success, partial, row error + status region + ARIA wiring, retry (successful rows are not re-sent, cart unchanged), search hide/return keeps the error, variation-row error, ambiguous scenarios (route abort, HTTP 500, 403, malformed 200, a 13 s hold → client abort at 10 s) with no automatic retry, 390 px smoke, Slice 1–4 smoke. Commits: `5c07c53` (contract + UX, v1.0.28), `0aee6f1` (stale-summary clearing, v1.0.29).


---

## ADR-017 — Quick Order Excel/CSV import: browser parsing, authoritative read-only validation, auto-clamp, direct cart add

**Status:** Gate 1 (foundation) and Gate 2 (modal) PASSED on staging 2026-10-06 (plugin v1.0.33). **Pre-production manual acceptance PASS 2026-10-07 — production deployment not currently applicable (production environment not yet provisioned).** **Not deployed to production (none exists); production closure deferred until production is provisioned** — see "Pre-production manual acceptance". Detail: plugin `readme.md` (Excel/CSV import sections).

### Context

Client-approved designs QO-05/06/07: a modal "File upload → Validacija → Rezultat → Dodavanje u košaricu" opened by an "Excel Import" button next to the Quick Order search. Explicit client rule: a requested quantity above the additional orderable quantity is automatically reduced when the reduced quantity is > 0. Discovery, a real-staging identifier/stock audit (1,535 orderable units: every unit has a unique `_sku` and `_ARTICLE_CODE`, no cross-namespace collisions, all managed stock, no backorders/sold-individually/parent-managed pools) and two staging acceptance passes preceded this record.

### Decision (final — do not reopen)

- **Formats / parsing:** `.xlsx` and `.csv` only, parsed LOCALLY in the browser (`fflate` 0.8.3 + DOMParser for xlsx; dependency-free quote-aware CSV). Static committed templates (`assets/templates/`, identifier column formatted as Text). Limits: 2 MB, 500 rows, ZIP central directory checked before any inflate, no formulas executed, no macros, DOCTYPE/entities rejected. The server never receives the file; only `{row, identifier, quantity}`.
- **Validation:** one READ-ONLY endpoint `POST /dreampoint-b2b/v1/quick-order/import/validate` (`no-store`). Order is security-critical: syntax → ONE batch lookup of `_ARTICLE_CODE` AND `_sku` (published unit + published parent) → B2B authorization via `dp_b2b_product_accessible` on the parent → ONLY then ambiguity / variable parent / purchasability → duplicate merge → stock clamp → name. Hidden, nonexistent, unpublished and orphan identifiers are byte-identical (`identifier_not_found`). Ambiguity fails closed; no namespace precedence. EAN is not an identifier.
- **Identifier normalization (one contract, input AND stored value):** strip leading/trailing TAB, LF, VT, FF, CR, space, NBSP, BOM only (SQL `REGEXP_REPLACE` on the stored side; PHP/JS use the same class). No fuzzy matching, no zero-padding, no numeric coercion, internal whitespace untouched.
- **Duplicates:** merged per RESOLVED unit (first-occurrence order, also across `_sku`/`_ARTICLE_CODE` aliases); summed quantity is capped at 99,999.
- **Clamp:** `final = min(requested, additional orderable)` using WooCommerce semantics: managed stock − quantity already in the current cart − quantity granted to earlier import rows, keyed by `get_stock_managed_by_id()`; unmanaged / backorders / sold-individually follow WooCommerce. `0 < final < requested` → `adjusted` (still eligible); `final = 0` → `unavailable`. The final quantity is the only stock-derived number that reaches the client (intentional, inherent in the client rule); no raw stock, managed flag, pool id, price or brand.
- **Validation is NOT a reservation** (no hold, lock or timer). Final `/cart/sync` stays authoritative and validates again; the normalized quantity is attempted exactly once; no hidden second clamp.
- **Modal (Gate 2):** private transient state only (nothing in the visible `QuickOrderState`, storage or DB; reopening = fresh session). Result statuses Spremno / Prilagođeno / Greška; partial import supported; summary counts resulting units and states the spreadsheet row count. Cart step reuses `CartSubmit` (additive, **max 50 per chunk**) with real chunk progress; an ambiguous chunk stops the import at that boundary (no retry, later chunks not sent, counts added / unknown / not sent, only "Pregled košarice"). Existing unsent QO selections are preserved; an overlapping imported unit yields an informational notice, never silent clearing. No close/Escape while the cart request is in flight. Croatian copy is centralized in PHP (`import_copy()`).
- **Shared `CartSubmit` fix:** `wp_localize_script()` delivers top-level scalars as strings, so `cartSyncMaxBatch` was `"50"` and `i += "50"` concatenated, producing oversized chunks (e.g. 120 → 50 + 70; the server rejects > 50) for any submit above 100 items — in Excel Import AND the normal Quick Order submit. Fixed with `Number()`. Staging proof (served code, network layer): 49→49, 50→50, 51→50+1, 100→50+50, 101→50+50+1, 120→50+50+20, 500→10×50; real >100 cart submissions passed for both paths (118 units via Excel Import, 101 via main QO), carts restored.
- **Modal keyboard fix (9282361):** key handling lives on `document` (capture) while the modal is open; focus on `<body>` after a click on non-focusable modal text/backdrop previously broke Escape and the focus trap.

### Data-quality compatibility (not a data fix)

8 published variation `_ARTICLE_CODE` values in the ERP-derived staging data end with a trailing LF. Import defensively applies the symmetric boundary normalization above; a normalized collision audit (duplicates, `_sku`↔`_ARTICLE_CODE`, parent vs unit) was clean. The stored data was NOT mutated and no ERP/importer cleanup task was opened.

### Acceptance (staging `dreampoint.b2b.uncledev.cloud`, 2026-10-06)

Real theme/modal integration; XLSX and CSV through the real validation REST; User Switching `vis_full` / `vis_rule_cat` / `vis_rule_brand` / `vis_offer` / `vis_none` (hidden, unpublished, orphan, hidden variable parent and whitespace-normalized identifiers indistinguishable from nonexistent; `vis_none` = no access); privacy/oracle boundary; adjusted quantities; cart-aware validation (stock 5, cart 2, request 12 → 3); real direct `/cart/sync`; partial and ambiguous final results; shared chunking; manual QO selection preserved + overlap notice; close/cancel semantics; structural accessibility; 390px browser viewport; 500-row validation/render (≈1.1 s, 4.5k DOM nodes); focused Gate 1 and main-QO regression; staging fingerprint unchanged (product meta, orders, carts, options except cron). Verdict: **Gate 1 PASS, Gate 2 staging PASS.**

### Pre-production manual acceptance (2026-10-07) — PASS; production NOT deployed

All three manual blockers were closed on staging (plugin v1.0.33, runtime `729d059`) with no defect found and no code change:

1. **Real Microsoft Excel smoke — PASS.** The committed template (SHA-256 `4e9529d9…edefc1`, identical local and staging) opened with no repair/corruption/macro/external-link warning; column A is Text by default; `000046` typed into A2 stayed exactly `000046`; saved as XLSX, Excel fully closed, reopened: still `000046`, no warnings. That saved/reopened file was then uploaded through the real staging Excel Import UI (`vis_full` via User Switching): `000046` resolved as `Boca Urban Basic`, quantity 1, status `Spremno`, no error or warning (Excel save/reopen → browser parser → staging validator, one real chain).
2. **Real focused desktop-Chrome keyboard smoke — PASS** (manual, genuinely focused window, not synthetic events): trigger reachable and openable by keyboard; Tab/Shift+Tab stayed trapped in the modal; Escape in the upload state closed it and restored focus to the trigger; Escape during validation behaved per contract with no cart mutation; result/footer controls traversable in both directions with visible focus; Escape during the in-flight cart request did NOT dismiss the modal and no misleading close/cancel control was present. This run added one `Boca Urban Basic` unit to the staging cart of `vis_full`.
3. **Real physical mobile-device smoke — PASS** (reported by the project owner: no layout, scrolling, modal, upload/result or usability issue observed). The exact device model/OS/browser was not recorded in the report, so iOS/Safari coverage must not be inferred (see residual observations).

**Continuation point: "Pre-production acceptance PASS / production deployment not currently applicable — production environment not yet provisioned."** Excel Import implementation and pre-production acceptance are complete. No further Excel Import work is currently required. Resume production-related work only after DreamPoint B2B production is provisioned/defined. No known functional blocker. DreamPoint B2B production does not exist yet (staging is the only provisioned deployment target); that is a wider project-provisioning matter and is NOT an Excel Import implementation blocker. Final production-closure documentation is written only after a production environment is provisioned and this feature is deployed there.

Residual observations (not tasks unless decided): download-to-disk event not captured (serving, hashes and `download` attributes verified); Safari/WebKit untested; no screen-reader certification; existing cart-bridge Toastify messages can appear beside modal outcomes; hidden XLSX rows are imported with a warning (Gate 1 decision).

### Commits

`213d035` Gate 1 foundation · `ef08233` stored-identifier boundary normalization · `635a230` Gate 2 modal + `CartSubmit` chunking fix · `9282361` modal keyboard/focus fix (v1.0.33) · `729d059` removal of accidentally committed test screenshots (no code impact).

---

## ADR-018 — B2B partner approval/activation: Apros is the source of truth (client-confirmed); reconciliation with current implementation

**Date:** 2026-10-07
**Status:** Accepted (architecture, client-confirmed) / **Implementation INCOMPLETE — onboarding activation BLOCKED on two Apros/ZGData API-semantics answers**
**Owner:** Customer/Partner architecture (Apros ↔ WordPress). Supplements ADR-002; does not change catalog visibility (ADR-009 / frozen visibility system).

### Primary client source

`B2B odgovori na pitanja.docx` (the document ADR-009 cites; recovered outside the repository, §3 "Potvrda registracijskog procesa" verified 2026-10-07). Confirmed process:

1. The new partner submits a registration request through the webshop.
2. Točka sna receives the notification.
3. The partner is opened/created manually in Apros.
4. The partner is approved in Apros by setting the attribute `B2B KUPAC = DA`.
5. After approval the partner becomes available through the Apros API and is synchronized and activated in the webshop.

Proposal text: "Apros ostaje jedini izvor istine za odobrenje i aktivaciju B2B partnera." Client answer: "Slažemo se s dogovorenim modelom." This is **primary client-confirmed architecture**. Supporting earlier evidence: Apros (Leo) 2026-07-02 / workshop 2026-06-09 ("…nakon toga se takav partner pojavljuje na endpointu za listu partnera"); email 2026-05-15 (attributes `B2B KUPAC DA/NE` and `B2B E-MAIL`; DreamPoint sends Apros an Excel of partners to open for B2B).

**Scope limit:** the document does **not** define how `dp_bucket_id` / CMS catalog visibility is assigned, nor its order relative to approval. Do not read bucket assignment into this decision.

### Wording reconciliation

"Dream Point decides who gets B2B access" (earlier notes) and "Apros is the approval authority" are compatible: DreamPoint makes the business decision, it is recorded in Apros through `B2B KUPAC`, and Apros is the system of record the webshop consumes. WordPress must not independently approve a partner.

### Current implementation vs the confirmed contract (verified 2026-10-07, staging `9b927c8`)

| Concern | State |
|---|---|
| Registration + admin notification email + customer pending email | Implemented; TODO #10 remains COMPLETE/PASS |
| New registrant starts pending | Yes: `user_register` writes `approved=false`, stored as `''`. This also holds for users created by the partner sync (an earlier claim that the meta is absent for sync-created users was wrong) |
| Pending gate (`/approval-pending` redirect) | Implemented; inactive on staging because `DP_BYPASS_APPROVAL=true` (pending behavior is untested there) |
| Exit from pending by detecting Apros approval | **NOT implemented** |
| ADR-002 automatic polling | **NOT implemented** (no cron, no Action Scheduler job) |
| Manual partner sync `apros_pricing_sync_partners` / `wp importer partners` | Exists (protected `apros-pricing` + `uncle-dev-importer`); manual, unscheduled; GET `partnerList/get` with no filter/params; matches by email, else creates a `customer` user (random password, no email to the user); sets `apros_partner_code`, `apros_legal_form_code`; does **NOT** set `approved=true`, assign a role, send the activation email or touch buckets; treats every returned row as relevant; ignores partners that later disappear |
| Manual wp-admin "Odobri" (`approved=true` + approval email) | Legacy (April 2026, before ADR-002); **contradicts** the Apros-source-of-truth model — an admin can set `approved` with no Apros approval and no `apros_partner_code` |
| Manual "Opozovi odobrenje" | **Unresolved** (deactivation behavior unspecified: DP-D02 open; blueprint ASSUMPTION). No sync would restore it |
| REST `POST /dreampoint-b2b/v1/approve-user` + `DP_ERP_WEBHOOK_SECRET` | Legacy/superseded (Apros confirmed no webhook); dormant on staging (secret undefined → 500); not removed |
| `apros_partner_code` | Intended normal source: the Apros synchronization (ADR-002 step 9). Manual profile entry = fallback / current operational path |
| Activation email | Intended trigger: after sync-detected activation. Current trigger (manual "Odobri") is legacy |
| B2B role | ADR-002 says "B2B rola" but never names one; only `customer`/`shop_manager` exist. Unresolved |
| `dp_bucket_id` (catalog visibility) | Separate concern. An approved/activated account may exist without a bucket; the visibility engine then fails closed (`no_access`). Assignment workflow and approval↔bucket ordering UNRESOLVED |

### partnerList / B2B KUPAC evidence (internal archaeology complete)

- ZGData doc v1.0 `partnerList/get`: "popis poslovnih partnera (kupaca) s osnovnim matičnim podacima"; fields `partnerCode, name, address, city, postalCode, taxId, email, partnerLegalFormCode`. **`taxId` = "OIB partnera"** (closed for the documented domestic case). **`email` = "E-mail partnera"** only. **No `B2B KUPAC` field is exposed.**
- Apros attributes `B2B KUPAC DA/NE` and `B2B E-MAIL` exist as separate attributes. Apros said an approved partner "se pojavljuje" on the partner-list endpoint — this supports but does NOT prove that the endpoint returns only `B2B KUPAC=DA` partners. The legal-form code list includes "Kupac građanin", a weak hint that the master data is broader than B2B.
- No evidence describes `DA → NE`. No captures/logs of past `partnerList` calls exist. Staging pricing tables contain a single partner (2870, the ZGData sample), so the sandbox offers no negative control.
- A fresh read-only TEST GET was **not executed** (blocked by the Claude auto-mode PII classifier; no bypass attempted). It would only prove observed behavior, not contractual semantics.
- No evidence that `partnerList.email` is the `B2B E-MAIL` attribute rather than the general partner email. The sync matches users by email and would create a duplicate "Apros" account if the emails differ.

### Open questions for Apros/ZGData (exactly two, blocking)

1. Does `partnerList/get` return **exclusively** partners with `B2B KUPAC = DA`? If an existing partner is changed from `DA` to `NE`, does it stop appearing in `partnerList/get`?
2. Does the `email` field returned by `partnerList/get` contain the **`B2B E-MAIL`** attribute (used for webshop account matching) or the partner's **general** email address?

These are API-semantics facts, not new business-design decisions. (Secondary, non-blocking: `taxId` content for foreign partners, legal forms 106/111.)

### Open internal questions (not to be resolved by invention)

- Exact WP representation of synchronized Apros activation (`approved` as a mirror vs another canonical state).
- Final fate of manual "Odobri"; semantics/fate of manual "Opozovi".
- Exact activation-email trigger.
- Whether a concrete B2B role is still required.
- Polling trigger/frequency (ADR-002: cron, frequency an operational detail).
- Safe matching strategy when the registration email differs from the Apros email (`taxId`/OIB is a documented candidate key, not decided).
- `dp_bucket_id` assignment workflow and approval↔bucket ordering.

### Stop state

**ONBOARDING / APROS ACTIVATION: BLOCKED** pending the two API-semantics answers above. No implementation of the final activation sync should begin until they are known. This does not reopen TODO #10 (COMPLETE/PASS). DP-B06 stock reservation remains independently on HOLD (Reserved Stock Pro not delivered); TODO #11 (`/akcija/`) remains separate and PARTIAL. No production environment exists; nothing here is a production validation claim.

### Related

ADR-002 (flow; polling not implemented), ADR-008 (importer discovery; partner sync omitted), ADR-009 (cites the client document; visibility boundary), ADR-010 (`apros_partner_code`), `docs/project-status-matrix.md` §1.5/§1.7, `docs/active/status.md` TODO #9/#10.

### Addendum 2026-10-08 — Pending-user enforcement for existing-order payment (Phase 3)

**Phase 3 — PRIMARY R1+R2 RUNTIME PASS / FALLBACK PARTIAL — ACCEPTED FOR LOCAL COMMIT**

Scope: a customer whose B2B approval is revoked (or who was never activated) must not initiate a *new* payment attempt for an existing order, including an order created while previously approved. Existing orders are not cancelled, deleted or altered, and payment-provider callbacks, webhooks, refunds and reconciliation are not intercepted (they run without a logged-in user, to which the guard does not apply). The activation predicate is unchanged: `dreampoint_b2b_user_is_activated()` (administrators and shop managers exempt, `DP_BYPASS_APPROVAL` respected). This extends the local pending-user enforcement that was added in two earlier local commits (`3ab9467` visibility/Quick Order, `514d70e` cart and checkout); all three are local only as of this date.

Implementation (`themes/dreampoint-b2b/inc/pending-checkout-guard.php`):
- **R1 — Store API existing-order checkout:** the narrowly scoped `rest_request_before_callbacks` filter now also matches `POST /wc/store/v1/checkout/{id}` (WooCommerce `CheckoutOrder` route, not the standard `Checkout` route). Blocked users receive HTTP 403 `dp_b2b_not_activated` before `is_authorized()`, order/customer updates and gateway execution.
- **R2 — classic `order-pay`:** `WC_Form_Handler::pay_action()` runs on `wp` priority 20, before `template_redirect` and therefore before the frontend pending redirect. A `wp` priority 10 callback detects a genuine order-pay submission (`$_POST['woocommerce_pay']`, `$_GET['key']`, `order-pay` query var) from a blocked user and removes only `pay_action` for that request (priority read via `has_action()`), adding one translated error notice.
- **R2 fallback:** `woocommerce_before_pay_action` (priority 1) adds the error notice so WooCommerce's own `wc_notice_count('error')` gate stops `process_payment()` if the primary interception were ever absent.

Validation (isolated local runtime; disposable scratch database with a restricted DB user, disposable WordPress copy, synthetic users/orders, instrumented offline test gateway, `DP_BYPASS_APPROVAL=false` in scratch only):
- Primary R1 and R2 passed for pending, revoked-after-order-creation, `failed`-order retry, other-customer and guest-order scenarios: **zero order save attempts, zero gateway calls, order data unchanged**; exactly one guard notice (classic) or 403 `dp_b2b_not_activated` (Store API).
- Approved customers, administrators, shop managers, anonymous visitors and `DP_BYPASS_APPROVAL=true` were not blocked by the guard (WooCommerce's own ownership/order-key checks preserved); negative controls (no payment marker, no key, no order-pay context, unrelated POST) did not remove the handler. Phase 2 cart/checkout protections and the standard Store API checkout path were unaffected.
- Network isolation was PHP-level capability removal (no cURL/sockets/mail/process functions) plus WordPress HTTP/mail blocking, not an OS firewall. Requests were exercised through internal `rest_do_request()` and the real `wp` hook lifecycle, **not** real browser HTTP.
- Diagnostics resolved the only unexplained results: the restored-approval Store API call that did not reach the gateway was a stale guard error notice left in the same WooCommerce session by the earlier blocked classic attempt (in a browser the order-pay page prints and clears it; not a guard regression). All attempted outbound HTTP was WooCommerce core Tracks telemetry (`pixel.wp.com`, `WC_Tracks_Client`) triggered by order attribution and order status transitions in permitted flows; all attempts were blocked and no actual transmission occurred. `woocommerce_allow_tracking` is `yes` in the local database; telemetry state on other environments is unverified.

**Known limitation, knowingly accepted for the local commit:** the R2 fallback prevents gateway execution but is **PARTIAL protection, not a full pass** — when the primary `wp` interception is absent, WooCommerce still performs `set_payment_method()` + `save()` before its notice gate, so the order's payment method can change (observed: `cod` → test gateway, one order save, status unchanged, no gateway call). The fallback must not be described as a full pass. Moving the fallback ahead of that save would be a separate, explicitly authorized change.

Not verified / not authorized: real browser HTTP (cookies, nonces, redirects), staging, production, real payment gateways and their return flows, bundle runtime, a full approved-customer checkout regression, guest-order payment policy, and the shop-manager frontend redirect inconsistency. No deployment has been authorized.

### Addendum 2026-10-08 — Phase 1–3 pending-user enforcement: staging deployment and E2E closeout

**Status: E2E PARTIAL — SPECIFIC GAPS REMAIN.** This note supersedes the "local only" wording in the Phase 3 addendum above; it does not rewrite it.

Deployment: commits `3ab9467` (Phase 1), `514d70e` (Phase 2 A+B) and `1c2e0c1` (Phase 3) were pushed to `origin/master` and deployed to staging as a fast-forward; staging `master` = `origin/master` = `1c2e0c1c0aeabdc560b5fc9d780ac63f7e94bf76`. Static verification passed (file hashes identical to the committed files, PHP lint, include order, clean worktree).

Validation (real HTTP against staging with `DP_BYPASS_APPROVAL` temporarily `false`, users impersonated through User Switching; the constant was restored to `true` afterwards):
- **Phase 1 PASS** — pending users are redirected to `/approval-pending`; Store API/`wp-v2` product reads, Quick Order REST (products, variations, `cart/sync`, `import/validate`) and AJAX product search expose nothing; approved users see exactly their `dp_bucket_id` catalog; administrators unaffected.
- **Phase 2 PASS** — pending/revoked users get 403 `dp_b2b_not_activated` on Store API add-item, `cart/items`, update-item, `items/{key}` and batch; standard Store API checkout is rejected with 409 `dp_b2b_not_activated`; an existing cart is preserved after revocation; approved users add, update, use Quick Order and check out normally.
- **Phase 3 PASS (primary paths)** — revoked/pending users get 403 on Store API `checkout/{id}`; the classic order-pay submission is intercepted and the order stays untouched (status, payment method, timestamp, no notes, no ERP); after approval is restored both paths complete (BACS, `on-hold`). The documented R2 fallback limitation (PARTIAL) is unchanged and was not re-tested.
- **Apros sandbox PASS** — WooCommerce order #23407 (approved test customer, COD, delivery location chosen) → ERP document 4345, exactly one export note, no duplicate export observed. Blocked attempts produced no orders and no export.

NOT TESTED: the classic checkout form (the site uses the Checkout Block, so no classic form/nonce exists); anonymous order creation (the one request was blocked by the execution environment's safety classifier and was not worked around); external card gateways (only BACS/COD are installed); WooCommerce 11.2.0 runtime was validated only through this staging run.

Open findings (both pre-existing, neither a Phase 1–3 regression; neither is implemented or decided):
- **Finding A — anonymous shopping API access.** The frontend login wall works (`dreampoint_b2b_restrict_guest_access()` on `template_redirect`, `inc/b2b-registration.php`; this corrects the earlier audit statement that no guest wall existed). It does not cover API/early-hook paths: an anonymous visitor can mutate a guest cart through Store API `add-item` (201), `wc-ajax=add_to_cart` and `?add-to-cart=` (added before the redirect). `woocommerce_enable_guest_checkout` is `yes`. Whether an anonymous order can be created is unverified. Needs a focused decision and assessment before production.
- **Finding B — bucket enforcement at cart/checkout.** Catalog, product pages and Quick Order listings respect `dp_bucket_id`, but an approved customer can add an out-of-bucket product by direct ID through Store API (201) and Quick Order sync can update that cart line; whether checkout revalidates bucket membership is unestablished. Needs focused follow-up before production (same area as the previously deferred "Slice C").

Restoration: `DP_BYPASS_APPROVAL=true` restored, `wp-config.php` byte-identical to the baseline (same SHA-256, owner/group, mode, mtime); `approved` metadata of all customers restored to empty; the temporary `apros_partner_code` on the test account removed; the temporary fail-safe process and script removed; no code changed or deployed during the E2E. Intentionally retained test artifacts: orders #23405 and #23406 (`on-hold`, BACS), #23407 (`processing`, ERP 4345); stock of the tested product 457 → 454; test e-mails were sent (including the admin "new order" notification); User Switching session tokens for two test accounts remain until they expire. No cleanup was performed.

Environment corrections found during this work: staging has no object-cache/page-cache drop-ins and no LiteSpeed plugin (the stack note claiming Redis/LSCache is inaccurate for staging); the `approval-pending.php` template and published page ID 106 exist; the ERP order export (`uncle-dev-importer/order.php`) runs synchronously on `woocommerce_order_status_processing`, `woocommerce_payment_complete` and `woocommerce_thankyou`, so any staging order that reaches those events writes to the Apros sandbox.

### Addendum 2026-10-08 — Finding A/B (anonymous shopping, bucket cart/checkout enforcement): implementation, staging deployment and E2E

**Status:** Finding A (anonymous cart mutation) — **COMPLETE — STAGING VERIFIED for cart mutation**; anonymous order creation **NOT TESTED — ENVIRONMENT RESTRICTION** (blocked by code path and local tests only). Finding B (bucket enforcement) — **COMPLETE — STAGING VERIFIED** with the residual gaps below. Phase 1–3 behavior is unchanged (guards for logged-in pending/revoked users and existing-order payment keep their previous predicate).

Implementation commit `c3b3d70` (`fix(dp-b2b): enforce guest and bucket cart authorization`), deployed to staging by fast-forward (staging `master` = `origin/master` = `c3b3d70`; SHA-256 of the five files identical to local, PHP lint clean on staging, `DP_BYPASS_APPROVAL=true` untouched, worktree clean). Files: `inc/pending-checkout-guard.php`, `inc/registration-approval.php`, `inc/visibility/class-access-guard.php`, `inc/visibility/class-query-filter.php`, `plugins/dp-b2b-quick-order/inc/class-cart-sync.php`.

Design:
- **Finding A.** `dreampoint_b2b_cart_guard_block_reason()` returns `login_required` (anonymous), `not_activated` (logged in, not activated) or null and is used by every cart/checkout consumer. Store API cart mutations return 401 `dp_b2b_login_required` for anonymous (403 `dp_b2b_not_activated` for pending); anonymous `POST /wc/store/v1/checkout` is rejected 401 before an order exists; `check_cart_items`/`store_api_cart_errors` block an existing guest cart. `DP_BYPASS_APPROVAL` does not apply to anonymous users (`dreampoint_b2b_user_is_activated(0)` is always false). The old predicate `dreampoint_b2b_cart_guard_blocks_current_user()` (logged-in only) is deliberately kept for existing-order payment (Store API `CheckoutOrder`, classic order-pay), so guest-order payment policy is unchanged. The guest-checkout WP option and the frontend login wall were not changed.
- **Finding B.** Eligibility is the existing `dp_b2b_product_accessible` predicate (default `false`, fail-closed), evaluated on the parent product of a variation. Add boundary: `woocommerce_add_to_cart_validation` (the real boundary for Store API — its add-item does NOT call `WC_Cart::add_to_cart()`; it builds the line or calls `set_quantity()` itself, WC 11.2.0 `CartController::add_to_cart`) plus `woocommerce_add_to_cart_quantity` at `PHP_INT_MAX` (single point inside `WC_Cart::add_to_cart`, `class-wc-cart.php:1212`; rejection = quantity 0 = no cart mutation). Checkout boundary: `woocommerce_check_cart_items` and `woocommerce_store_api_cart_errors` (409 `dp_b2b_product_not_available`). Ineligible lines are never removed; checkout stays blocked until the customer fixes the cart. Quick Order `sync_item()` rejects any positive quantity (new line or increase) for an ineligible product (`product_unavailable`); quantity 0 (removal) is always allowed. Existing-order payment is not revalidated against buckets.
- **Privileged users.** `dreampoint_b2b_current_user_is_staff()` (`manage_options` or `manage_woocommerce`) replaces `manage_options` in the visibility layer (catalog bypass, brand terms, single-product guard, REST scrub, `dp_b2b_product_accessible`) and in the frontend approval redirect, so administrators and shop managers are exempt consistently. Before this, shop managers were activated for the cart guard but saw an empty catalog (the code comment already said "admins and shop managers bypass").
- **Performance.** No memoization: a rule-based check of a 40-line cart costs 1 query; `custom_offer` costs about 1 indexed point query per line (`Access_Table::user_can_see`, PK `(user_id, product_id)`); there is no cross-call cache, so an access change is detected on the next validation.

Staging E2E (real HTTP, 2026-10-08, staging `c3b3d70`; bucket-131 test customer `vis_rule_cat`, User Switching; allowed products 18893, 9339, 6180; out-of-bucket 5874 → Store API 404):
- Anonymous: `/` and `/shop/` → 302 `/my-account`; `/my-account/`, register and lost-password → 200; Store API add-item and update-item → 401 `dp_b2b_login_required`; `wc-ajax=add_to_cart` → `error:true`; `?add-to-cart=` → redirect; cart stays empty. **PASS.** Anonymous checkout / existing guest cart → order: **NOT TESTED — ENVIRONMENT RESTRICTION** (an anonymous checkout request was blocked by the safety classifier earlier and was not retried).
- Bucket 131: allowed add (Store API) 201; 5874 via Store API → 400, wc-ajax → error, classic `?add-to-cart=` → not added, Quick Order new line → `failed/product_unavailable`; allowed Quick Order add → `added`. **PASS.**
- Existing ineligible lines (user temporarily moved from bucket 131 to 134 with `wp user meta update`, restored to 131 afterwards): Quick Order increase → `failed`; Store API add-item increase → 400; lines preserved; `update-item` with the same quantity → 200; Store API `POST /checkout` → 409 `dp_b2b_product_not_available`, no order created (order count and max ID unchanged); Store API remove-item and Quick Order quantity 0 → removed; reduction 3→2 allowed. **PASS.**
- Correction restores checkout: bucket back to 131, cart of an allowed product → Store API checkout (BACS) created order **#23408** (`on-hold`, 18893×2, customer 4, stock 18→16, e-mails sent, no ERP export — `on-hold` is not an export status). **PASS.** The Apros export itself was not re-tested (code unchanged; #23407 → ERP 4345 from the earlier E2E remains the evidence).
- Administrator: product 5874 visible, Store API add 201, Quick Order add ok, catalog total 451. **PASS.** Shop manager: **NOT TESTED on staging** (no account exists; none created) — local tests only.
- Phase 1–3 regression: the deployed file keeps the unchanged `CheckoutOrder`/order-pay logic; live pending/revoked enforcement was **not re-run** (staging bypass is `true`) — the earlier 2026-10-08 staging E2E evidence stands.

Residual gaps (known, not blockers):
1. **Store API `update-item` (and classic cart-page update) still lets the customer INCREASE the quantity of an existing ineligible line** (observed 3→4 on staging). It cannot be purchased because checkout is blocked, but it is inconsistent with the Quick Order rule. Smallest fix: reject an update for an ineligible product whose new quantity exceeds the current line quantity (Store API mutation filter and `woocommerce_update_cart_validation`). Not implemented.
2. **Bundles.** `uncledev-product-bundles` is not active on staging and no bundle product exists there. If a bundle child is outside the buyer's bucket, the child's `add_to_cart()` is rejected by the quantity filter and the bundle plugin silently skips it (`class-bundle-cart.php:380`), so the stand could enter the cart without that child. No authorization risk; order-integrity risk only if bundles are used with bucket-restricted children. Hook point if needed: `uncledev_bundles_validate_item`.
3. **Performance.** `custom_offer` customers: one point query per cart line per validation (about 500 for a 500-line cart); not measured at that size.
4. Not tested: classic checkout form (the site uses Checkout Block), external payment gateways (only BACS/COD), shop manager on staging, anonymous order creation, Phase 3 R2 fallback runtime (unchanged accepted limitation).

Side effects: order #23408 retained (no cleanup); test e-mails sent (including the admin "New order"); the test customer's billing data now holds the test values (`E2E Test`, `e2e-nonexistent@t.invalid`); the administrator's cart was emptied by mistake during testing and restored to its previous contents (11089×2, 12806×2, 5874×1; cart item keys may differ). `vis_rule_cat` bucket 131 and `approved` metadata are back to their original values.

### Addendum 2026-10-08 — Finding B closeout: ineligible-line quantity increases blocked (residual gap 1 above is CLOSED)

**Root cause.** The first Finding B change protected new additions (validation hook + `woocommerce_add_to_cart_quantity`), Quick Order and checkout, but Store API `update-item` / `items/{key}` and the classic cart update change an existing line through `set_quantity()` and do not pass either add-path filter. Checkout was blocked, but the quantity of an ineligible line could still be increased (observed 3→4 on staging).

**Fix** (commit `d5bfdf0`, only `inc/pending-checkout-guard.php`, +48/−6; deployed to staging by fast-forward `c3b3d70` → `d5bfdf0`, SHA-256 identical, lint clean, `DP_BYPASS_APPROVAL=true` untouched):
- Store API: `woocommerce_store_api_cart_item_quantity_validation` (new in WooCommerce 11.2.0, called from `QuantityLimits::validate_cart_item_quantity()` before `set_quantity()`; also covers the add-item top-up and `items/{key}`). Returns `WP_Error` `dp_b2b_product_not_available` (HTTP 400) when the new quantity exceeds the current line quantity and the product is no longer eligible. WooCommerce older than 11.2.0 has no such filter (no effect for update-item; add-item and checkout remain protected independently) — the 11.2.0 dependency is intentional.
- Classic: `woocommerce_update_cart_validation` now takes four arguments and rejects the same case with the standard error notice; the update for that line is skipped, other lines are processed normally.
- Rule: increase of an ineligible line is rejected; unchanged, decrease and removal stay allowed; lines are never altered or deleted by the guard. Eligibility is still the single `dp_b2b_product_accessible` predicate (parent product); administrators and shop managers are exempt through it.

**Local validation** (isolated scratch WordPress, WC 11.2.0, bypass off and on): Store API 3→4 rejected (400, quantity preserved), 3→3 and 3→2 allowed, `items/{key}` PUT 2→5 rejected, add-item top-up rejected, eligible increase allowed; classic increase rejected with notice, decrease/removal allowed, eligible increase allowed; Quick Order increase of an ineligible line rejected and additive behavior for eligible lines unchanged (4→5); checkout with an ineligible line 409 with no order, eligible cart checkout succeeds; anonymous 401, pending 403, administrator/shop manager increases allowed.

**Staging validation** (real HTTP, `vis_rule_cat`, bucket temporarily 131→134 with `wp user meta update`, restored to 131; cart empty before and after; no order created — order count 6 / max ID 23408 unchanged): eligible increases allowed under bucket 131 (Store API 2→3, classic form 3→4); after the bucket change Store API `update-item` 3→4 → 400 `dp_b2b_product_not_available` (quantity unchanged), `items/{key}` PUT 3→5 → 400, add-item top-up → 400, same quantity 3→3 → 200, decrease 3→2 → 200; Quick Order +1 on an ineligible line → `failed/product_unavailable`; classic cart form: increase rejected (quantities unchanged), decrease 4→3 allowed, classic remove link removes the line; Store API `POST /checkout` with an ineligible cart → 409 `dp_b2b_product_not_available`; after restoring bucket 131 the cart page shows no ineligibility notice. Not re-tested: positive checkout (no extra order; #23408 stands), anonymous order creation, shop manager on staging, classic checkout form, external gateways, bundles (unused on staging).

**Final status.** Finding B — **COMPLETE — STAGING VERIFIED**. Finding A unchanged (cart mutation staging-verified; anonymous order creation not tested). Remaining known limitations: bundle children outside a bucket are skipped silently (bundles inactive on staging); `custom_offer` cart validation costs about one indexed query per line; the `woocommerce_store_api_cart_item_quantity_validation` filter requires WooCommerce ≥ 11.2.0.

---

## ADR-019 — WooCommerce email localization: Croatian site locale on staging; accepted staging SMTP sender rewrite

**Date:** 2026-10-08
**Status:** Accepted / **COMPLETE — STAGING RENDERING VERIFIED; DELIVERY NOT TESTED** (production not provisioned)
**Owner:** Staging environment configuration (not Git-tracked). No theme/plugin code changed.

### Root cause
Standard WooCommerce order emails were English because the site locale was `en_US` (`WPLANG` empty) and no Croatian language pack was installed (`wp-content/languages` did not exist). The theme only hand-translated the new-account email (`inc/emails.php`); there are no theme overrides for order emails.

### Change (staging, 2026-10-08, as `dream9399`)
- `wp language core install hr`, `wp language plugin install woocommerce hr` (WP 7.1.3, WC 11.2.0). Locale identifier and language slug are both `hr`.
- `WPLANG`: empty (`en_US`) → `hr`.
- Administrator (user 1, `uncledev82@gmail.com`) usermeta `locale` set explicitly to `en_US` (was unset) so wp-admin stays English. No other user touched (all other users: locale unset → follow site default).
- `DP_BYPASS_APPROVAL=true` unchanged.

### Validation (non-sending, real WC email classes, existing order #23408 read-only; order status/meta/modified date verified unchanged)
- Runtime: `get_locale()`=`hr`; admin `get_user_locale(1)`=`en_US`; frontend `<html lang="hr">`.
- New Order (admin) and Customer Processing Order: subject, heading, body, order-table labels, address labels, footer and `<html lang="hr">` all Croatian (e.g. `[Dreampoint B2B Shop]: Nova narudžba #23408`, `Hvala Vam na narudžbi`).
- Still English (not translation-pack issues): payment gateway title `Direct bank transfer` and shipping title `Flat rate` (admin-saved WooCommerce settings, to be edited in WC settings); `Additional Information` + value `No` for the "use same shipping address" field (rendered by the third-party `silkypress-input-field-block` plugin).
- `inc/emails.php` remains compatible: its "is default" check compares against the (now Croatian) WC default, empty saved values still match, so the custom Croatian new-account texts keep applying. No code change.
- No test email sent; delivery is therefore NOT verified.

### Accepted limitation — staging SMTP sender rewrite
A real New Order email for #23408 showed application From `uncledev82@gmail.com` but delivered From `armin.lusija@gmail.com` (path via `ax42.uncledev.com` and Gmail SMTP, which rewrites the sender). The address exists nowhere in WordPress code, options, users or DB. Accepted for staging; transport intentionally untouched.

### Pending production-readiness task — "DreamPoint B2B — Production Transactional Email Delivery"
Before launch: choose a transactional provider; approved sender on the client's domain; SPF/DKIM/DMARC; WooCommerce integration; verify From/Reply-To; test Gmail, Outlook/Hotmail and others (direct Hetzner delivery has had Hotmail/Outlook problems); bounce/error handling and logging; staging-vs-production routing; validate customer and admin order notifications; document rollback and ownership. Hr language packs must also be installed on production.

### Rollback
`wp option update WPLANG ''`; optionally `wp user meta delete 1 locale`. Installed packs are harmless.

### Note
Staging `git status` shows untracked `wp-content/languages/` (installed packs). Harmless to `git pull`; a `.gitignore` entry is a proposed, not yet approved, follow-up.

### ADR-019 addendum — remaining gaps closed (2026-10-08, code commit `ccc0591`)
- **Payment title** (`woocommerce_bacs_settings.title`): `Direct bank transfer` → `Izravna bankovna uplata`. **Shipping title** (flat rate, zone Croatia, `woocommerce_flat_rate_2_settings.title`): `Flat rate` → `Fiksna cijena dostave`. Only the `title` keys were patched (`wp option patch update`); cost 4.99, gateway id, bank data untouched. Rollback: patch the titles back to the original English values above.
- **Stored on orders:** WC copies these titles into the order at creation, so already-placed orders (e.g. #23408) keep the English titles; only new orders get Croatian. Validation therefore used an in-memory (never saved) order; #23408 verified unchanged.
- **Multilingual (HR default, EN secondary):** titles are single-language option values; the Croatian text is the default-language source and a future multilingual plugin can register them as translatable strings for English. No architectural conflict.
- **silkypress-input-field-block:** `Additional Information`, `No`, `Yes` are translatable strings in domain `silkypress-input-field-block`, but the plugin ships no language files. Fix: own per-locale file `themes/dreampoint-b2b/languages/plugins/silkypress-input-field-block-hr.{po,mo}` loaded by an `init`-20 hook in `inc/woocommerce.php` (no plugin file edited; missing locale file = no-op, so English stays English). Rendered admin New Order: `Dodatne informacije … Ne`. The customer Processing email has no such section.
- **Git:** `/languages/` (repo root = `wp-content/languages`, downloaded packs only, 0 tracked files) added to `.gitignore`; staging deployed to `ccc0591`, `git status` clean. The theme `languages/` folder stays tracked.
- **Still English, deliberately not changed (out of the approved scope, awaiting approval):** free-shipping title `Free shipping` (zone Croatia, instance `free_shipping:1`) and the BACS checkout description (`Make your payment directly…`; not in emails). Production must repeat the WC title settings and install hr packs.

### ADR-019 closeout (2026-10-08, staging configuration only, no code change)
- **Free Shipping** (`free_shipping:1`, zone Croatia, `woocommerce_free_shipping_1_settings.title`): `Free shipping` → `Besplatna dostava`. Only `title` patched; `requires=coupon`, `min_amount=0`, `ignore_discounts=no` untouched. Rollback: patch `title` back to `Free shipping`. WC resolves `free_shipping:1 => Besplatna dostava`; flat rate and BACS titles unchanged.
- **BACS description — intentionally NOT changed.** Current value is the English WC default (`Make your payment directly into our bank account. Please use your Order ID as the payment reference. …`). On staging BACS has no bank account details (`account_details`, `iban`, etc. empty) and no `instructions`, so WooCommerce shows no bank data on the thank-you page or in emails, and there is no custom bank-transfer/payment-reference logic in the theme (only `inc/checkout-logic.php` gateway restrictions). The proposed wording ("podatke za uplatu pronaći ćete u potvrdi narudžbe") would be false today. Open decision: how payment details are delivered (bank accounts configured in WC, or invoice/proforma outside WC) must be defined by the business before the production wording is set; do not invent a reference/model rule. Its description then needs an English translation as well.
- **Multilingual:** the BACS/Flat-rate/Free-shipping titles and the BACS description are stored single-language option values (Croatian = default-language source); they need English translations or language-aware equivalents when the multilingual system is introduced. No PHP overrides were added.
- **Status:** email/checkout localization otherwise closed; delivery not tested; production SMTP task unchanged.

### ADR-019 BACS visibility check (2026-10-08, read-only) — PARTIAL, not closed
- Business context (user-confirmed): payment is not made through the B2B shop; orders go to ERP and the invoice is sent later; BACS is only the internal default gateway. No bank details must be added.
- **Runtime evidence (staging, `wp eval`):** the WooCommerce Blocks `bacs` payment-method data sent to the checkout JS contains the English default description (`Make your payment directly into our bank account…`); classic `payment_fields()` outputs the same. `thankyou_page()` for #23408 outputs nothing and emails carry no description (`instructions` empty, 0 bank accounts), so thank-you page, order details and order emails do NOT show it (source inspection of `class-wc-gateway-bacs.php` + theme `thankyou.php`/`order-details.php`: only payment method *title* is printed).
- **Unresolved:** the user-confirmed Croatian "PLAĆANJE / Plaćanje se ne vrši putem B2B shopa…" block was not found anywhere on staging (theme/plugin source, checkout page content id 12 — standard `checkout-payment-block` — or any DB text column). Its origin, and whether the English description is rendered next to it in the real Blocks checkout DOM, is NOT verified (a DOM check needs a cart, which was out of scope). Proposed next step (needs approval): a guest Playwright pass that adds one product to the cart and reads the payment section, or a full-section screenshot from the user. If the English text is visible, smallest fix is the BACS description setting only (Croatian ERP wording confirmed by the user), no code.

### ADR-019 final closeout — BACS description (2026-10-08, staging configuration only, no code change)
- **Clarification:** the Croatian "PLAĆANJE — Plaćanje se ne vrši putem B2B shopa…" block seen earlier comes from the newly delivered final Figma checkout design, not from the current staging checkout; that is why it was not found in code or DB. Implementing that design is a separate task.
- **Confirmed workflow:** ERP-driven. Payment is not collected in the B2B shop; orders are processed through Apros; invoices are emailed later; BACS is only the internal default gateway; no bank details are configured (and none are to be added).
- **Change:** `woocommerce_bacs_settings.description` only (`wp option patch update`, as `dream9399`).
  - Original: `Make your payment directly into our bank account. Please use your Order ID as the payment reference. Your order will not be shipped until the funds have cleared in our account.`
  - Final: `Plaćanje se ne vrši putem B2B shopa. Nakon slanja narudžbe, narudžba se obrađuje kroz ERP sustav, a račun će biti poslan naknadno na vašu e-mail adresu.`
  - Untouched: gateway id/enabled state, title `Izravna bankovna uplata`, `instructions`, bank fields, all other settings.
- **Validation (runtime, `wp eval`):** gateway `get_description()` and the WooCommerce Blocks `bacs` payment-method data both return the new Croatian text; BACS/Free shipping/Flat rate titles unchanged (`Izravna bankovna uplata` / `Besplatna dostava` / `Fiksna cijena dostave`); locale `hr`, admin `en_US`; orders still 6; `DP_BYPASS_APPROVAL=true`. **Not performed:** browser DOM verification of the checkout, and any email delivery test (rendering-verified only).
- **Rollback:** `wp option patch update woocommerce_bacs_settings description "<original English text above>"`.
- **Separate pending tasks:** English translations of these stored settings via the future multilingual solution (HR default, EN secondary); production transactional SMTP configuration; Figma checkout design alignment; production must repeat the WC title/description settings and install hr packs.

---

## ADR-020 — Figma alignment program: presentation-only policy, desktop-only design reference, additive token migration

**Date:** 2026-10-08
**Status:** Accepted (policy); Batch 1 Steps 0-2 implemented and deployed to staging (2026-10-09). Plan, progress and handoff: `docs/active/figma-alignment.md`.

### Context

The final design was delivered after the implementation (Figma file `Te4kr3o8kXDgBLzHewKSYU`, page **UI** `11148:22601`, all frames 1440 px). A read-only gap audit (2026-10-08) found the functional structure largely matching and the visual layer diverging systematically: the theme palette/components were inherited from JekaaStore (`$brand #8C907E`, `$pink #e3869c`, `$text-primary #303030`) while Figma uses `#0d121c`/`#384250`/`#f3f4f6`/`#d2d6db` with black pill buttons, a dark top bar, a black footer and Petrona/Montserrat typography (fonts already present).

### Decisions

1. **Presentation-only.** Figma governs visual presentation. It never overrides approved business logic: B2B registration/approval/Apros, authentication and guest restrictions, bucket visibility, binary stock display (ADR-016), pricing and ERP data, cart/checkout/payment/order processing, Apros orders, cart reservation architecture (ADR-007). Genuine conflicts are recorded and excluded until explicitly approved (list: `docs/active/figma-alignment.md` §11).
2. **Desktop Figma only.** No mobile/tablet frames exist or will be supplied. Responsive layouts are developed pragmatically with each component batch; their absence in Figma is not a defect or blocker.
3. **Additive token migration.** The existing SCSS variables (`$brand`, `$pink`, `$text-primary`, …; 100+ usages across excluded areas) keep their values. New namespaced tokens (`$dp-*` plus `:root` `--dp-*` custom properties, emitted once from `style.scss`) are introduced and consumed by migrated components via scoped selectors and new mixins. The old palette variables are retired in a later cleanup batch after all pages are aligned.
4. **Order:** Batch 1 = tokens, shared buttons/inputs, header, footer, title/breadcrumb banner (each with its responsive pass and translation-ready strings); catalog/PDP/QO visuals, account, cart/checkout presentation follow in later batches.
5. **Multilingual readiness:** customer-facing strings introduced or touched use `__()` with text domain `dreampoint-b2b`; ERP identifiers stay language-independent; DB content (page titles, menus, ACF options) needs a per-language content strategy deferred to the multilingual solution; no temporary translation workarounds.

### Consequences

- Generated CSS/JS remain tracked and deployed; every step ends with a clean `npm run build` and rebuilt generated files in the same commit.
- Global `.button` restyle is a gated, separately validated sub-step because `button-primary`/`button-outline` are used by cart, checkout, account and shop styles.

### Update 2026-10-09 — container width policy

The Figma content width (1200px at 1440px) is adopted for the 1400-1499px range: `.container` max-width 1252 -> 1216px (border-box + 8px padding = 1200px usable). The client explicitly asked for a wider layout on large monitors, so the >=1500px tier (1434px) is PRESERVED and must not be removed or reduced; tiers <=1399px are unchanged; `wishlist.scss` keeps its hard-coded 1236px. Deliberate exception: the auth split layout (AUTH-01) and full-bleed backgrounds do not follow the 1200px grid. Implementation: commit `26d9aa8` (`sass/theme/_grids.scss`); Step 2 banner: `5d5c910`.

---

## ADR-021 — Wishlist functionality removed (client decision)

**Date:** 2026-10-09
**Status:** Accepted. Implemented locally (not yet deployed). Supersedes the wishlist-related notes in ADR-020 (open decision 3, the wishlist heart as "EXTRA", and the `wishlist.scss` 1236px container exception).

### Context

TI WooCommerce Wishlist (`tinvwl`) was integrated into the header action area (desktop and the mobile fixed bottom toolbar), the product cards, the PDP and a wishlist page with theme template overrides. The final Figma design does not contain a wishlist and the client confirmed it is no longer required; the site owner deactivated the plugin.

### Decision

Remove all theme-owned wishlist integration. Do not uninstall the plugin, delete its data or touch the database.

### Removed

- Markup: header wishlist counter (`header.php`), product-card heart (`woocommerce/content-product.php`).
- Templates: `woocommerce/ti-wishlist.php`, `ti-wishlist-empty.php`, `ti-wishlist-product-counter.php`.
- PHP: wishlist page stylesheet enqueue and the `dreampoint_b2b_admin_wishlist_variation_fix` footer script (`functions.php`), `tinvwl-js` footer handle (`inc/enqueue-plugin-overrides.php`), a wishlist mention in a comment (`inc/pending-checkout-guard.php`).
- Sass/CSS: `.wishlist-area` and the counter selector in `_header.scss`, `.add-to-fav`, `.wishlist_item.out-of-stock` and `.tinv-wishlist` rules in `_content.scss`, `.add-to-fav` and `.tinvwl-after-add-to-cart` in `shop-single.scss`, the `.tinv-wishlist` selector in `shop-archive.scss`, `sass/pages/wishlist.scss` and `css/pages/wishlist.css`; generated outputs rebuilt.
- Mobile toolbar spacing re-derived for four controls (see `docs/active/figma-alignment.md` section 14).

### Not touched / follow-up

- The plugin and its data stay as they are. The icomoon font glyphs used by the wishlist stay in the font file.
- DB-managed content is not part of this change: the plugin-created page `/wishlist/` still exists on staging (HTTP 200), and menus, widgets, ACF options and plugin options were not inspected. Decide separately in WP Admin.
- Historical records that mention the wishlist (for example the 2026-09 audit note about a TI Wishlist script and `localStorage`) are kept as history.

### Consequences

- Product-card and PDP action areas contain only add-to-cart/inquiry controls; the header action area holds home (mobile), search (mobile), QUICK ORDER and cart.
- Staging QA after an approved deploy: header at 1440/1024/768/390/360/320, product cards and PDP layout, cart/checkout/Quick Order unchanged, no console errors from removed assets (`wishlist.css` is no longer requested).

### Update 2026-10-09 — deployment and page retirement

The removal was deployed to staging with commit `50b98a7` and validated in the browser (no wishlist markup, requests or new console errors). On staging only, the plugin-created WordPress page ID 20 (`/wishlist/`) was moved to Trash after confirming it was not referenced by menus, widgets, ACF options or theme mods; it is restorable (`wp post untrash 20`) and the plugin options (`tinvwl-*`, including `tinvwl-page`) were left untouched. Retiring or deleting the page permanently, and uninstalling the plugin, remain separate decisions.

---

## ADR-022 — Button system: Figma Button set on the existing `.button` ecosystem

**Date:** 2026-10-09
**Status:** Accepted; Step 3b-2b implemented, deployed to staging and validated (staging HEAD `56ce5f17fe6de728e51cd35f905843979e5919a3`). Steps 3b-2c and 3b-2d are open. Builds on ADR-020 (presentation-only, additive tokens) and the Suplementi-style Sass ownership (`_vars` tokens, `_mixins` definitions, `_utilities` emitted classes; commits `e1f5ed1`, `a48a940`).

### Context
The final Figma Button set (Size XL/L/M/S x Style Primary/Secondary/Destructive x State x Icon) had to be adopted without a parallel button system. A first attempt (`.dp-btn` classes, `4cc0421`, preserved on the local branch `backup/step3a-dp-btn-rejected`) was rejected.

### Decisions
1. The existing classes stay the public API: `.button` = Figma L (40px), `.button--sm` = M (32px), `.button--xl` = XL (48px), `.button--outline` = Secondary. No `.dp-btn`, no new size modifier (S 24px and Destructive are not used by any consumer yet).
2. `button-primary` / `button-outline` take an optional `$figma` size; without it they emit the exact legacy CSS, so unmigrated consumers (WC Blocks checkout, WBW, refund controls, `.fr-request-form`) are untouched until Step 3b-2c.
3. Appearance modifiers and size modifiers set disjoint properties (no dependence on source order); icon spacing uses compound selectors.
4. Typography: Montserrat 600, no uppercase; the header QUICK ORDER override keeps its uppercase label. Focus is a box-shadow ring; disabled stays legacy (Figma defines neither).
5. Template consumers were migrated only where a Figma target is established (My Account L, mini-cart/hero M, cart `button--xl`); the Shop Brand CTA (`brand-hero.php`) was deliberately left unchanged.
6. Specificity doctrine for form controls: a rule that replaces a legacy `.custom-form` control rule keeps the legacy specificity (`:where(:not(.button))`), otherwise scoped overrides such as the header search button lose (regression `56ce5f1`).

### Consequences / open
See `docs/active/figma-alignment.md` section 15 for the outstanding list (Shop Brand size, components not renderable on staging, mobile "Filtriraj" 32px, blog hover, Steps 3b-2c/3b-2d).

### Update 2026-10-09 (Step 3b-3a)
Four template class corrections from the per-instance audit were implemented in `07faa01` and deployed to staging (contact-info Secondary M, hero Primary XL, featured-section Quick Order CTA Primary XL, My Account address edit Primary L). No decision above changed; the existing `.button` ecosystem expressed all four targets. The hero mapping in decision 5 ("mini-cart/hero M") is superseded: hero is Primary XL. Status: deployed, partially runtime-validated (contact-info and hero not rendered on staging). Record: `docs/active/figma-alignment.md` section 16.

### Update 2026-10-09 (Step 3b-3b)
Capabilities added without a parallel system: `.button--s` (24px), `.button--destructive` and `.button--icon-only` (all four sizes, radius 8), composed with the existing size/appearance modifiers (`a79f643`). First consumer: the simple-product card action (`f3438a8`), plus the card-overlay stacking fix (`5430547`). Decision 1 ("no new size modifier") is superseded for S: the Figma set uses S for the icon-only Destructive cart remove, so `.button--s` is public; `.button--sm` still means M. Staging validated; the real add-to-cart transaction is not yet exercised. Record: `docs/active/figma-alignment.md` section 17.

---

## ADR-023 — Toastify add-to-cart notice: keep DreamPoint's native-WooCommerce integration; no Suplementi migration

**Date:** 2026-10-09
**Status:** Accepted; refinement `34c80a1` deployed to staging, UI QA PASS. Simple-product AJAX add-to-cart E2E PASS on staging (2026-10-09).

### Context
A read-only comparison with the Suplementi theme (staging, HEAD `bb4e2b7`) was run to port its Toastify experience. DreamPoint already ships Toastify 1.12.0 as its own hardened fork (`js/toastify.min.js`: `textContent` rendering, `role="status"`/`aria-live`) with `css/src/toastify.min.css`. Add-to-cart requests are owned by WooCommerce's native `wc-ajax=add_to_cart` (`add-to-cart.min.js`); the only DreamPoint notice listener is `$(document.body).on('added_to_cart')` in `js/ajax-add-to-cart.js`, which shows the toast and opens the mini-cart. The Quick Order plugin reuses it through a synthetic `added_to_cart` after its own sync.

### Decision
1. No migration: Suplementi's custom `admin-ajax` endpoint and click handler are NOT copied. They would send a second request next to WooCommerce's own handler, cache a nonce in the page, and (as written) show a success toast on server failure (the JS reads `response.error` but the PHP error payload is `data.error`).
2. The hardened Toastify fork is kept; the stock Suplementi library is not substituted.
3. `dpAddToCart` / the `added_to_cart` listener stay authoritative; no new listeners, endpoints or triggers. Cart guards (`woocommerce_add_to_cart_validation`, `woocommerce_add_to_cart_quantity`), bucket visibility, ADR-007 reservation and checkout/ERP are untouched.
4. Refinement only: success text `Proizvod dodan u košaricu!` and close label `Zatvori obavijest` via PHP `esc_html__` in `dpAddToCart`; new optional `closeAriaLabel` in the fork (default `Close notification`); at <=767.98px bottom toasts get `margin-bottom: $dp-mobile-toolbar-height` (73px) so they sit 15px above the fixed toolbar (Toastify writes an inline `bottom`, so a constant margin is the CSS-only lift). Desktop placement, 3000ms duration and the close button are unchanged.

### Consequences / deferred
- Re-apply the three `closeAriaLabel` edits (documented in the file header) if the fork is ever replaced.
- Deferred: PDP max-quantity toast (`product-single.js`) still has the English close label; dormant `.is-danger` loses to the `!important` background in `toastify.min.css`; the toast offset must be revalidated if the toolbar height changes (it is derived from 14px + 44px + 14px + 1px).
- Validation: synthetic notifications first (1440/390/360/320, two-toast stacking, close, 3000ms timing), then a controlled real-click E2E (product 7897, admin session, viewport 929px): exactly one `wc-ajax=add_to_cart` POST (200), one `adding_to_cart`, one `added_to_cart`, one success toast with the Croatian text and close label, fragments and counter 5 to 6, mini-cart opened ~100ms after, no navigation or JS errors; the added line was removed through the native remove link and the baseline cart was verified restored. This confirms that WooCommerce's native handler plus the single DreamPoint `added_to_cart` listener yields one request, one event and one toast per click.
- Still unvalidated: guard/bucket negative paths, variable-product and out-of-stock branches, Quick Order AJAX integration, the mobile AJAX lifecycle (the mobile toast UI QA stands).

---

## ADR-024 — Homepage Segments Section: dynamic tiles from segment landing pages

**Date:** 2026-10-12
**Status:** Accepted; implemented and STAGING VERIFIED (commits `2582ea0`, `3e5928a`; focus ring `370a722`). Item 5 below was carried out on 2026-10-12: the legacy block code (`04077060`) and its ACF group plus JSON (`549ef5c`) are removed. Production has no DreamPoint deployment.

### Context
The homepage Segment tiles (Lifestyle / Toys / Outdoor, ADR-009 implementation) reused the generic `acf/featured-categories-section` block with a manually maintained ACF repeater (`main_categories`: image, label, custom link). That is a hand-kept copy of data that already exists: the three segment landing pages (`page-segment-landing.php`, `dp_page_segment_context`). `brand_segment` is an ACF select on `product_brand` terms and is not a taxonomy; there are no segment terms and no segment archives (ADR-009 rejected a new taxonomy). The Figma Segmenti design (`11149:69872`) adds a per-segment DP logo that has no existing data source.

### Decision
1. A dedicated block `acf/segments-section` (no fields) renders the segments dynamically; it is an additional block, not a rename of the old one.
2. Source = published pages with template `page-segment-landing.php` and `dp_page_segment_context` in `lifestyle|toys|outdoor` (`dreampoint_b2b_get_segment_pages()`); canonical order Lifestyle, Toys, Outdoor; a segment with no page or with more than one page is skipped (never picked arbitrarily). Name = page title, link = `get_permalink()`, background = the page's native Featured Image. No product or `product_brand` queries, so the visibility engine is not involved.
3. The three per-segment logos are static optimized PNG theme assets (`img/segments/{lifestyle,toys,outdoor}.png`), because the Figma logos are raster textured discs, not vectors. No ACF logo field, no new taxonomy, no new image size.
4. The homepage migration is a content change (page 17), done structure-aware via `parse_blocks` / `serialize_blocks` with an out-of-web-root backup; Git does not carry it, so it must be repeated per environment.
5. The legacy Featured Categories block, template, Sass/CSS and ACF group `group_68b81d764f72a` stay in place until the unused-components cleanup.

### Consequences / deferred
- Adding a fourth segment still needs a code change (the fixed key list, `dp_page_segment_context` choices, the logo asset) plus ACF choices — unchanged from ADR-009.
- Source photographs are only ~792x474 landscape, so the portrait tiles are soft on high-density screens; portrait originals are a content task.
- Lesson: Slick `initADA()` sets `tabindex="0"` on slide wrappers after the `init` event (duplicate tab stops); and `slick-init.js` must be versioned by its own mtime (`dreampoint_b2b_asset_ver()`), not by `_S_VERSION`.
- Not verified: real keyboard Tab and the visible focus ring, physical touch swipe (see `docs/active/figma-alignment.md` section 20). Legacy cleanup is NOT done.

## ADR-025 — Catalog menu panel rendered by PHP inside `li.cat-toggler` (no JS relocation)

- Date: 2026-10-16. Commit `ba67bcb` (staging verified).
- Before: `dreampoint_b2b_catalog_menu_desktop()` was echoed as a child of the header `<nav>` and `js/mobile-menu.js` moved it into `li.cat-toggler` on DOMContentLoaded (cause of the FOUC fixed in `299770a` by a pre-move CSS rule).
- Now: filter `walker_nav_menu_start_el` (`dreampoint_b2b_append_catalog_panel()`, `inc/nav-categories.php`) appends the panel right after the link of the `cat-toggler` item, only when the desktop `wp_nav_menu()` call passes `'dp_catalog_panel' => true` (header.php). The mobile menu-1 render passes no arg, so there is exactly one `#catalog-menu`. Desktop JS relocation and the `nav > .catalog-menu` pre-move CSS rule were removed.
- Retained: the mobile submenu (`.sub-menu.catalog-menu-mobile`) is still moved by `mobile-menu.js` (out of scope).
- Verified on staging (1440/768/390): panel in final DOM position in server HTML; hidden at rest and opens on hover/focus-within even with JS blocked; no flash with delayed JS; `aria-expanded` sync, Escape, Tab/Shift+Tab, no focusable links while hidden; mobile drill-down; no horizontal overflow; no console errors.
- Known pre-existing behavior (unchanged): Escape only dismisses when focus is inside the item; a pure mouse hover is not dismissed.

## ADR-026 — Single-option variation attributes: preselected and hidden on the PDP (2026-10-18)

- Rule: a variable-product attribute whose variations use exactly ONE value (WooCommerce `get_variation_attributes()`, i.e. values actually used by variations, not all configured terms) is preselected server-side and its row hidden. Multi-value attributes stay visible; dependent narrowing to one remaining value is deliberately NOT treated as single (no rows disappearing mid-selection).
- Implementation (staging `a68fed4`): filter `woocommerce_dropdown_variation_attribute_options_args` in `inc/woocommerce.php` sets `selected` + class `dp-single-option`; CSS in `sass/pages/shop-single.scss` hides the row via `:has()` (the last row keeps only the "Clear" link; link hidden when every attribute is single); `js/product-single.js` re-selects hidden values after WooCommerce "Clear" (`reset_data`). The select stays in the form, so WooCommerce variation resolution / add-to-cart are untouched. No importer change: the importer already writes `_default_attributes` for single-value attributes.
- Reference (JekaaStore, read-only audit): inline JS in `themes/dp/js/functions.php` (`hideSingleOptions`, enqueued at priority 9999) counts enabled non-empty `<option>`s per select and hides/auto-selects at <=1. Weaknesses not reproduced: hardcoded `pa_boja` exclusion, 100 ms `setInterval` value-restoring hack, `console.log`, hides rows when options narrow dynamically (and at 0 enabled options), flash of unhidden content until JS runs.
- Known limitations: no coverage for out-of-stock-only single value (behaves as native WC: stock message, no add-to-cart); when the last attribute is single and others are not, the "Clear" link sits in its own column.

### ADR-026 addendum — JekaaStore `pa_boja` exception audit (2026-10-18, read-only, no code change)

- Condition: `hideSingleOptions()` (JekaaStore `themes/dp/js/functions.php` ~1660, active copy `functions.php` ~1670) does `if (attributeName.includes('pa_boja')) { $row.show(); return; }` — the colour row is never hidden. Present in the oldest available backup (`functions.php.bak.20260616`); no comment, no git (theme is not a repository), no docs/memory mention.
- Confirmed facts: on Jekaa `pa_boja` is the only attribute of type `image` (colour swatch via the Themeisle/ThemeHigh swatches plugin); Model/Dob/Dioptrija/Volumen are plain `select`. A single-colour product (86397) renders the colour swatch visibly with the value selected while the single Model row is hidden. `js/product-variations-slider.js` drives gallery<->variation sync through `.thwvs_variation_pa_boja li` swatches. 93 of 100 variable products have a multi-colour `pa_boja`, 7 a single colour; the other attributes are mostly single-value.
- Not provable: the intent (no comment/history). Strongest hypothesis: UI/business choice — colour is visual product information and a swatch, so it stays visible even with one value. Not an ERP-structure protection (nothing ERP-specific in the code).
- DreamPoint: `pa_boja` is a plain `select` (no swatches plugin, no slider/swatch coupling), so the Jekaa technical reason does not apply. Current behaviour: single-colour products (6 on staging: 17718, 17713, 17697, 15883, 15877, 15871) hide the colour row; the colour then appears only in the product description text (e.g. "Latte"), not as a dedicated indicator. No functional problem (variation resolves, add-to-cart works).
- Recommendation: OWNER DECISION. Keep as is, or keep colour visible. Smallest implementation if wanted: in `dreampoint_b2b_single_option_attribute_args()` return early when `$args['attribute'] === 'pa_boja'` (one line; PHP-only, CSS/JS untouched). Not applied.

### ADR-026 addendum 3 — Quick Order: single-value attributes hidden from the option cell (2026-10-21)

- Quick Order has no attribute selectors: every variation is its own purchasable line (fixed `variation_id`, cart payload is `product_id` + `variation_id` only, attributes are resolved by WooCommerce server-side). The PDP rule therefore maps to display: an attribute with exactly ONE value across all variations of the product (and present on every variation) is omitted from the OPCIJA text of its lines; multi-value attributes stay visible. `pa_boja` stays visible (addendum 2). No selection state, no cart write, no extra REST request.
- Implementation (plugin `dp-b2b-quick-order` v1.0.35): `assets/src/single-option.js` (`singleValueAttributeKeys`), used by `ProductList.#optionHTML`; `get_variation_details()` adds an additive `key` (taxonomy, e.g. `pa_boja`) to each attribute pair. The line's accessible name keeps all attributes; selected-variation chips are unchanged. Narrowing by filters is not recalculated (same as PDP). Tests: `tests/variations/run-single-option-tests.mjs`.
- Limitation: a product whose only attributes are all single (one variation) shows just the catalog number in OPCIJA (unless it has `pa_boja`).

## ADR-027 — Binary stock display on the PDP: owner rule `p.stock { display: none; }` (2026-10-18)

- Business requirement (client, via owner): B2B partners see availability, never the exact quantity.
- Rule (`sass/pages/shop-single.scss`, compiled `css/pages/shop-single.css`, staging `932877c`): hides WooCommerce's `p.stock` line ("N na zalihi" / "Nema na zalihi"). The binary indicator is separate and unaffected: `.stock-status` ("Proizvod na stanju" / "Proizvod nije na stanju"), rendered by `content-single-product.php` and updated per variation by `js/variation-stock.js`.
- Verified on staging (desktop + 390 px, no console errors, no overflow): simple in-stock (18902), simple out-of-stock (18221, no add-to-cart), variable in-stock with variation selection (18323), variable out-of-stock variation (13617: "Proizvod nije na stanju", add-to-cart unavailable). No backorder or unmanaged-stock simple product exists on staging (not testable); `p.stock` for backorder would also be hidden, `.stock-status` would still show in/out only.
- LIMITATION — visual hiding only, quantity is still disclosed to anyone inspecting the page: (1) `p.stock` text remains in HTML; (2) variation JSON `data-product_variations` contains `availability_html` ("N na zalihi") and `max_qty`; (3) `input.qty[max]` equals the stock quantity; (4) `product-single.js` shows "Maksimalna količina: N" when the user exceeds `max`. Items 3-4 are the inherent cart-limit oracle (same as ADR-016 residual). Quantity-free output would need PHP filters (`woocommerce_available_variation` to drop `availability_html`/`max_qty`, `woocommerce_get_availability_text`, qty `max_value`/message handling) — broader, NOT implemented, requires separate owner approval.

### ADR-026 addendum 2 — `pa_boja` stays visible (owner-approved 2026-10-18)

- Owner decision: colour is useful product information, so `pa_boja` is never hidden, but is still pre-selected when it has a single value. Other single-value attributes remain hidden (unchanged); multi-value attributes untouched.
- Implementation (staging `3092fee`): `dreampoint_b2b_single_option_attribute_args()` (`inc/woocommerce.php`) now adds two classes: `dp-single-selected` (every single-value select: restored after WooCommerce "Clear") and `dp-single-option` (hides the row via CSS) — the latter is skipped for `pa_boja`. `js/product-single.js` reset handler now targets `.dp-single-selected`. CSS unchanged.
- Related fix (`1b0944e`): `js/variation-stock.js` also listens to `show_variation`. After "Clear" restores pre-selected attributes WooCommerce fires `reset_image` after `found_variation`, which removed the availability message while the variation stayed resolved and purchasable (visible on all-single products such as 17718).
- Verified on staging: 17718 / P-57634 (single colour + hidden single Model): colour visible, variation 17719, Clear keeps colour and variation and the "Proizvod na stanju" message; 17216 / P-57491 (multi colour, multi Dob, hidden Model/Dioptrija): pick Dob+Boja -> 17222, Clear resets visible selects only; 18323 earlier. Select2 on the single colour: combobox role, label "Boja", Enter opens, 390 px no overflow. Coverage gap: no staging product has a single colour plus a multi-value other attribute (logic identical). Known: the Select2 list of a single-colour select still offers the "Odaberi opciju" placeholder (pre-existing Select2/WC behaviour).

### ADR-027 addendum — Stock visibility closed; CSS-only solution approved (2026-10-18)

- Final business requirement (owner): B2B partners see whether a product is in stock or out of stock, but not the informational text with the exact number of units on the product page. This is a visual presentation requirement, not a requirement to prevent technical discovery of quantities (HTML, variation JSON, Store API, cart validation messages).
- Decision: the existing `p.stock { display: none; }` (commit `932877c`, staging verified) is sufficient and approved; `.stock-status` remains the binary availability indicator. Minimum/maximum order quantity rules, quantity input limits (`max`, `max_qty`), WooCommerce stock validation and error messages, cart, checkout and Store API stay unchanged.
- Superseded: the broader stock-confidentiality proposal from the 2026-10-18 read-only audit (stock-format override, `max_qty`/`max_value` removal, Store API maximum filter, generic cart messages) is NOT APPROVED / NOT REQUIRED under this scope. The audit findings in ADR-027 and the 2026-10-18 notes are kept as historical evidence only; technical visibility of quantities is an acknowledged limitation of CSS-only hiding, not an open defect.
- Status: COMPLETE — VERIFIED.

## ADR-028 — PDP shows only the native Description tab; ERP data audit for future tabs (2026-10-19)

**Status:** Implemented and verified on staging (`76e4162`, `657508c`, `4ecd403`, `a2750fb`). Staging only (no production environment exists).

### Context

`woocommerce/content-single-product.php` wrapped `woocommerce_output_product_data_tabs()` in `<div class="wc-tabs-section block" style="display: none;">` since the monorepo migration (recorded as a known pre-existing item in ADR-014). The whole tab section (Description, Additional information, Reviews) was hidden on every PDP. Figma PDP-01/02 (`11148:32726`, `11148:32859`) show the tabs Opis / Specifikacije / Sadrzaj paketa / Dokumenti and no reviews. Owner decision: show ONLY "Opis" now.

### Decision

- Inline `display: none` removed. The tab section is rendered through the native WooCommerce tabs API; the filter `dreampoint_b2b_product_tabs()` (`inc/woocommerce.php`, `woocommerce_product_tabs` priority 99) keeps only `description`. Additional information, Reviews and plugin-added tabs are removed from the PDP tab UI only; reviews/attribute data and WooCommerce settings are untouched (reviews are NOT disabled globally).
- Source: native long description (`post_content`) via WooCommerce's own description panel. WooCommerce registers the Description tab only when `post_content` is non-empty, and the template renders the `.wc-tabs-section` wrapper only if the tab output is non-empty, so a product without a long description shows no tab, no empty panel and no spacing (staging: 370 of 451 published products have a long description).
- Duplicate fix: no product has a `post_excerpt`, so `the_excerpt()` auto-generated a copy of the long description in the summary column. The `.short-description` block now renders only when `has_excerpt()` is true. The short description is never used as a substitute for the Opis tab.
- Mobile: the old rules that hid the tab list and panel at `sm-down` were removed (they would have hidden the only tab on mobile) and `js/tabs-dropdown.js` returns early when fewer than 2 tabs exist; the dropdown logic is preserved for future multi-tab use.
- Figma alignment (scoped to `#product-single-main .wc-tabs-section`; the shared `custom-tabs` mixin is untouched): 14px/600, 0.28px tracking, sentence case, 2px active underline, 28px gap to content, two-tone `:focus-visible` ring (the global `a:focus { outline: none !important }` swallowed any outline).

### ERP data audit (read-only; source `uncle-dev-importer` `AprosProvider` + staging DB SELECTs, 451 published products)

Payload: `articleList/get` returns exactly `articleId, code, title, barcode, wholesalePrice, vatRate, stock, classification, virtualArticle, visible, brandId, isNew, isSpecialOffer, description` (ADR-011 record, 2026-09-30, 10,186 articles) plus separate attribute endpoints (`articleAttributeList/get`, `attributeList/get`, `attributeItemList/get`, variation variants) and image endpoints. The provider maps `description` -> `set_description()` (`Importer.php:163`), ERP attributes -> WooCommerce global `pa_*` attributes (`AprosProvider.php:93-111,374-378`, `Importer.php:196-306`; attribute id 154 "Kolekcija" skipped), `barcode` -> `_global_unique_id`/`_erp_barcode`, `code` -> `_ARTICLE_CODE`. `ProductToImport::$attachments` exists but the provider never populates it; weight/dimensions/unit/min-quantity/package fields are never assigned.

| Potential tab | ERP source field | WooCommerce storage | Population evidence (staging) | Already displayed? | Recommendation |
|---|---|---|---|---|---|
| Opis | `articleList.description` | `post_content` | 370/451 non-empty; 240 plain text, 130 with HTML; median 621 chars; no variation descriptions | YES (this ADR) | DONE |
| Specifikacije | `articleAttributeList` (attribute title + item) | global `pa_*` taxonomies + `_product_attributes` | only 5 attributes exist: `pa_model` (303 products; 123 visible non-variation, e.g. "Adapter"), `pa_boja` (214, mostly variation), `pa_dob` (65; 3 visible non-variation, "ADULT"), `pa_dioptrija` (60, variation), `pa_volumen` (24, variation). Weight / length / width / height: 0 populated. 132/451 products have a visible non-variation attribute | Variation attributes appear in the variation selectors; non-variation ones are not shown (native Additional information tab removed) | PARTIAL: thin (model/age only, no technical specs, no weight/dimensions/material); a tab would be mostly empty. Real specs need an importer/ERP extension |
| Sadrzaj paketa | none in the payload | none | no package-contents/unit/multipack field imported (Figma "Multipack 1 kom" has no ERP source here) | n/a | NOT AVAILABLE; needs an ERP field + importer extension |
| Dokumenti | none in the payload | none (`ProductToImport::$attachments` unused) | 0 PDF/document attachments in the Media Library (only png/jpeg/webp/svg) | n/a | NOT AVAILABLE; needs an ERP document endpoint/field + importer + attachment handling |

UNKNOWN: whether the ZGData API offers additional endpoints/fields beyond those the importer reads. The statements above cover only the payload documented/observed in ADR-011 and the importer code.

### Consequences / deferred

- Specifikacije, Sadrzaj paketa and Dokumenti tabs are NOT implemented; adding any tab means extending `dreampoint_b2b_product_tabs()` deliberately. Reviews stay out of the PDP UI (no Figma design); WooCommerce review settings unchanged.
- ADR-014 known item 1 (tabs hidden) is superseded by this ADR. The "review tabs zero-size" follow-up (status.md 2026-10-19) is explained by the same inline style.
- Auto-excerpt duplication is fixed only on the PDP summary; archive/search excerpts are untouched.

## ADR-029 — ACF homepage block groups: accordion pattern, DB mirror, removal of two `block == all` legacy groups (2026-10-19)

Status: implemented on staging (code `4465f09`, DB operations below). Staging only; no production environment exists.

### Findings (inventory, staging, read-only first)

- **Reference pattern (owner's edit, committed `88060c0`):** group `Block / <Name>` whose first field is an Accordion labelled `<Name> Block` with **Open = on**, then the block's content fields. Hero (`group_6731e19dcb10e`, post 90), About (`group_6731e355488f8`, 70), Contact Form (`group_673355f944b28`, 85), Testimonials (`group_681c7051a4961`, 97) are DB-backed and mirrored to `acf-json/`; the owner's admin saves had left the staging tree dirty (four JSON files, plus two untracked exports) - those exports were byte-preserved, committed, and are the canonical state.
- **There never was a "Segments Section" field group.** `acf/segments-section` has no fields (content comes from the Segment Landing pages). The editor showed "Blogs Block" instead because of the next finding.
- **Root cause of the inconsistent block sidebars:** the legacy groups `Block / About Global` (`group_67d3f63feb822`, DB post 78: accordion + a note about "Company Info Intro") and `Block / Blogs` (`group_67373135351da`, post 81: accordion, a `title` text field, a note about listing five blogs) used location `block == all`, so they were attached to EVERY ACF block. Evidence of non-use: no reference to either group, any of their field keys, "About Global" or a blogs block anywhere in theme, importer, Quick Order or mu-plugins; no `acf/blogs-*` block is registered; no postmeta/options reference their keys; `title` values stored under the Blogs field key exist only as `""` in revision 23509 (segments + company-features blocks, no data in the live homepage); no template reads them (`blocks/templates/*` read `title` through each block's own group). Both were created from the JekaaStore theme and never used here.
- **Why Featured Section / Company Features were missing from the ACF Field Groups list:** they (and Brands, Contact Info, Featured Brand, Latest / Discounted / Featured Products and other groups) are registered ONLY by Local JSON (`ID = 0`, no `acf-field-group` post). The frontend works from the JSON file; the admin list shows DB posts only (JSON-only groups sit under "Sync available"). Not a synchronisation bug: a hand-authored JSON group has no DB mirror (see "Field Group Creation Doctrine" in the theme `CLAUDE.md`).

### Changes

- `acf-json/group_6a6c692a74f90.json` (Featured Section): accordion `Featured Section Block` Open = on (keys and field names unchanged).
- `acf-json/group_6a704ca216bac.json` (Company Features): new accordion `Company Features Block` (open) added in front; the existing message field keeps its key and is relabelled "Note".
- NEW `acf-json/group_8f6a94b081cd4.json` `Block / Segments Section` (location `block == acf/segments-section`): accordion `Segments Section Block` (open) + a note, no data fields (same note-only pattern as Company Features / Contact Info).
- **Database (not representable in Git):** (1) the three groups above were imported into the DB with `acf_import_field_group()` and JSON writing disabled (no DB post existed for these keys, so no duplicate risk): posts **23510** (Featured Section, 5 fields), **23516** (Company Features, 2), **23519** (Segments Section, 2); verified `same_fields = true` against the JSON, DB `post_modified` newer than JSON `modified` (no "Sync available"), 0 duplicate post names across groups and fields. (2) `acf_delete_field_group()` removed posts **78** (2 field posts) and **81** (3 field posts); their untracked JSON exports were deleted; no group with `block == all` remains.
- **Backups (staging):** `/home/dreampoint.b2b/dp-backups/acf-homepage-admin-20261019/` - `staging-dirty-tree.patch`, `acf-json-dirty/` (the six exports incl. the two deleted groups), `db-groups-78-81.json` (posts + meta of both groups and their field posts), `db-group-list-before.json`. Restoring a deleted group = re-import its JSON from the backup (`acf_import_field_group()`).

### QA and remaining state

- ACF Field Groups list: 13 groups, no duplicates; the new/updated groups are listed; About, Hero, Contact Form, Testimonials, Featured Section, Company Features, Segments Section show Accordion Open = on. Page editor (homepage 17, blocks only selected, nothing saved): Segments Section, Company Features and Featured Section show only their own accordion/fields, no "Blogs Block" / "About Global". Homepage frontend identical at 1280/390 (segments, brands, Quick Order panel, USP row, contact card with photo, links); no broken images or console errors.
- **Still JSON-only (not in the admin list, outside this task's scope; same cause):** Brands, Contact Info, Discounted Products, Featured Brand, Featured Products, Latest Products block groups and the Brand / Komercijalist / FAQ / Page Intro / Features Content / Login-Register / Customer Bucket / importer groups. Brands, Contact Info (message-only header) and the four product/brand block groups with accordion Open = off still differ from the pattern. Decision pending whether to mirror/normalise them the same way. `Form IDs fields` (DB post 34) shows "Sync available" (pre-existing, not touched).

### Addendum (2026-10-19, later) — remaining six block groups; Testimonials and Shared Surface Context audit

- **Six groups aligned and mirrored (code `df5328c`, staging):** Brands (`group_690a1b2c3d4e5`) and Contact Info (`group_6a9021b3c9d01`) got a new leading accordion `Brands Block` / `Contact Info Block` (Open on; Contact Info's former message header keeps its key and is relabelled "Note"); Featured Brand (`group_6a6b2b32514db`), Latest Products (`group_68b826ae66979`), Discounted Products (`group_6a6c6c9485b48`) and Featured Products (`group_6835b1c19422b`) kept their accordions, only Open changed 0 -> 1. Group keys, titles, location rules, content field keys/names and return formats unchanged. None had a DB post (JSON-only, `ID = 0`), so they were imported one by one with `acf_import_field_group()` and JSON writing disabled (no global sync): posts **23522** (Brands), **23526** (Contact Info), **23529** (Featured Brand), **23535** (Latest), **23539** (Discounted), **23543** (Featured Products); each verified `same_as_json = true`, DB `post_modified` newer than the JSON `modified`, 0 duplicate post names across groups and fields, staging working tree clean. Backup: `/home/dreampoint.b2b/dp-backups/acf-six-groups-20261019/` (JSON copies of the six groups plus Testimonials and Shared Surface, and `inventory-before.json`). Rollback: delete posts 23522..23543 with `acf_delete_field_group()` (JSON stays authoritative) and restore the JSON copies from the backup.
- **Block / Testimonials (`group_681c7051a4961`, DB post 97) - RETAINED, required infrastructure of a registered but currently unused block.** It is the field definition (title, `items` repeater: testimonial / author / rating) of `acf/testimonials-section`, which is fully implemented (`blocks/testimonials.php`, `blocks/templates/testimonials.php` reading `get_field('items')`, `sass/blocks/testimonials.scss`, the `testimonials` entry in `inc/enqueue-block-styles.php`, the Slick gate `$slider_blocks` in `functions.php` and the `.testimonials-slider` init in `js/slick-init.js`). Present since the repository migration, from the original theme block set. Usage on staging: 0 posts/revisions/reusable blocks/templates contain the block, no postmeta or option references its field keys, no stored testimonial data; the Figma HOME/segment designs have no testimonials section. Deleting only the ACF group would leave a registered block with no editable fields, and removing the block itself (registration, template, SCSS/CSS, Slick init, enqueue map) was not requested and is a separate, wider cleanup: not done. Open decision for the owner: keep the block in the library or retire the whole block.
- **Shared Surface Context (ADR-009) (`group_dp_shared_surface`, DB post 23367) - RETAINED, architectural dependency; ADR-009 untouched.** ADR-009 is not an ACF group: it defines the `dp_visibility_context = 'shared_surface'` query primitive (`inc/visibility/class-query-filter.php`, Phase A) and its callers (Phase B, `inc/homepage-segments.php`). The ACF group is Phase B's configuration: `dp_page_segment_context` (select homepage / lifestyle / toys / outdoor) plus the per-page toggles `dp_show_discounted_products` and `dp_show_featured_products`. Active consumers: `dreampoint_b2b_is_shared_surface()` / `dreampoint_b2b_shared_surface_query_args()` (called by `blocks/templates/brands.php`, `latest-products.php`, `discounted-products.php`, `featured-products.php`), `dreampoint_b2b_segment_section_enabled()` (the two toggles), the segment helpers used by the Segments Section (ADR-024) and `page-segment-landing.php`. Stored data: `dp_page_segment_context` is set on the homepage (17, revision 23508) and the three segment pages (23369 lifestyle, 23370 toys, 23371 outdoor); the toggles are unset (default enabled). Removing the group would drop the editor UI for the page context and break segment/homepage visibility behaviour, so it is not removable; no superseding ADR exists.
- **QA:** ACF Field Groups list 19 groups (was 13), all six present once, accordions Open = on; block editor on the homepage (17) and Lifestyle (23369): Brands, Contact Info, Latest, Discounted and Featured Products blocks show their own accordion expanded, no "Blogs Block" / "About Global", no page errors; frontend homepage, `/lifestyle/`, `/toys/`, `/outdoor/` at 1280/390/360: sections present with unchanged heights (homepage segments 644, Quick Order panel 478, USP 4 items, contact card with photo), no broken images, overflow, failed requests or console errors. Featured Brand / Discounted / Featured Products render empty on these pages (no featured-brand block in the segment pages; discounted/featured empty by content). No pre-change baseline exists for the segment pages; the evidence of no regression is that field keys/names and block data references are untouched and the editor still lists the stored products. Hero and About are not used on any live page (only in old revisions), so they have no frontend to verify.
- **Remaining JSON-only groups (not in the admin list, not part of any block pattern):** Brand Fields, Features Content, Login/Register Settings, Komercijalist Settings, FAQ Settings, Page Intro Text, Customer Bucket Rules and the importer groups (Uncle Dev Importer, Product Import Settings, Product Category Settings, Product Variation Images); `Form IDs fields` (DB post 34) keeps its pre-existing "Sync available". Mirroring them follows the same procedure if wanted.

### Addendum (2026-10-19, last) — Segments Section editor presentation (code `9ee2871`)

- **Symptom:** in the page editor the Segments Section showed the whole unstyled tile list (about 1180 px of full-size images and links) while every other ACF block shows a compact "<Name> Section / Edit Block" card (about 124 px) with its fields in the Block sidebar.
- **Root cause (not the ACF group):** Segments Section was the only block registered with `'mode' => 'preview'` (all 18 others use `'edit'`; it also carried an `example`), and the homepage content stores the same value (`<!-- wp:acf/segments-section {"name":"acf/segments-section","align":"wide","mode":"preview"} /-->`), which wins over the registration default. In preview mode ACF renders the block template inside the editor, where the theme CSS is not loaded. The sidebar was already consistent since ADR-029 (accordion "Segments Section Block", Open on, note). The block has no editable data fields by design: tiles come from the Segment Landing pages (ADR-009 Phase B / ADR-024), so the note-only group stays as is.
- **Fix:** `blocks/segments-section.php`: `'mode' => 'edit'`, `example` removed (new instances behave like the other blocks; the frontend does not depend on the mode). `js/admin-segments-block-mode.js` + `dreampoint_b2b_enqueue_segments_block_mode()` in `inc/homepage-segments.php`: the official `blocks.getBlockAttributes` filter maps a saved `"mode":"preview"` of `acf/segments-section` to `edit` while the editor parses the content, so existing pages open compact without any database write (stored content still says `preview` until the owner saves the page, then it becomes `edit`; the page opens as not dirty). Backup of the two staging PHP files: `/home/dreampoint.b2b/dp-backups/segments-block-mode-20261019/`. No ACF JSON or DB change.
- **Autosave finding (read-only):** the "autosave more recent than the version below" notice is benign: autosave 23509 (author 1, 2026-10-19 10:47:58) has byte-identical content to the live post 17 (modified 10:15:12); it was created by earlier editor sessions. It was not restored, deleted or published, and the page was never saved; live content and autosave are unchanged after this task.
- **QA:** homepage editor: Segments Section loads in `edit` mode, 5 of 5 blocks valid, page not dirty at load or after selecting blocks, 0 invalid blocks, 0 console/page errors; canvas card "Segments Section / Edit Block" like Brands, Featured Section and Company Features; sidebar: Segments Section Block accordion open + the existing note, no duplicate controls. Frontend homepage and `/lifestyle/` at 1280/390/360 unchanged (segments 644 / 626 / 581 px, three tiles linking to `/lifestyle/`, `/toys/`, `/outdoor/`, images loaded, no overflow, no failed requests, no console errors; the Lifestyle landing still renders Latest Products through the ADR-009 shared-surface path). Note: after a deploy OPcache can delay new PHP (and the editor script) by up to 60 s.

### Addendum (2026-10-19, final) — persisted Segments Section mode normalised, runtime workaround removed (code `04f3bc8`)

- **Original serialized attribute (homepage post 17, the only live occurrence):** `<!-- wp:acf/segments-section {"name":"acf/segments-section","align":"wide","mode":"preview"} /-->`. Scope check (read-only, whole `wp_posts`): only post 17 and three historical copies contain `acf/segments-section` (revisions 23412 and 23508, autosave 23509, all `preview`); no segment landing page, `wp_block` / template, postmeta or option contains it.
- **Method:** one-time, scoped `wp_update_post()` (WP-CLI `eval-file` run as `--user=admin`, `post_content` only; kses verified not to alter the content). Dry run first: exactly 1 occurrence of the needle, the block comment string `"mode":"preview"` -> `"mode":"edit"` (length 1096 -> 1093), 9/9 parsed blocks identical except `acf/segments-section` attr `mode`, `serialize_blocks(parse_blocks())` round-trips byte-identical before and after. Not saved through Gutenberg; no search-and-replace over the database.
- **Persisted result (independent check):** content md5 `f8b4c06d...` -> `c4765ea9...`; the only attribute difference is that `mode`; post author/status/title/slug/parent/dates/template unchanged, postmeta unchanged (no keys changed), `post_modified` 10:15:12 -> 10:57:42 (expected), one new revision 23546 (content = new). Pre-existing revisions 18, 1744, 1748, 1783, 23375, 23412, 23508 untouched. The admin's autosave 23509 (identical to the previous live content and to revision 23508) was removed by WordPress core once the post had been saved newer than it; no unique content was lost (backup + revision 23508 hold it). Historical revisions still say `preview`; restoring one of them would bring the legacy mode back (the block would then show the large preview until re-saved).
- **Backup / rollback:** `/home/dreampoint.b2b/dp-backups/segments-mode-persist-20261019/` - `post-17-content-before.html` (exact original `post_content`), `post-17-before.json` (post row, all meta, revision/autosave inventory with md5). Rollback: `wp_update_post()` of post 17 with the saved HTML (or restoring revision 23508 in the editor); the registration `'mode' => 'edit'` would then need the removed filter again to keep the compact card.
- **Workaround removed:** `js/admin-segments-block-mode.js` and `dreampoint_b2b_enqueue_segments_block_mode()` deleted; `blocks/segments-section.php` keeps `'mode' => 'edit'`. No other content depends on the filter (checked above).
- **QA:** homepage editor reloaded without the filter (script absent, `hasFilter` false): Segments Section loads in `edit` mode, 5 of 5 blocks valid, page not dirty at load or after selecting blocks, no recovery/warning notices (the "autosave more recent" notice is gone), compact "Segments Section / Edit Block" card, accordion "Segments Section Block" open with the note, 0 console/page errors; the page was never saved from the editor. Frontend homepage and `/lifestyle/` at 1280/390/360 unchanged (segments 644 / 626 / 581 px, tiles to `/lifestyle/`, `/toys/`, `/outdoor/`, images loaded, no overflow/failed requests/console errors; Latest Products on Lifestyle still renders through ADR-009).


## ADR-030 — Targeted Sass deduplication: `text-sm-medium` mixin, redundant `.slider-grid .section-heading h2` removed (2026-10-19)

Status: implemented, staging verified (code `75e46ff`). Follows the "SCSS (reuse before duplication)" rule in `CLAUDE.md`. No visual change was authorised or produced.

- **A — `text-sm-medium` (new, `sass/theme/_mixins.scss`):** Montserrat 500, 12px / 20px, letter-spacing -0.132px. The audit note said "14/20"; the real value is 12/20. Consumers (4): `company-features.scss` `.features-text p`; `contact-info.scss` `.commercialist > p`, `.commercialist-contact p`, `.contact-details p`. Only typography is in the mixin; colour, margins, `max-width` stay local. Not merged (different weight or inherited from the container): `.features-text` / `.contact-content` containers (no weight), `h3`/`.features-title` (700), `.commercialist h2` (700, 16px), `.commercialist-name` / `strong` (700). Compiled diff: only `font-family: "Montserrat"` added explicitly on the three contact-info `p` rules, which already inherited the identical family from the global `p` rule.
- **B — Montserrat 16/20 (-0.176px): NOT consolidated.** `.contact-info .commercialist h2` is weight 700, `.featured-section .container > p` is weight 500, so they are not the same style; one consumer each, a parameterised mixin would be speculative. Revisit only if a third 16/20 consumer appears.
- **C — `.slider-grid, .blogs .section-heading h2 { @include heading-3 }` (`_content.scss`, formerly ~line 1231): redundant, removed.** It emitted declarations byte-identical to `.section-heading h2` (including the 1199.98 / 767.98 breakpoints); the only difference was specificity (0,2,1 vs 0,1,1). A scan of every compiled rule ending in `h2` (style.css, blocks, pages) found no competing rule with specificity between the two that matches `.section-heading h2` (the wysiwyg `h2:not(.section-heading h2 ...)` rules exclude it explicitly). The surrounding `.slider-grid, .blogs .section-heading` flex/`a.link` rules were kept (they have an independent purpose, notably `justify-content: center` on sm-down). Note: `.blogs` no longer appears in any template (dead selector; left untouched, out of scope).
- **D — distinct Petrona styles: confirmed, no change.** `shop-single.scss` `h3` (500, 18/22, centred), `_header.scss` search input (200, 48px), `featured-section.scss` `h2` (600, 64/72), `.brands h2` (600, 40/normal, sentence case) and `contact-form.scss` `.contact-intro h2` (600, 40, line-height 1.2) all differ in weight, size or line-height and have one consumer each.
- **Verification:** fresh `sass` build before the change was byte-identical to the committed `style.css` / `css/blocks/*.css`; after the change only `style.css` and `css/blocks/contact-info.css` changed (expanded diff = exactly the removed rule + three `font-family` lines). Staging browser QA (authorised existing session), computed-style + box-size hashes before vs after at 1280 / 768 / 390 / 360 on `/`, `/lifestyle/` (2 `.section-heading` blocks), `/akcija/` (no blocks, empty) and a product page (`#recomended-products` heading): all identical for headings, `.brands`, `.contact-info`, `.featured-section`; 0 horizontal overflow, 0 console errors. Not testable on staging: `.company-features` is not placed on any page (mixin proven by compiled-CSS identity: `company-features.css` unchanged byte-for-byte). The product-page `#recomended-products` container hash differed at 360/390 only (slick slider state/timing); its heading and `.section-heading` hashes are identical and repeated measurements of the new state were stable.
- **Rollback:** staging backups in `/home/dreampoint.b2b/dp-backups/sass-dedup-20261019/` (`style.css`, `contact-info.css`, `contact-info.scss`); or `git revert 75e46ff`.


## ADR-031 — Shared slider navigation in `.section-heading` and Figma "Heading 2 / Medium" section titles (2026-10-19)

Status: implemented, staging verified (code `1e35eee` + `bb540a1`). Reference architecture: Suplementi staging/local theme (read-only; local HEAD = staging HEAD `12e4a9e`).

- **Suplementi reference (read-only):** `blocks/templates/latest-products.php:19-29` puts `<div class="nav-btns"><a class="slick-prev btn-prev"><a class="slick-next btn-next"></div>` inside `.section-heading` after the `h2`; Slick is initialised with `arrows: false` through `initializeSlickSlider(slider, parent, opts)` in `js/slick-init.js:109-149`, which binds `.btn-prev/.btn-next` to `slickPrev/slickNext` and shows/hides `.nav-btns` from the CURRENT `slidesToShow` (re-evaluated on the Slick `breakpoint` event). Layout: `sass/components/_content.scss:126-150` (`.section-heading` flex, `.nav-btns` flex/gap 24) and `:308-335`/`:1026-1048` (`.slider-grid` slide stretching, `.slick-list` mobile `padding-right: 25%` peek). DreamPoint already shared this SCSS (same framework origin); the difference was JS/markup: DreamPoint product sliders used Slick's own absolutely-positioned arrows (+ dots below 992px) with a once-evaluated `slidesToShow` helper only for blog related posts.
- **Shared markup:** `template-parts/slider-nav.php` (`<button type="button" class="slick-prev btn-prev|slick-next btn-next" aria-label>` in `.nav-btns`). Consumers: `blocks/templates/{latest-products,discounted-products,featured-products,bestseller}.php`, `woocommerce/content-single-product.php` (`#recomended-products`), `woocommerce/cart/cross-sells.php`, `template-parts/content.php` (blog related posts). Latest/discounted/bestseller render it only when the query has products.
- **Shared JS (`js/slick-init.js`):** `productSliderOptions` (arrows/dots off, 4/3/1 slides), `bindSliderNav()` (per block instance; hides `.nav-btns` when `slideCount <= current slidesToShow`; sets `.slick-disabled` + `aria-disabled` on prev at the start and next at the end; re-syncs on `reInit afterChange breakpoint`) and `initNavSlider()` (finds `.section-heading .nav-btns` inside the closest `.slider-grid, .cross-sells, .related-posts`, so two blocks of the same type on one page - Noviteti + Akcija on a segment page - never share state). Replaces `initializeSlickSlider()`. `.gallery-slider` (no heading) keeps `slickDefaults` with Slick's own arrows; segments, testimonials, notification and hero sliders are untouched.
- **Focus:** global `button:focus { outline: none !important }` removed the native ring, so `.section-heading .nav-btns .slick-prev/.slick-next:focus-visible` has `box-shadow: 0 0 0 3px $dp-color-focus-ring-primary`.
- **Typography (Figma proves unification):** DreamPoint Figma `HOME-01` (`11148:31626`) titles Noviteti (`11148:31702`), Akcija (`11148:31709`), Istaknuti proizvodi (`11148:31716`), PDP "Povezani proizvodi" (`11148:32830`) and HOME "Naša zastupništva" (`11148:31573`) all use "Heading/Heading 2/Medium" = Petrona SemiBold 40px, line-height normal, tracking 0, sentence case. New mixin `heading-2-medium` (`_mixins.scss`; 32px below 768px is an inferred step) with two consumers: `.section-heading h2` (was `heading-3`: Petrona 400 32px uppercase 0.04em) and `.brands h2` (colour stays local). `heading-3` keeps its other consumers. Figma depicts NO arrows and NO "Pogledaj sve" on the product rows; the navigation is an owner-requested implementation (Suplementi pattern), the "Pogledaj sve" links stay commented out (unchanged).
- **Brands decision:** the Brands slider is a continuous autoplay marquee (`variableWidth`, `arrows: false`, no navigation). `.slider-grid` is the product-card-slider contract (slide stretching, `overflow: unset`, mobile `.slick-list { padding-right: 25% }` peek for the 1-up card slider), so the marquee no longer carries it; it keeps the shared `.section-heading` wrapper (consistent heading spacing 40 / 24px) from `a8dd085`. No padding override was needed.
- **Not testable on staging (no data/instance):** featured-products, bestseller (block also missing from the `$slider_blocks` allow-list in `functions.php:279`, pre-existing), cart cross-sells, blog related posts (only "Hello world" exists). They use the same partial + helper; PHP lint and JS syntax pass. Documented gap, not verified in a browser.
- **QA (staging, authorised session, 1280 / 768 / 390 / 360):** lifestyle page (2 blocks): headings 40px / 32px, 600, sentence case; nav buttons 32px (24px on mobile), right-aligned in the heading, 0 native Slick arrows; prev disabled at start, next disabled at last position (1280: 6 slides / 4 shown -> index 2; 768: 3; 390/360: 1-up, index 5), transform verified (`-276px`, `-552px`); Akcija (empty) renders no nav; PDP recommended: nav hidden at 1280 (4/4), visible at 768/390/360; Brands: `padding 0`, full width, heading 40 / 32px; 0 horizontal overflow, 0 console errors.
- **Rollback:** backups `/home/dreampoint.b2b/dp-backups/slider-nav-20261019/`; or `git revert bb540a1 1e35eee`.

### Addendum (2026-10-19) — ADR-031 verification pass (code `eb4546d`)

- **Owner decisions:** removing `slider-grid` from Brands approved (marquee, not a product-card slider); desktop navigation arrows stay transparent glyphs (no filled squares).
- **Bestseller / Slick loading — confirmed latent defect, fixed.** `dreampoint_b2b_needs_slick()` (`functions.php`) loads Slick CSS + JS only on the front page, posts, blog, PDP, or singular pages whose `post_content` contains an allow-listed ACF block. `acf/bestseller-section` is a registered, insertable block (verified on staging: `acf_get_block_type` and the WP registry both register it; `dreampoint_b2b_collect_block_names()` returns `acf/bestseller-section`) but was missing from `$slider_blocks`, so a page with only that block would get no Slick: products unstyled/stacked and the new `.nav-btns` visible but inert. One line added to the allow-list; verified read-only on staging with an in-memory post (`needs_slick` true for a Bestseller-only page, false for a page without slider blocks). No post on staging contains the block (searched), so there was no live symptom. No CSS map entry is needed (the block has no block stylesheet).
- **Focus ring corrected:** the first ring used `$dp-color-focus-ring-primary` (`#c7d7fe`, ~1.5:1 on white) which is too faint for a borderless icon button. Now the two-tone ring used elsewhere: `0 0 0 2px $dp-color-bg-primary, 0 0 0 4px $dp-color-text-primary` on `.section-heading .nav-btns .slick-prev/.slick-next:focus-visible`.
- **Code review of consumers (no change needed):** per-instance binding via `closest('.slider-grid, .cross-sells, .related-posts')` (PDP `#recomended-products` is a `.slider-grid`; cart cross-sells is `.cross-sells`; related posts `.related-posts`); `slick-initialized` guard; empty / short sliders hide `.nav-btns` (`slideCount <= slidesToShow`, e.g. PDP 4 of 4 at 1280); latest/discounted/bestseller render the partial only when the query has products; the PDP "no related products" fallback is a single child, so the nav hides. Buttons are `<button type="button">` with `aria-label`; disabled ends use `aria-disabled` + `.slick-disabled` and stay focusable (focus is not lost at the first/last slide).
- **Browser QA limits (explicit):** the extension-controlled tab is `visibilityState: hidden` / `document.hasFocus() false`, so physical pointer and keyboard input never reach the page (only synthetic `pointermove` arrived; `mouse.down/up`, `keyboard.press`, `locator.click({force:true})` produced no trusted click/keydown) and CSS transitions/`:focus-visible` do not behave like a foreground tab. Therefore NOT verified: real pointer clicks on the arrows (only `elementFromPoint` hit-testing, which returns the button, plus DOM `.click()`), real Tab navigation, and the applied computed focus ring after the last change (the new rule text is verified in the served stylesheet via CSSOM; the previous ring colour was verified as applied with `transition: none` under `focus({focusVisible:true})`). Runtime resize between breakpoints was exercised with `setViewportSize` + a synthetic `resize` event (show 4 -> 3 -> 1 -> 4, disabled state re-synced, `cur` clamped to 2 when returning to 4-up) but is timing-flaky in a hidden tab.
- **Multiple instances:** staging has only one populated product slider per page (`/lifestyle/` Noviteti; Akcija is empty), so isolation was checked with a synthetic in-memory harness (two extra copies of the real server markup injected into the page, `slick-init.js` re-run; nothing persisted): original / A / B moved independently and each pair of buttons only affected its own slider. Not staging data.
- **Not browser-tested (no staging instance):** featured-products, bestseller, cart cross-sells, blog related posts (code-reviewed only).

### Addendum (2026-10-19) — disabled slider arrow hover (code `39d4f08`)

- **Root cause:** `vendors/slick.scss` `.slick-prev:hover, .slick-next:hover` sets `color: #fff; background-color: $dp-color-btn-primary-hover` (#1f2a37) at every width, while the later, equal-specificity `.slick-disabled` rule only changes `color` (30% alpha: dark `rgba(13,18,28,.3)` outside 992-1199px). Hovering a disabled arrow therefore kept the 30% dark glyph (disabled `color` wins by source order) but added the dark hover fill, so the glyph vanished. Reproduced pre-fix (hover emulated in source order): 1280 / 360 `rgba(13,18,28,.3)` on `rgb(31,42,55)`.
- **Fix (scoped, no `!important`):** `sass/components/_content.scss`, `.section-heading .nav-btns .slick-prev/.slick-next { &.slick-disabled:hover { background-color: transparent; @include bp(lg) { background-color: $dp-color-btn-primary-bg } } }`, i.e. the resting background of the base rule, so a disabled arrow looks identical hovered or not. Vendor slick rules and other sliders (PDP gallery, notification bar, hero, testimonials) are untouched; enabled hover/focus unchanged.
- **Verification (computed, hover emulated by cloning each `:hover` rule as `.__hv` immediately after the original, same source order and specificity; physical hover is not possible in the hidden extension tab):** disabled hover == disabled normal at 1280 / 1100 / 768 / 390 / 360 (e.g. `rgba(13,18,28,.3)` on transparent; 1100: `rgba(255,255,255,.3)` on `#0d121c`); enabled hover unchanged (`#fff` on `rgb(31,42,55)`); no overflow, no console errors.
- **Closeout (2026-10-20): disabled-arrow hover bug — CLOSED / VERIFIED.** Manual staging hover verification: PASS, confirmed by the project owner ("Sve je u redu, proverio sam") with a physical pointer, which resolves the hidden-tab limitation noted above for this fix. Implementation commit `39d4f08`, documentation commit `685644b`; staging runs `39d4f08`. No further code changes required. Scope of the confirmation is the disabled-arrow hover only: other slider instances, keyboard navigation and runtime resize were not separately reported as manually tested, and earlier documented QA limitations stand.


## ADR-032 — Registration / password-setup lifecycle corrections: recovery endpoint exempt from the approval gate, clearer registration and approval e-mails (2026-10-20)

**Status:** implemented on staging (`908f6b8` + `fd15dd6`), verified at code/logic level only; the live pending-user flow is NOT E2E verified (staging `DP_BYPASS_APPROVAL=true`, no real pending account, no e-mail sent). Complements ADR-018 (approval model) and the 2026-10-20 read-only lifecycle audit; earlier text is unchanged.

**Audit findings this ADR acts on:** registration generates the password (`woocommerce_registration_generate_password=yes`, no password field), auto-logs the customer in and e-mails a native set-password link (WordPress key, `password_reset_expiration` = 86400 s, no filter overrides, single-use, a new key overwrites the old one). Approval (`approved` meta) and password setup are independent, and the approval e-mail carried no password guidance. `dreampoint_b2b_restrict_unapproved_access()` redirected every logged-in pending request except `approval-pending`, so a customer still logged in from registration who opened the set-password link in the same browser was bounced to `/approval-pending` before the form rendered.

**Decision A — gate exemption (`inc/registration-approval.php`):** after the `approval-pending` check the gate returns when `dreampoint_b2b_is_password_recovery_page()` is true (`is_account_page() && is_wc_endpoint_url('lost-password')`, the helper already used for the standalone AUTH screens). Only that endpoint is exempt: request form, "e-mail sent" screen, `?show-reset-form` / `action=newaccount` form. WooCommerce still performs key/cookie/bridge-token validation, nonces, expiry and reset processing (POST handling runs on `wp_loaded`, before the gate). No custom handler, token storage or broad My Account exemption. The recovery endpoint renders only the recovery screens (standalone AUTH layout, no header/footer/dashboard intro, see section 46 of `figma-alignment.md`). The success redirect (`my-account/?password-reset=true`) is intentionally NOT exempted (that would expose the dashboard): a logged-in pending customer who sets a password lands on `/approval-pending` without the "password reset" notice.

**Decision B — approval e-mail (`dreampoint_b2b_send_approval_email()`):** one added paragraph: customers who have not set a password can request the link through the native Lost Password page (`wc_lostpassword_url()`), customers who did simply sign in. No new reset key is generated at approval (it would invalidate an unused earlier link); approval state, handlers, roles and ERP code are untouched.

**Decision C — registration e-mail (`woocommerce/emails/customer-new-account.php`, new helper `dreampoint_b2b_password_link_validity_label()` in `inc/emails.php`):** below the native set-password link: "Poveznica vrijedi 24 sata i može se upotrijebiti samo jednom. Ako istekne, novu možete zatražiti ovdje: Izgubili ste lozinku?". The validity is read from the same `password_reset_expiration` filter WordPress uses (Croatian plural: 1 sat, 2-4 sata, 5+ sati), so the text follows the real expiry. Key generation is still the native `WC_Email_Customer_New_Account::generate_set_password_url()`; no extra e-mail.

**Verification:** `php -l` on all changed files. Logic harness (stubs, not E2E) loading the real gate with simulated state: a pending logged-in user passes on the four `lost-password` states (form, `action=newaccount` link, `show-reset-form`, `reset-link-sent`) and on `approval-pending`; is redirected to `/approval-pending` on the dashboard, orders, edit-account, shop, product, cart, checkout, quick-order and homepage and on a non-account URL that merely contains "lost-password"; approved users and guests are unaffected. Label helper: 24 h -> "24 sata", 1 -> "1 sat", 5 -> "5 sati", 21 -> "21 sat", 22 -> "22 sata". Read-only render of the registration template on staging with a dummy URL (no send, no real key): new paragraph present and correct. Browser regression (anonymous + admin): login, registration, lost-password form and confirmation, approval-pending and the normal dashboard render as before. NOT verified: the real pending flow with the gate active, the actual e-mails (the approval e-mail is built inline and was not rendered), expired/invalid link behaviour at runtime (code path unchanged: WooCommerce shows its "key is invalid or already used" notice and the request form).

**Remaining (not implemented, owner decisions):** a rejected/revoked state (ADR-018), the missing "password reset" confirmation for logged-in pending customers, the WooCommerce invalid-key notice rendering above the AUTH layout, the plain-text variant of the new-account e-mail (not overridden, English), and the approval-time e-mail timing relative to the 24 h link expiry.


### Addendum (2026-10-20) — one-time "password set" confirmation on `/approval-pending/` (code `01620b6`)

**Problem:** a logged-in pending customer who sets a password is redirected by WooCommerce to `my-account/?password-reset=true`; the approval gate redirects that request to `/approval-pending/` before My Account renders, so WooCommerce's own success notice (printed from the forgeable URL parameter on the My Account page) is never shown.

**Success signal:** the WooCommerce action `woocommerce_customer_reset_password` (`class-wc-form-handler.php:1338`), fired in `process_reset_password()` only after the nonce, reset key and new password were validated and the password was saved. The `?password-reset=true` parameter is never read.

**Implementation (no new storage, handler or session):**
- `dreampoint_b2b_pending_password_set_notice()` (`inc/registration-approval.php`, hooked to that action) queues one native `wc_add_notice( ..., 'success' )` — "Lozinka je uspješno postavljena. Vaš račun i dalje čeka odobrenje administratora." — only when the current logged-in user owns the reset and `dreampoint_b2b_user_is_activated()` is false (guests, other users and approved customers keep the native flow; with `DP_BYPASS_APPROVAL` every user counts as activated, so nothing is queued on staging). The WooCommerce session carries it across the redirect chain (nothing prints notices in between).
- `approval-pending.php` reads `wc_get_notices( 'success' )`, removes only the success type from the session (`wc_set_notices`, other notice types untouched) and prints each as `<p class="lr-pending__notice" role="status">` at the top of the AUTH-03 column (no global WooCommerce banner above the standalone layout; the theme's notice banners are full-width bars and were not reused). Consumed once: a refresh or later visit shows nothing. `sass/pages/myaccount.scss`: 4 lines (success colour `$dp-color-success-strong`, weight 600) for that element.

**Why a forged URL cannot trigger it:** the notice exists only if the server-side action ran for the same logged-in user after a successful reset; appending `?password-reset=true` (or any parameter) to `/approval-pending/` or any other URL queues nothing and the template reads no query parameter. An invalid/expired key never reaches the action. Residual edge: if the customer never loads `/approval-pending/` after the reset, the queued notice stays in their WooCommerce session until the next notice-printing page (for a pending user, `approval-pending` or the lost-password screen).

**Verification:** `php -l`; build. Logic harness (stubs, NOT E2E) with the real functions and the real template: queued for pending + own reset; not queued for a guest, another user, an approved customer; no duplicate on a double call; first load shows it, an unrelated error notice is preserved, refresh and a normal visit show nothing, a forged `?password-reset=true` shows nothing. Staging (`01620b6`, admin session, bypass on): `/approval-pending/` unchanged with and without the parameter; anonymous login, registration, lost-password, confirmation unchanged; dashboard unchanged; the notice's look was checked only by DOM injection at 1440/390 (NOT the real flow). **NOT E2E verified:** the real reset by a pending logged-in customer (needs the bypass off, a pending account and a real reset link; none was created, no e-mail sent, no token consumed).

## ADR-033 — Users list: duplicate "Tvrtka" and legacy "Task ID" columns removed (2026-10-20)

- **Duplicate Tvrtka:** the protected `b2b-partner-importer` registers `b2b_company` (sortable, click-to-filter, `billing_company` search); the theme (`inc/registration-approval.php`) separately registered `billing_company` with the same label and meta. The theme copy was removed; the plugin column is retained (richer, plugin is protected and untouched).
- **Task ID:** `b2b_task_id` column + `task_id` user meta come only from `b2b-partner-importer` (Excel/CSV import column "Šifra partnera", documented there as a TASK-ERP code, not Apros). No code in theme or plugins reads `task_id`; the Apros partner identifier is `apros_partner_code` (ADR-010/018). Staging: `task_id` 0 non-empty rows, `apros_partner_code` 1, no overlap. Decision: hide the column only (theme filter `manage_users_columns` @99 unsets it). Meta, the importer's profile field and import logic are unchanged; it was NOT renamed to "Apros ID" because it is not one.
- **Verification:** `php -l`; staging `b4cfd4b` Users header = username, name, email, role, posts, b2b_company, b2b_komercijalist, user_jetpack, user_approval, dp_bucket; Tvrtka sortable; 9 rows.

- **Addendum 2026-10-20 — Edit User profile cleanup (staging `6e7c46b`):**
  - **billing_phone:** the theme's duplicate admin input (`dreampoint_b2b_admin_phone_field` / `..._save_admin_phone_field`, both `name`/`id` `billing_phone`) was removed from `inc/my-account.php`; WooCommerce's native "Customer billing address" field is the only one. With duplicate names PHP kept the last value, so the removed field never took effect. Registration, My Account, checkout, e-mails and the ERP payload are untouched.
  - **oib / task_id (importer-owned, legacy):** the two rows are hidden on `user-edit`/`profile` only (`admin_head` CSS `#your-profile tr:has(input#oib|#task_id)`, scoped by screen). `dreampoint_b2b_drop_legacy_importer_post_keys` (priority 1 on `personal_options_update`/`edit_user_profile_update`) unsets `$_POST['oib']` and `$_POST['task_id']` before the importer's priority-10 save, so profile saves never create empty `oib`/`task_id` rows or overwrite existing values. The protected importer, its CSV mapping and its "Zaduženi komercijalist" field are unchanged. Consequence: these two values are no longer editable in WP Admin; CSV import still writes them.
  - **Kept visible/editable:** `billing_oib` (active OIB), `apros_partner_code`, `apros_country_code`, `assigned_komercijalist`, `billing_company`.
  - **Not verified:** a real profile save (no form was submitted); the no-write property is verified by hook order on staging plus an isolated test of the unset function. No `oib` → `billing_oib` migration; imports of legacy CSVs would still land OIB in the hidden `oib` key.

## ADR-034 — PDP shows the ERP catalog number ("Kataloški broj"), per variation (2026-10-20)

- **Client requirement (confirmed):** "Kataloški broj artikla prikazuje se na stranici artikla — dolazi iz Apros-a; prikaz kataloškog broja ima prioritet nad SKU-om" (`docs/erp-discovery-findings.md:31`, May 2026; repeated in `stakeholder-question-matrix.md:16`, `Sastanak-Pitanja-i-Odluke.docx` section 1, `b2b-erp-migration-plan.md:107,119`). The INT-01 "SKU-first" recommendation is an internal search-strategy proposal (ADR-014) and does not apply to the PDP.
- **Previous (incorrect) mapping:** `woocommerce/content-single-product.php` rendered `get_sku()` (`P-<articleId>`, e.g. `P-58782`) under "Šifra artikla". Header live search and Quick Order already used `_ARTICLE_CODE` (ADR-014/015); this was a PDP-only gap (Figma audit: "catalog no./EAN").
- **Correction:** shared helper `dreampoint_b2b_get_catalog_number()` in `inc/product-search.php` = non-empty `_ARTICLE_CODE`, else `get_sku()`. Used by the PDP and by the header search (`inc/ajax-handlers.php`, behaviour unchanged). PDP label is now "Kataloški broj". Hidden `<meta itemprop="sku">` keeps the WC SKU; `data-product_sku`, `_sku`, `_ARTICLE_ID`, `_erp_id`, cart/order/ERP identifiers are untouched.
- **Variations:** each published variation carries its own `_ARTICLE_CODE` (staging: 1,291 of 1,291 differ from the parent's; none empty) and the cart/order unit is the variation, so a selected variation shows its own catalog number (filter `woocommerce_available_variation` adds `dp_catalog_number`; `js/product-single.js` on `found_variation`), consistent with the Quick Order per-variation `catalog_number`. Clearing the selection restores the parent's number. A variation without its own code keeps the parent's number (never the variation SKU, which is the internal variation id). This variation rule is derived from the existing QO contract and data model, not from an explicit client statement.
- **Verification (staging `8864fe1`, admin session, read-only):** #19033 default `Kataloški broj: 24vezica`; variations ballerina/breeze/cotten show `002587`/`002586`/`002585` (variation ids 19037/19036/19035); Clear restores `24vezica`; simple product #7639 `55997N`; #13408 `DASH/W`; AJAX search `58782` → one row, `Kataloški broj: 24vezica`; schema meta SKU `P-58782` unchanged. Not tested: empty-`_ARTICLE_CODE` SKU fallback and no-identifier case (no such staging data, none created), Quick Order UI, add-to-cart.
- **Post-deploy incident (2026-10-20, RESOLVED — NO CURRENT ERROR):**
  - **Confirmed:** one HTTP 500 at 12:47:46 CEST on `GET /product/dugacka-vezica-za-bocu/`, with the matching PHP fatal `Call to undefined function dreampoint_b2b_get_catalog_number()` in `woocommerce/content-single-product.php:32` (OLS `error.log`, single occurrence; the only 500 on the vhost that day). The next request at 12:48:57 returned 200 without intervention; later PDP (#19033, #7639) and homepage checks returned 200 with the expected markup; no recurrence or other related errors were found.
  - **Suspected:** mixed code versions during the deploy window (the new template executed while the helper in `inc/product-search.php` was not yet available to that request).
  - **Unconfirmed:** the underlying mechanism. A stale OPcache entry for `product-search.php` is consistent with the evidence (git writes `inc/` before `woocommerce/`, so a partial pull alone would not explain it) but is a hypothesis only; no OPcache log evidence exists. No fix was applied. Practical note: allow ~60 s after a pull before the first verification request.

## ADR-035 — PDP MPC / MOQ / Multipack: requirements reconstruction, no code change (2026-10-20)

**Status:** Investigation closed, no code change. Staging only (no production environment exists). Follow-up to the T4 verification (`docs/active/figma-alignment.md` §47), which found no staging data for these rows.

### Evidence reviewed
`docs/project-status-matrix.md` DP-B01, `docs/erp-discovery-findings.md` ("Količine"), `docs/stakeholder-question-matrix.md` B-01, `docs/b2b-erp-migration-plan.md`, ADR-011, ADR-028 (ERP data audit), Figma PDP-01 `11148:32726` and PDP-02 `11148:32859`, theme code, and a read-only staging inspection (451 published products, 1,291 variations; the importer plugin was not modified). No meeting notes or client correspondence beyond these documents were found in the project.

### Requirements matrix

| Feature | Client evidence | Data source | Current implementation | Decision | Action |
|---|---|---|---|---|---|
| MPC ("Neobvezujuća MPC") | ADR-011: "the business wants a non-binding recommended retail price shown above the B2B price on the PDP only" (2026-09-30; no dated meeting source recorded) | Manually entered ACF `dp_non_binding_mpc` (number, optional). Apros `articleList/get` has no MPC-like field (checked 2026-09-30, 10,186 articles) | Rendered from `dreampoint_b2b_get_non_binding_mpc()`, informational only, never part of price calculation; 0 staging products have a value | **B: confirmed, already implemented** | None. No data entered by Claude; the owner populates the field |
| MOQ (minimum order quantity) | DP-B01 (confirmed, workshop June 2026) and ERP discovery (May 2026): no minimum, no maximum, no quantity steps; native WooCommerce quantities | None: no `_wc_min_qty`-style meta, no min-quantity mapping in the importer, no plugin enforcing it | Native WooCommerce (`get_min_purchase_quantity()` = 1); no PDP "Minimalna količina narudžbe" row | **E for enforcement (explicitly excluded by the client). D for the display row** | Do not add any minimum or step logic. The Figma row reads "1 kom" in both frames, consistent with "no MOQ", so it is treated as a placeholder |
| Multipack | None found: appears only in Figma ("Multipack 1 kom"); DP-B01 says no packaging or steps | None: ADR-028 audit found no package/unit/multipack field in the Apros payload and none imported; no staging attribute (`pa_*` = model, boja, dob, dioptrija, volumen) | Not rendered | **D: unresolved; if required, C (needs an Apros field + importer extension, protected plugin)** | None; no mapping invented |

### Findings that remain open (owner decisions, not implemented)
1. Should the PDP show a constant "Minimalna količina narudžbe: 1 kom" row at all? There is no data behind it and the client said there is no MOQ.
2. Is "Multipack" a real requirement? If yes, which Apros field carries it, and is it display-only or an order multiple (the latter contradicts DP-B01)?
3. MPC placement and label: Figma puts the MPC / MOQ / Multipack block below the stock line and above the quantity/add-to-cart controls, labelled "Neobavezujuća preporučena MPC"; ADR-011 placed MPC directly above the price with the label "Neobvezujuća MPC". Left unchanged (layout/copy decision, not part of this investigation).
4. If MPC should eventually come from Apros, that is the separate investigation already stated in ADR-011.

### Consequences
No theme, ACF, importer or data change. T4 stays IMPLEMENTED — PARTIALLY VERIFIED: the MPC row and the sale-price scenarios are still not observable on staging because no such data exists.

### Addendum 2026-10-20 (reconciliation with `B2B odgovori na pitanja.docx` §5.4) — corrects the Multipack / MOQ wording above

The matrix above was written without re-reading §5.4 of the client's response document (original text in `B2B odgovori na pitanja.docx`; the copy of 2026-09-21 carries the owner's inline EDIT notes). It therefore understated the client evidence. Historical text above is kept unchanged; this addendum supersedes it where they differ.

- **Client request (§5.4 "Upsell funkcionalnost na stranici proizvoda"):** show additional or alternative products when a product has a minimum order quantity or a mandatory multiple / multipack packaging. Client answer: "Ovu opciju bi uključili." Stated dependencies in the same document: an upgrade of the data the webshop receives from the ERP, and a suitable plugin (estimate 8 h; plugin cost NOT included; the summary table row 4 repeats "Potrebna nadogradnja ERP podataka i odgovarajući plugin"). This is the client's evidence for the Multipack / MOQ concepts; the earlier statements "Multipack: no client evidence" and "MOQ: E" are therefore inaccurate as written.
- **What stays correct:** DP-B01 (`project-status-matrix.md`, `erp-discovery-findings.md` "Količine", May-June 2026) records that today there are no minimums, maximums or quantity steps, and that no PDP Figma value ("1 kom") has a data source. §5.4 does not contradict it: it is a conditional upsell that applies only to products that will carry MOQ / multipack data once the ERP provides it. It does not ask for quantity enforcement, and none must be introduced from it. Whether obligatory multiples will ever be enforced (which would change DP-B01) is NOT stated by the client and remains an open question.
- **Status of §5.4: CONFIRMED CLIENT REQUIREMENT, BLOCKED BY MISSING DATA AND A PLUGIN DECISION (category C).** No implementation, audit or deferral record of §5.4 exists elsewhere in the project (searched `docs/` and Git history); the PDP "Predloženo za vas" row only renders native WooCommerce upsell ids (`$product->get_upsell_ids()`), which is unrelated to MOQ / multipack. Missing: the Apros field(s) for minimum quantity / pack multiple (none in the payload, ADR-028 audit), an importer extension (protected plugin), and the plugin / mechanism for the upsell. Nothing was implemented or invented.
- **Revised open questions:** (1) which Apros field will carry MOQ / multipack and when; (2) are the multiples display-only or enforced; (3) which plugin or native mechanism the client expects for the upsell (cost is the client's); (4) the two Figma questions on the constant "1 kom" rows and the MPC placement / label stay as listed above.

### Addendum 2026-10-20 (owner decision on the minimal §5.4 approach) — documentation only, nothing implemented

**Status of §5.4: CLIENT-CONFIRMED — MINIMAL TECHNICAL APPROACH SELECTED — IMPLEMENTATION PENDING DATA-SOURCE AND BUSINESS-RULE CONFIRMATION.** The client confirmed the requirement ("Ovu opciju bi uključili", see the previous addendum); the technical approach below is the project owner's direction, NOT a client approval, and is not an authorization to implement now. Evidence base: the read-only discovery of 2026-10-20 (APROS API documentation v1.0 `articleList/get` and variation / attribute endpoints, importer code, `woocommerce/content-single-product.php` upsell block, staging catalog: 0 products with `_upsell_ids`).

**MOQ / Multipack data**
- APROS is the preferred authoritative source. The documented API payload does not expose MOQ, order multiple, pack size, unit of measure or substitute-article relations (NOT FOUND in the API documentation; ADR-028 audit).
- Open: whether the client maintains these values in APROS (or intends to introduce them) and whether they can be exposed through the integration.
- WooCommerce-managed optional informational fields are an acceptable FALLBACK PROPOSAL only if the client explicitly approves that data-ownership model. No field was created and the importer was not touched.

**Conditional upsell (selected simplest approach, not implemented)**
- Reuse native WooCommerce Linked Products → Upsells (`_upsell_ids`, `$product->get_upsell_ids()`).
- Reuse the existing PDP block "Predloženo za vas" in `woocommerce/content-single-product.php` and its `content-product` cards; the existing category fallback and current PDP behaviour stay unless a narrowly defined change is explicitly approved.
- No additional upsell plugin is planned for the basic implementation. No automatic alternative-product matching, no new recommendation engine, no new PDP section unless later justified and approved.
- The conditional display behaviour is to be defined only after the data source and the exact business rule are confirmed.

**Quantity enforcement:** MOQ and Multipack values are informational in this approach. No minimum-quantity validation, quantity increments, maximum limits or cart/checkout restrictions are introduced; DP-B01 remains in force. Any future enforcement needs a separate explicit business decision.

**Separate requirement:** Reserved Stock Pro (ADR-007) belongs exclusively to cart stock reservation. Its pending purchase is NOT a dependency of §5.4, and the two requirements must not be merged. The unspecified "odgovarajući plugin" in the original §5.4 estimate is not identified in any source.

**Remaining confirmations (not resolved):**
1. Whether MOQ / Multipack values exist in APROS and can be exposed through the integration.
2. If not, whether the client approves optional WooCommerce-managed informational fields.
3. The exact conditional behaviour of the existing "Predloženo za vas" block.
4. Which upsell products the administrator should link manually.

T5 (Cart Visual Alignment) is independent of this item and not blocked by it.

## ADR-036 - Akcija diagnosis and existing-customer migration plan (2026-10-29, diagnostic/planning only; nothing implemented)

### N2 - /akcija/ is empty because staging holds no WooCommerce sale prices

Read-only staging inspection (wp eval as site user, no writes): `wc_get_product_ids_on_sale()` = 0 for administrator, `vis_full`, `vis_none` and `vis_offer`; non-empty `_sale_price` rows 0 (published products 451, variations 1291); `wc_product_meta_lookup.onsale = 1` rows 0; sale-date meta rows 0. Lookup data is consistent with the postmeta. The page query (`inc/woocommerce.php`, `loop_shop_post_in` = `wc_get_product_ids_on_sale()` or `[0]`) therefore returns nothing by design; visibility filtering does not change a result that is already empty. No technical defect was found; the page works as designed on the current data.

Mapping facts (staging plugin code, read only): `uncle-dev-importer` `AprosProvider.php` reads `salePriceWithVat` (plus `saleDateStart`, `saleDateEnd`, `saleTitle` into `_SALE_*` meta) as `discounted_price`; `Importer.php` sets `_sale_price` when `discounted_price < price`, otherwise clears it (simple and variation paths). Note `price` comes from `wholesalePrice` (net) while `salePriceWithVat` is a VAT-inclusive field name, so the comparison may be between differently-taxed values: unverified, needs an APROS payload with a sale article. `apros-pricing` marks an item on sale at RUNTIME for the current partner when the price source is the brand discount (`woocommerce_product_is_on_sale`, `woocommerce_product_get_sale_price` filters). `wc_get_product_ids_on_sale()` reads the database lookup, not those filters, so partner-specific brand discounts never appear on /akcija/. Three different things: (1) WooCommerce sale prices (what /akcija/ lists), (2) APROS promotional price (`salePriceWithVat`, imported into (1) when present), (3) partner brand discount Rabat 1 (runtime display only, not listed).

Missing evidence (not decided): whether the APROS sandbox/production returns any article with `salePriceWithVat` (needs an ERP read, not authorised in this task), and the business rule for /akcija/ (promo articles only, or also brand-discounted ones: DP-E02). No fix was implemented.

### N3 - Existing live B2B shop customers: migration approach (plan only)

Sources of truth: early workshop notes (`erp-discovery-findings.md`, `project-status-matrix.md`): about 60 customers, about 10 active, "Apros exports existing users, WooCommerce imports them", initial partner list sent by DreamPoint as Excel. Newer decision ADR-018: Apros `B2B KUPAC = DA` is the only source of truth for approval/activation. `partnerList/get` is a partner master (`partnerCode, name, address, city, postalCode, taxId, email, partnerLegalFormCode`): it has no login accounts and no passwords, so it is NOT a webshop account export. Password hashes of the old shop are not assumed portable.

Approach: (1) get a CSV/XLSX of the live shop's accounts from the client (or its shop vendor); (2) reconcile each row to an APROS partner by `taxId` (OIB) first, then `partnerCode`, then e-mail; (3) dry-run report only (matched, unmatched, duplicates, e-mail mismatches); (4) after approval, idempotent import keyed on lower-cased e-mail plus `apros_partner_code`: create or update a `customer` user, set `apros_partner_code`, never set `approved` and never assign a bucket from the file unless the client provides it; (5) activation stays with ADR-018 (blocked on the two `partnerList` questions); (6) onboarding by password-reset link (ADR-032 recovery exemption), sent only after activation and on the owner's go-ahead; (7) rollback = list of created user IDs written by the run, delete only those; test first on a 3-5 row sample on staging. Reuse `wp importer partners` for partner linking; the import must not touch `apros-pricing`, `uncle-dev-importer` or `b2b-partner-importer`.

Required export fields: e-mail (login), first name, last name, company, OIB, APROS partner code (if known), phone, billing address, delivery addresses, country, registration date, active/inactive flag, customer group or catalog scope if any (bucket input), commercialist if any. Not needed: password hashes.

Open questions: (a) Does the live shop run WooCommerce and can it export users? (b) Is `partnerList/get` limited to `B2B KUPAC = DA` and does `DA -> NE` remove a partner (ADR-018 Q1)? (c) Is `partnerList.email` the `B2B E-MAIL` attribute (ADR-018 Q2)? (d) Which accounts are active and should be migrated (about 10 of about 60)? (e) Bucket/catalog scope per customer. (f) Several users per company allowed (one account each, confirmed earlier): how are extra users identified? (g) When may reset e-mails be sent?

Estimate after the source format is confirmed: 6 h (dry-run reconciliation report 2 h, idempotent import with rollback list 3 h, staging sample test 1 h). Excludes client/APROS waiting time.

Draft client request (Croatian, NOT sent):

> Poštovani, za prebacivanje postojećih korisnika s trenutne B2B web trgovine na novi DreamPoint B2B sustav trebamo: 1) izvoz korisničkih računa iz trenutne trgovine (CSV ili Excel) sa stupcima: e-mail, ime, prezime, tvrtka, OIB, šifra partnera u Aprosu (ako postoji), telefon, adresa računa, adrese dostave, država, datum registracije i status (aktivan/neaktivan); lozinke nam nisu potrebne, korisnici će lozinku postaviti sami putem poveznice za obnovu lozinke; 2) popis koje račune treba prenijeti (npr. samo aktivne); 3) za svakog kupca, ako je poznato, koji dio kataloga smije vidjeti; 4) potvrdu da je u Aprosu za sve kupce koji se prenose postavljeno `B2B KUPAC = DA` i je li polje e-mail partnera ujedno B2B e-mail; 5) odgovor ZGData-a na dva pitanja o `partnerList/get` (vraća li samo partnere s `B2B KUPAC = DA` i nestaje li partner kada se postavi na NE; je li vraćeni e-mail B2B e-mail); 6) datum od kojeg smijemo korisnicima poslati e-mail za postavljanje lozinke. Hvala.

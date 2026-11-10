# Dreampoint B2B — AI Context

> Full technical documentation is located in `docs/dev-context.md`.
> Claude must ignore it unless explicitly asked to use it.

> Minimal runtime context for Claude Code CLI.
> This file contains only key rules and current project decisions.
> Do not treat this as full project documentation.

---

## Project Summary

Dreampoint B2B is a WooCommerce-based B2B system.

Core B2B visibility system is implemented and tested.

Visibility system includes:
- per-user catalog visibility
- bucket-based rules
- custom offer product access
- brand/category/product filtering
- SQL-level enforcement
- WooFilter Pro compatibility

IMPORTANT:
Do not modify or re-analyze the visibility system unless explicitly requested.

---

## Stack

- WordPress
- WooCommerce
- PHP 8.3+
- ACF Pro
- Redis Object Cache
- LiteSpeed Cache
- Cloudflare on staging/production
- WooCommerce Product Filter
- ~~TI WooCommerce Wishlist~~ - removed 2026-10-09 (`docs/decisions.md` ADR-021); no wishlist code remains in the theme

---

## Language / Localization

Primary language: Croatian (`hr_HR`)

Text domain:
`dreampoint-b2b`

Rules:
- All PHP labels must use `__()` / `_e()`
- Text domain must be `dreampoint-b2b`
- JS labels must not be hardcoded
- Use `wp_localize_script()` for JS strings

---

## WooCommerce Tax / PDV

WooCommerce tax is handled natively.

Current tax logic:
- product prices are entered excluding tax
- WooCommerce calculates tax automatically
- tax applies to products and shipping
- checkout displays native tax line

IMPORTANT:
- Do NOT add custom PDV/tax rendering
- Do NOT inject custom checkout total rows
- Do NOT use render_block hacks for tax display
- Keep WooCommerce native tax behavior

---

## Visibility System Status

Visibility system is DONE and tested.

Test users:
- vis_none
- vis_full
- vis_rule_cat
- vis_rule_brand
- vis_offer

Visibility behavior has been verified for:
- shop
- category archives
- brand archives
- search
- sorting
- pagination
- direct product URLs
- WooFilter Pro filters

IMPORTANT:
Do not touch visibility code unless explicitly requested.

### Debugging Caveats (full original text: `docs/decisions.md` § "Appendix — Visibility Debugging Caveats")

- `product_brand` terms are filtered by the visibility engine (`get_terms` filter, `inc/visibility/class-query-filter.php` → `filter_brand_terms()`). Unauthenticated WP-CLI (`get_current_user_id() = 0`) can legitimately get an empty set; a logged-in `manage_options` admin bypasses the filter. An empty `product_brand` result is a visibility-context signal, not proof of missing data — verify the execution context (admin / anonymous-CLI / a visibility test user) first.
- `COUNT(_price) > 1` on a variable parent is native WooCommerce state (one `_price` row per distinct visible-variation price; parent `_regular_price`/`_sale_price` intentionally absent), not corruption — check `product_type` first. Multiple `_price` rows on a simple product may be anomalous. Analysis: `docs/decisions.md` ADR-006.

---

## Local Environment

See `.claude/environment.md`

Environments: only localhost and staging (`dreampoint.b2b.uncledev.cloud`) exist. This project has NO production environment; never plan, mention or prepare a production deployment. Staging is the only remote target; localhost needs explicit owner approval for execution.

---

## Claude Operating Rules

For functional testing:
- use browser / Playwright only
- test through real UI
- verify what the user sees

DO NOT:
- use MySQL
- inspect the database
- guess database names
- guess table structure
- infer schema
- run DB queries unless explicitly asked

If database access is truly needed:
- ask for confirmation first
- explain why browser/WP admin/WP-CLI is not enough

## Staging — Testing As Another User (User Switching)

The standard `User Switching` plugin is installed and active on staging. It is the DEFAULT mechanism whenever testing, debugging or acceptance needs the perspective of an existing WordPress user.

- Not having a user's password is NOT a blocker. Never request, reset or change passwords/capabilities merely for testing.
- Flow: administrator → native "Switch To" → real authenticated browser/HTTP test → native "Switch Back" → administrator.
- Prefer real authenticated browser/HTTP over CLI emulation whenever behavior depends on user, cookies, WooCommerce session, B2B visibility or REST auth.
- Always restore and verify administrator identity at the end.

## Playwright Login Reliability

Browser autofill can interfere with automated login flows during Playwright testing.

Prefer explicit Playwright focus/type/click interactions over JS form submission or evaluate-based login flows when authentication behaves inconsistently.

---

## Catalog Navigation — UI Source of Truth

The DreamPoint B2B navigation UX is defined ONLY by the Figma design, node "Katalog proizvoda menu":
https://www.figma.com/design/lZvGxdZfmaLJgAMo4NBgpp/DreamPoint-B2B?node-id=11022-51566

`inc/nav-categories.php` and its markup/CSS (category grid with subcategory thumbnails, 6h shared transient) were copied from the JekaaStore B2C theme as a temporary development placeholder. They are NOT a specification: never infer menu depth, thumbnails, behavior or data needs from them.

Design facts (Figma, 2026-10-02): "Katalog proizvoda" opens a panel with a left list of category rows (each with a chevron, i.e. expandable), a "Prikaži sve kategorije" link, and a right column "Popularni proizvodi" (4 compact cards: image, name, price). "Popularni proizvodi" is independent of the selected/active category. Category rows show no thumbnails. The row labels and the row count in Figma are placeholders: Figma defines the UI pattern only, never the taxonomy or the number of categories. Lifestyle/Toys/Outdoor are separate homepage segment blocks, not menu items. See `docs/decisions.md` ADR-013 (update 2026-10-02).

---

## Product Search — Current State (ADR-014)

Closed on staging 2026-10-05, commit `b5ce399`. Full record: `docs/decisions.md` ADR-014.

- Search is native WordPress/WooCommerce plus a small DreamPoint identifier extension (`inc/product-search.php`). **Relevanssi is NOT installed** (neither locally nor on staging) and there is no Relevanssi dependency in PHP/JS — never describe it as part of the stack.
- Matches product title/content (native) plus `_sku`, `_ARTICLE_CODE` (catalog number), `_global_unique_id` (EAN); a hit on a published variation returns the parent. `MIN_CHARS = 2`. Identifier lookup collects at most 200 deterministic ids (accepted limitation). No SKU-first ranking; no typo tolerance / "did you mean".
- The AJAX handler must keep `'post_status' => 'publish'` (admin-ajax.php otherwise exposes draft/pending/future products) and B2B visibility filtering. Never reintroduce a shared search-result HTML cache keyed only by the search term (the old `dp_search_*` transient was visibility-unaware and was removed).
- UI sources: default/focus state = temporary screenshot-derived design (popular chips from ACF field `search_popular_terms` in group `group_dp_search_panel`; recent searches in localStorage `dpRecentSearches`, max 4); live AJAX results = Cotra production UI as a temporary UI/UX reference ONLY (no Cotra search backend/indexing/ranking was ported). The final DreamPoint Figma search design is pending — expect CSS refinements.
- No click/tap handler may remove or empty result/default DOM that contains the link or form being activated (iOS/WebKit cancels the navigation/submit).

---

## Coding Rules

PHP:
- use `dreampoint_b2b_` prefix
- use typed return values where appropriate
- prefer early returns
- keep changes minimal
- avoid broad refactors

SCSS (reuse before duplication):
- before writing a repeated group of declarations, check `sass/theme/_mixins.scss` (e.g. `heading-1..6`, `paragraph-*`, `caption-m`, `card*`, `button-*`) and the `$dp-*` tokens in `_vars.scss`; reuse what exists
- add a shared mixin only for a meaningful declaration group with 2+ real consumers; never a mixin for one consumer or a trivial single declaration
- identical-looking is not identical: confirm the full effective style (all breakpoints, inherited rules) before merging; intentional Figma-driven differences stay local (e.g. `.brands h2` is Petrona 600/40 sentence case, while `.section-heading h2` uses `heading-3`)
- keep selectors scoped to their component, add no specificity, and do not change visual output while deduplicating

JavaScript:
- use IIFE pattern
- use `'use strict';`
- avoid global variables
- do not hardcode translated strings

---

## Buttons (ADR-022)

- Buttons use the existing `.button` ecosystem only: `.button` = Figma L, `.button--sm` = M, `.button--xl` = XL, `.button--outline` = Secondary. Never introduce `.dp-btn` or another parallel system.
- `button-primary` / `button-outline` emit legacy CSS unless a Figma size is passed (`@include button-primary('l')`); do not change legacy callers (WC Blocks, WBW, refund) outside Step 3b-2c.
- Rules that replace legacy `.custom-form` control rules must not exceed the legacy specificity (use `:where(...)`); details in `docs/active/figma-alignment.md` section 15.

---

## Performance Rules

- Prefer WooCommerce native APIs
- Do not add custom caching unless explicitly requested
- Do not modify Redis/LSCache behavior without clear reason
- Do not optimize ahead of confirmed need

---

## Scope Rules

When given a task:
- solve only that task
- do not redesign existing systems
- do not modify unrelated files
- do not introduce new architecture unless requested
- ask before making risky changes

---

## Golden Rule

If something already works, do not touch it.

---

## Completed Tasks

Archived implementations are in `docs/frozen/`.
Read the relevant file before modifying any completed feature.

- `docs/frozen/checkout-logic.md` — billing prefill, billing master protection (the shipping-based payment rules described in its original text were removed in A8; see its "Superseded / Current Implementation" section)

---

## Figma Alignment Program (ADR-020)

Final design: Figma page **UI** (`11148:22601`, file key `Te4kr3o8kXDgBLzHewKSYU`), desktop 1440 px only — no mobile/tablet frames exist or will be supplied; build responsive layouts pragmatically alongside each component batch.

- Figma governs visual presentation ONLY. Never change B2B registration/approval/Apros, auth/guest rules, bucket visibility, binary stock display (ADR-016), pricing/ERP data, cart/checkout/payment/order logic or cart reservation (ADR-007) because of a design difference — record the conflict instead.
- Do not change values of existing SCSS palette variables (`$brand`, `$pink`, `$text-primary`, …); introduce namespaced `$dp-*` / `--dp-*` tokens and migrate components with scoped selectors.
- Plan, audit summary, step order, validation and handoff: `docs/active/figma-alignment.md`. Do not repeat the gap audit.

---

## Canonical Docs

Use `docs/index.md` as the documentation entrypoint.

Do not infer architecture from random task files.
Prefer canonical docs listed in the index.

---

## When to use dev-context.md

Only consult docs/dev-context.md if:
- task explicitly requires low-level implementation details
- enqueue logic needs to be modified
- build system needs to be updated

Otherwise:
ignore it completely.

---

## ACF Governance

Full original rules and incident detail (verbatim): `docs/decisions.md` § "Appendix — ACF Governance (original CLAUDE.md text)". Mandatory rules:

- **Tracking:** every active editable field group lives in `acf-json/` and is committed to Git. ACF Pro GUI remains the primary editing interface; export to `acf-json/` immediately after creating a group in the GUI. DB-only groups need explicit justification; remove orphaned/unused groups. Governance operations (export, deletion, sync) must not modify runtime behavior.
- **Creation:** never add a permanent Theme Field Group as JSON-only. Create/edit through the ACF admin UI, or import an existing JSON definition via ACF's native sync (`acf_import_field_group()`, same call as the admin "Sync available" action). A hand-authored JSON with no DB post (`ID = 0`) is a temporary state. Every persistent group must end with BOTH an `acf-field-group` DB post AND a matching `acf-json/` file (JSON = canonical, version-controlled; DB post = editable mirror). After introducing/syncing a group verify: no permanent "Sync available" badge, Local JSON sync works, no duplicate or orphan group.
- **Known JSON-only exceptions** (`ID = 0`, do not "fix" proactively): `group_dp_bucket_fields` (Customer Bucket Rules, frozen visibility system — do not touch without explicit request) and `group_685f00b0c4d10` (Page Intro Text). `group_udp_product_bundle` is PHP-registered by a plugin (`local => php`) — out of theme scope.
- **JSON/DB mirror before admin edits (2026-10-14 incident):** saving a group in the ACF editor exports the DB definition over `acf-json/<group>.json`; a stale DB mirror silently deletes JSON-only fields (General Settings lost 9 definitions). Before any admin edit confirm the DB mirror equals the canonical JSON (no "Sync available", compare field counts/definitions); prefer editing definitions in JSON via Git. Staging needs a clean working tree (`git status`) or the next pull fails. Align the DB mirror with a by-ID update of the existing post, never `acf_import_field_group()` by key (creates duplicate group and field posts). Details: `docs/active/figma-alignment.md` section 32.
- **Local JSON priority / updates:** with Local JSON enabled `acf_get_field_group( $key )` returns a JSON-derived object (`ID = 0`) — expected ACF behavior. For any export, sync validation, governance operation or update: load by post ID (`acf_get_field_group( $post_id )`), pass the post ID to `acf_get_fields()`, target the real DB-backed group, never rely on the key. After the DB update let ACF regenerate the Local JSON; do not hand-edit it. Reason: a by-key update once created a second, empty, duplicate group under the same key (DreamPoint B2B incident).

---

## Quick Order System

Custom B2B ordering interface (not a normal WooCommerce archive). Do NOT propose third-party Quick Order plugins. Status: COMPLETE, maintenance mode (`docs/active/current-phase.md`). Canonical architecture: `docs/frozen/quick-order-local-state-architecture.md`; the full original rule text (UX goals, technical, cart sync, variation, filtering, performance, Blocks, simplicity, API rules) is preserved verbatim there in "Appendix A".

Standing invariants:
- WooCommerce native cart/session is the source of truth. NO custom cart persistence, custom PHP session handling or cookie-based cart. localStorage only as a temporary UX cache; frontend JS state for debounce batching; sync to the WC cart via debounced AJAX (no repeated full cart refreshes).
- Checkout, totals, shipping, taxes, coupons and payment stay native and Blocks/Store API compatible. No legacy fragment refresh / mini-cart fragment hacks, no classic-shortcode-checkout assumptions, no parallel cart logic.
- Filtering must never bypass visibility, bucket rules, brand/category restrictions or custom offer visibility. Visibility and checkout logic are DONE: do not redesign them, modify bucket/access logic or add a permission architecture. Read `docs/frozen/checkout-logic.md` before touching checkout/payment/visibility.
- NEVER call `get_available_variations()` or `wc_get_product()` across large product collections; no full product objects, recursive variation hydration, unnecessary meta loading, whole-catalog loads or giant unpaginated variation payloads. Use lightweight/precomputed variation payloads, selective-field responses, paginated/lazy lists, batched updates, minimal DOM re-rendering.
- Architecture: custom REST endpoint(s) (`/wp-json/dreampoint-b2b/v1/quick-order`), progressive enhancement; no major architecture change unless explicitly requested.
- Keep it simple (WooCommerce B2B project, not a SaaS platform): no microservices, frontend state managers (Redux-style), event bus, CQRS, repository/service over-abstraction, DDD, or premature optimization.

## Quick Order — Filtering Is Native-First (ADR-015)

Quick Order filtering is native-first. WooCommerce global attributes remain native `pa_*` taxonomies and use the existing WBW/WooCommerce filtering pipeline. Custom Quick Order code exists only where necessary to enforce B2B visibility/security or integrate the native filter UI.

- The term vocabulary in the QO sidebar must stay B2B-visibility-safe (`DP_Quick_Order_Term_Scope`): global `hide_empty` and term counts are NOT a visibility mechanism; filter counts stay off.
- Dostupnost (WBW `wpfInStock`) is the only stock filter. Do not add custom attribute filtering, a second stock filter or fake counts to match a mockup.

## Quick Order — Page Requirement

Quick Order depends on a normal WordPress page entity existing in the database.

Canonical page configuration:
- Title: `Quick Order`
- Slug: `quick-order`
- Content: `[dp_quick_order]`
- Template: default

Important:
Git deploys do NOT sync WordPress DB content. If Quick Order exists locally but not on staging/production, verify the page entity exists before modifying plugin architecture.

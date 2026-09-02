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
- Relevanssi
- TI WooCommerce Wishlist

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

### Debugging Caveat — get_terms() on product_brand

`product_brand` queries are filtered by this visibility engine through the `get_terms` filter (`inc/visibility/class-query-filter.php` → `filter_brand_terms()`). WP Admin and WP-CLI can therefore legitimately return different term counts for the same taxonomy.

- An unauthenticated WP-CLI context (`get_current_user_id() = 0`) may receive an empty `product_brand` term set even when the taxonomy actually contains terms — a logged-in admin (`manage_options`) bypasses the filter and sees the full set.
- This is expected behavior caused by the visibility layer, not missing taxonomy data or a wrong database.
- When debugging Brands functionality, verify the execution context (logged-in admin vs. anonymous/CLI vs. a specific visibility test user) before assuming `product_brand` terms do not exist.

Rule of thumb: an empty `product_brand` result is a visibility-context signal, not proof of missing data.

### Debugging Caveat — multiple `_price` postmeta rows on variable products

`COUNT(_price) > 1` for a `product` post is **not** corruption by itself — check `product_type` before treating it as an anomaly.

- **Variable parent:** WooCommerce (`WC_Product_Variable_Data_Store_CPT::sync_price()`, WC 11.0.1) stores **one `_price` postmeta row per distinct visible-variation price**, sorted, and intentionally deletes the parent's `_regular_price` / `_sale_price`. Multiple `_price` rows plus absent parent regular/sale price is the correct native state.
- **Simple product:** multiple `_price` rows may be anomalous and worth investigating.
- Full analysis (and why a 2026-09 audit briefly misclassified 16 Apros variable parents as corrupted): `docs/decisions.md` ADR-006.

---

## Local Environment

See `.claude/environment.md`

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

## Playwright Login Reliability

Browser autofill can interfere with automated login flows during Playwright testing.

Prefer explicit Playwright focus/type/click interactions over JS form submission or evaluate-based login flows when authentication behaves inconsistently.

---

## Coding Rules

PHP:
- use `dreampoint_b2b_` prefix
- use typed return values where appropriate
- prefer early returns
- keep changes minimal
- avoid broad refactors

JavaScript:
- use IIFE pattern
- use `'use strict';`
- avoid global variables
- do not hardcode translated strings

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

- `docs/frozen/checkout-logic.md` — payment rules, billing prefill, billing data protection

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

### ACF Governance Standard

- All active editable field groups must be tracked in `acf-json/` and committed to Git
- ACF Pro GUI remains the primary editing interface — `acf-json/` provides Git protection and reproducibility
- New field groups must be exported to `acf-json/` immediately after creation in the GUI
- DB-only field groups require explicit justification — no silent accumulation
- Orphaned or unused field groups must not be retained without purpose — remove them
- Governance operations (export, deletion, sync) must not modify runtime behavior

### Field Group Creation Doctrine

- Do not introduce new permanent Theme Field Groups as JSON-only definitions. Always create/edit through the ACF admin UI, or import an existing JSON definition using ACF's native sync mechanism (`acf_import_field_group()` — the same call the admin "Sync available" action triggers). A hand-authored JSON file with no matching DB post (`ID = 0`) is a temporary/incomplete state, never the intended end state.
- Every persistent field group must end up with BOTH: a `acf-field-group` database post AND a matching file in `acf-json/`. The JSON file remains the canonical, version-controlled source; the database post is the editable mirror the ACF admin UI operates on.
- After introducing or syncing a field group, verify: it appears in **Custom Fields → Field Groups** without a permanent "Sync available" badge, Local JSON sync is functioning correctly, and no other field group targets the same location (duplicate) or exists without a matching JSON/DB counterpart (orphan).
- Known pre-existing exceptions (JSON-only, `ID = 0`, not to be "fixed" proactively): `group_dp_bucket_fields` (Customer Bucket Rules — part of the frozen visibility system; do not touch without explicit request) and `group_685f00b0c4d10` (Page Intro Text). `group_udp_product_bundle` is registered via PHP by a plugin (`local => php`, not JSON) and is entirely out of theme scope.

### Local JSON Priority

When a field group already exists in `acf-json/`, ACF treats the local JSON file as authoritative.

Observed behavior:
- `acf_get_field_group( $key )` returns an object with `ID = 0` when a local JSON version exists
- export logic that relies on a database-backed field group will produce incomplete exports (empty `fields` array)
- this is expected ACF behavior, not a bug

Operational rule — before any field-group export, sync validation, or governance operation:
1. Check whether the field group already exists in `acf-json/`
2. If yes — load by post ID (not by key): `acf_get_field_group( $post_id )`
3. Pass the post ID to `acf_get_fields()` to retrieve fields from the database
4. Do not assume `acf_get_field_group( $key )` represents a database-backed object

### Updating Existing Theme Field Groups

- When updating an existing permanent Theme Field Group, never rely on `acf_get_field_group( $key )` when Local JSON is enabled.
- With Local JSON enabled, `acf_get_field_group( $key )` may resolve to the JSON-derived object (`ID = 0`) instead of the real database-backed Field Group.
- Updates must always target the actual database-backed Field Group rather than the Local JSON-derived object.
- After the database update, allow ACF to regenerate/synchronize the Local JSON — do not hand-edit the JSON file to reflect the change.
- This prevents accidental duplicate Field Groups and preserves the DB-first → Local JSON mirror doctrine.

This rule was introduced after a real-world duplicate Field Group incident on the DreamPoint B2B project: updating an existing Field Group by key (instead of by post ID) caused ACF to treat the Local JSON-derived object as authoritative, fail to recognize the existing database post, and create a second, empty, duplicate Field Group post under the same key. Future sessions should treat this as the reason the by-post-ID rule exists, not an abstract precaution.

---

## Quick Order System (Phase 6)

Dreampoint B2B will include a CUSTOM Quick Order system.
Do NOT propose third-party Quick Order plugins.

Architecture goals:
- optimized for large B2B orders
- fast bulk ordering
- minimal page reloads
- WooCommerce-native cart compatibility
- scalable for large catalogs and variations

IMPORTANT:
Quick Order is NOT a normal WooCommerce archive.
Treat it as a specialized B2B ordering interface.

### UX Goals

- extremely fast ordering
- inline quantity editing
- fast filtering
- minimal clicks
- mobile usability
- stable performance with large catalogs

Expected layout:
- table/grid-based ordering interface
- sticky cart summary/footer
- inline quantity controls
- variation selection inline
- stock visibility inline
- price visibility inline

Potential future support:
- matrix ordering
- SKU quick search
- repeat previous orders
- saved order templates
- favorites/frequent products

### Technical Rules

Use WooCommerce native cart/session as source of truth.

DO NOT:
- build custom cart persistence
- replace WooCommerce cart logic
- create custom PHP session handling
- create custom cookie-based cart systems

Allowed:
- localStorage only as temporary frontend UX cache
- frontend JS state for debounce batching
- AJAX synchronization with WooCommerce cart

### Cart Synchronization

Preferred architecture:
- frontend state stores temporary quantities
- quantities sync to WooCommerce cart via AJAX
- debounce/throttle requests during rapid quantity changes

IMPORTANT:
Checkout, totals, shipping, taxes, coupons, and payment methods must continue using native WooCommerce behavior.

### Variation Handling

CRITICAL — Do NOT use `get_available_variations()` on large product collections.
Reason: huge memory usage and slow response times.

Preferred approach:
- lightweight variation payloads
- precomputed variation data
- optimized REST/API responses
- minimal variation hydration

### Filtering

Quick Order filtering must remain compatible with:
- visibility system
- bucket rules
- brand/category restrictions
- custom offer visibility

Do NOT bypass visibility filtering.

### Performance

Quick Order must be optimized for large catalogs, large variation counts, and many simultaneous quantity changes.

Prefer:
- paginated or lazy-loaded product lists
- lightweight API responses
- debounce AJAX sync
- minimal DOM re-rendering

Avoid:
- loading entire catalogs at once
- giant variation payloads
- unnecessary WooCommerce object hydration
- repeated full cart refreshes

### Architecture Direction

Preferred future architecture:
- custom REST API endpoint(s) (`/wp-json/dreampoint-b2b/v1/quick-order`)
- custom frontend rendering
- WooCommerce-native backend/cart
- progressive enhancement approach

IMPORTANT:
Do not implement major architecture changes unless explicitly requested.

### Scope Protection

Visibility system is DONE. Checkout logic is DONE.

Do NOT:
- redesign checkout
- redesign visibility system
- modify bucket/access logic
- introduce new permission architecture

Quick Order must integrate with existing systems, not replace them.
Before modifying checkout, payment, or visibility logic — read `docs/frozen/checkout-logic.md`.

### WooCommerce Blocks Compatibility

Quick Order must remain compatible with:
- WooCommerce Cart Block
- WooCommerce Checkout Block
- Store API

Prefer:
- Store API aware architecture
- WooCommerce-native cart operations
- compatibility with block-based checkout

Avoid:
- legacy fragment refresh dependencies
- assumptions tied to classic shortcode checkout
- mini-cart fragment hacks
- legacy WooCommerce AJAX fragment patterns

IMPORTANT:
Existing checkout logic already supports WooCommerce Blocks and Store API behavior.
Quick Order must integrate with the current checkout architecture instead of introducing parallel cart logic.

### Simplicity Rule

Quick Order should remain operationally simple.

Prefer:
- incremental improvements
- minimal moving parts
- WooCommerce-native behavior
- straightforward architecture
- simple debugging and maintainability

Avoid:
- microservice-style architecture
- unnecessary abstraction layers
- premature optimization
- frontend state managers unless clearly needed
- Redux-style architecture
- event bus systems
- CQRS patterns
- repository/service over-abstraction
- "domain-driven" architecture for standard WooCommerce flows

IMPORTANT:
This is a WooCommerce B2B project, not a SaaS platform.
Keep architecture pragmatic and maintainable.

### API Performance Rules

Quick Order endpoints must be optimized for:
- low memory usage
- minimal database queries
- minimal payload size

Prefer:
- selective field responses
- raw/lightweight data structures
- batched updates where appropriate
- minimal WooCommerce object hydration
- optimized variation payloads

Avoid:
- returning full WooCommerce product objects
- recursive variation hydration
- unnecessary meta loading
- calling `wc_get_product()` in large product loops
- large unpaginated variation payloads

IMPORTANT:
Calling `wc_get_product()` or `get_available_variations()` across large product collections can cause:
- memory explosion
- Redis/object cache churn
- excessive object hydration
- slow AJAX/API responses

Quick Order must remain performant on large B2B catalogs with high variation counts.

---

## Quick Order — Page Requirement

Quick Order depends on a normal WordPress page entity existing in the database.

Canonical page configuration:
- Title: `Quick Order`
- Slug: `quick-order`
- Content: `[dp_quick_order]`
- Template: default

Important:
Git deploys do NOT sync WordPress DB content. If Quick Order exists locally but not on staging/production, verify the page entity exists before modifying plugin architecture.

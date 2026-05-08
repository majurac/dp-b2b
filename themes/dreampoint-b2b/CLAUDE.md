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
- PHP 8.x
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

---

## Local Environment

Site:
http://localhost:8080/dp-b2b

WP Admin:
http://localhost:8080/dp-b2b/wp-admin

Test users password:
TestVis2025!

Test users:
- vis_none
- vis_full
- vis_rule_cat
- vis_rule_brand
- vis_offer

Admin credentials:
[ADD ADMIN USER/PASS HERE]

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

Archived implementations are in `docs/tasks/`.
Read the relevant file before modifying any completed feature.

- `docs/tasks/checkout-logic.md` — payment rules, billing prefill, billing data protection

---

## When to use dev-context.md

Only consult docs/dev-context.md if:
- task explicitly requires low-level implementation details
- enqueue logic needs to be modified
- build system needs to be updated

Otherwise:
ignore it completely.

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
Before modifying checkout, payment, or visibility logic — read `docs/tasks/checkout-logic.md`.

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

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

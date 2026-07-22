# Dreampoint B2B — Technical Developer Context

> This file is for developers, not for Claude runtime.
> Claude must ignore this file unless explicitly told to use it.
> Authoritative runtime rules are in CLAUDE.md.

---

## Project Identity

- WordPress B2B shop, package `Dreampoint_B2B`, text domain `dreampoint-b2b`
- Primary code comment language: Serbian
- Dev pipeline: local (XAMPP) → Hetzner staging → production
- Page IDs change on every DB migration — never hardcode IDs in code; use slugs or `is_page_template()`

---

## File Structure — Where Things Are

| What | Location |
|------|----------|
| Hook registration (all) | `functions.php` (sole loader) |
| WooCommerce-specific code | `inc/woocommerce.php` (loaded only when WC is active) |
| ACF blocks | `blocks/*.php` (auto-include glob, except `index.php`) |
| B2B registration approval | `inc/registration-approval.php` |
| Email classes | `inc/emails.php` |
| My account customizations | `inc/my-account.php` |
| Custom breadcrumb | `inc/custom-breadcrumb.php` |
| Device detection helper | `dreampoint_b2b_is_below_lg()` — in `functions.php` |
| WooCommerce template overrides | `woocommerce/` folder |
| JS source | `js/theme-scripts.js` → compiles to `js/theme.min.js` |
| SCSS source | `sass/style.scss` → compiles to `style.css` |
| WooCommerce CSS | `sass/pages/woocommerce.scss` → compiles to `css/pages/woocommerce.css` (part of the `build:pages` directory compile, same as any other page stylesheet) |

---

## Build System

Node.js v24.14.1 LTS (upgraded April 2026). Build tools: sass@1.99.0, esbuild@0.21.5.

### Canonical production build

```
npm run build
```

This is the **single canonical command** for producing production assets. It runs, in order: `build:css`, `build:pages`, `build:blocks`, `build:vendors`, `build:js` — each a directory-to-directory (or single-entry) Sass/esbuild compile with `--style=compressed --no-source-map` (CSS) / `--minify` (JS). Only the output of `npm run build` may be committed as generated CSS/JS.

| Script | Source → Output |
|--------|------------------|
| `build:css`     | `sass/style.scss` → `style.css` |
| `build:pages`   | `sass/pages/` → `css/pages/` |
| `build:blocks`  | `sass/blocks/` → `css/blocks/` |
| `build:vendors` | `sass/vendors/` → `css/vendors/` |
| `build:js`      | `js/theme-scripts.js` → `js/theme.min.js` |

### Watch commands — local development only

```
npm run watch        — parallel watch:css + watch:pages + watch:blocks + watch:vendors + watch:js
npm run watch:css    — sass/style.scss, expanded style + source map
npm run watch:pages  — sass/pages/, expanded style + source map
npm run watch:blocks — sass/blocks/, expanded style + source map
npm run watch:vendors — sass/vendors/, expanded style + source map
npm run watch:js     — esbuild --watch
```

`watch:*` output (expanded CSS, `.css.map` files) is for local iteration only. `*.css.map` is gitignored — never commit it, and never commit a `.css` file generated in watch mode. If a CSS file was edited or regenerated while `watch` was running, **run `npm run build` before committing** so the compressed, no-source-map canonical output replaces the watch-mode artifact.

### Build determinism policy

- `npm run build` is reproducible: given the same committed Sass sources, it always regenerates byte-identical compressed CSS/JS (verified — re-running against unchanged sources produces zero diff).
- Committed generated CSS/JS must always be derivable from the currently committed Sass/JS sources. If a generated file and its source ever disagree (e.g. a generated asset was committed from an uncommitted local source edit), that is a build-determinism bug — regenerate via `npm run build` from committed sources, don't hand-patch the generated file.
- `npm run rtl` (rtlcss on `style.css` → `style-rtl.css`) is a separate, manual pipeline — not part of `npm run build` or `npm run watch`, and not auto-verified by the determinism check above.

Rules:
- `js/theme-scripts.js` is the only source entry for `js/theme.min.js` (global JS, all pages)
- `style.css` is compiled from `sass/style.scss` — do not edit directly
- `sass/style.scss` header is corrected to "Dreampoint B2B"

---

## Script / Style Enqueue Logic

### Conditional loading rules

- `js/variation-stock.js` — enqueued on `is_product()` with `wp_localize_script` for `stockText`
- `js/tabs-dropdown.js` — enqueued on `is_product()`
- `js/checkout-b2b-info.js` — enqueued on WC checkout pages
- Select2 — loaded only where needed: `dreampoint_b2b_needs_select2()` covers `is_cart()`, `is_checkout()`, `is_account_page()`, `is_shop()`, `is_product_category()`, `is_product_tag()`, `is_tax('product_brand')`, `dreampoint_b2b_akcija_page_detected()`
- CF7 — enqueued on `is_page_template('contact.php')` (not by slug — migration-safe)

### Plugin dequeue map

- `wc-cart-fragments` — dequeued outside cart/checkout pages (AJAX polling cost on B2B site)
- `jquery-migrate` — replaced with empty stub: `wp_deregister_script` + `wp_register_script('jquery-migrate', '', [], null, false)`. Reason: full removal without stub caused PHP notices in plugins that declare jquery-migrate as a dependency. If CorvusPay fails at checkout, remove the stub first.

### Defer strategy

- LSCache JS Defer handles plugin and WC scripts (processes final HTML output — reliable for third-party)
- PHP-level `wp_script_add_data('strategy','defer')` ONLY for theme-specific handles
- Never mix both approaches on the same handle (can double-defer or break load order)

---

## Caching

- Redis Object Cache — active, do not modify behavior
- LiteSpeed Cache — active, do not modify behavior
- `update_post_meta_cache = false` + `update_post_term_cache = false` on shop/archive pages — speeds query ~15%
  - REVERT both flags to `true` if: WPF attribute/brand/category filters show missing options or wrong counts, or if a custom `meta_query` is added to `pre_get_posts`

---

## Architecture Decisions (WHY)

### jQuery Migrate — empty stub, not dequeue
Full removal without stub caused PHP notices because plugins declaring `jquery-migrate` as a dependency could not find it.
If CorvusPay fails: remove stub, let jquery-migrate load normally.

### LSCache defer for plugins, PHP defer only for theme
LSCache processes final HTML output — more reliable for third-party scripts.
Mixing both on the same handle can double-defer or break order.

### render_block PDV hack removed — April 2026
The `render_block_woocommerce/checkout-order-summary-totals-block` filter and `strrpos` injection were removed.
WooCommerce reconfigured: prices without tax, tax displayed natively. Do not re-add.

### update_post_meta_cache = false on shop pages
WC Product objects load meta internally on demand — main WP query does not access custom meta directly.
Speeds query ~15%. Revert if WPF filters break.

### CF-Device-Type priority over wp_is_mobile()
CF-Device-Type header comes from Cloudflare (present on staging and production only).
`wp_is_mobile()` is extended with a filter that corrects iOS "Request Desktop Site" mode.
Mobile rendering must be tested on staging, not locally — CF header is absent locally.

### wc-cart-fragments dequeue outside cart/checkout
WC cart-fragments does AJAX polling on every page load. Expensive on a B2B site.
Only loaded where mini cart is functional.

### Select2 — is_woocommerce() intentionally excluded
Shop and category pages do not use Select2 dropdowns.
Adding `is_woocommerce()` would load Select2 on all WC pages unnecessarily.
If a new page needs Select2, add it to `dreampoint_b2b_needs_select2()`.

### touchstart/touchmove passive:false — intentional
Slick Carousel swipe gestures require `preventDefault()`.
With `passive: true` the browser ignores `preventDefault()` → scroll instead of swipe.
Lighthouse warns about this — ignore it. Acceptable trade-off.

---

## B2B Registration & Approval Flow

1. User fills registration form (company name, OIB, contact data)
2. WC new-account email sent to user (pending approval state) — `woocommerce/emails/customer-new-account.php`
3. Admin notification sent — `WC_Email_Admin_B2B_New_Registration` class in `inc/emails.php`
4. WP default admin notification suppressed
5. Dreampoint reviews → creates partner in Apros ERP
6. ERP calls webhook: `POST /wp-json/dreampoint-b2b/v1/approve-user`
   - Auth: `X-DP-ERP-Token` header (value from `DP_ERP_WEBHOOK_SECRET` in `wp-config.php`)
   - Body: `{"email":"..."}` or `{"user_id":...}`
   - Writes `_erp_approved_at` to user meta
7. User receives approval email, gains access

**CRITICAL:** `DP_ERP_WEBHOOK_SECRET` must be defined in `wp-config.php` on every environment. Without it, webhook returns 500.

---

## Akcija (Discounted Products) Page

- URL: `/akcija/`
- WordPress page with slug `akcija` must exist in WP admin
- Implementation: `pre_get_posts` (priority 8) transforms main query into WC product archive filtered to `wc_get_product_ids_on_sale()`
- WPF filter and native WC sorting work automatically
- `template_include` serves `archive-product.php`
- `is_woocommerce` filter ensures WC context
- `dreampoint_b2b_akcija_page_detected()` — static flag helper; reason: `is_page()` returns false after `pre_get_posts`
- `pre_get_posts` priority 8 — WC hooks (priority 9–10) see the modified query
- **Note:** `woocommerce/archive-product-discounted.php` is on disk but no longer loaded. Can be deleted after confirming `/akcija/` works.

---

## WooCommerce Template Overrides

| Template | Purpose |
|----------|---------|
| `woocommerce/archive-product.php` | Main shop/category archive |
| `woocommerce/archive-product-discounted.php` | Deprecated — no longer in use (see Akcija section) |
| `woocommerce/emails/customer-new-account.php` | New account email (pending approval state) |
| `woocommerce/emails/admin-b2b-new-registration.php` | Admin notification email (HTML) |
| `woocommerce/emails/plain/admin-b2b-new-registration.php` | Admin notification email (plain text) |
| `woocommerce/ti-wishlist.php` | TI Wishlist override |
| `woocommerce/ti-wishlist-empty.php` | TI Wishlist empty state |
| `woocommerce/ti-wishlist-product-counter.php` | TI Wishlist counter |

WC 10.4.0 email compatibility: both email templates include `FeaturesUtil` import, `$email_improvements_enabled` flag, and `.email-introduction` wrapper.

---

## Block Checkout

The block checkout content is wrapped in `.container.custom-form` via a `the_content` filter in `functions.php`.

`js/checkout-b2b-info.js` (formerly `custom-checkout.js`):
- Inserts B2B info into checkout
- Uses MutationObserver (replaced setTimeout)
- All labels are translatable via `dpCheckoutB2B` localized object
- IIFE + `use strict`, guard against double-insertion

---

## Fonts

Font files are in the `fonts/` directory:
- `Jost-Light.woff2`
- `Jost-Medium.woff2`
- `Jost-Regular.woff2`
- `Marcellus-Regular.woff2`

`dreampoint_b2b_font_preloads()` in `functions.php` — currently has empty `$fonts` array. Populate with woff2 paths for fonts visible in first render (max 3–4) before staging deploy.

---

## Image Optimization

`wp_get_attachment_image_attributes` filter assigns `fetchpriority="high"` to the first `$count <= 4` images.
Value `4` must be validated against the actual number of above-fold products in the grid layout on staging.

---

## Project Roadmap (8 Phases)

> **Note:** This is the original planning roadmap. Phase statuses below reflect the initial plan scope and have not been updated as individual systems were implemented. For current system-level implementation status, see `docs/active/status.md`. Several systems marked "Pending" below have since been built (Phases 5, 6, partial 7) — but the phases themselves are not permanently closed as broader design, ERP integration, and testing remain active.

| Phase | Content | Estimate | Status |
|-------|---------|----------|--------|
| 1 | Design (UX/UI) — login/reg, homepage pre/post login, categories, listing, product page, cart, checkout | 150h | In progress |
| 2 | Core B2B system — WooCommerce setup, registration, admin approval, login-only access, permissions structure | 130h | In progress |
| 3 | Frontend implementation — design implementation, responsive, UX optimization | 100h | In progress |
| 4 | ERP Integration (Apros) — endpoints, partner sync, delivery locations, discounts, price lists, order sync, cron | 80h | Pending |
| 5 | Personalization — Bucket System (catalog/brand visibility per user) | 100h | Pending |
| 6 | Custom functionality — Custom Quick Order, bulk ordering, advanced search and filters | 140h | Pending |
| 7 | Checkout, payment (CorvusPay + BACS), shipping (GLS plugin + label print, delivery locations) | 50h | Pending |
| 8 | Testing and go live — functional testing, ERP test scenarios, validation, deploy | 50h | Pending |

Do not implement anything from a future phase while current phase is not stable.

---

## ERP vs WooCommerce — Responsibility Split

### Apros ERP is source of truth for:
- **Prices** — sends final net price per price list and country (not discount percentage); price lists for foreign buyers
- **Contractual conditions** — `sif_kup`, brand, discount percentage
- **Discounts** — by brand or category, per individual buyer (`sif_kup` + brand)
- **Business partners list** — sync in Phase 4
- **Recipients list** — delivery locations per business partner (for GLS)
- **User approval** — partner is manually created in Apros and receives approval attribute there
- **Advance payment flag** (`advance_only`) — forces BACS payment method
- **Free shipping rules** — ERP sends flag for buyers who always get free shipping
- **Order sync** — partner ID + location, order sync in Phase 4

### WooCommerce (CMS) is responsible for:
- **Catalog item visibility per user** — exclusively through Bucket System (Phase 5)
- Bucket System is NOT managed in Apros — pure CMS logic
- UI/UX, templates, checkout flow

### Item Sync (Phase 4):
- Uses existing B2C endpoint: `articleList/get`
- ERP adds to existing payload: `b2bArticle` (boolean) + `wholesalePrice`
- Stays on old endpoints since B2C already handles variations, attributes, images, etc.
- Sync covers: products, prices, discounts, stock, orders, photos, descriptions
- Additional categorizations = subcategories created in ERP, synced to shop
- Cron sync (periodic, not real-time for everything)

---

## Checkout, Payment, Shipping

### Payment
- **CorvusPay** — card payment (Phase 7)
- **BACS** — direct bank transfer (advance/prepayment)
- **BACS forced logic:** if ERP sends `advance_only` flag for a company → BACS is the only available option (server-side check, not JS-only) — Phase 7

### Shipping
- **GLS plugin** — ready-made plugin with label printing
- ERP sends recipient list (delivery locations per partner) — Phase 4/7
- Free shipping above a defined amount
- Special rules for buyers with always-free shipping (flag from ERP)
- Alternative delivery address option
- Personal pickup (optional)

---

## Installed Plugins — Feature Map

| Plugin | Feature |
|--------|---------|
| `tinvwl` (TI WooCommerce Wishlist) | Wishlist / save products |
| `cwginstock` (Back In Stock Notifier) | Product availability notifications |
| Relevanssi | Fast search by name or SKU |
| GLS plugin | Shipping + label print (Phase 7) |
| CorvusPay | Card payment (Phase 7) |
| WooCommerce Product Filter (WPF) | Faceted filtering |

Features NOT done with plugins:
- Quick Order — custom solution in Phase 6 (no plugins)
- Min/max order quantity — deferred indefinitely

---

## Staging TODOs (Status as of May 2026)

| # | Task | Priority | Status |
|---|------|----------|--------|
| 1 | PDV filter → Slot/Fill API | — | ✅ RESOLVED — render_block filter removed, native WC tax display |
| 2 | CorvusPay + jquery-migrate testing | BLOCKER | Open — install CorvusPay on staging, test full checkout; if fails, remove jquery-migrate stub |
| 3 | LSCache JS Defer config in admin | HIGH | Open — configure on staging: Page Optimization > JS Defer |
| 4 | fetchpriority count validation | MEDIUM | Open — verify actual above-fold product count in grid on staging |
| 5 | Font preload list | MEDIUM | Open — populate `dreampoint_b2b_font_preloads()` with actual woff2 paths (max 3–4) |
| 6 | update_post_meta_cache — test WPF filters | MEDIUM | Open — verify attribute/brand/category filters work; revert if broken |
| 7 | CF7 contact slug | — | ✅ RESOLVED — now uses `is_page_template('contact.php')` |
| 8 | GTM snippet in footer.php | LOW | Open — add directly before `</body>`, no GTM plugin |

Also pending:
- Define `DP_ERP_WEBHOOK_SECRET` in `wp-config.php` on each environment
- Test new B2B user registration → verify both emails (partner + admin)
- Test ERP webhook: `curl -X POST /wp-json/dreampoint-b2b/v1/approve-user -H "X-DP-ERP-Token: SECRET" -d '{"email":"test@test.com"}'`
- Test `/akcija/` page: WPF filter, sorting, pagination
- Delete `woocommerce/archive-product-discounted.php` after confirming `/akcija/` works

---

## Known Risks

### DB migration — page IDs change [CRITICAL]
Page IDs change on every migration (local → staging → production).
Symptom: `is_page(42)` conditions fail on other environments.
Fix: always `is_page('slug')` or `is_page_template('template.php')`.

### CorvusPay + jquery-migrate stub — untested combination
jquery-migrate is stubbed, CorvusPay not tested without it.
Symptom: checkout crashes or payment form does not load.
Fix: remove stub, let jquery-migrate load. (Staging TODO #2)

### update_post_meta_cache = false — latent filter risk
Symptom: attributes, brands, or custom taxonomies disappear from WPF filter.
Fix: revert both flags to `true` in `dreampoint_b2b_optimize_product_query()`.

### LSCache + Cloudflare — two cache layers
After deploys that change HTML/JS/CSS: purge both cache layers.
Symptom: old JS served with new PHP (JS errors in console).
Order: LSCache purge (WP admin) → Cloudflare purge (CF dashboard).

### CF-Device-Type unavailable locally
`dreampoint_b2b_is_below_lg()` falls back to UA detection without Cloudflare header.
Symptom locally: server-side mobile view can be wrong.
Fix: test mobile view on staging, not locally.

### Multilanguage (WPML) — /akcija/ page
`is_page('akcija')` works only for HR.
For other languages: replace with `icl_object_id`.

---

## Cleanup Status

### Cleaned (completed)
- `inc/my-account.php` — "fjok.hr" replaced with "Dream Point d.o.o.", "Loyalty Club" menu item removed
- `inc/custom-breadcrumb.php` — CPT "job" code and hardcoded ID 312 removed
- `js/jquery.min.js` — deleted (WP provides jQuery)
- `js/products-tabs.js` — deleted + enqueue and AJAX handler in `blocks/products-tabs.php` removed
- `js/variation-stock.js` — enqueue added on `is_product()` with `wp_localize_script` for `stockText`
- `js/tabs-dropdown.js` — enqueue added on `is_product()`
- `composer.json` — updated to PHP `>=8.3` (commit bbba828, 2026-06-01)

### Still needs attention
- `footer-shop.php` — incomplete template, ends div structure without header pair — verify intent before deploy
- Font preload list — empty in `functions.php`; fonts confirmed in `fonts/`: `Jost-Light.woff2`, `Jost-Medium.woff2`, `Jost-Regular.woff2`, `Marcellus-Regular.woff2`

---

## Users List (WP Admin)

- Added "Tvrtka" (Company) column — `billing_company`
- "Odobreno" (Approved) column shows green/red with translatable labels
- Both columns are translatable

---

## Design Requirements

- Design must align with DreamPoint main website (visual, UX, structure)
- Shop is strictly closed — only registered and approved B2B users
- Focus: simplicity of ordering, speed, clarity
- 4 sections visible after login: igračke, lifestyle, izipizi, outdoor
- Dual language: Croatian (primary), English (secondary)

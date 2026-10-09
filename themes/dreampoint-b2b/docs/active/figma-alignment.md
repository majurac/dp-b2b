# Figma Alignment Program — Audit Summary, Batch 1 Plan, Handoff

Status: IN PROGRESS - Batch 1 Steps 0-2 DONE and deployed to staging (commit `5d5c910`, 2026-10-09); Step 3 is next (not started). Created 2026-10-08. Policy record: `docs/decisions.md` ADR-020. Progress record: section 13.

## 1. References

- Final Figma file: https://www.figma.com/design/Te4kr3o8kXDgBLzHewKSYU/DreamPoint-B2B--Copy- (file key `Te4kr3o8kXDgBLzHewKSYU`).
- Authoritative design page: **UI** (`11148:22601`). Pages "HIGH fidelity" (`7089:2307`) and "Design exploration" (`5315:12495`) are historical only.
- Figma MCP note: `get_metadata` without a node id lists only the first (empty, separator) page. Pages load incrementally: use `use_figma` (read-only scripts, `figma.setCurrentPageAsync` once per call, return value <= 20 KB) and `get_design_context` with explicit node ids. Figma `letterSpacing` values are PERCENT (e.g. 4 on a 16px font = 0.64px).
- UI page frames (all 1440 px, no mobile/tablet frames exist or will exist): AUTH-01..04 (`11148:31215/31244/31277/31292`), Home (`31523`, `31626`, `11283:49824`), CAT-01/02/04/05 (`31425`, `33646`, `40292`, `40433`), PDP-01/02/03 (`32726`, `32859`, `11276:25194`, `11276:47939`), BR-01/02 (`33325`, `39940`), QO-01..07 (`33784`, `39288`, `39132`, `34439`, `35136`, `35841`, `36541`), CART-01..05 (`37269`, `38411`, `38590`, `38769`, `38948`), CH-01..03 (`37320`, `38222`, `38357`), MPR-01..05 (`37443`, `37742`, `37814`, `37916`, `37998`, `38145`), CON-01/02 (`37535`, `37699`), SYS-01/02 (`37607`, `37653`).
- Components used by Batch 1: Primary menu `11019:19694`, Secondary menu `11019:19695`, Footer `11148:38330` (component `11022:47388`), Button set `11148:40692`, Search `11148:41007`, title/breadcrumb in CH-02 (`11148:38225/38226`), watermark `11148:38224`.

## 2. Business-logic preservation rule (user-confirmed 2026-10-08)

Figma is authoritative for visual presentation only. It never overrides: B2B registration fields/validation/approval/Apros, authentication and guest restrictions, bucket visibility, binary stock display (ADR-016), pricing/discounts/ERP data, cart and checkout rules, payment/order processing, Apros order integration, cart reservation (ADR-007). A genuine conflict is documented and excluded from implementation until explicitly approved.

## 3. Responsive policy (user-confirmed 2026-10-08)

Desktop 1440 px Figma is the only visual reference. Mobile/tablet are developed pragmatically WITH each component batch (not in a late separate phase), from the desktop design, the existing architecture and standard responsive UX. Missing mobile frames are not a defect or blocker. No separate mobile audit.

## 4. Gap audit summary (verified 2026-10-08, staging as admin, read-only)

Functional structure mostly matches Figma; visual layer does not. Single systemic cause: palette/components inherited from the JekaaStore theme.

| Area | Figma | Staging | Status |
|---|---|---|---|
| Tokens | text `#0d121c`, `#384250`, bg `#f3f4f6`/`#f9fafb`, border `#d2d6db` | `$brand #8C907E` (olive), `$pink #e3869c`, `$text-primary #303030` | PARTIAL |
| Header | dark top bar + 72px light bar, black pill QUICK ORDER, CRO selector | olive top bar, pink button, no CRO, wishlist heart (EXTRA) | PARTIAL |
| Footer | black, logo+links+socials, 3 columns, credits row | light, ACF contact/newsletter; columns ACF-driven (empty on staging) | PARTIAL |
| Title/breadcrumb | left, Petrona 64/72, grey breadcrumb, dp watermark | grey centered band, uppercase | PARTIAL |
| Cart | table with SKU, reservation banner (ADR-007) | cards, coupon field (EXTRA), no reservation | PARTIAL / BACKEND |
| Checkout | Contact/Address/Payment cards | WC Blocks form (hr BACS text already approved) | PARTIAL |
| PDP | catalog no./EAN, MPC, min qty, tabs, "Obavijesti me" | Šifra artikla, dropdowns, no tabs; MPC only if ACF `dp_non_binding_mpc` set | PARTIAL |
| Quick Order | near match | near match; English WBW strings; Figma shows "Na stanju 46 kom" (conflicts with ADR-016) | MATCHED/PARTIAL |
| Brands | 4 columns, tabs | 3 columns, many brand images missing (data) | PARTIAL |
| Registration | Ime, Prezime, E-mail, Lozinka, Firma, OIB | 7 B2B fields, set-password mail | CONFLICT (decision) |
| 404/500, Contact, FAQ, My Account | per frames | partially English, duplicate `/contact/` and `/kontakt/` pages | PARTIAL |

Customer-facing English strings seen on staging (separate i18n follow-up): page titles "My account", "Cart", "Shop", "Quick Order", "Contact"; WC dashboard text (`header.php` ~457, `woocommerce` domain, no hr translation); "451 products", "In Stock", "Search categories", "CLEAR", "Add to Wishlist", "Sort By", "Tax", "Flat rate", "Cash on delivery", "First Name/Last Name".

## 5. Preflight facts for Batch 1 (verified in code)

- **Build:** `npm run build` = sass `sass/style.scss` -> `style.css` (compressed, no map) + `sass/pages/` -> `css/pages/` + `sass/blocks/` -> `css/blocks/` + `sass/vendors/` + esbuild `js/theme-scripts.js` -> `js/theme.min.js`. Generated `style.css`, `style-rtl.css`, `css/pages/*.css`, `css/blocks/*.css`, `js/theme.min.js` are TRACKED and deployed (root `.gitignore` note). Watch-mode output (expanded CSS, maps) must never be committed: run a clean `npm run build` before committing (frontend-runtime.md).
- **Style entry:** `style.scss` imports grids, vars, mixins, typography, components/{header,catalog-menu,content,footer,nav}, pages/quick-order. Standalone page/block SCSS import `theme/_vars.scss` themselves. Enqueue: `dp-style` (global) + conditional `dp-page-*` in `functions.php` ~356-381; cache-bust `_S_VERSION` = max(mtime style.css, theme.min.js).
- **Tokens are compile-time SCSS variables** (no CSS custom properties). Usage counts: `$brand` 55 in 15 files, `$pink` 13 in 6, `$text-primary` 74 in 18, `$text-secondary` 46 in 12, `$black` 34 in 13, `$background-brand` 26 in 8, `$border-primary` 20 in 9. Changing their values would restyle everything at once, including excluded areas.
- **Button mixins:** `button-primary` / `button-outline` (`_vars.scss` ~479-535; pink fill, 58px height, olive hover) are used by `_content.scss:529/532` (scoped to `.custom-form`), `_mixins.scss:15` (global `.button`), `checkout.scss:230`, `myaccount.scss:547/556`, `shop-archive.scss:249/253`, `shop-single.scss:413`. Global form-control rules live under `.custom-form, .fr-request-form` (`_content.scss` ~458+): 46px height, 0 radius.
- **Type mixins** exist (`heading-1..6`, `paragraph-*`, `button-text*`, `card*`); fonts present and sufficient: Montserrat 400/500/600/700 + Petrona 600 (`fonts/`). Icon font `icomoon` has facebook/instagram/linkedin/twitter/youtube/search/cart/user/chevron-*; no X glyph, no vuesax-style icons.
- **Breakpoints (`bp()` mixin):** xxs 370, xs 575.98, sm 767.98, md 991.98, lg 1199.98, xl 1399, desktop 1200-1550, desktop-lg 1551+. Container max-width: 1100 (>=1200), 1252 (>=1400), 1434 (>=1500) with 8px gutters; Figma content width is 1200 at 1440 (120px margins).
- **Header:** `header.php` — `#top-header` (hidden by `wp_is_mobile()` server-side, with `hide-md-sm`; menu = WP menu "Sitemap Menu" from DB; the "Moj Račun" link sits in a bare `.col` outside the nav and is unstyled), main bar with `dreampoint_b2b_catalog_menu_desktop()` + `menu-1`, search, `Quick Order` button (`.button.button--sm`), TI Wishlist counter, mini-cart. Quick Order page uses an `inner-page--quick-order` header variant (`header.php:77`). Title/breadcrumb block `header.php` ~366-420 (`.inner-heading`, styled in `_content.scss:1176`). Also `header-shop-archive.php`, `footer-shop.php` (TODO #8, intent unverified).
- **Footer:** `footer.php` is ACF-options driven (field group `acf-json/group_6733549273639.json`: `footer_sitemaps`, `socials`, `company_*`, `copyright`, `cards`, newsletter `subscribe_*`). Staging DB options appear empty for columns (ACF options are DB content, not synced by Git). Newsletter band is not in Figma; its admin-only notice "Newsletter form ID nije postavljen" is visible to admins only. Footer is hidden on the Quick Order page (`dreampoint_b2b_is_quick_order_page()`).
- **Assets missing from the repo:** dp watermark pattern PNG (Figma `11148:38224`, opacity 0.1), footer logo with "DREAM POINT" wordmark (Figma `11022:47162`, 124.7x47.6), X (Twitter) social glyph, optionally vuesax chevron/search/cart/profile icons. All exportable from Figma MCP (`download_assets`/`get_design_context` asset URLs expire in 7 days; export and commit the files).

## 6. Figma values confirmed for Batch 1 (inferred values marked)

- **Colors:** text-primary `#0d121c`; text-secondary `#384250`; text-tertiary/icon-secondary/border-inverse `#6c727e`; text-inverse `#f9fafb`; bg-primary `#fff`; bg-mono `#fcfcfd`; bg-secondary `#f9fafb`; bg-tertiary `#f3f4f6`; bg-brand `#0d121c`; footer bg `#000` (Dreampoint/950); border-primary `#d2d6db`; border-secondary `#e5e7eb`; icon-primary `#111927`; success `#17B26A`/`#067647`; error/destructive `#f04437` (hover `#fca19b`); primary hover `#1f2a37`; secondary hover border `#9da4ae`/bg `#f3f4f6`.
- **Type:** Petrona SemiBold 64/72 (H1), 40 (H2); Montserrat 20 SemiBold (column titles), 16 SemiBold uppercase tracking 4% (footer links), 14/18 Regular (links), 12 SemiBold tracking 2% (menus), 12 Regular (breadcrumb; current page Bold). Checkout H1 is Montserrat Bold 40 (exception).
- **Spacing:** 0/8/12/16/24/40/120. **Radii:** 2, 8 (search/cards), 6 (textarea), buttons XL 24 / L 20 / M 16 / S 12.
- **Shadows:** card/xs `0 1px 3px rgba(10,14,21,.16)`; card/xl `0 2px 5px rgba(10,14,21,.16), 0 10px 24px rgba(10,14,21,.20)`.
- **Buttons:** XL 48h pad 16/24 font 18; L 40h pad 12/20 font 14 (header QUICK ORDER); M 32h pad 8/16 font 14; S 24h pad 5/14 font 12; Primary bg `#0d121c` text `#f9fafb`; Secondary bg `#f9fafb` border `#e5e7eb` text `#384250`; states Default/Hover/Pressed; icon None/Left/Right/Only.
- **Header:** secondary bar `#0d121c`, 8px vertical padding, 1200 content, items 12px SemiBold `#f9fafb`; primary bar `#f9fafb` 72px, logo mark 56px, search 500x40 radius 8 border `#d2d6db` bg `#fcfcfd` placeholder 16 Regular `#384250`, cart icon 24.
- **Footer:** bg `#000`, padding 40/120, gap 24; row 1 logo | uppercase links gap 32 | socials 24px gap 12; row 2 three columns (title 20 SemiBold, links 14/18 gap 12); row 3 divider `#6c727e` + credits 12px SemiBold uppercase tracking 4% `#6c727e`.
- **Inferred (not in Figma):** all breakpoints, mobile menu/footer collapse patterns, focus rings, disabled states beyond the button set.

## 7. Token migration strategy

1. Do NOT change values of `$brand`, `$pink`, `$text-primary`, `$black`, `$background-brand` etc. in Batch 1 (100+ usages across excluded areas).
2. Add a new partial `sass/theme/_dp-tokens.scss` with namespaced SCSS variables (`$dp-*`) AND matching CSS custom properties on `:root` (`--dp-color-*`, `--dp-space-*`, `--dp-radius-*`, `--dp-shadow-*`, type tokens), imported ONLY from `style.scss` so the `:root` block is emitted once. Standalone page/block files may reference `var(--dp-*)` at runtime since `dp-style` loads on every page.
3. New mixins (`dp-button($size,$style)`, `dp-input`, `dp-heading-*`) consume the new tokens. Old mixins stay for unmigrated pages.
4. Migrate component-by-component with scoped selectors (`#masthead`/`#top-header`, `.page-footer`, `.inner-page .inner-heading`, new `.dp-btn*` + the global `.button` switch as its own gated step). No `!important` hacks, no duplicate conflicting definitions: when a component is migrated its old declarations are replaced, not overridden.
5. Safe globally: additive tokens; `.inner-heading` typography; container width change (see risks). Needs scoping: buttons/inputs (global `.button` hits cart, checkout, account, shop), anything touching `button`, `input`, `h1`, `ul` element selectors (WooCommerce Blocks, WBW, TI Wishlist, Select2, Quick Order).
6. Retiring the old palette variables is a LATER cleanup batch after all pages are aligned, not part of Batch 1.

## 8. Batch 1 scope

IN: tokens; shared buttons and form controls; header + navigation presentation; footer presentation; title/breadcrumb banner; responsive adaptation of those; translation readiness of modified customer-facing strings.
OUT: product card, catalog filters, PDP, Quick Order, registration logic, My Account logic, cart reservation, cart/checkout structure, payment config, ERP, multilingual plugin, unrelated cleanup.

## 9. Batch 1 execution steps (each independently verifiable; one commit per step; every step includes its own desktop 1440 / tablet 768 / mobile 390 pass)

**Step 0 — Baseline and build determinism (no code change).** Capture baseline screenshots (home, shop, PDP, QO, cart, checkout, my-account, brands, guest login/register/lost-password) at 1440/1024/768/390 with the existing Playwright session (read-only; screenshots stay in `.playwright-mcp/`, git-ignored). Run `npm run build` on a clean tree and confirm `git status` shows no diff in tracked generated files (if it does, resolve drift before anything else). Rollback: n/a.

**Step 1 — Tokens (additive, zero visual change).** Files: new `sass/theme/_dp-tokens.scss`, `sass/style.scss` (import), rebuilt `style.css` (+`style-rtl.css` only if `rtl` is part of the process — verify). Figma: variable defs of `11283:49824`/`11148:33784` (section 6). Validation: pixel-equal baseline screenshots; computed `--dp-*` present on `:root`. Dependency: Step 0. Rollback: `git revert` + rebuild.

**Step 2 — Title/breadcrumb banner + container width.** Files: `header.php` (~366-420 markup only if needed), `sass/components/_content.scss` (`.inner-heading`, ~1176; `body.home .inner-page` ~38), `sass/theme/_grids.scss` (container 1252 -> 1216 at >=1400 ONLY if confirmed), `template-parts/brand-hero.php` check, new asset `img/bg/dp-pattern.png` (exported from `11148:38224`, compressed, `loading` not applicable — CSS background). Figma: `11148:38225/38226`, Home/CAT/MPR titles (Petrona 64/72). Behavior: left-aligned H1 + breadcrumb (grey, current Bold), watermark top-right, per-page Checkout exception (Montserrat Bold 40). Responsive: H1 scales (clamp ~32-64), watermark hidden/reduced under 768. i18n: breadcrumb labels already `__()`; page titles are DB content (English "My account", "Cart", "Shop", "Quick Order") — record, do not fix here. Risks: shop archive (`header-shop-archive.php`), brand hero, contact/FAQ intro text, `.has-background` variant, thank-you page hidden. Validation: shop, PDP, cart, checkout, my-account, brands, contact, FAQ screenshots. Dependency: Step 1.

**Step 3 — Buttons and form controls.** Files: `sass/theme/_vars.scss` (new mixins only, old untouched) or the new tokens partial, `sass/theme/_mixins.scss:15` (global `.button`), `sass/components/_content.scss` (~458-535 `.custom-form`), then page files that call `button-primary` (`checkout.scss:230`, `myaccount.scss:547/556`, `shop-archive.scss:249/253`, `shop-single.scss:413`) migrated one file at a time with rebuilt `css/pages/*.css`. Figma: Button set `11148:40692` (section 6), Search `11148:41007`, input container (bg `#fcfcfd`, border `#d2d6db`, radius 8; textarea 6). Sub-steps: 3a new `.dp-btn` mixin + header QUICK ORDER only; 3b global `.button` switch (visible on every page; needs cart/checkout/account/shop validation). Do not touch `wc-block-components-*` or WBW/Select2/TI Wishlist styles. Responsive: touch target >= 44px on <768 even where Figma M/S sizes are smaller. i18n: button labels already via `__()`; no label changes. Dependency: Step 1. Rollback: per-sub-step revert.

**Step 4 — Header.** Files: `header.php`, `sass/components/_header.scss` (972 lines), `_nav.scss`, `_catalog-menu.scss`, `header-shop-archive.php` (verify), icons (existing icomoon first; vuesax only if visibly different and exported as SVG). Figma: Primary menu `11019:19694`, Secondary menu `11019:19695`. Behavior: dark secondary bar, 72px light primary bar, black pill QUICK ORDER (L), search 500x40 radius 8, cart 24px, chevrons on Katalog proizvoda/Brandovi (Brandovi dropdown content is OUT of scope — chevron only if a submenu exists), fix the unstyled "Moj Račun" link (Figma: profile icon + "Moj račun", right side). NOT in Batch 1: "CRO" language selector (needs multilingual decision; render nothing, no placeholder). Wishlist heart stays (EXTRA, business decision), restyled to the icon size. Content (DB, staging): WP menu "Sitemap Menu" label "Prodajni predstavnik" -> "Moj prodajni predstavnik" is a menu edit, not code — record prior value before editing. Responsive: keep existing mobile header (`mobile-menu.js`, `mobile-search-toggle`, `hide-md-sm`); `#top-header` is omitted server-side via `wp_is_mobile()` (LiteSpeed cache vary risk — do not change this behavior in Batch 1); restyle the existing mobile menu with tokens. i18n: "Moj Račun" -> "Moj račun" via `__()`, aria labels already `esc_attr__`. Risks: sticky header (`sticky-header.js`), search AJAX (`ajax-search.js`, `inc/product-search.php`), mini-cart fragments, catalog mega menu (`inc/nav-categories.php` placeholder; final menu UX is Figma node `11022:51566`/ADR-013, separate task), Quick Order header variant. Dependency: Steps 1-3a.

**Step 5 — Footer.** Files: `footer.php`, `sass/components/_footer.scss`, `footer-shop.php` (verify intent, TODO #8), assets (footer logo SVG from `11022:47162`, social SVGs incl. X from `5005:5515..5523`), ACF options content on staging (DB: `footer_sitemaps` three columns "Mapa stranice/Servisne stranice/Uvjeti", `socials`, `copyright`/legal links — content task, record prior values; ACF JSON group is already tracked, no field-group change expected; if a new field is needed follow the ACF Field Group Creation Doctrine). Figma: Footer `11148:38330`. Behavior: black footer, row 1 logo + uppercase primary links + 24px socials, row 2 three columns, row 3 divider + credits. Newsletter band is not in Figma: keep it functional and neutrally styled; decision pending (do not remove). Footer stays hidden on the Quick Order page (existing behavior) pending confirmation. Primary links row: reuse existing URLs via `dreampoint_b2b_*` helpers or a new registered menu location (decide in step; do not hardcode Croatian text — use `__()`). Figma credits row uses English labels ("Privacy Policy", "Terms of Service", "Cookies Settings"): treat as editable content via ACF/menu, not hardcoded. Responsive: columns stack 3 -> 1 under 768, row 1 wraps (logo, links, socials), 44px touch targets. Dependency: Steps 1-3a. Risks: LiteSpeed/Cloudflare cache of footer, MC4WP form, cookie banner plugin if any.

**Step 6 — Verification closeout (no deploy).** Re-run the full baseline matrix, build determinism, accessibility spot-checks (keyboard focus visible on header/footer/buttons, contrast >= 4.5:1), console errors, horizontal overflow at 390/768. Deploy only on explicit user approval.

## 10. Validation and safety plan

- **Safe to automate (read-only, existing admin session in the isolated Playwright profile, plus fresh guest contexts):** page screenshots at 1440/1024/768/390, computed-style assertions, console/network error check, horizontal-overflow check, keyboard-tab/focus-visible checks, guest redirect checks (`/my-account/` login, `/?action=register`, lost-password), link status checks.
- **Needs explicit authorization:** User Switching to partner users (vis_none/vis_full/vis_rule_cat/vis_rule_brand/vis_offer) for bucket-visibility checks, Quick Order add-to-cart, any cart mutation, checkout submission (creates an order and may trigger Apros export), email-triggering flows (registration, lost password), stock changes, partner state changes, ACF options/menu edits on staging. Never bulk-clean the admin cart (existing rule).
- **Protect:** guest restrictions (anonymous cart/checkout stay rejected), bucket visibility, Quick Order, cart/checkout and payment/ERP flows, authentication/registration. Staging `DP_BYPASS_APPROVAL=true`; admin sees all products, so visibility must be checked as partner users when authorized.
- **Rollback:** every step is a single commit of sources + rebuilt generated files; rollback is `git revert` + clean rebuild; no DB writes in code steps. Content edits (menus, ACF options) are logged with prior values before change.
- **Regression hot spots:** global `.button` consumers, `.custom-form` inputs, `.inner-heading` on shop/brand/contact/FAQ, container width, sticky header, mini-cart fragments, search dropdown, WC Blocks checkout buttons, WBW filter UI, Quick Order sticky footer/header variant, `body.home` styles.

## 11. Open decisions (do NOT block Batch 1)

1. Registration fields (Figma AUTH-02 vs 7 mandatory B2B fields, ADR-018/TODO #10) — keep implementation.
2. Figma "Na stanju 46 kom" in QO vs ADR-016 binary stock — keep binary.
3. Coupon field (not in Figma) — keep until client decides. (Wishlist: REMOVED 2026-10-09, see section 14.)
4. "Dodaj novu adresu" / default-address card (hidden in Figma) — needs client decision with ERP.
5. Cart reservation (ADR-007) — Reserved Stock Pro purchase/installation; presentation (`CART-02..05`) follows the plugin state.
6. Payment copy — approved hr ADR-019 text wins over Figma text (Figma has typos "obraduje"/"ce").
7. "CRO" language selector — depends on the multilingual solution.
8. Newsletter band, Figma English legal labels, footer on the Quick Order page, Brandovi dropdown content. (Global container question RESOLVED 2026-10-09, see section 13.)

## 12. Next-session handoff

Already verified (do NOT repeat): the full Figma-vs-staging gap audit (section 4), Figma token/component values (section 6), build/enqueue/token architecture (section 5), the token migration strategy (section 7). Do not re-audit unrelated screens or run a mobile audit.
Start here: Step 0 (baseline screenshots + clean-build determinism check), then Step 1 (additive tokens). Ask before any staging content edits or deploy. Business logic stays untouched (section 2).

## 13. Progress record (2026-10-09)

### Done
- **Step 0:** `npm run build` is deterministic (clean tree before and after). Baseline screenshots 1440/1024/768/390 in the git-ignored `.playwright-mcp/baseline/` (guest: login, register, lost-password, home, kontakt, faq; admin session: shop, PDP, Quick Order, cart, checkout, my-account, brendovi at `/brendovi/`).
- **Step 1** (`8eb2501`): additive tokens `sass/theme/_dp-tokens.scss` (45 `--dp-*` custom properties on `:root` + `$dp-*` SCSS variables), imported only from `style.scss`; no legacy variable changed; no component consumed the tokens at that point.
- **Container** (`26d9aa8`): `.container` max-width at >=1400px changed 1252 -> 1216px in `sass/theme/_grids.scss` (border-box + 8px padding = 1200px usable content, the Figma content width). See "Container width policy".
- **Step 2** (`5d5c910`): shared title banner `.inner-page .inner-heading` (left aligned, 24px padding, no fill, dp watermark `img/bg/dp-pattern.png` exported from Figma `11148:38224` with the 10% opacity baked in and hidden below 768px, Petrona 600 64/72 H1 -> 48/56 <=1199 -> 32/40 <=767, 12px grey breadcrumb with Bold current page, intro text 380px); checkout keeps its H1 (Montserrat Bold 40, 32 on mobile). New WooCommerce override `woocommerce/global/breadcrumb.php` (copy of core template 2.3.0 that wraps the current crumb in `<span class="breadcrumb__current">`); the custom breadcrumb `inc/custom-breadcrumb.php` already used a `<span>` and was not touched.
- **Staging:** pushed to origin/master and fast-forward pulled on staging (`dream9399`), staging HEAD = `5d5c91067f43ab76eab9585efe572c32d6c65db7`, tree clean. `wp cache flush` succeeded.

### Container width policy (confirmed 2026-10-09; the client asked for a wider layout on large monitors)
| Viewport | `.container` max-width | Usable content |
|---|---|---|
| <=1399px | existing tiers (576: 526, 768: 706, 992: 946, 1200: 1100) | unchanged |
| 1400-1499px | 1216px (Figma 1200px content) | 1200px |
| >=1500px | 1434px (preserved; do NOT remove or reduce) | 1418px |

`wishlist.scss` kept its own hard-coded 1236px at >=1400px (decision superseded: the wishlist feature and `wishlist.scss` were removed 2026-10-09, section 14). Verified on staging at 1399/1400/1499/1500: 1100/1216/1216/1434, no horizontal overflow.

### Staging QA (admin session, read-only) and limitations
- Verified: banner/breadcrumb/H1 metrics on shop, category, PDP (breadcrumb only), brands, kontakt, FAQ, cart, checkout, my-account, approval-pending at 1440/1024/768/390; home and Quick Order have no banner (unchanged); no horizontal overflow anywhere; the watermark loads (200, 11 KB, `max-age=604800`).
- Visually reviewed: kontakt, checkout, FAQ, cart, my-account, brands, approval-pending (1440), PDP (1440), shop and my-account (390). No material regression.
- **Cosmetic follow-up (not fixed, outside the Step 2 scope):** on `/my-account/` (logged in) the dashboard intro paragraph keeps the legacy `.my-acccount-intro { max-width: 740px; margin: 0 auto }` (`sass/pages/myaccount.scss`), so it is a centered block with left-aligned text and no longer lines up with the left-aligned H1. Smallest fix: `.inner-heading .my-acccount-intro { margin-left: 0; }` (decide with the My Account batch).
- Not covered: guest-visible pages other than login/register/lost-password (the site redirects guests); a guest/LiteSpeed-cached HTML check beyond the login page. The Figma comparison used measured Figma values (positions, sizes, fonts, colors), not an image overlay.
- **Pre-existing console findings (not caused by Step 2, not fixed):** 404 `wp-content/uploads/woocommerce-placeholder-384x282.webp` on `/brendovi/` (present in the baseline; brand images are missing data) and 404 `img/ico/lock.svg` on `/my-account/` (file not in the repo, not referenced by the theme SCSS).
- **LiteSpeed:** documented procedure (`docs/deploy-runbook.md`, `rules/hetzner.md`): `wp litespeed-purge purge-all`; on failure continue, do not retry; manual fallback WP Admin -> LiteSpeed Cache -> Toolbox -> Purge All (never delete the cache directory). During the deploy `wp litespeed-purge all` failed with "not a registered wp command" (not retried). A guest fetch of `/my-account/` is `no-cache, private` with `style.css?ver=1791531001`, equal to the server mtime of `style.css`, so the new CSS is served and a purge is not required for correctness. An optional manual purge stays an operator action.

### Step 3 readiness (buttons and form controls) - recommended first slice: 3a header QUICK ORDER only
- **Why this slice:** the header QUICK ORDER anchor (`header.php` ~255, `<div class="quick-order-btn"><a class="button button--sm">`) is the only element where Figma shows the new button (Primary, size L: 40px high, padding 12/20, 14px, radius 20, bg `#0d121c`, text `#f9fafb`, hover `#1f2a37`), and `.quick-order-btn` has no SCSS and no JS dependency. The generic `.button.button--sm` (`sass/theme/_mixins.scss:14-26`, with `!important` padding/height/font-size) is shared with the search dropdown (`inc/ajax-handlers.php`), filter buttons, brand/segment blocks, `form-edit-address.php` and `template-parts/loop.php`, so it must NOT be changed in this slice.
- **Files:** `header.php` (anchor classes only: replace `button button--sm` with `dp-btn dp-btn--primary dp-btn--l`; label/URL/`esc_html_e` unchanged), new `sass/components/_dp-buttons.scss` (new `dp-btn` mixin + `.dp-btn*` selectors consuming `$dp-*`/`--dp-*`), `sass/style.scss` (one import), rebuilt `style.css`. No change to `_vars.scss` mixins, `_mixins.scss`, `_header.scss` or any page file.
- **WooCommerce dependencies:** none (theme markup outside WC templates, not in WC Blocks, WBW, Select2 or TI Wishlist). The mobile sticky header uses the same anchor, so the mobile state must be checked.
- **Validation:** `npm run build` clean; `style.css` diff shows only new `.dp-btn*` rules; staging (after an approved deploy) read-only at 1440/1024/768/390 + 1500: header default/hover/focus-visible, sticky state, mobile sticky bar, Quick Order page header variant (`dp-qo-header`, unaffected), touch target >=44px below 768 (`min-height: 44px` in the <768 override), no layout shift of the search/wishlist/cart cluster; other `.button` consumers unchanged (spot-check cart, checkout, my-account, shop, search dropdown).
- **Next slices (after 3a is validated):** 3b global `.button` switch (cart `checkout.scss:230`, `myaccount.scss:547/556`, `shop-archive.scss:249/253`, `shop-single.scss:413`, `_content.scss:528-541`, one file at a time), then 3c form controls (`.custom-form`, `_content.scss` ~458+: bg `#fcfcfd`, border `#d2d6db`, radius 8, textarea 6; Search `11148:41007`). Do not touch `wc-block-components-*`, WBW, Select2 or TI Wishlist styles.

## 14. Wishlist removal (2026-10-09, ADR-021)

The client confirmed that wishlist functionality is no longer required and the site owner deactivated the TI WooCommerce Wishlist plugin. All theme-owned wishlist integration was removed (see `docs/decisions.md` ADR-021): the header/mobile-toolbar heart counter, the product-card heart (`.add-to-fav`), the PDP add-to-wishlist styling, the three `ti-wishlist*.php` template overrides, the wishlist page stylesheet (`sass/pages/wishlist.scss`, `css/pages/wishlist.css`) and its enqueue, plus the related admin script and dependency references.
- Earlier statements in this document that mention the wishlist heart (section 4 header row, section 11 item 3, section 13 container notes, Step 4 header text) are historical; the wishlist control no longer exists, so the header action area now holds only home (mobile), search (mobile), QUICK ORDER and cart.
- The mobile fixed toolbar gap formula was re-derived for four controls: `gap: clamp(6px, calc((100vw - 280px) / 3), 24px)`; the tablet (768-991px) 8px gap workaround was dropped because three controls fit with the default 24px gap.
- The wishlist-heart presentation question for later batches (product cards, PDP) is moot; product-card and PDP action areas only contain the add-to-cart/inquiry controls.


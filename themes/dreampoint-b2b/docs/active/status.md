# Quick Order — Implementation Status

Last updated: 2026-07-21 (Catalog Filters — New/Best Seller/Already Ordered —
plus the WBW AJAX container-check placeholder fix deployed to staging and
end-to-end validated; Quick Order's planned development cycle is now
COMPLETE — see Milestone note below)

## Update 2026-10-29 (T6 Croatian localization) - PARTIALLY VERIFIED ON STAGING (commit `615ea8d`)

Implemented and verified on staging: guest "First Name"/"Last Name" -> "Ime"/"Prezime" (`form-register.php`), My Account dashboard intro moved to a Croatian `dreampoint-b2b` msgid (`header.php`), and page titles in the staging DB (IDs 13 / 11 / 10: "Moj račun" / "Košarica" / "Trgovina"; slugs unchanged). Cart baseline for the test customer was empty and was restored to empty. Open (owner decisions, not changed): cart "Tax" label is the tax-rate name stored in WooCommerce settings (HR 25 %, name "Tax"), the Checkout page title/H1 remains "Checkout", and "(optional)" and "Cash on delivery" remain English; WBW strings and "Best seller" are outside T6. Full record: `docs/active/figma-alignment.md` section 50.

## Update 2026-10-28 (T6 Croatian localization preflight) - PREFLIGHT COMPLETE, OWNER INPUT REQUIRED; NOTHING IMPLEMENTED

Read-only, code-level preflight only (no staging or database access, no changes). Page titles are DB content; unpackaged `woocommerce`-domain strings (e.g. guest "First Name" / "Last Name") are the only reliably untranslated item; other reported English WooCommerce strings may stem from the admin user's intentional `en_US` locale (hypothesis). Proposed terminology is NOT approved; slugs stay unchanged; WBW plugin strings are outside T6. Preliminary estimate 4 h (was 3 h). Four unresolved owner decisions and the full record: `docs/active/figma-alignment.md` section 49. T1-T3b CLOSED, T4 PARTIALLY VERIFIED, T5 CLOSED, Quick Order v1.0.36 CLOSED (unchanged); ERP/APROS, MOQ/Multipack (ADR-035) and Reserved Stock Pro dependencies unchanged; no production environment.

## Update 2026-10-21 (Quick Order v1.0.36: single-option attributes + qty +/- SVG click fix) - STAGING VERIFIED

Two focused plugin commits, both on staging only (production not touched). (1) `2450c5a`, v1.0.35: single-value variation attributes are hidden from the OPCIJA text (ADR-026 addendum 3, `pa_boja` stays visible); verified against all 49 variable products on staging (multi-value attributes and the single-colour products stay visible, REST `key` field present on every attribute). (2) `5e61a33`, v1.0.36: `row-controller.js` now resolves the +/- buttons with `closest()`, because real mouse clicks land on the nested `<svg>`/`<path>` and the old `e.target.matches()` ignored them (bug predated 2450c5a, introduced with the SVG steppers in `ab8a0c3`); regression test `tests/row/run-qty-button-tests.mjs` fails on the old code and passes on the new. Final authenticated staging check (variation 17160, real clicks on the SVG icons): `+` -> quantity 1, selected chip and footer updated; `-` -> quantity 0, chip and footer reset; no cart / wc-ajax / Store API request, no console errors or warnings, "Dodaj u košaricu" never clicked, no order placed. Local-only cart architecture unchanged. Remaining limitation: selected-variation chips still show all attributes, including the hidden single-value ones (intentionally unchanged). Record: `docs/decisions.md` ADR-026 addendum 3.

## Update 2026-10-20 (§5.4 minimal approach recorded) - DOCUMENTATION ONLY, NOTHING IMPLEMENTED

§5.4 (MOQ/multipack conditional upsell): client-confirmed; owner selected the minimal technical approach (native Linked Products → Upsells in the existing "Predloženo za vas" block, no plugin, no matching engine, informational values only, DP-B01 unchanged). Implementation pending data-source and business-rule confirmation (APROS availability of MOQ/Multipack, optional WooCommerce fallback fields only with client approval, exact block behaviour, which upsells to link). Independent of the Reserved Stock Pro cart-reservation item and of T5. Record: `docs/decisions.md` ADR-035 addendum (owner decision).

## Update 2026-10-20 (MPC / MOQ / Multipack requirements) - INVESTIGATION CLOSED, NO CODE CHANGE

MPC = confirmed and already implemented (manual ACF, ADR-011). MOQ enforcement = explicitly excluded by the client (DP-B01: no minimums, maximums or steps); the Figma "Minimalna količina narudžbe 1 kom" row has no data source and is an open owner question. Multipack = Figma-only, no client evidence, no Apros field (ADR-028). [Corrected 2026-10-20 after reconciliation: the client DID request a conditional MOQ/multipack upsell (§5.4, "Ovu opciju bi uključili"), blocked by missing ERP data and a plugin decision; no quantity enforcement is implied. See the ADR-035 addendum.] Matrix, open questions and evidence: `docs/decisions.md` ADR-035. T4 classification unchanged (IMPLEMENTED - PARTIALLY VERIFIED). T5 (cart) has since been CLOSED / VERIFIED on staging (`docs/active/figma-alignment.md` section 48).

## Update 2026-10-20 (T4 PDP and product-card typography) - IMPLEMENTED ON STAGING (`41590fc`), PARTIALLY VERIFIED

Theme SCSS only: PDP title / catalog number / price / stock label / MPC / description and the shared product-card title (two-line clamp) and price aligned to Figma PDP-01 and the Product card; shared mixins untouched. Measured on staging (admin session, read-only) at 1440/768/390 for PDPs and 390/1440 for cards; variation select/clear and ADR-034 verified. Not verified: MPC rows, sale prices, homepage/cart cross-sell/category card contexts (none available or not opened). Corrects the PDP title value in `figma-alignment.md` section 42. Details: `docs/active/figma-alignment.md` section 47. T5 (cart) has since been CLOSED / VERIFIED on staging (`docs/active/figma-alignment.md` section 48).

## Update 2026-10-19 (Segments Section persisted mode) — PERSISTED MODE NORMALISED; EDITOR WORKAROUND REMOVED (staging `04f3bc8`)

Homepage post 17 now stores `"mode":"edit"` for `acf/segments-section` (one scoped `wp_update_post()`, dry-run proven, only that attribute changed, backup `/home/dreampoint.b2b/dp-backups/segments-mode-persist-20261019/`); the temporary `blocks.getBlockAttributes` script was removed and the editor shows the compact card without it. No other live content carried the legacy attribute (only old revisions). Frontend and ADR-009 unchanged. Record: `docs/decisions.md` ADR-029 addendum (final).

## Update 2026-10-19 (Segments Section editor) — SEGMENTS SECTION EDITOR CONSISTENCY FIXED AND VERIFIED ON STAGING (`9ee2871`)

Cause: Segments Section was the only ACF block in `preview` mode (registration and saved content), so the editor canvas showed the unstyled tile list instead of the compact "Edit Block" card. Registration now `edit`; an editor-only `blocks.getBlockAttributes` filter opens the already-saved `preview` instance in `edit` without any database write. The page was not saved; the "autosave more recent" notice is benign (autosave 23509 is identical to the live post). ACF group unchanged, frontend and ADR-009 behaviour unchanged. Record: `docs/decisions.md` ADR-029 addendum (last).

## Update 2026-10-19 (final ACF admin cleanup + audit) — SIX GROUPS ALIGNED AND MIRRORED (staging `df5328c`); TESTIMONIALS AND SHARED SURFACE RETAINED

Brands, Contact Info, Featured Brand, Latest, Discounted and Featured Products now follow the open-accordion pattern and appear in the ACF Field Groups list (DB posts 23522, 23526, 23529, 23535, 23539, 23543; backup `/home/dreampoint.b2b/dp-backups/acf-six-groups-20261019/`). Audit verdicts: Testimonials group RETAINED (required infrastructure of the registered but unused `acf/testimonials-section` block, 0 usages, no data; retiring the whole block is an open owner decision); Shared Surface Context RETAINED (ADR-009 Phase B configuration, live values on the homepage and the three segment pages; ADR-009 unchanged). Frontend unchanged. Record: `docs/decisions.md` ADR-029 addendum.

## Update 2026-10-19 (ACF homepage admin cleanup) — DONE ON STAGING (code `4465f09`, DB operations documented in ADR-029)

Owner's accordion-open edits (Hero/About/Contact Form/Testimonials) committed; Featured Section and Company Features aligned with the accordion pattern and mirrored into the ACF admin list (DB posts 23510/23516); note-only "Block / Segments Section" group added (23519, the block never had a group); legacy `block == all` groups "About Global" (78) and "Blogs" (81) removed after evidence and backup (`/home/dreampoint.b2b/dp-backups/acf-homepage-admin-20261019/`). Frontend unchanged. Open: Brands, Contact Info, Featured Brand, Latest / Discounted / Featured Products and other groups are still JSON-only. Record: `docs/decisions.md` ADR-029.

## Update 2026-10-20 (password-set confirmation on approval-pending, ADR-032 addendum) — IMPLEMENTED ON STAGING (`01620b6`), logic-level verified only

A pending customer who sets a password now sees a one-time "Lozinka je uspješno postavljena. Vaš račun i dalje čeka odobrenje administratora." on `/approval-pending/` (trigger: `woocommerce_customer_reset_password`, carried by the WooCommerce session, consumed on display, not forgeable by URL). NOT E2E verified (staging bypass on, no pending account, no real reset). Details: `docs/decisions.md` ADR-032 addendum.

## Update 2026-10-20 (registration / password lifecycle corrections, ADR-032) — IMPLEMENTED ON STAGING (`fd15dd6`), logic-level verified only

Pending customers can now use the `lost-password` endpoint (set-password link, request form, confirmation) through the approval gate; the registration e-mail states the 24 h / single-use validity with a Lost Password link; the approval e-mail explains how to set a password. No reset key is generated at approval. NOT E2E verified (staging `DP_BYPASS_APPROVAL=true`, no real pending account, no e-mail sent). Details, security review and open items: `docs/decisions.md` ADR-032.

## Update 2026-10-20 (password recovery RCA + AUTH-04) — IMPLEMENTED / VISUALLY VERIFIED ON STAGING (`93896d0`); real reset NOT E2E tested

Root cause of the header/dashboard text around the lost-password form: logged-in users got the full page chrome on every account endpoint (only guests were exempt), and the post-submit confirmation was WooCommerce's unstyled core template. Fixed with a lost-password-endpoint-only standalone AUTH gate and a Figma AUTH-04 confirmation override; reset processing and e-mail untouched. "Ponovo pošalji mail" links to the request form (it cannot resend in one click): owner decision. Details, state matrix and unverified states: `docs/active/figma-alignment.md` section 46.

## Update 2026-10-20 (T3b approval pending / AUTH-03) — IMPLEMENTED AND VERIFIED ON STAGING (`4c2d193`), as admin (no real pending user)

Standalone split page per Figma AUTH-03 (`approval-pending.php` on the shared `.login-register--pending` shell, no site header/footer, new icon + photo assets, Figma copy, logout and mailto actions). Separate from T3 (unchanged). Details and the unverified items: `docs/active/figma-alignment.md` section 45. Next backlog item: T4.

## Update 2026-10-20 (T3 assets) — T3 LOGIN / REGISTRATION COMPLETE / VERIFIED ON STAGING (`1374d10`)

Figma photo (attachment 23547, ACF option `lr_image`, previously empty) and the horizontal wordmark (`img/logos/dreampoint-wordmark.svg`, login + register templates only) integrated and verified at 1440/1280/768/390/360; photo made viewport-high/sticky on tall pages. Details: `docs/active/figma-alignment.md` section 44. Next backlog item: T4.

## Update 2026-10-20 (T3 login / registration) — LAYOUT AND TYPOGRAPHY VERIFIED ON STAGING (`612efec`), PARTIAL: photo and logo asset pending

Theme SCSS only (`sass/pages/myaccount.scss` + compiled CSS): grey `#f3f4f6` page, 584px column top-aligned, Heading 1 Medium title, 48px logo gap, 14px link/footer styles. `lr_image` is empty and the Figma wordmark logo differs from the ACF `logo` mark: owner data, nothing uploaded. Details: `docs/active/figma-alignment.md` section 43. Next backlog item: T4.

## Update 2026-10-19 (T2 photo content) — T2 COMMERCIALIST PHOTO COMPLETE / VERIFIED ON STAGING (temporary dummy portrait, attachment 23506)

Staging content only (no code change, staging HEAD `f68b0f9`): the dummy commercialist `1745` has a temporary original placeholder portrait in `photo_commercialist`; the homepage contact card shows it with the caption "Vaš prodajni predstavnik" at 1280/768/390/360 (persisted state, no injection). Replace it with a real photograph if the representative becomes real. Open T2 item: USP icon order (content). Details: `docs/active/figma-alignment.md` section 42, "T2 photo content assigned".

## Update 2026-10-19 (T2 completion: commercialist photo) — IMPLEMENTATION COMPLETE / VERIFIED; PHOTO CONTENT PENDING (staging `f68b0f9`)

New optional ACF image field `photo_commercialist` (`field_6a0c4e7b91d3a`, return format ID) on the existing `komercijalist` group `group_67b45eed142b8` (JSON-only group on staging, live without DB sync); the homepage contact card renders the caption "Vaš prodajni predstavnik" and, when set, the photo of the logged-in partner's assigned representative (selection logic unchanged). Empty state verified on staging at 1280/390/360; the populated state only by non-persistent injection (no real photo exists, no record changed). Owner action: upload representative photos. Details: `docs/active/figma-alignment.md` section 42, "T2 completion".

## Update 2026-10-19 (T2 homepage lower sections) — T2 IMPLEMENTED AND VERIFIED ON STAGING (`e837c96`), contact-card photo BLOCKED

Brands/Quick Order headings (Figma 40px / 64px), Quick Order panel (478px, ACF photo right 50%), four-column USP row and contact card are aligned and QA-verified at 1280/390/360. Not done (content dependencies, no invented assets): the sales-representative photograph and caption (`komercijalist` has no photo field) and the USP icon order in the `features_items` option. Record, deviations and Figma nodes: `docs/active/figma-alignment.md` section 42, "T2 implemented". Next backlog item: T3.

## Update 2026-10-19 (T1 checkout visual fix) — T1 CHECKOUT VISUAL FIX IMPLEMENTED AND VERIFIED ON STAGING (`1a98eab`)

Theme-only CSS (`sass/pages/checkout.scss`, compiled `css/pages/checkout.css`): injected company/billing headings aligned to Figma (Montserrat 14/600), floated labels no longer cover filled inputs and selects. QA at 1280/390/360 without typing or submitting; invalid/autofill/disabled states unverified; checkout PHP/JS, payment, shipping and validation untouched. Details, before/after and new out-of-scope observations (pre-existing "(optional)" checkbox-row overlap at 390, radio-dot offset): `docs/active/figma-alignment.md` section 42, "T1 implemented". Next backlog item: T2.

## Update 2026-10-19 (remaining Figma audit) — REMAINING FIGMA ALIGNMENT AUDIT COMPLETE (read-only; staging `7b5ea10`, docs HEAD `1e33d26`)

Single prioritized backlog (T1-T10, whole-hour estimates) in `docs/active/figma-alignment.md` section 42. Confirmed: checkout oversized headings and label overlap (CSS root cause: `padding-top:0` on inputs with absolute labels), UA-blue order-number link (no Figma target), unaligned homepage lower blocks, login/registration split layout (photo is empty ACF `lr_image` data), no horizontal overflow at 390/360 on 10 pages. Recommended first task: T1 checkout visual repair (theme CSS only). No code, build, deploy or staging change.

## Update 2026-10-19 (PDP Description tab) — PDP DESCRIPTION TAB IMPLEMENTED AND VERIFIED ON STAGING (`a2750fb`); ERP FUTURE-TABS AUDIT COMPLETE

Record: `docs/decisions.md` ADR-028 (supersedes ADR-014 known item 1). Commits `76e4162` (tab section activated, single Description tab, mobile rules removed, single-tab JS guard), `657508c` (short-description block only with a real excerpt: it auto-duplicated the description), `4ecd403` (Figma tab typography), `a2750fb` (keyboard focus ring). Staging QA (read-only, Playwright, admin session, 0 console errors): `lepeza-feel-free-maxi` (simple) and `termos-boca-clima-titanium` (variable) show exactly one tab "Opis" with the native description, one occurrence of the text, no review UI, no hidden focusables, no horizontal overflow at 1280/390/360; `nuuna-biljeznica-sweet-joe-graphic-format-s` (simple) and `boca-urban-mini` (variable) have no long description: no wrapper/tab/panel. Add-to-cart, variations and related products present; no pricing, stock, checkout or importer change.
Documents feasibility (RESEARCH ONLY, not approved): `docs/active/erp-product-documents-feasibility.md` — official API v1.0 has no document endpoint; vendor confirmation required.
ERP audit result: Specifikacije = PARTIAL (only model/age/colour/diopter/volume attributes; no weight/dimensions), Sadrzaj paketa = NOT AVAILABLE, Dokumenti = NOT AVAILABLE (matrix in ADR-028). Deferred: all three tabs; reviews; ERP/importer extensions.

## Update 2026-10-19 (product reviews) — REVIEW-TAB FOLLOW-UP RECLASSIFIED: B (NOT SPECIFIED IN FIGMA / NO ACTION REQUIRED)

Read-only investigation (Figma MCP + theme code + existing docs; no code, staging or settings changed; Phase 4 closeout above is unchanged).

- **Figma:** PDP-01/PDP-02 (`11148:32726`, `11148:32859`, file `Te4kr3o8kXDgBLzHewKSYU`) have a tab bar with `Opis / Specifikacije / Sadržaj paketa / Dokumenti` (plus an empty fifth slot). No Recenzije/Reviews tab, rating stars, review count or review form on the PDP; the Product card (`11148:32832`) has no stars either. Absent from the approved design, not explicitly prohibited anywhere in the docs.
- **Implementation:** `woocommerce/content-single-product.php:332` wraps `woocommerce_output_product_data_tabs()` in `<div class="wc-tabs-section block" style="display: none;">` (present since the monorepo migration `e90017a`; already recorded as a pre-existing known item in `docs/decisions.md` ADR-014, "mobile tabs dropdown"). So the ENTIRE tabs section (Description, Additional information, Reviews) is hidden on every PDP; the zero-size bounds seen during Phase 4 QA are this inline style, not a review-specific fault. WooCommerce review markup is still emitted (the real `.woocommerce-noreviews` notice was observed). No theme code removes reviews; no document requires them.
- **Conclusion:** hidden review UI is not a regression and not an actionable Figma mismatch. The historical QA observation (zero-size panel, stars not visible) stays valid as a record. If product tabs are later implemented per Figma (Opis/Specifikacije/Sadržaj paketa/Dokumenti), that is a separate PDP task and the owner must decide then whether reviews are wanted at all (WooCommerce setting, not decided here).
- Review-star colours in Phase 4 remain "no Figma star evidence; existing-token fallback" (§41); that is unchanged.

## Update 2026-10-19 (closeout) — FINAL FIGMA COLOUR ALIGNMENT: IMPLEMENTATION COMPLETE / VERIFIED WITH DOCUMENTED QA LIMITATIONS (staging `14ed363`; docs HEAD `9352aaa`; staging only)

Phases 1-3 complete and verified (`0329887`, `48af45d`). Phase 4 implemented (`14ed363`, `docs/active/figma-alignment.md` §41): Sass build and compiled-CSS diff passed, WCAG AA passed for the modified notice pairs, deployed to staging, authenticated (admin session, read-only) QA found no regression in the tested scope. This supersedes the "NEXT TASK - Phase 4 (NOT STARTED)" paragraph of the 2026-10-18 entry below (kept as history). THEME colour alignment is finished; Quick Order and WBW plugin palettes are separate future scopes.

**Verified on real rendered elements:** `.woocommerce-noreviews` (PDP), `.woocommerce-info` (My Account orders), `.required`, password strength `short`/`bad`/`strong`, focus ring, unchanged stock colour, cart and checkout (view only), 9-page overflow sweep at 390 and 360, 0 console errors.
**NOT verified on real rendered elements (compiled CSS and/or synthetic DOM only):** success and error notices, checkout valid/invalid field states, CF7 invalid state, review stars, password strength `good`.
**Separate follow-ups (not Phase 4, not fixed):** (A) ~~staging PDP review tabs/panel zero-size~~ - CLOSED 2026-10-19 as not an actionable defect (classification B, see the 2026-10-19 product-reviews entry above; the whole `.wc-tabs-section` is hidden by a long-standing inline `display:none`, and reviews are not in Figma); (B) `/my-account/orders/` order-number link is UA-default `rgb(0,0,238)` (no theme colour rule), low-priority design alignment; (C) pre-existing checkout issues (oversized "Podaci tvrtke / Adresa naplate" headings, e-mail label overlapping its value) - already recorded in §39; (D) Quick Order and WBW plugin palettes.

## Update 2026-10-18 (end of day) — FINAL FIGMA COLOUR ALIGNMENT: PHASES 1–3 COMPLETE AND VERIFIED (staging `a1fba35`; staging only, no production environment exists)

Closed: legacy olive/pink cleanup (`docs/active/figma-alignment.md` §38), `img/ico/lock.svg` deployed (`ffb9761`), old refund-form SCSS removed (§39), colour phases 1–2 (code `0329887`, §39) and phase 3 (code `48af45d`, §40). Each phase: clean Sass build, rule-by-rule compiled-CSS diff, staging QA. Details and mappings live in `figma-alignment.md` §38–40 (not repeated here).

**NEXT TASK — Final Figma Colour Alignment, Phase 4 (NOT STARTED).** Scope: WooCommerce success/error/info notices; validation colours and required-field indicators; password-strength presentation; review-star colours; remaining colour literals (incl. `#000`) only where a confirmed semantic mismatch exists; remaining legacy shadows only where Figma alignment is justified. Constraints: colour changes only (no dimensions, spacing, typography, layout, PHP/JS/business logic); preserve checkout and WooCommerce validation behaviour; verify WCAG AA, especially white text on error backgrounds (Figma `#f04437` is 3.7:1, use the existing `$dp-color-error-text` `#d92d20` deviation); no order submissions or customer-data changes; keep functional stock colours (`#067647/#17B26A/#B42318/#F04438`) and documented accessibility exceptions (footer credits `#9ca3af`, search `#868c96`/`#141414`, control border); do not remove the unused `--dp-color-*` properties; Quick Order and WBW plugin palettes are separate future tasks; no localhost without explicit authorisation. Known visual-QA limitations carried over (verified in compiled CSS only, not blocking): disabled buttons, hero/about blocks, order-details/thank-you, `lr-footer` on login/registration, modal and sliding-content close states, pointer hover states. Pre-existing non-colour UI issues (oversized checkout "Podaci tvrtke / Adresa naplate" headings, e-mail label overlapping its value) are separate tasks.

## Update 2026-10-16 (catalog menu FOUC) — CATALOG MENU FOUC FIXED AND VERIFIED (staging `299770a`)

Root cause (measured on staging, rAF sampling): `header.php` renders `#catalog-menu` as a direct child of the header `<nav>`, but every hide/position rule is scoped to `#header #main-header ul.links li .catalog-menu`. Until the deferred `js/mobile-menu.js` moves the panel into `li.cat-toggler` on DOMContentLoaded, it was `position:static; visibility:visible` (132x706 px, ~340-420 ms) and then snapped hidden. Fix: one pre-move rule `#header #main-header nav > .catalog-menu { position:absolute; visibility:hidden; opacity:0; pointer-events:none }` in `sass/components/_catalog-menu.scss` (+ compiled `style.css`); no PHP/JS change. Verified at 1440/768/390 with `mobile-menu.js` delayed 2 s: 0 visible frames; hover, focus-within, Tab into panel, Escape, `aria-expanded`, no tabbable links while closed, no console errors, header height/trigger position unchanged. Known limitation: the existing 0.15 s visibility transition means focus opens the panel after ~150 ms (pre-existing). Localhost not used.

## Update 2026-10-14 (ACF recovery) — General Settings ACF recovered, canonical JSON and DB sync verified (staging)

Commit `e28c0c0` (JSON; 38 -> 35 fields, unused `company_info` intentionally gone) plus a by-ID sync of DB group 37; option values unchanged. Root cause: stale DB mirror overwrote newer JSON on an admin save. Rule added to the theme `CLAUDE.md`. Details: `docs/active/figma-alignment.md` section 32.

## Update 2026-10-14 (contact field) — `contact_info_button` is now an ACF Text field (staging verified)

Commit `e18ab86`: Link -> Text, same key `field_6a9021b3c9e03`; only the label is stored, destination stays automatic. Staging value empty (enter a label to show the button). See `docs/active/figma-alignment.md` section 31 addendum.

## Update 2026-10-14 (contact) — Contact Info button: context-aware contact form anchor (staging verified)

Commit `4a9c2c6`: `[contact_info]` button goes to `#contact-form` on the Contact page and to `<contact page>#contact-form` elsewhere (`dreampoint_b2b_contact_form_url()`); ACF `contact_info_button` supplies only the label (staging value empty: enter a label to show the button). Details: `docs/active/figma-alignment.md` section 31.

## Update 2026-10-14 (a11y) — Step 6 accessibility P1 fixes: PASS WITH KNOWN ISSUES — ACCESSIBILITY P1 FIXES VERIFIED (staging)

Commits `8a832dd`, `c8b12bc`, `d22c9a0`: visible focus rings on header controls and skip link, closed mini-cart / mobile-search overlay no longer in the tab order, `aria-expanded` / `aria-controls` on the Katalog and Brendovi triggers (and mobile submenu triggers), footer credits `#9ca3af` (owner-approved accessibility deviation from Figma). Search contrast, mobile touch targets and screen-reader testing remain open. Details: `docs/active/figma-alignment.md` section 30.

## Update 2026-10-14 (admin) — Newsletter, Cards and Popup ACF tabs hidden (reversible, STAGING ONLY)

The three tabs of "General Settings" (options page `theme-settings`) are hidden from the admin for all users by an `acf/prepare_field` filter in `inc/acf-hidden-fields.php` (required from `functions.php`, commit `45e6c9a`). Hidden keys: tabs `field_681dd4904b40c` (Newsletter), `field_683414b4aefe1` (Cards), `field_68343caea15e7` (Popup); fields `field_681dd4a04b40d`, `field_681dd4a84b40e`, `field_681dd4c14b40f` (subscribe_title / _text / _form_id), `field_68341555eb4fa`, `field_683414c3aefe2` (cards_title, cards), `field_68343cbea15e8`, `field_68343d73a15e9` (popup_title, popup_show). ACF JSON, definitions, stored values and frontend `get_field()` (footer newsletter band logic) are untouched; ACF Field Groups screen unchanged; not role-specific. **Restore:** add `define( 'DP_SHOW_HIDDEN_ACF_FIELDS', true );` to `wp-config.php` (the only definition; the file just reads it); remove the line to hide again; OPcache can delay either change up to ~60 s. **Staging validation 2026-10-14:** theme-settings shows Company Info, Logo, Socials, Brands, Footer Sitemap, Copyright (49 fields), none of the 10 hidden keys; a normal save kept a sentinel in hidden `popup_title` (restored to empty afterwards; only side effect: `copyright` newlines normalised to CRLF by the browser textarea); toggle on = 9 tabs / 61 fields / 10 of 10 keys back, toggle off = hidden again; wp-config.php restored byte-identical to the backup (`/home/dreampoint.b2b/dp-backups/footer-acf-20261014/wp-config.before`); frontend footer unchanged, no PHP warnings, no JS errors. **Production not deployed.**

## Update 2026-10-14 (ACF JSON reconciliation) — FOOTER STEP 5 ACF RECONCILIATION — VERIFIED

Only `company_address` removed from `group_6733549273639.json`; `company_timings`, Brands tab / `intro_text_brands`, `contact_info_*`, `popup_title` kept; the open conflict in the entry below is resolved. See `docs/active/figma-alignment.md` (end of section 28).

## Update 2026-10-14 (footer ACF) — Owner's ACF-driven logo/copyright deployed; OPEN: ACF JSON conflict

Commit `b6a4850`: footer logo from `logo_extended`, credits from `copyright` (staging values set, attachment 23458); owner typo `$$logo_extended` fixed. The owner's local `acf-json/group_6733549273639.json` also drops `company_timings`, `company_address`, `intro_text_brands` (still used) and was NOT deployed (kept uncommitted locally). Needs an owner decision. Details and rollback: `docs/active/figma-alignment.md` section 28.

## Update 2026-10-14 (footer typography) — FOOTER STEP 5 TYPOGRAPHY — FIGMA ALIGNED, STAGING VERIFIED

Commit `e81fd95`: copyright / credit / legal text 12/20 SemiBold 4% uppercase per Figma (was the corporate 16 px); layout unchanged. Details: `docs/active/figma-alignment.md` section 27.

## Update 2026-10-14 (footer copyright area) — FOOTER STEP 5 COPYRIGHT AREA — STAGING VERIFIED

Owner-requested correction (commits `f1929f7`, `310027a`, `766387f`): copyright/credits left and legal list right, per the dream-point.hr `.copyright-area`; the three legal items link to the corporate pages by owner decision (supersedes the plain-text rule of the entry below). Details: `docs/active/figma-alignment.md` section 26.

## Update 2026-10-14 (footer content) — Step 5: FOOTER STEP 5 PARTIAL — LEGAL/CONTENT DEPENDENCIES REMAIN

Commit `8f1190b` (staging HEAD): five social icons always visible (non-interactive without a URL), three exact legal labels (plain text until a destination exists), Croatian copyright line. Staging ACF: LinkedIn URL from the corporate footer, sitemap column "Mapa stranice". Real-data browser QA passed at 1440/1024/768/390. Open: legal pages, cookie consent, other social URLs, hidden columns. Details, rollback and QA: `docs/active/figma-alignment.md` section 25.

## Update 2026-10-13 (footer implemented) — Step 5: two-variant footer STAGING VERIFIED (limits noted)

Commit `edaf4a1`: compact footer on the homepage (owner screenshot), full Figma footer elsewhere; new menu location `footer-primary` + staging menu "Footer primary"; valid-links-only helpers (`inc/footer.php`); newsletter band only for a working Mailchimp form; contact column / cards no longer rendered. Real ACF content (socials, sitemap columns, legal pages) is still empty, so icons/columns/legal links were verified by simulated markup only. Details and NEXT SESSION HANDOFF: `docs/active/figma-alignment.md` section 24. The preflight (section 23) status line is superseded by this update.

## Update 2026-10-13 (footer) — Step 5 preflight: FOOTER STEP 5 — PREFLIGHT COMPLETE / IMPLEMENTATION NOT STARTED

Read-only Figma-vs-staging footer audit finished; plan and owner decisions in `docs/active/figma-alignment.md` section 23. Key facts: staging footer ACF (`socials`, `footer_sitemaps`, `copyright`, `cards`, `logo_extended`) is empty; most legal/service pages in the Figma columns do not exist; newsletter (Mailchimp for WP) is unconfigured and its empty band renders for all visitors; no cookie-consent plugin. TODO #8 resolved (`footer-shop.php` is the shop-archive closing partial); TODO #6 (GTM in `footer.php`) is stale.

## Update 2026-10-13 (JEKAA assets) — brand asset recovery PARTIAL

JEKAA B2C lists only 13 brands; just 3 assets filled real gaps (Eat My Socks logo + hero, SECRID hero 990 px). Coverage on staging: logos 29/61, heroes 28/61, 32 brands with neither, Done by Deer still without hero. Backup/rollback `/home/dreampoint.b2b/dp-backups/brand-jekaa-20261013/`. Details: `docs/active/figma-alignment.md` section 22, last subsection.

## Update 2026-10-13 (brand directory) — /brendovi/ cards fixed on staging

All 61 cards visible again (commit `751cd6a`): stable 236px card with name/dark fallback, broken placeholder `<img>` removed; 17 hero photos imported from dream-point.hr (Secrid and Done by Deer reverted: source only 282x236, pixelated on brand pages). Backup/rollback in `/home/dreampoint.b2b/dp-backups/brand-heroes-20261013/`. Details: `docs/active/figma-alignment.md` section 22, last subsection.

## Update 2026-10-13 (brand URLs) — /marka/ 404 fixed on staging

Root cause: `hr` locale makes WooCommerce's brand slug `marka`, but stored rewrite rules still had `brand/`. Fixed with `wp rewrite flush` (snapshot in `/home/dreampoint.b2b/dp-backups/rewrite-flush-20261013/`); all 61 `/marka/<slug>/` pages verified. Open: production flush/decision, optional 301s from `/brand/`, pin "Brand base"; and the 19 newly logoed brands have invisible cards on `/brendovi/` until they get a `brand_image` (see `figma-alignment.md` section 22, last two subsections).

## Update 2026-10-13 (brand logos) — recovery from dream-point.hr PARTIAL

19 logos imported from the first-party `dream-point.hr/brendovi/` (exact name match) to staging; 28 of 61 brands now have a logo, 33 still need an owner upload (CHILLYS attachment 108 is a product photo, not a logo). Backup/rollback in `/home/dreampoint.b2b/dp-backups/brand-logos-20261013/`. Found a pre-existing defect: brand links `/marka/<slug>/` 404 on staging because stored rewrite rules still use `/brand/` (needs a rewrite flush + URL decision, not done). Details: `docs/active/figma-alignment.md` section 22, last subsection.

## Update 2026-10-13 (later) — brand logo provenance and sticky header

Brand logos are VERIFIED locally maintained (Apros `brandList` gives only `brandId`/`title`; the importer writes only `remote_category_id`); 52 of 61 staging brands still need a logo (owner, WP Admin -> Products -> Brands -> "Thumbnail"; NOT "Featured Image"). Unexplained drift: CHILLYS (283) lost `thumbnail_id` (attachment 108 still exists). Sticky `#header` (owner's Sass change, compiled) deployed in `484af58` and staging-verified at 1440/1024/390. Details: `docs/active/figma-alignment.md` section 22 follow-up.

## Update 2026-10-13 — Figma Step 4B.2: Brandovi logo dropdown STAGING VERIFIED

Commits `eb6796b` and `94f8895` (staging HEAD `94f8895`; production not touched): server-rendered logo panel on the desktop `brands-toggler` menu item (`inc/nav-brands.php`, `js/brands-menu.js`, `sass/components/_brands-menu.scss`), real `product_brand` terms name ASC with `thumbnail_id` logos and text fallback, visibility-filtered per user (no bypass, no cache), hover/focus/Escape/touch-first-tap, mobile stays a plain link. Visibility verified per test user at function level; touch is synthetic-only. Details: `docs/active/figma-alignment.md` section 22.

## Update 2026-10-12 — Figma Step 4B.1: header visual alignment STAGING VERIFIED

Commits `50f06af` and `6e24eea` (staging HEAD `6e24eea`; production not touched): secondary bar 40px `#0d121c` with "Moj račun" icon + text, primary bar `#f9fafb`, 56px logo, nav typography, 40px search field. Staging QA PASS at 1500/1440/1024/768/390 (no overflow, no page errors; catalog hover/keyboard, AJAX search, QUICK ORDER, mobile menu and toolbar intact). Owner-assigned class `brands-toggler` on Brandovi is preserved; the Brandovi dropdown is pending (next: Step 4B.2). Details: `docs/active/figma-alignment.md` section 21.

## Update 2026-10-12 — Segments Section: interaction QA and legacy cleanup COMPLETE on staging

Staging HEAD `549ef5c` (production not touched). Keyboard focus ring fixed (`370a722`: the global `a:focus { outline: none !important }` swallowed the outline; now an inset box-shadow overlay) and verified with real keyboard input at 1440px; touch swipe and tap on the tiles confirmed by the project owner on a physical device (owner-confirmed, not automated). The legacy Featured Categories block (`04077060`: block, template, Sass/CSS, enqueue mapping, `$slider_blocks` entry, Slick initializer) and its exclusive ACF group `23359` with seven fields plus the JSON file (`549ef5c`) are removed; the earlier "five fields" count was wrong, the guarded preflight found seven. The shared `.categories-btn` mixins and utility, the `category-slider` image size and the `company-features` selectors stay on purpose. Rollback backup: `/home/dreampoint.b2b/dp-backups/acf-featured-categories-20261012/`. Details: `docs/active/figma-alignment.md` section 20. The previously listed Segments Section follow-ups (keyboard focus, physical swipe, legacy cleanup) are closed; the source-photo resolution limit and the wide-screen tile size remain accepted. This does not make the Figma alignment programme or the project complete.

## Update 2026-10-12 — Segments Section (dynamic homepage segment tiles) — STAGING VERIFIED

Staging HEAD `3e5928a` (also local and origin/master at the time; production not touched). The homepage Featured Categories block was replaced on staging by the new dynamic `acf/segments-section`, which renders the published Lifestyle / Toys / Outdoor segment landing pages (title, `get_permalink()`, native Featured Image, per-segment static PNG logo; ordering and eligibility from `dp_page_segment_context`; no new ACF fields, no taxonomy — `brand_segment` is an ACF select on `product_brand`, not a taxonomy). Commits: `2582ea0` (block, helpers, Sass/CSS, JS init, logos) and `3e5928a` (Slick duplicate tab-stop fix and per-file `slick-init.js` version). Staging content: homepage page 17 migrated (backup in `/home/dreampoint.b2b/dp-backups/segments-phase2-20261012/`), Featured Images 23409/23410/23411 assigned to pages 23369/23370/23371. QA PASS at 1440/820/390, assets over HTTPS, console clean. Outstanding: real keyboard focus ring and physical swipe not verified; source photographs are only ~792x474 landscape (sharper portrait originals wanted); legacy Featured Categories code and ACF group remain until the unused-components cleanup. Details: `docs/active/figma-alignment.md` section 20, ADR-024. This does not make the Figma alignment programme or the project complete.

## Update 2026-10-12 — Segment Featured Image admin guidance — STAGING ADMIN VERIFIED

Commit `884e9f1` (`inc/homepage-segments.php`, `js/admin-segment-image-hint.js`; staging HEAD `884e9f1`). Segment Landing pages (Lifestyle 23369, Toys 23370, Outdoor 23371) now show an informational Croatian note under the native Featured Image control in the block editor: recommended 500 x 750 px (2:3), vertical high-resolution photo, image is cropped to the tile. Lightweight target for the largest measured tile (~457 x 682 px), not a Retina/2x one; nothing is enforced. Official `editor.PostFeaturedImage` filter, no ACF field, no CSS. Staging admin QA PASS (note present on the three segment pages, absent on Homepage 17 and My Account 13, hides/restores with the template selection without saving, no duplicates, no new JS errors, no content change). Accepted limitation: it relies on the saved segment context, so a new segment page or a changed context shows the note only after save and reopen. Details: `docs/active/figma-alignment.md` section 20. Production not touched.

## Update 2026-10-09 — Product card variable / out-of-stock actions done (`2e3a5c3`) — staging browser QA PASS

Staging, local and origin/master HEAD `2e3a5c3`. Variable and out-of-stock product-card actions now use the Secondary M icon-only button (32x32, `icon-chevron-right`) as plain links to the product permalink, and the localized "Nema na stanju" status is visible again in the card's price slot; the simple-product action was already done and its branch is unchanged. Validated on staging at 1440/1199/390 (`/shop/`: `# OFFICE READING`, `#C SUN BABY`): sizes, icon, status text, hit-testing, one navigation click per action with no cart or AJAX request, aria attributes, forced focus/hover/pressed states, no overflow, console clean. Not covered: simple-product browser retest, 320/992, screen-reader software. The overall Figma alignment is NOT complete. **Next recommended task: Featured Categories — focused Figma alignment** (not started); a separate unused-components cleanup is planned after the primary alignment and before final responsive/release QA. Record: `docs/active/figma-alignment.md` section 19.

## Update 2026-10-09 — CSS cache-busting fixed (`fff3d37`) — staging verified

Staging HEAD `fff3d37` (also local and origin/master). A stale `cart.css` (`?ver=1791542854`, max-age 7 days) after the cart remove button deploy (`d77b7d1`) exposed that page, WooCommerce and block CSS shared `_S_VERSION`. Nine version arguments in `functions.php`, `inc/woocommerce.php` and `inc/enqueue-block-styles.php` now use `dreampoint_b2b_asset_ver()` (19 stylesheets, per-file `filemtime()`). Staging: `cart.css?ver=1791548401` equals the server mtime and loads fresh without a forced refresh; WooCommerce and homepage block versions match; cart/shop/home smoke passed. Remaining: `brands.css` own equivalent versioning, vendor CSS and some JS still on `_S_VERSION` (out of scope); the cart remove functional test was later manually confirmed as PASS by the user on staging (2026-10-09; not an automated test; `docs/active/figma-alignment.md` section 18). Record: `docs/active/block-css-cache-busting-followup.md`, `docs/decisions.md` (update after the ADR-009 cache-busting note).

## Update 2026-10-09 — Product card, button capabilities and Toastify refinement — staging PASS; simple-product AJAX add-to-cart E2E PASS

Deployed to staging and validated read-only (staging HEAD `34c80a1`): button capabilities `.button--s`, `.button--destructive`, `.button--icon-only` (`a79f643`); simple-product card action migrated to `button button--outline button--sm button--icon-only` with `icon-cart` (`f3438a8`); product-card action overlay fix (`5430547`, `.action-holder` positioned + `pointer-events`); Toastify refinement (`34c80a1`: Croatian success text, localized close label, mobile toast lifted 15px above the 73px fixed toolbar). Status: product-card visual and overlay QA PASS (1440/390/320); Toastify UI and responsive QA PASS (1440/390/360/320, synthetic notifications). **Simple-product AJAX add-to-cart E2E: PASS on staging (2026-10-09).** Product 7897 (SKU P-53466), authorized staging admin session, one real Playwright locator click (`isTrusted: true`, viewport 929px), in a context where the tab was visible and focused. Directly observed (instrumentation + native network recording, not inferred from cart state): exactly one `wc-ajax=add_to_cart` POST (200, `quantity=1&product_id=7897`, response `fragments` + `cart_hash`, no error); one `adding_to_cart` and one `added_to_cart` event; button `loading` then `added`; exactly one success toast (`Proizvod dodan u košaricu!`, close label `Zatvori obavijest`, ~3.4s lifetime); fragments refreshed, counter 5 to 6, mini-cart opened ~100ms after the toast; no navigation, no JS errors. Server-backed re-read confirmed one new cart line; only that line was then removed through the native WooCommerce remove link and the baseline (three original lines, total 5, counter 5) was verified restored. History: two earlier attempts were PARTIAL (instrumentation lost when the Playwright MCP connection dropped; the add and a targeted rollback were verified server-side only) and BLOCKED (hidden Chrome tab stalled locator clicks) before this run. Not covered by this E2E and still unvalidated: partner approval / bucket guard negative paths; variable-product and out-of-stock card branches (functionally); Quick Order AJAX integration; the mobile AJAX lifecycle (earlier mobile Toastify UI QA stands); deferred items: PDP max-quantity toast close label, dormant `.is-danger` styling. This does not validate all WooCommerce cart scenarios. Variable and out-of-stock card actions are intentionally unchanged (no Figma card state). Deferred: PDP max-quantity toast keeps the English close label; dormant `.is-danger` conflicts with the Toastify `!important` background; toast offset assumes the 73px toolbar (`$dp-mobile-toolbar-height`). Details: `docs/active/figma-alignment.md` section 17, ADR-022 update 2026-10-09 (Step 3b-3b), ADR-023.

## Update 2026-10-09 — Figma Step 3b-3a (four button variant corrections) — DEPLOYED / PARTIALLY VALIDATED

Commit `07faa01` (PHP class changes only, no Sass/CSS change) corrected four confirmed button variants from the instance-to-template audit: contact-info `button button--outline button--sm` (Secondary M), hero `button button--xl` (Primary XL; replaces the wrong Step 3b-2b Secondary M mapping), featured-section Quick Order CTA `button button--xl` (Primary XL), My Account address edit `Izmijeni` without `button--sm` (Primary L). Pushed and fast-forward deployed to staging (staging code HEAD `07faa01`); PHP syntax checked on staging. Runtime PASS: featured-section CTA (48px) and My Account `Izmijeni` (40px). Runtime NOT TESTABLE (not PASS): contact-info (ACF link empty on staging) and hero (block absent from the staging homepage). Open: runtime verification of those two when they naturally render, auth button icon corrections, icon-only M Secondary / S Destructive capability, Step 3b-2c, Step 3b-2d. Details: `docs/active/figma-alignment.md` section 16, ADR-022 update 2026-10-09 (Step 3b-3a).

## Update 2026-10-09 — Figma Step 3b-2b (shared button activation) — staging PASS

The existing `.button` ecosystem now renders the Figma Button set (`11148:40692`): `.button` = Primary L (40px), `.button--outline` = Secondary, `.button--sm` = M (32px), `.button--xl` = XL (48px), Montserrat 600, no uppercase, Figma hover/pressed states and a keyboard focus ring. Deployed and validated on staging, final staging HEAD `56ce5f17fe6de728e51cd35f905843979e5919a3` (`56ce5f1`); implementation commits `52564b3` (tokens), `939e281` (opt-in mixins), `7b0a5b2` (Sass activation), `023c038` (approved template class migrations), `56ce5f1` (header search regression fix). Details, architecture and outstanding items: `docs/active/figma-alignment.md` section 15 and `docs/decisions.md` ADR-022.

Not validated at runtime (components were not rendered on staging) and not presented as validated: Shop Brand CTA (no confirmed Figma size), hero block, about block, blog card hover and WooCommerce notice buttons. Still open: mobile "Filtriraj" is 32px high (touch-target adequacy to be checked), Step 3b-2c (WC Blocks / WBW / refund controls) and Step 3b-2d (QUICK ORDER override cleanup).

## Update 2026-10-09 — Figma Step 3a closeout (staging PASS)

Completed and validated on staging (HEAD `02e8ca0`): Step 3a header QUICK ORDER button on the existing `.button` — PASS; wishlist theme integration removal (ADR-021) — PASS; obsolete wishlist page ID 20 moved to Trash on staging (restorable); mobile pagination overflow fix — PASS; Quick Order footer responsive fix — PASS. Non-blocking observations, intentionally not reopened: (1) desktop header is not actually sticky (`#header` gets `.sticky`, but `#main-header { position: sticky }` sits inside a header-height parent; pre-existing); (2) on shop pagination at 390px the next arrow wraps alone onto a second row (cosmetic). Next: Step 3b (global `.button` alignment with Figma) — scope proposal awaiting approval; nothing implemented.

## Update 2026-10-09 — Mobile follow-ups and Quick Order footer fix deployed to staging

Staging HEAD `02e8ca0`: QUICK ORDER keeps 14px on phones (`c091b58`), shop pagination wraps on narrow phones (`4ce51ef`, no page-level overflow at 360/320px), docs (`79c79e5`) and the Quick Order footer fix (`02e8ca0`: `flex-wrap: wrap` on `.dp-qo-footer__actions` at <=767px in `plugins/dp-b2b-quick-order/assets/dist/quick-order.css`, plugin version 1.0.33 -> 1.0.34 for `?ver=` cache invalidation). Browser QA PASS on `/quick-order/` at 1440/768/390/360/320px: served `quick-order.css?ver=1.0.34`, footer buttons side by side at >=390px and stacked inside the footer at 360/320px, `scrollWidth` equals `clientWidth` everywhere, the product table keeps its own horizontal scroll, no console errors or failed requests; header QUICK ORDER and the four-control mobile toolbar unchanged. Staging only; production has not been touched. The Wishlist page ID 20 stays in Trash on staging (ADR-021).

## Update 2026-10-09 — Step 3a + wishlist removal deployed to staging; follow-up fixes (local commits, not deployed)

Commits `69ebf05` (header QUICK ORDER Figma button on the existing `.button`) and `50b98a7` (wishlist removal, ADR-021) are pushed and deployed to staging (staging HEAD `50b98a7`); browser QA PASS (QUICK ORDER 144.6x40 desktop, 4-control mobile toolbar fits at 390/360/320, no wishlist nodes/requests, only the two known 404s). Follow-ups committed locally and NOT deployed: `c091b58` (QUICK ORDER keeps 14px on phones) and `4ce51ef` (shop pagination `flex-wrap`, fixes the 360/320px page-level overflow). Staging only: the obsolete WordPress page ID 20 `/wishlist/` was moved to Trash (restorable; plugin options untouched). Open: Quick Order footer button overflows the page by ~23px at 320px (plugin CSS, see `docs/active/figma-alignment.md` section 14); desktop header is not actually sticky (pre-existing, `#main-header` is `position: sticky` inside a header-height parent).

## Update 2026-10-09 — Wishlist functionality removed from the theme (ADR-021, local commit, not deployed)

The client no longer requires the wishlist and the TI WooCommerce Wishlist plugin is deactivated. All theme-owned wishlist code was removed (header/mobile-toolbar counter, product-card heart, PDP styling, three `ti-wishlist*.php` overrides, `wishlist.scss`/`wishlist.css` and its enqueue, the admin variation-fix script, dependency references). The plugin itself and its data were not touched. **Open DB-managed item:** the plugin-created WordPress page `/wishlist/` (title "Wishlist") still exists on staging (HTTP 200) — decide in WP Admin whether to trash it; menus/widgets/ACF options and plugin settings were not inspected or changed. Not deployed; staging QA scope in ADR-021.

## Update 2026-10-09 — Figma alignment Batch 1 Steps 0-2 deployed to staging (ADR-020)

Step 0 (baseline + deterministic build), Step 1 (additive `$dp-*`/`--dp-*` tokens, `8eb2501`), the container adjustment (`26d9aa8`: 1216px at 1400-1499px, 1434px preserved at >=1500px, tiers <=1399px unchanged, wishlist 1236px kept) and Step 2 (shared title banner, breadcrumbs, dp watermark, `5d5c910`) are done, pushed and fast-forward deployed to staging (staging HEAD `5d5c910`); validated read-only as admin at 1440/1024/768/390 plus 1399/1400/1499/1500. No business logic, DB or config change. Remaining QA limitations, the My Account intro alignment follow-up, the two pre-existing console 404s (WC placeholder webp on `/brendovi/`, `img/ico/lock.svg` on `/my-account/`), the LiteSpeed note (`litespeed-purge` WP-CLI command not registered on staging; guest CSS version verified current; manual purge optional) and the recommended Step 3 first slice (3a: header QUICK ORDER button only) are recorded in `docs/active/figma-alignment.md` section 13. Next: Step 3a after approval.

## Update 2026-10-08 — Final Figma alignment: gap audit done, Batch 1 PLANNED (ADR-020)

Read-only Figma-vs-staging audit completed against the final design (page UI `11148:22601`, desktop 1440 px only; no mobile/tablet frames will be supplied). Functional structure largely matches; the visual layer (palette, header, footer, buttons, title banner) does not. Business logic stays untouched (presentation-only rule, ADR-020); responsive layouts are built pragmatically with each batch. Batch 1 = tokens, shared buttons/inputs, header, footer, title/breadcrumb banner — plan, token migration strategy, validation plan, open decisions (none blocking) and next-session handoff in `docs/active/figma-alignment.md`. **No code, staging, or Figma change was made.** Next step: Step 0 (baseline screenshots + clean-build determinism check), then Step 1 (additive tokens).

## Update 2026-10-08 — Finding A/B (anonymous shopping, bucket enforcement) deployed to staging `c3b3d70`; staging-verified

Anonymous cart mutation (Store API, wc-ajax, `?add-to-cart=`) is rejected (401 `dp_b2b_login_required`); anonymous checkout is rejected before an order is created (code path + local tests; real anonymous order attempt NOT TESTED — environment restriction). `dp_bucket_id` is enforced on add-to-cart (validation hook + `woocommerce_add_to_cart_quantity`) and revalidated at checkout (409 `dp_b2b_product_not_available`); ineligible lines stay in the cart; Quick Order rejects increases of ineligible lines; administrators and shop managers are exempt consistently. Positive checkout after correction: order #23408 (BACS, on-hold, no ERP export). The `update-item`/classic cart-update quantity-increase gap was closed in `d5bfdf0` and staging-verified (Finding B COMPLETE — STAGING VERIFIED; ADR-018 addendum "Finding B closeout"). Residual gaps: bundle child skipped silently (bundles unused on staging), `custom_offer` per-line query cost, shop manager and classic checkout not tested on staging. Full record: `docs/decisions.md` ADR-018, addendum "Finding A/B". Staging `DP_BYPASS_APPROVAL` remains `true`.

## Update 2026-10-08 — Pending-user enforcement Phase 1–3 deployed to staging; E2E PARTIAL (ADR-018 addendum)

Phases 1–3 (`3ab9467`, `514d70e`, `1c2e0c1`) are on `origin/master` and staging (`1c2e0c1`); real-HTTP staging validation confirmed catalog/Quick Order/bucket visibility, cart and checkout enforcement, approval revocation/restoration, and primary existing-order payment interception (Store API + classic order-pay); Apros sandbox export verified (order #23407 → ERP 4345). Overall: **E2E PARTIAL — SPECIFIC GAPS REMAIN**. NOT TESTED: classic checkout form (site uses Checkout Block), anonymous order creation. The Phase 3 R2 fallback stays PARTIAL. Two open pre-existing findings: **A** anonymous Store API/AJAX/`?add-to-cart=` cart mutation with guest checkout enabled, **B** `dp_bucket_id` not enforced when adding to cart by ID (checkout revalidation unestablished). Staging `DP_BYPASS_APPROVAL` is back to `true` (enforcement inactive for logged-in users); test orders #23405/#23406/#23407 are intentionally retained. Details, evidence and restoration record: `docs/decisions.md` ADR-018, addendum 2026-10-08.

## Update 2026-10-07 — B2B onboarding / Apros activation: BLOCKED on two API-semantics answers (ADR-018)

Client-confirmed architecture (`B2B odgovori na pitanja.docx` §3, recovered 2026-10-07): registration → Točka sna → partner created in Apros → `B2B KUPAC = DA` → partner appears via Apros API → synchronized and **activated** in the webshop; "Apros ostaje jedini izvor istine za odobrenje i aktivaciju B2B partnera"; client: "Slažemo se s dogovorenim modelom." It does not define `dp_bucket_id` assignment. Full reconciliation, current-vs-contract table and open questions: `docs/decisions.md` ADR-018.

- Registration (TODO #10) stays COMPLETE/PASS. Automatic ADR-002 polling is NOT implemented; a manual unscheduled sync exists (`wp importer partners`) that maps users/`apros_partner_code` but does not activate. Manual "Odobri" is legacy and contradicts the model; "Opozovi" unresolved; REST `approve-user` legacy/superseded.
- **Stop state: onboarding/Apros activation BLOCKED** pending two Apros/ZGData answers: (1) does `partnerList/get` return only `B2B KUPAC=DA` partners and does `DA→NE` remove a partner; (2) is `partnerList.email` the `B2B E-MAIL` attribute or the general partner email. Do not start the final activation sync before they are known. Internal decisions (activation representation, fate of Odobri/Opozovi, email trigger, B2B role, polling, email-mismatch matching, bucket assignment/ordering) are listed in ADR-018.
- DP-B06 remains on HOLD (Reserved Stock Pro); TODO #11 remains PARTIAL. No production environment exists.

## Update 2026-10-05 — Quick Order redesign slices 1–3 (staging-accepted)

Search (ADR-014 reuse, `qo_search`, `catalog_number`, binary stock), state row (popular searches, active chips, no-results, reset) and the new product list/footer visuals are implemented and accepted on staging at plugin v1.0.22 (commits `06d1ac7`, `516d44d`, `ab8a0c3`, `80aa419`, `e827fc9`; docs commit follows). Local-state/cart-sync architecture unchanged. Details: `docs/frozen/quick-order-local-state-architecture.md` Addendum 2026-10-05; plugin `readme.md`.

Remaining QO scope (not scheduled): Excel Import; Model/Boja/Dob WBW blocks (Boja/Pakovanje blocks do not render on staging view 3); brand visibility-safe list/show-more; row-level cart error UX (incl. `quantity_allowed` semantics); mobile QO design; broader filter slice; WBW drops `orderby` when a filter is applied (pre-existing); search placement under the sidebar on mobile.

## Update 2026-10-06 — Slice 4: native-first filter sidebar (staging-accepted, plugin v1.0.27)

Brand, Model (`pa_model`), Boja (`pa_boja`), Dob (`pa_dob`) and Dostupnost are native WBW blocks in view 3 (Pakovanje removed); the term vocabulary is B2B-visibility-safe inside Quick Order (`DP_Quick_Order_Term_Scope`) and WBW's AJAX queries keep the visibility engine; counts off; Dostupnost is the only stock filter; one integration shim for attribute chip removal. Commits `d994580`, `b4939da`, `0a48a01`, `53bfd9d`, `50e19a8`. Record: `docs/decisions.md` ADR-015; `docs/frozen/quick-order-local-state-architecture.md` Addendum 2026-10-06.

Remaining QO scope (not scheduled): Excel Import; row-level cart error UX (incl. `quantity_allowed` semantics); mobile QO design (the taller sidebar pushes search lower on narrow screens); WBW drops `orderby` when a filter is applied (pre-existing). Brand "show more" is NOT needed (native scroll + search accepted). Follow-up candidate: the theme's brand `get_terms` filter still returns all brands outside Quick Order to rule-based users without brand rules.

## Update 2026-10-06 — Slice 5: row-level cart/submit error UX (staging-accepted, plugin v1.0.29)

`/cart/sync` returns a normalized, non-sensitive failure contract (`failed` + `out_of_stock` / `quantity_unavailable` / `product_unavailable` / `not_addable`, one result per submitted item, no stock figure); variation ownership is verified before stock is read, hidden/nonexistent/mismatched items are indistinguishable, and an existing cart line respects current stock. The client shows the reason on the affected row (`aria-invalid` / `aria-describedby`) and a polite footer summary instead of `alert()`; an unusable response (network/HTTP/timeout) is an *ambiguous* state with its own message and no auto-retry. Commits `5c07c53`, `0aee6f1`. Record: `docs/decisions.md` ADR-016; `docs/frozen/quick-order-local-state-architecture.md` Addendum 2026-10-06 (Slice 5). Residual risk: a manual retry after a lost-but-applied response would add twice (no idempotency added).

Remaining QO scope (not scheduled): Excel Import; mobile QO design; WBW drops `orderby` when a filter is applied (pre-existing); theme brand `get_terms` leak outside Quick Order.

## Update 2026-10-06 — Excel/CSV import (Gate 1 + Gate 2 staging PASS, plugin v1.0.33)

Excel Import (upload → validation → result → add-to-cart → completion) is implemented and accepted on staging; decisions and acceptance: `docs/decisions.md` ADR-017, plugin `readme.md`. A shared `CartSubmit` chunking bug (`cartSyncMaxBatch` arriving as the string `"50"`) was fixed for both Excel Import and the normal QO submit (staging-proven up to 500 items). Checkpoint: local/origin/staging runtime HEAD `729d059`; production NOT deployed.

**Update 2026-10-07 — pre-production manual acceptance PASS.** Real Microsoft Excel smoke (incl. `000046` save/reopen and the saved file resolving as `Boca Urban Basic` through the staging UI), real focused desktop-Chrome keyboard smoke and real physical mobile-device smoke all passed with no defect and no code change (details: ADR-017 "Pre-production manual acceptance"). Staging runtime unchanged (`729d059`, plugin v1.0.33); production NOT touched.

**Safe continuation point: "Pre-production acceptance PASS / production deployment not currently applicable — production environment not yet provisioned."** Excel Import implementation and pre-production acceptance are complete. No further Excel Import work is currently required. Resume production-related work only after DreamPoint B2B production is provisioned/defined. Staging is the only provisioned DreamPoint B2B deployment target; the absence of production is a wider project-provisioning matter, not an Excel Import blocker. No known functional blocker. Do not restart Gate 1/2 or manual acceptance. Residual (non-blocking) observations are listed in ADR-017.

Remaining QO scope (not scheduled): mobile QO design; WBW drops `orderby` when a filter is applied (pre-existing); theme brand `get_terms` leak outside Quick Order.

## Milestone: COMPLETE (2026-07-21)

Quick Order has completed its planned development cycle and has entered
**maintenance mode**. All systems tracked in this document are implemented,
staging-deployed, and end-to-end validated as of commit `2d5c00a`. No open
implementation work remains for this feature. Future changes are
enhancements against a stable baseline, not completion of pending scope —
see `docs/active/current-phase.md` and the plugin's `readme.md` (theme-root
`wp-content/plugins/dp-b2b-quick-order/readme.md`) for the authoritative
current-state description.

**Release status:**

| Stage | Status |
|-------|--------|
| Local implementation | COMPLETE |
| Staging deployment | COMPLETE (latest: commit `2d5c00a`, 2026-07-21) |
| Staging validation | COMPLETE (incl. variable products/variations — see synthetic catalog generator run, 2026-07-10; catalog filters + WBW AJAX lifecycle + local-state + additive cart sync — see 2026-07-21 validation below) |
| Release candidate | READY |
| Production deployment | NOT APPLICABLE — no production environment provisioned for this project. `dreampoint.b2b.uncledev.cloud` (`dream9399`) is currently the only deployment target. Do not treat staging as production. |

---

**Architecture note (2026-07-10):** Quick Order has transitioned from the
real-time CartSync model to the local-state workspace model. See
`docs/frozen/quick-order-local-state-architecture.md` (canonical, current) and
the Supersession Note in `docs/frozen/quick-order-sync-architecture.md`
(historical). Verified locally via Playwright (vis_full test user): zero
network requests on quantity change, pagination-persistent local state,
single chunked submit, partial-failure rows retained, keyboard navigation,
visible focus indicators on new controls. **Staging-verified 2026-07-10**
(`dreampoint.b2b.uncledev.cloud`, commit `e7b98ab`): >50-row batch chunking
(51 items → 2 sequential requests, 50+1), mini-cart fragment refresh +
Toastify, cart icon counter increment, cache-busting (bumped
`DP_QUICK_ORDER_VERSION` 1.0.1→1.0.2 after finding 7-day browser cache with a
static version string), full golden-path E2E.

**Variable-product staging validation (2026-07-10, commit `3e9dc1c`):** staging
catalog has no real variable products, so the existing synthetic catalog
generator (`docs/historical/synthetic-b2b-catalog.md`) was run on staging
(taxonomies + 200 simple + 10 variable/183 variations) to validate variable-
product rendering, independent variation rows/qty controls, local subtotal,
bulk add-to-cart, and WC cart contents end-to-end — including an
organically-triggered partial-failure/stock-guard path (one row exceeded
available stock, correctly rejected and retained locally while the rest of
the batch synced). Catalog reset (`wp dp-b2b reset-catalog`) and cart cleanup
ran afterward; staging was back to its pre-test state (6 original products)
**as of 2026-07-10 — superseded, see policy note below.**
Release candidate is READY. **Production deployment is NOT APPLICABLE** — no
production environment is provisioned for this project; `dreampoint.b2b.uncledev.cloud`
remains the only deployment target.

**Staging dataset policy (2026-07-13, supersedes the reset described above):**
the generated synthetic catalog is now a **persistent development dataset**,
not a disposable per-test fixture. Staging currently carries the full
generated set (216 products / 183 variations / 42 categories / 34 brands,
plus the 6 original products) via `wp dp-b2b generate-catalog` (all three
phases). **Do not run `wp dp-b2b reset-catalog` on staging** except (a) after
ERP import becomes available, or (b) on explicit user instruction. Routine
Quick Order development and regression testing should use this dataset as-is;
only remove ad-hoc cart contents/test orders created during a session, never
the catalog itself. See `docs/historical/synthetic-b2b-catalog.md` for
generator mechanics.

**Superseded 2026-09-02 — synthetic PRODUCTS removed from staging (ERP import
is now live):** the `2026-07-13` "do not reset" condition (a) has been met.
The Apros ERP import (`wp-content/plugins/uncle-dev-importer`) populated staging
with real product data on 2026-08-19, so the synthetic products were removed
via `wp dp-b2b reset-catalog --batch=20260713_1138` — **210 parent products +
183 variations** deleted. The `[DEV]` product categories (24) and brands (30)
and all Brand Fixtures were **retained** (batch mode never touches terms).
Staging's product catalog is now the ERP dataset. Full record + the
`recalcMetaValues()` WBW orphan-row cleanup that followed:
`docs/decisions.md` ADR-006.

**Staging validation — 2026-07-21 (commit `2d5c00a`):** full Playwright
regression against `dreampoint.b2b.uncledev.cloud/quick-order/` after
deploying the WBW AJAX container-check placeholder fix and configuring
WBW's "Product List / Loader Selector" (admin, filter views 2 and 3) to
`.dp-qo-wbw-ajax-placeholder`. Confirmed: Brand/Sort By/In Stock/native
Clear all proceed via AJAX with zero full-page reloads;  `wpfAjaxSuccess`
fires on every WBW-driven change; Quick Order's own REST endpoint
(`/wp-json/dreampoint-b2b/v1/quick-order/products`) re-fetches and re-renders
on each change; the placeholder itself stays hidden throughout; the visible
table is exclusively Quick Order's own `table.dp-qo-table` (WBW never
renders a competing product list); local quantity state and the footer
subtotal survive a WBW-triggered re-render even when the edited row scrolls
off the newly-sorted/filtered result set; Add to Cart posts to
`/quick-order/cart/sync` and is reflected in the native WooCommerce mini-cart
count; `.is-added` and footer totals update/reset correctly around the
cart-sync round trip; browser Back/Forward triggers a full document reload
(this is WBW's own documented `popstate` → `location.reload()` behavior,
already recorded in `docs/frozen/quick-order-local-state-architecture.md`
§11's 2026-07-13 revision note — not a regression introduced by this fix);
zero console errors/warnings and zero failed network requests throughout.

| System | Status | Stable | Staging Ready | Notes |
|--------|--------|--------|---------------|-------|
| CartSync (debounce, token, abort) | SUPERSEDED | — | — | Real-time per-keystroke cart writes removed. See `docs/frozen/quick-order-local-state-architecture.md`. |
| Local Quick Order state + explicit submit | ACTIVE | Yes | Yes | See `docs/frozen/quick-order-local-state-architecture.md` §2–§4. Staging-validated 2026-07-21 (quantity state and footer subtotal survive a WBW-triggered re-render; deferred submit confirmed via Add to Cart round trip). |
| Pagination | ACTIVE | Yes | Yes | In-place re-render. Quantities re-hydrate from local state across pages instead of resetting — verified via Playwright (set page 1 → page 2 → back to page 1, quantity persisted). See local-state doc §2. |
| Variation handling | ACTIVE | Yes | No | Dropdown removed; each variation is an independent purchasable line, stacked inside its parent's single `.dp-qo-row` using the table's real Naziv/Stanje/Cijena/Kol. columns (no `colspan`, not a sibling top-level row). See local-state doc §6. |
| Visibility integration | ACTIVE | Yes | Yes | Gate fires on `add_to_cart` only. No retroactive revalidation — intentional. Unaffected by the local-state transition (fires at submit time now instead of per keystroke, same gate). |
| Sorting | ACTIVE | Yes | Yes | `qo_orderby` / `qo_order` params. Title and price sort, ASC/DESC toggle. Isolated from WOOF `orderby` detection. |
| Filter integration (WOOF/WBW) | ACTIVE | Yes | Yes | Category, brand, price range, and pa_* attribute filters via REST — all three taxonomy-based filters (category/brand/attributes) now share one parsing pipeline driven by WBW's own `data-taxonomy`/`data-get-attribute`/`data-query-logic` DOM metadata, no hardcoded param-name/delimiter assumptions (2026-07-13 hardening — see `docs/frozen/quick-order-local-state-architecture.md` §11 doctrine). Filter-change detection via WBW's native `wpfAjaxSuccess` event (was a `history.pushState` wrapper). Isolation guard strips wpf_query from QO WP_Query instances. WBW's own AJAX success handler previously fell back to a full `location.reload()` on this page (no `ul.products` container to satisfy its own container check) — fixed 2026-07-21 by pointing WBW's "Product List / Loader Selector" setting at a hidden `.dp-qo-wbw-ajax-placeholder` element (commit `2d5c00a`); see frozen doc's 2026-07-21 revision note. |
| Catalog filters (New/Best Seller/Already Ordered/In Stock) | ACTIVE | Yes | Yes | See `docs/active/quick-order-catalog-filters-spec.md` (design record, now historical) and plugin `readme.md` → "Catalog Filters" (current, authoritative). In Stock fully native WBW; other three are Quick Order-owned, `qo_*` URL params. Staging-validated 2026-07-21 (Brand/In Stock/native Clear exercised directly; New/Best Seller/Already Ordered confirmed present and wired via commit history — `35f5730`, `94220ae` — and the synthetic catalog's seeded tiers, see `docs/historical/synthetic-b2b-catalog.md`). |
| Cart totals footer | ACTIVE | Yes | Yes | Local-state subtotal/count footer (local-state doc §3), verified via Playwright to compute correctly (item count, row count, subtotal). No longer reads `data.totals` from a sync response. Staging-validated 2026-07-21: updates on qty change, resets to zero after a successful Add to Cart sync. |
| Variable stock neutral state | ACTIVE | Yes | Yes | Table was stale — this was delivered in the 2026-05-12 V1.1 plan. Neutral badge before variation selection is moot once all variations render as independently purchasable rows grouped under their parent (local-state doc §6), but the badge logic itself already exists. |
| Qty +/- buttons | ACTIVE | Yes | Yes | Table was stale — this was delivered in the 2026-05-12 V1.1 plan (`product-list.js` / `row-sync.js` already implement +/- controls). |
| Admin bypass | ACTIVE | Yes | Yes | Table was stale — this was delivered in the 2026-05-12 V1.1 plan (`manage_woocommerce` bypass in access guard). |
| Cross-page cart hydration | MOOT | — | — | No longer a limitation under the local-state model — state persists across in-page pagination by design (local-state doc §2). Still not persisted across navigation/reload (§5, intentional). |
| Offline / network failure handling | DEFERRED | No | No | Applies to the submit action only now (local-state doc §9) — no retry/queue persistence if a chunked submit fails partway. |
| Performance guards | ACTIVE | Yes | Yes | `CART_SYNC_MAX_BATCH = 50` unchanged server-side; frontend chunks submits over 50 rows sequentially (`cart-submit.js`, local-state doc §4.2) — not yet exercised with a real >50-row selection locally (dev catalog page size is 50, so chunking logic is implemented but chunk-boundary behavior is unverified beyond code review). No object hydration, still true. AbortController/stale-token guards no longer apply — nothing is in-flight to race. |
| Playwright E2E coverage | DEFERRED | No | No | Automated E2E test suite is intentionally out of scope for the current project. Validation is currently performed through repeatable Playwright-driven verification. Performed repeatedly through development, most recently the full 2026-07-21 staging regression above. Deferred item is specifically an automated, repeatable suite file in the repo, not test coverage itself. |

---

## Status Key

- **ACTIVE** — built, tested locally, works as designed
- **EXPERIMENTAL** — partially built; behavior may be incomplete or untested
- **DEFERRED** — intentionally not built yet; no regression, just missing functionality

---

## ACF Governance Coverage

Last updated: 2026-06-01 (Wave 4 complete — commit a014af6)

| Area | Status | Notes |
|------|--------|-------|
| Active editable field groups in `acf-json/` | COMPLETE | 7/7 active groups tracked and Git-protected |
| Frozen blocks without field groups (10 blocks) | INTENTIONAL | Static blocks with no editable fields. Do NOT add field groups to these — absence is intentional. |
| New field groups (future) | ONGOING RULE | Any new ACF field group created in GUI must be immediately exported to `acf-json/`. See `CLAUDE.md` ACF Governance section. |

Wave 4 governance is complete unless new field groups are introduced. Do not reopen past waves.

---

## Frontend Architecture — Leorigine Alignment

Last updated: 2026-06-02

| Status | DEFERRED — interrupted by ACF governance work (Wave 4) |
|--------|---|

Scope of pending alignment work:
- `functions.php` organization
- Sass/CSS architecture
- JS file organization
- Enqueue structure

**Hard constraint:** B2B-specific systems (visibility engine, Quick Order, checkout logic) must NOT be simplified, reorganized, or merged during Leorigine alignment. Structural changes apply to general frontend scaffolding only.

This is unfinished architecture work — not a locked system and not abandoned. Resume when the ACF/blocks governance cycle is complete.

---

## Staging TODOs (Open / Blocked)

Last updated: 2026-09-23 (Homepage/Segment Landing follow-ups added — see `docs/decisions.md` ADR-009)

These items remain open due to external dependencies. Do NOT mark as resolved unless the blocking dependency is confirmed resolved.

| # | Item | Priority | Status | Blocked by |
|---|------|----------|--------|------------|
| 12 | Populate real `brand_segment` values on ERP-sync `product_brand` terms (Homepage/Segment Landing) | REQUIRED | Open | Valid business/content input per brand — must not be guessed. Outdoor membership specifically has no institutional-knowledge source identified yet. |
| 13 | Segment Landing "Istaknuti proizvodi" manual product curation (Lifestyle/Toys/Outdoor) | MEDIUM | Open | Content/editorial task, not code |
| 14 | Company Features `features_items` ACF Options content on staging (currently `NULL`) | MEDIUM | Open | Content population — block renders nothing until filled |
| 15 | Segment Landing hero — Figma-specific images + "badge" field | LOW | Open | Badge blocked by DB-only `featured-section`/`featured-brand` ACF field group (no `acf-json/` entry); images pending pixel-level Figma visual pass (`get_screenshot` MCP was rate-limited throughout implementation) |
| 1 | CorvusPay + jquery-migrate testing | BLOCKER | Open | CorvusPay test environment access not yet available |
| 2 | LSCache JS Defer config in WP admin | HIGH | Open | Requires staging deploy |
| 3 | `fetchpriority` count validation | MEDIUM | Open | Requires staging deploy (above-fold grid layout) |
| 4 | Font preload list (`dreampoint_b2b_font_preloads()`) | MEDIUM | Open | Final site design / above-fold font selection |
| 5 | `update_post_meta_cache = false` — verify WPF filters | MEDIUM | Open | Requires staging deploy |
| 6 | GTM snippet in `footer.php` | LOW | Open | GTM account data / final site design |
| 7 | `woocommerce/archive-product-discounted.php` deletion | LOW | Done — file already absent from disk, no git-tracked deletion found (predates current history or was never tracked) | — |
| 8 | `footer-shop.php` — verify intent | LOW | Open | Design clarification |
| 9 | ~~Define `DP_ERP_WEBHOOK_SECRET` in `wp-config.php` on all environments~~ | ~~REQUIRED~~ | **SUPERSEDED 2026-10-07 (ADR-018)** — Apros confirmed no approval webhook (ADR-002) and the client confirmed the Apros-sourced approval model; the REST `approve-user` endpoint is legacy and dormant (secret intentionally undefined on staging). Do not define the secret for the current architecture. | — |
| 10 | Test B2B registration flow + both emails | REQUIRED | **DONE / PASS (2026-10-07, staging, theme commit `9b927c8`).** Registration form collects the seven required B2B fields (Tvrtka, OIB, Telefon, Adresa, Grad, Poštanski broj, Država); server-side validation (OIB exactly 11 digits, country from WooCommerce allowed countries); canonical `billing_*` meta stored and copied via the existing shipping path; one valid submission creates exactly one account (`approved` empty → pending, redirect to `/approval-pending/`; earlier first-click observation not reproducible). Admin email carries the company in the subject and all B2B data; customer email is Croatian with the native WooCommerce set-password link. Both real emails were received in Gmail and the real password link was clicked and opened the set-password flow. **Email design decision:** WooCommerce *Email Improvements* is intentionally disabled (`woocommerce_feature_email_improvements_enabled=no`); WooCommerce classic emails are used with the classic default colors and DreamPoint `#0D121C` as base/accent (`woocommerce_email_base_color`) — these are DB options on staging, not code. QA users 8, 9, 10 deleted after dependency check (no orders/content). **Boundary:** `DP_BYPASS_APPROVAL=true` remains a staging condition, so the real non-bypass approval guard was NOT runtime-tested here; it stays a go-live consideration. | — |
| 11 | Test `/akcija/` page — WPF filter, sorting, pagination | REQUIRED | Verified locally (2026-08-04) — 2 blocking bugs found and fixed in `inc/woocommerce.php` (page pinned to zero results; on-sale restriction discarded by native WC query). Per-page mismatch also fixed. `orderby=price` site-wide sorting bug (found during this validation, reproduced on `/shop/`, unrelated to this page) root-caused and fixed same day — see `docs/decisions.md` ADR-004. See `docs/dev-context.md` → "Akcija (Discounted Products) Page". | Staging re-verification still required — local only | **2026-10-07 staging QA: PARTIAL** — page loads (HTTP 200, no console errors, no mobile overflow at 390px), WBW filter sidebar renders and the empty state is shown, but staging currently has ZERO on-sale products (no `_sale_price` rows, `wc_get_product_ids_on_sale()` = 0), so on-sale population, filter, sort and pagination could not be verified without mutating catalog data (not done). Needs at least a few real or approved test on-sale products on staging.
| 16 | WooCommerce email localization (Croatian) | MEDIUM | **Staging: RENDERING VERIFIED, DELIVERY NOT TESTED (2026-10-08, ADR-019).** Site locale `hr`, hr packs installed, admin kept `en_US`. Payment/flat-rate titles and silkypress strings now Croatian (ADR-019 addendum, `ccc0591`). `Free shipping` → `Besplatna dostava` done (ADR-019 closeout). BACS description set to the approved Croatian ERP-workflow text (ADR-019 final closeout). Staging localization CLOSED; email delivery not retested; EN translations, production SMTP and Figma checkout alignment are separate tasks. | Production: Transactional Email Delivery task (provider, SPF/DKIM/DMARC) + hr packs on production. Staging SMTP sender rewrite to `armin.lusija@gmail.com` accepted. |

Full detail on items 1–8: `docs/dev-context.md` → "Staging TODOs" and "Cleanup Status".

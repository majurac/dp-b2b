# DreamPoint B2B — Master Roadmap and Task Tracker

Created 2026-10-31 (documentation-only consolidation session; no code, staging, database, API or Git-history change). **This is the single authoritative roadmap.** `docs/active/status.md` keeps a short summary and the chronological log; `docs/active/figma-alignment.md` keeps the Figma detail per section; `docs/decisions.md` keeps approved decisions. Nothing in this file is an approved decision: every "recommended" item needs explicit owner authorization.

Environments: localhost + staging (`dreampoint.b2b.uncledev.cloud`) only. **No production DreamPoint B2B exists** — never plan a production deploy.

## 0. Baseline (verified 2026-10-31, read-only)

- Repo `C:\xampp2\htdocs\dp-b2b`, branch `master`, HEAD `227d59b` (T7, local only, NOT pushed; one commit ahead of `origin/master`).
- 9 pre-existing uncommitted files, preserved untouched: `css/pages/myaccount.css`, `sass/pages/myaccount.scss`, `header.php`, `inc/b2b-registration.php`, `inc/checkout-logic.php`, `inc/my-account.php`, `woocommerce/myaccount/user-form.php`, `docs/active/figma-alignment.md`, `docs/active/status.md` (= T7.1 + T7.2 + 2026-10-29 documentation). This session added only documentation on top (this file, status.md summary, figma-alignment §54, index.md row).
- Staging: runs T7 runtime files only (15 files deployed outside git, staging git HEAD `320b46d` shows 7 modified + 8 untracked: reconcile before any `git pull`). T7.1/T7.2 NOT deployed. Staging was NOT re-queried this session (reuse of the 2026-10-29 verified facts; ADR/Rule 14).
- Figma: primary file `Te4kr3o8kXDgBLzHewKSYU` (page UI `11148:22601`, desktop 1440 only; URL node `11148:38315` is a checkout frame). FAQ file `FcZ1ja4QTalgsMB1vKGOWH` node `79:22758` (preview file) is a NEW reference not recorded before. Figma frames were NOT re-read this session; no measurement in this file is Figma-verified unless it cites an earlier section.

## 1. Status vocabulary

COMPLETED — VERIFIED · IMPLEMENTED — QA PENDING · LOCAL ONLY · STAGING DEPLOYED — QA PENDING · PARTIAL · NOT STARTED · BLOCKED — DEPENDENCY · BLOCKED — OWNER/CLIENT DECISION · UNKNOWN — NEEDS AUDIT. **Priority** = business/technical importance (P0–P3). **Order** = when to do it (R = ready now, W = wait for decision/dependency, L = late/after others). They are independent fields.

## 2. Owner observations U01–U16 → tracking IDs

| U | Observation | Tracked as | Status (summary) |
|---|---|---|---|
| U01 | Contact-info styling (Contact page + `/my-account/komercijalist/`) | **D4** (new; extends T2 contact card) | NOT STARTED, UNKNOWN — NEEDS AUDIT (architecture) |
| U02 | Result-count "products" untranslated | **B4** (new; part of T6 residue) | NOT STARTED, root cause UNKNOWN |
| U03 | WBW filter strings untranslated | **B3** (new; T6 explicitly excluded WBW) | NOT STARTED, source UNKNOWN |
| U04 | Product cards Figma alignment | **B1** (new; builds on T4 §47, §17/§19) | PARTIAL (typography/actions done) |
| U05 | WBW filter frontend skin | **B2 = T9** (plugin-dependent, approval needed) | NOT STARTED, BLOCKED — OWNER (approval) |
| U06 | FAQ page | **E2** (new) | UNKNOWN — NEEDS AUDIT (template exists) |
| U07 | My Account ERP notices | **D2** (new; T7 §53.1 deliberately omitted) | NOT STARTED, BLOCKED — OWNER (exact notice wording/scope not approved; conflict in D2, §4) |
| U08 | Orders "View" button | **D3** (T7 scope) | STAGING DEPLOYED — QA PENDING (style); label UNKNOWN |
| U09 | Quick Order page incomplete | **E1 = T10** | PARTIAL; visual work BLOCKED — OWNER (plugin approval) |
| U10 | Checkout incomplete | **WS-A** (A1–A10) + **WS-F** (F1–F4) | functional PARTIAL, visual INCOMPLETE |
| U11 | Product archive | **C1** (depends B1+B2) | UNKNOWN — NEEDS AUDIT for residual gaps |
| U12 | Product detail | **C2** (T4 §47 + T8 + deferred ADR-028 items) | PARTIAL |
| U13 | Contact page completion | **D5** (depends D4) | UNKNOWN — NEEDS AUDIT |
| U14 | Brands listing polish | **C3** | owner-reported functionally complete; visual polish NOT STARTED |
| U15 | Brand detail + hero | **C4** (depends B1+B2) | owner-reported functionally complete; hero polish NOT STARTED |
| U16 | 404 / empty search / "Adresa nije uspostavljena" | **E3 / E4 / E5** (separate templates) | UNKNOWN — NEEDS AUDIT (see E3–E5 in §4) |

Overlaps: U04+U05 unlock U11 and U15 (and U09 only if QO uses the same WBW markup — ADR-015 says QO uses the native WBW pipeline, so likely shared but the skin scope must be audited before assuming). U01 unlocks U13. U07+U08 are small add-ons to the T7 family. U10 is the only item with unresolved business decisions.

## 3. Dependency map

```
B1 cards ──┬─> C1 archive ──┐
           └─> C4 brand detail (+ hero polish, own task)
B2 WBW skin ─┬─> C1
             ├─> C4
             └─> E1 Quick Order sidebar (ONLY if same markup; audit first)
B3 WBW strings, B4 WC strings ──> independent of B1/B2 (config/translation catalog), can ship first
D4 contact-info component ──> D5 Contact page   (also /my-account/komercijalist/)
D1 T7/T7.1 commit+deploy+QA ──> D2 notices, D3 View button, A1 (T7.2 Store API)
A1 (needs Option A decision) ──> A10 functional checkout QA
A2 ERP failure visibility ──> A7 confirmation/e-mail QA, A10
A3 payment model + A4 shipping/delivery (APROS) ──> F1/F2/F3 checkout visual
A5 reservation (Reserved Stock Pro purchase) ──> cart/checkout reservation UI
WS-F ──> WS-G final acceptance;  all ──> G
```
Independent right now (no decision needed): B3, B4 (audit), D4 (audit), E2 (audit), E3/E4/E5 (audit), C3 (visual), C1/C2 residual audits, and — after one owner approval for B1 scope — B1.

## 4. Roadmap by workstream

Fields per task: **ID · title · sources · P · order · status · local · staging · dependencies/decisions · acceptance · evidence · effort (h; I = implementation, Q = QA) · next action.** `UNKNOWN` = not enough evidence to estimate.

### WS-A — Checkout and order integrity (U10)

| ID | Task | Sources | P | Order | Status / local / staging | Dependencies & decisions | Acceptance | Evidence | Effort | Next action |
|---|---|---|---|---|---|---|---|---|---|---|
| A1 | T7.2 Store API billing master protection (Option A) | §53.5, §53.7, status EOD #1 | P0 | R (owner review) then W (deploy approval) | **DEPLOYED TO STAGING 2026-11-02 — RUNTIME QA PASS (QA-1..8, QA-7 added 2026-11-02 follow-up)** (Option A OWNER APPROVED 2026-10-31); uncommitted/not pushed; staging: 6 runtime files outside git (§53.9) | Hooks `woocommerce_store_api_cart_update_customer_from_request`, `woocommerce_store_api_checkout_update_customer_from_request` (restore 7 master fields) and `woocommerce_store_api_checkout_update_order_from_request` (explicit error for empty street/city/country); approval did NOT cover commit/push/deploy. Gate 1 closed locally 2026-10-31 (owner-approved): `rest_request_before_callbacks` keeps the order's persisted billing master for `checkout/{order_id}` (historical snapshot preserved) and `woocommerce_before_customer_object_save` restores the master inside Store API / `update_order_review` requests (§53.8). Open: customers with incomplete master data (business follow-up); runtime QA | New Blocks orders carry master billing; shipping/phone editable; no billing overwrite | 25/25 + 31/31 + 40/40 isolated tests (96; WC sequence simulated; negative controls fail) | I done; Q UNKNOWN | Owner review of §53.9 + addendum; commit/push only with owner approval; open: customers with incomplete master data (not measured) |
| A2 | ERP export failure visibility + safe retry policy | status EOD, `uncle-dev-importer/order.php` audit | P0 | W | NOT STARTED | Open decision 4; retry only after idempotency/duplicate-order check with Apros | Failed export visible in admin + notification; no duplicate orders | exporter audit (read-only) | UNKNOWN | Owner/APROS: retry policy |
| A3 | Payment model | open decision 2; Figma CH-03 | P1 | W | **DECIDED — BACS ONLY (2026-11-02); STAGING ALIGNED** (COD disabled on staging) | Business decision CONFIRMED earlier (DP-A03 in `project-status-matrix.md`, `erp-discovery-findings.md`, ADR-019 closeout): BACS is the only gateway, no payment is collected in the shop, orders go to APROS and are invoiced later. Consolidated in ADR-037. Earlier "open decision" wording was outdated | Written decision recorded in ADR-037 | gateway config verified on staging checkout (only `bacs` offered) | DONE (staging) | none; `paymentTypeId` for BACS is tracked in A11 |
| A4 | Shipping address / delivery location / "add address" | ADR-010, open decision 3 | P1 | W | **PARTIAL** — APROS delivery-location rules CONFIRMED and unchanged (ADR-010); shipping METHODS (GLS 4,99 / Osobna dostava – područje Zagreba 4,99 / Kupac sam preuzima robu 0,00) configured on staging 2026-11-02 as **INTERIM — OWNER-AUTHORIZED — CLIENT APPROVAL PENDING** (ADR-037) | Verified 2026-11-02 (read-only UI, ADR-037 addendum): 0-location (admin) and 2+ locations (existing partner 2870, 8 locations: nothing preselected, selection kept across Dostava/Preuzimanje switching and reload). **NOT VERIFIED:** exactly-1-location scenario (no such account) and reset after a completed order (needs an order). Shipping-address cards / "add new address" still pending APROS clarification; shipping VAT, free-shipping rules and APROS shipping identifiers unresolved. Shipping methods are NOT client-approved | decision recorded | order #23358 (delivery location 2+) | UNKNOWN | Client approval of shipping methods; APROS answer (deferred, see A11) |
| A5 | One-hour cart-level stock reservation (DP-B06) | ADR-007, current-phase HOLD | P1 | W | BLOCKED — DEPENDENCY (Reserved Stock Pro not purchased/installed) | Client purchase; cart UI `CART-02..05` follows plugin | reservation enforced at cart level | native `hold_stock_minutes=60` covers unpaid orders only | UNKNOWN | Client delivers plugin |
| A6 | MOQ / MPC / multipack | ADR-035, §5.4 | P2 | W | MPC done (ADR-011); MOQ enforcement excluded (DP-B01); conditional upsell BLOCKED | APROS data source + rules; native Upsells approach chosen (minimal) | per ADR-035 addendum | ADR-035 | UNKNOWN | Data-source answer |
| A7 | Order confirmation, order number, order e-mails QA | status EOD, ADR-019 | P1 | W | NOT STARTED | Order number format + VAT/contact person source (open decision 5); depends on A2/A3 | confirmation + e-mails verified on staging | none yet | UNKNOWN | After A2/A3 |
| A8 | Stale payment rules in `inc/checkout-logic.php` (`gls`, `pickup_location`, stripe/paypal) | status EOD | P2 | R (small, but touches checkout → owner approval) | NOT STARTED | Checkout is high blast radius | dead rules removed, checkout unchanged | audit note only | UNKNOWN | Owner approval + audit |
| A9 | Silkypress "Koristite istu adresu za dostavu" checkbox behaviour | open decision 6 | P2 | W | OPEN | Owner meaning; also pre-existing "(optional)" overlap at 390 (§42 T1 note) | decision + fix | §42 | UNKNOWN | Owner decision |
| A10 | Controlled functional staging checkout QA | status EOD #7 | P0 | L | PARTIAL (2026-11-02: billing-protection paths + one APROS sandbox order #23553 PASS, §53.9; full cart/order-confirmation/e-mail/payment-model QA still open) | Test partner, backups, APROS staging sandbox configuration confirmed (staging uses the APROS sandbox; no production APROS exists), no real partner; needs A1–A4 | cart→checkout→confirmation→ERP verified incl. billing protection | prior evidence: #23358, #23407, #23408 | Q UNKNOWN | After A1–A4 |
| A11 | ERP Shipping/Payment ID Mapping — Audit & Completion (admin page `wp-admin/admin.php?page=wc-erp-id-mapping` is not populated as expected) | owner backlog 2026-11-02; related A3, A4, A2 | P1 (proposed) | W (first step is a read-only audit, ready when owner starts it) | **IN PROGRESS — AUDIT COMPLETE, MAPPINGS UNRESOLVED (2026-11-02)** / local n/a / staging mappings unchanged (`wc_erp_id_all` still absent) | Scope update 2026-11-02 (ADR-037): payment mapping needed only for `bacs` (APROS `paymentTypeId` UNKNOWN); shipping mapping is keyed by WooCommerce `method_id` and the exporter does not pass `instance_id`, so GLS (`flat_rate:2`) and Osobna dostava (`flat_rate:3`) cannot receive distinct APROS shipping IDs; `pickup_location:0` is a separate key; fallback `1` is NOT a confirmed identifier (documented limitation, no plugin/exporter change authorized); **APROS inquiry DEFERRED BY OWNER — NOT A CURRENT ACTION** (BACS-only is confirmed, final shipping model not yet client-approved; mapping questions revisited after those decisions; the BACS `paymentTypeId` remains unknown); shipping article/product ID and shipping VAT unresolved. Historical leads only, NOT verified current facts or approved ERP IDs: option `wc_erp_id_all`; past mappings `free_shipping → 1`, `corvuspay → 2`; possible `pickup_location` ambiguity with shared `instance_id=0`. Staging uses the APROS sandbox integration. Do not invent ERP identifiers; do not assume historical values are correct; no change to the order export flow without explicit approval; no test orders created/exported during the audit. Overlaps A3/A4 (payment/shipping model) — check their decisions first | Cause of the empty/incomplete mapping identified; every required shipping/payment mapping verified against the APROS sandbox and confirmed project configuration; pickup-location ambiguity resolved or documented; mappings populated only after findings and exact IDs are owner-approved | none yet | UNKNOWN | Read-only audit: (1) inspect the mapping admin UI, (2) identify plugin and real storage mechanism, (3) list missing shipping/payment mappings and why, (4) verify identifiers against APROS sandbox/config, (5) propose a safe completion plan for approval |

### WS-B — Shared catalog components

| ID | Task | Sources | P | Order | Status | Dependencies | Acceptance | Effort | Next action |
|---|---|---|---|---|---|---|---|---|---|
| B1 | Product card Figma alignment (all contexts) | U04; T4 §47; §17, §19, §20 | P2 | R after scope audit | PARTIAL (title/price typography T4; actions §17/§19). Unverified in homepage/cart cross-sell/category-card contexts | Audit all card render sites (`woocommerce/content-product.php`, slider, related, brand) before coding; reuse mixins | One shared card matches Figma "Product card" `11148:32832` in every context | I/Q UNKNOWN until audit | Read-only audit of contexts |
| B2 | WBW filter skin (= T9) | U05; §42 plugin-dependent | P2 | W (approval) | NOT STARTED, BLOCKED — OWNER (theme-level override of plugin needs explicit approval; do not modify plugin) | Confirm whether `/shop/`, brand, QO use the same markup | Filter card/headings/chips per Figma; no behaviour change | I 5 (existing T9 estimate), Q UNKNOWN | Owner approval + shared-markup audit |
| B3 | WBW frontend string translations | U03 | P2 | R (audit) | NOT STARTED; known: "In Stock", "Out of Stock", "Search categories", "Clear", "Sort By"; "Dostupnost" already shown | Distinguish plugin option labels vs `.po/.mo` vs theme override; T6 excluded WBW | all frontend WBW strings Croatian | UNKNOWN | Read-only string-source audit |
| B4 | WooCommerce catalog string translations (result count etc.) | U02 | P2 | R (audit) | NOT STARTED; override exists `woocommerce/loop/result-count.php`; cause may be admin `en_US` locale vs real untranslated msgid (T6 §49 hypothesis) | Identify msgid/domain; no hardcoding | "N proizvoda" correct plural forms (hr) | UNKNOWN | Audit template + catalog |
| B5 | Shared responsive behaviour for B1/B2 | ADR-020 | P3 | L | NOT STARTED | Follows B1/B2; Figma desktop only | no overflow 390/768/1024 | UNKNOWN | With B1/B2 |

### WS-C — Catalog pages

| ID | Task | Sources | P | Order | Status | Dependencies | Notes | Effort |
|---|---|---|---|---|---|---|---|---|
| C1 | Product archive residual gaps | U11; CAT-02 | P2 | L (after B1/B2) | UNKNOWN — NEEDS AUDIT | B1, B2, B3, B4 | Only archive-specific gaps; do not duplicate B1/B2 | UNKNOWN |
| C2 | PDP Figma alignment | U12; T4 §47; ADR-028; T8 | P2 | R after audit | PARTIAL (typography done; gallery arrows, share row, USP strip, tabs Specifikacije/Paket/Dokumenti not done) | Preserve price/stock (ADR-027)/variation/qty/ERP; tabs 2–4 BLOCKED — data (ADR-028, documents feasibility); T8 = USP strip + search placeholder copy | Split visual vs functional; reviews NOT in Figma (closed B) | T8 est. 2 h; rest UNKNOWN |
| C3 | Brands listing `/brendovi/` polish | U14; §22 | P3 | R | owner-reported functionally complete; NOT independently verified | None | Do not rewrite functionality; brand logo content gaps are client work | UNKNOWN |
| C4 | Brand detail `/marka/…` + brand hero | U15; `inc/brand-hero.php` via `header-shop-archive.php` | P3 | L (hero own task; rest via B1/B2) | owner-reported functionally complete; hero polish NOT STARTED | B1, B2; open: Shop Brand CTA size unresolved (§15) | Track hero separately from card/filter work | UNKNOWN |

### WS-D — My Account and Contact

| ID | Task | Sources | P | Order | Status | Dependencies / decisions | Acceptance | Effort | Next action |
|---|---|---|---|---|---|---|---|---|---|
| D1 | T7 / T7.1 / T7.2 closeout (T7.2 Store API now implemented locally, see A1): owner review, real-save functional QA, commit, deploy, staging git-drift reconcile | §53–53.5, status EOD | P0 | R (review) then W (approval) | T7: STAGING DEPLOYED — QA PENDING (commit `227d59b`, backup `/home/dreampoint.b2b/backup-recovery/t7-20261029-142952/`); T7.1 LOCAL ONLY; T7.2 LOCAL ONLY partial | Explicit approval to commit/push/deploy; test customer for saves (profile, password, shipping, phone invalid/empty), multi-page orders, Povrat/pay/cancel, empty states, `vis_full` | all listed saves verified; company/OIB intact | Q UNKNOWN | Owner review |
| D2 | ERP informational notices on `/my-account/` and `/my-account/edit-address/` | U07 | P2 | W | NOT STARTED — string not in any PHP file; T7 §53.1 intentionally omitted it (ownership unverified) | **Conflict to resolve:** address page also has editable shipping addresses and the profile phone is editable, so "data are taken from ERP" is true only for company/billing. Owner must approve the exact wording for each page and its scope/placement (billing card only?), and confirm it supersedes the §53.1 omission; until then D2 stays BLOCKED. Do not change read-only rules | Notice shown where owner specifies, copy as supplied | I ≈ 1 once decided (small template change); Q UNKNOWN | Owner confirms scope |
| D3 | Orders table "View" button | U08; §53 (owner decision D3 = keep Povrat/order-again) | P3 | R (verify) | Style IMPLEMENTED in `227d59b` (`button--sm button--outline` + arrow after) and STAGING DEPLOYED — QA PENDING (computed-style only, no screenshots). "View" in the report is the localhost `en_US` label; staging label per D3 is "Pogledaj" — UNKNOWN for hr locale | Check if any Figma delta remains (MPR-05 `11148:37998`) | Button matches Figma, label Croatian + accessible name | Q ≈ 1 | Visual compare on staging, then decide delta |
| D4 | Shared contact-information component (Contact page + `/my-account/komercijalist/`) | U01; T2 §42 | P2 | R (audit) | NOT STARTED. Facts: `css/blocks/contact-info.css` is enqueued only for block `acf/contact-info-section` (`inc/enqueue-block-styles.php:37`); `contact.php` renders `blocks/templates/contact-info` via `get_template_part`, so the stylesheet never loads there (T2: "/kontakt/ unchanged"); komercijalist page uses `display_commercialist_contact_info()` with its own rules in `myaccount.scss` | Decide: reuse one stylesheet/markup vs two; sidebar narrows My Account width | Both pages styled consistently, no duplicated implementation | UNKNOWN | Architecture audit |
| D5 | Contact page remaining requirements | U13; §35–36, `docs/active/contact-form-routing-investigation.md` | P3 | L | UNKNOWN — NEEDS AUDIT; contact form Figma alignment + secure routing STAGING VERIFIED without e-mail delivery | D4 | Only page-specific residue | UNKNOWN | After D4 |
| D6 | My Account — View Order typography alignment (`/my-account/view-order/{id}/`; ref. order 147 is a visual reference only, NOT authorization to modify that order) | owner backlog 2026-11-02; ADR-020 | P3 (proposed) | R | **PLANNED — NOT STARTED** | **Visual/UI-only.** Must not touch order flow, statuses, data, checkout, ERP, payment/shipping logic, business rules or component functionality. Reuse existing theme typography tokens/mixins/components | Font family, sizes, weights, line heights, hierarchy and typography-related spacing consistent with the rest of DreamPoint B2B (desktop + responsive); content and order behaviour preserved; no business-logic change; responsive stays usable | UNKNOWN | Read-only typography review of the View Order template/styles vs established tokens |

### WS-E — Other pages and states

| ID | Task | Sources | P | Order | Status | Notes | Effort |
|---|---|---|---|---|---|---|---|
| E1 | Quick Order page (= T10) | U09; §42; ADR-015/016/017 | P2 | W (plugin approval) | Functional baseline COMPLETE (v1.0.36); header/intro/typography/chips/sidebar visual NOT STARTED, BLOCKED — OWNER (own plugin) | Preserve REST/cart sync, debounce, rollback; audit functional gaps separately from visual; sidebar may share B2 | T10 est. 4 h; rest UNKNOWN |
| E2 | FAQ page | U06; `faq.php`, `sass/pages/faq.scss`, `js/faq.js`, ADR-005 (malformed faq-category terms) | P3 | R (audit) | UNKNOWN — NEEDS AUDIT. Template + accordion + pagination + tabs exist; staging page `/cesto-postavljena-pitanja/` loaded OK in §29. Separate content / behaviour / visual vs new Figma `79:22758` | Content may be client work | UNKNOWN |
| E3 | 404 page | U16 | P3 | R (audit) | UNKNOWN — NEEDS AUDIT. `404.php` exists (icon, H1, text, Početna); it calls `status_header(404)` AFTER `get_header()` — verify real HTTP status and no "headers already sent" | HTTP status, navigation, accessibility, visual | UNKNOWN |
| E6 | Internal legal pages, footer links & cookie consent integration (source `https://dream-point.hr/`; target staging) | owner backlog 2026-11-02; extends X15 (legal/content footer dependencies) | P2 (proposed) | R (audit of source site/plugin first) | **PLANNED — NOT STARTED.** Footer legal links currently point to external pages | **Agreed (final, no further clarification):** create editable internal WP pages "Politika privatnosti" and "Opći uvjeti poslovanja"; copy source text VERBATIM, not the source layout; use the DreamPoint theme; no independent B2B rewrite, no extra legal analysis, not blocked on legal wording; the client reviews/adapts the texts before go-live (pre-live client responsibility). Cookie consent: use the SAME plugin as `dream-point.hr` (no custom system); keep its interactive preferences (not a static page); "Postavke kolačića" footer entry opens the plugin-managed preferences UI/destination; connect privacy/cookie references to consent components. Replace relevant external footer links with internal ones, keep footer styling/behaviour; point registration/checkout acceptance components at the internal pages without changing their validation or unrelated registration/checkout/ERP logic. Plugin install is a plugin change → needs owner approval at implementation time. **Acceptance:** both pages exist internally with verbatim source text and are client-editable; footer links use internal destinations; cookie settings use the source-equivalent plugin and open its preferences; acceptance components reference correct destinations; registration/checkout flows unchanged. **First action:** read-only identification of the source site's cookie plugin/config and of current footer + acceptance-component link locations, then request implementation approval | UNKNOWN |
| E4 | Search with no results | U16 | P3 | R (audit) | UNKNOWN — NEEDS AUDIT. `search.php` → `template-parts/content-none.php`; separate template from E3. Check live-search dropdown empty state too (ADR-014) | no-results HTTP status is normally 200 | UNKNOWN |
| E5 | "Adresa nije uspostavljena" empty state | U16 | P3 | R (audit) | UNKNOWN — NEEDS AUDIT. Lives in `woocommerce/myaccount/my-address.php:73` (T7, statically reviewed only, NOT runtime verified: needs a customer without addresses) | Figma MPR-02 `11148:37742` | UNKNOWN |

### WS-F — Checkout Figma alignment (visual INCOMPLETE)

| ID | Task | Frame | Status | Dependency |
|---|---|---|---|---|
| F1 | CH-01 checkout card structure | `11148:37320` | NOT STARTED (only T1 heading/label fix done, `1a98eab`); VAT number and contact person have no data source | A3, A4, A9; structural decision vs ADR-020 presentation-only |
| F2 | CH-02 address cards, default badge, "Dodaj novu adresu" | `11148:38222` | NOT STARTED, BLOCKED — OWNER/APROS (A4). Future separate task (owner direction 2026-11-02): shipping-method selection right below delivery-address selection, styled like the selectable address cards (Tarik suggestion); Figma has no shipping/pickup frame; native Blocks must be preserved; needs feasibility review and approval, not started | A4 |
| F3 | CH-03 payment info card + confirmation page (order number e.g. `DP-2025-000123`) | `11148:38357` | NOT STARTED, BLOCKED — OWNER (A7; A3 payment model is decided: BACS only) | A7 |
| F4 | Responsive checkout + visual regression QA | — | NOT STARTED | F1–F3 |

Effort for WS-F: UNKNOWN (no reliable basis until A3/A4 are decided).

### WS-G — Final acceptance (all NOT STARTED, run last)

G1 end-to-end functional QA (A10 + D1 + QO) · G2 ERP/APROS integration QA · G3 responsive visual QA 1440/1024/768/390/360 · G4 accessibility (open: search placeholder 2.75:1, search border 1.92:1, touch targets <44 px, skip-link target not focusable, no screen-reader test; §29–30) · G5 translation consistency (T6 residue, B3, B4, WBW, "Best seller", colour names) · G6 regression sweep (frozen systems: visibility, checkout, QO) · G7 unused-components cleanup (`.categories-btn`, about block).

## 5. Carried-over open items (preserved, not superseded by U01–U16)

| ID | Item | Status | Source |
|---|---|---|---|
| X1 | T6 localization residue (WBW, "Best seller", colour names) | merged into B3/B4/G5 | §49–51 |
| X2 | N2 `/akcija/` empty (0 sale prices) — ZGData must say where promo price is exposed | BLOCKED — DEPENDENCY (ZGData); drafts in ADR-036 NOT SENT | §52 |
| X3 | N3 existing-customer migration (plan only, conditional 6 h) | BLOCKED — DEPENDENCY (client export + ZGData answers) | ADR-036 |
| X4 | Onboarding / Apros activation sync (ADR-018) + `DP_BYPASS_APPROVAL=true` on staging + bucket assignment | BLOCKED — DEPENDENCY (2 `partnerList` API questions) | ADR-018 |
| X5 | Pending-user enforcement Finding A/B | A cart-mutation verified; B complete; test orders #23405–23408 retained; never bulk-clean admin cart | memory |
| X6 | Auth icons (login/register `button--icon-after`, reset-password Left icon), Left-icon cases | NOT STARTED | §15–16 |
| X7 | Step 3b-2c (WC Blocks, WBW, refund controls onto Figma mixins) and 3b-2d (QUICK ORDER override cleanup) | NOT STARTED (3b-2c overlaps F1 and B2) | §15 |
| X8 | Homepage: USP icon order (`features_items` content), about block (no Figma section), real commercialist photo | content / NOT STARTED | §42 |
| X9 | Testimonials block retire decision; JSON-only ACF groups | open owner decision | status 2026-10-19 |
| X10 | T3b/AUTH-04/ADR-032 real password-reset E2E and pending-account tests (staging bypass on) | NOT E2E VERIFIED | §45–46, ADR-032 |
| X11 | Excel import production readiness | not applicable until a production environment exists | ADR-017 |
| X12 | Production transactional e-mail / SMTP (ADR-019) | not applicable until production exists | ADR-019 |
| X13 | Documents/ERP product tabs (Specifikacije PARTIAL, Paket/Dokumenti NOT AVAILABLE) | BLOCKED — vendor | ADR-028, documents feasibility |
| X14 | Header "CRO" language selector | BLOCKED — multilingual decision | §11 |
| X15 | Newsletter band, English legal labels, footer on QO page, legal/content footer dependencies | owner/content | §11, §25 |
| X16 | Search design (final Figma pending), search contrast | open | CLAUDE.md, ADR-014 |
| X17 | Fancybox binding debt | accepted low-severity debt | `fancybox-binding-debt.md` |
| X18 | Frozen-doc conflict: `docs/frozen/checkout-logic.md` priority-999 and "My Account only channel" statements superseded by §53.5 (file not edited) | recorded | §53.5 |
| X20 | UNVERIFIED observation (2026-10-31 audit): staging tax rate HR 25 % shows `tax_rate_shipping=0` with `tax_based_on=shipping`, which may not match the `CLAUDE.md` statement that tax applies to shipping. Not investigated; not a confirmed defect | needs separate review | three-gate audit |
| X19 | Residual deployment risk: HTTP 500 on `/cart/` right after N1/N5 deploy (undefined helper; OPcache window suspected, unproven) | recorded | §52 |

Closed and NOT reopened: T1–T3b, T4 (partially verified), T5, T6, N1/N4/N5, Segments Section, colour alignment phases 1–4, Quick Order v1.0.36, slider navigation (ADR-031), PDP Description tab (ADR-028).

## 6. Prioritized view (priority ≠ order)

- **P0:** A1, A2, A10, D1 (data integrity / order flow). All wait on owner decisions or approval except the D1 review.
- **P1:** A3, A4, A5, A7, A11 (proposed).
- **P2:** B1–B4, C1, C2, D2, D4, E1, A6, A8, A9, E6 (proposed).
- **P3:** C3, C4, D3, D5, E2–E5, B5, D6 (proposed).
- WS-F (F1–F4) and WS-G (G1–G7) rows carry dependencies/order only; no priority has been assigned to them.

## 7. Next Ready Tasks (no unresolved external decision needed)

1. **D1 review** of T7/T7.1/T7.2 worktree (owner) — decides commit/deploy approvals and A1.
2. **B3/B4** read-only translation-source audit (U02/U03) — cheap, unblocks quick wins.
3. **D4** architecture audit (contact-info reuse) → then D5.
4. **E3/E4/E5** read-only audit of 404 / no-results / empty-address (HTTP status, a11y, template).
5. **E2** FAQ audit vs Figma `79:22758` (read Figma first).
6. **B1** context audit of all product-card render sites (then owner approves scope).
7. **C2/C3/C4/C1** residual-gap audits after B1 scope is known.
8. **D3** staging visual compare of the View button vs MPR-05.

## 8. Needs owner / client / APROS decision before work

A1 deploy/commit approval (Option A itself is approved), A2 retry policy, A4 delivery/address (APROS) and client approval of the interim shipping methods, A5 Reserved Stock Pro purchase, A6 MOQ data source, A7/F3 order-number format, A9 checkbox meaning, B2/E1 plugin-skin approval, D2 notice scope vs editable shipping/phone, commit/push/deploy approvals (T7.1/T7.2, T7 push), X2–X4 ZGData/APROS/client answers.

## 9. Maintenance rules for this file

Update the status column and evidence when a task changes state; never downgrade VERIFIED work without new evidence; add new tasks under the existing workstream with a new ID, never a duplicate; reference sections of `figma-alignment.md` instead of copying detail; keep this file the only roadmap.

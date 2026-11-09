# E6 — Legal Pages & Cookie Compliance: Local Handoff and Staging Checklist

Status: **IMPLEMENTED AND QA-VERIFIED LOCALLY (2026-11-09); NOTHING DEPLOYED, NOTHING PUBLISHED.** Only localhost + staging exist (no production). Companion: `docs/operational/e6-legal-pages-staging-procedure.md` (draft legal pages + seed script).

## 1. What is done, pending, and blocked

| Item | State |
|---|---|
| Draft pages "Politika privatnosti" / "Opći uvjeti poslovanja" (Rich Text template, verbatim `dream-point.hr` text) | DONE locally (drafts, not published) — commit `a152f08` |
| Footer helper: internal legal pages used only once published, otherwise `dream-point.hr` URLs | DONE — `a152f08` |
| Footer "Postavke kolačića" opens the plugin preferences panel when the plugin is active (fallback: external URL) | DONE — `068fc78` |
| Croatian banner/panel strings for GDPR Cookie Compliance (`.po/.mo`, theme loader) | DONE — `068fc78` |
| Mobile banner CSS (lift above fixed toolbar, overlay above toolbar, 44px buttons) | DONE — `068fc78` |
| GDPR Cookie Compliance 5.3.1 installed + configured | LOCAL DB ONLY (plugins dir is gitignored, settings are DB data) |
| WooCommerce Order Attribution disabled | LOCAL DB ONLY (E6-B2); `woocommerce_allow_tracking` untouched |
| Client review/approval of both legal texts; publish; set privacy page | **PENDING — client/owner** |
| Wording of the banner/panel claims ("only necessary cookies; no analytics or marketing") | **PENDING — client confirmation** |
| Plugin install, settings, Order Attribution change, legal pages on staging | **PENDING — owner approval + staging actions (section 4)** |

Commits (E6 only): `a152f08` feat: prepare legal pages with rich text template (code + `docs/operational/e6-legal-pages*`); `068fc78` feat: integrate cookie preferences and mobile banner styling. The Order Attribution change and plugin settings have no commit (DB only).

## 2. GDPR Cookie Compliance — exact configuration (plugin 5.3.1, Moove Agency, free)

Stored in option `moove_gdpr_plugin_settings`. Set through the plugin screens, or write the same keys with WP-CLI as the site user. Nothing else in the plugin is changed from defaults.

| Area | Key | Value |
|---|---|---|
| Look | `moove_gdpr_colour_scheme` | `2` (light) |
| | `moove_gdpr_brand_colour` | `#0d121c` |
| | `moove_gdpr_button_style` | `rounded` |
| | `moove_gdpr_infobar_position` | `bottom` |
| | `moove_gdpr_floating_button_enable` | `0` |
| Banner text | `moove_gdpr_info_bar_content` | `<p>Ova stranica koristi samo neophodne kolačiće potrebne za prijavu, košaricu i naplatu.</p>` (no `{setting}` placeholder: the Postavke button already does it) |
| Banner buttons | `moove_gdpr_accept_button_enable` / `_reject_button_enable` / `_settings_button_enable` | `1` / `1` / `1` |
| | `moove_gdpr_infobar_accept_button_label` / `_reject_button_label` / `_settings_button_label` | `Prihvaćam` / `Samo neophodni` / `Postavke` |
| Panel | `moove_gdpr_privacy_overview_tab_title` | `Pregled privatnosti` |
| | `moove_gdpr_privacy_overview_tab_content` | `<p>Ova stranica koristi samo neophodne kolačiće koji omogućuju prijavu, košaricu i naplatu. Ne koristimo analitičke ni marketinške kolačiće.</p>` |
| | `moove_gdpr_strictly_necessary_cookies_tab_title` | `Neophodni kolačići` |
| | `moove_gdpr_strict_necessary_cookies_tab_content` | `<p>Neophodni kolačići potrebni su za rad stranice (prijava, košarica, naplata) i ne mogu se isključiti.</p>` |
| | `moove_gdpr_modal_enabled_checkbox_label` / `_disabled_checkbox_label` | `Uvijek uključeno` / `Isključeno` |
| | `moove_gdpr_modal_allow_button_label` / `_reject_button_label` / `_save_button_label` | `Prihvaćam` / `Samo neophodni` / `Spremi postavke` |
| | `moove_gdpr_modal_powered_by_label` | `Pokreće` (plugin default is the literal `Powered by`, not translatable by `.mo`) |
| Categories | `moove_gdpr_third_party_cookies_enable`, `moove_gdpr_advanced_cookies_enable` | `0`, `0` (only **Strictly Necessary** exists; no optional category is justified by the verified inventory) |
| Logo | `moove_gdpr_company_logo` + `moove_gdpr_company_logo_id` | The site logo = ACF option `logo` (`get_field('logo','option')`): use its `url` and `ID` **on staging** (local ID 1153 does not carry over; it differs per environment). The default blue "GDPR" logo must not be left in place. |

Consent is stored in the browser cookie `moove_gdpr_popup` (365 days; `strict:1`, all other categories `0` after "Samo neophodni"). Free version limits: no consent log (Premium), no extra categories (Premium), blocks only scripts added in its settings. The banner appears ~1.5 s after load (plugin delay).

## 3. Code behaviour to know

- **Footer** (`inc/footer.php`, `dreampoint_b2b_footer_legal_links()`): "Postavke kolačića" = `home_url( '/#gdpr_cookie_modal' )` when `MOOVE_GDPR_VERSION` is defined (set unconditionally at the top of the plugin's main file, so it exists whenever the plugin is loaded), else `https://dream-point.hr/postavke-kolacica/`. Mechanism verified in the plugin JS (`assets/scripts/main.js`): clicks on `[href*="#gdpr_cookie_modal"]` / `#moove_gdpr_cookie_modal` open the panel in place; the same hash opens it on a fresh page load. Limit: the check proves the plugin is loaded, not that its banner/panel are enabled in its settings.
- **Translations**: loader in `inc/woocommerce.php` loads `languages/plugins/gdpr-cookie-compliance-<locale>.mo` at `init` 20 (same pattern as WBW/Silkypress). Only `determine_locale() === 'hr'` matches. Strings covered: sr-only banner title, close labels, "Cookie categories", "(opens in a new tab)", "Turn on %s first…". Settings texts above are DB data. Admin screens stay English. Local locale is `en_US` and `hr` is not installed, so QA used a temporary harness (since removed).
- **Mobile CSS** (`sass/components/_header.scss`, compiled into `style.css`): at <=767.98px the banner is lifted by `$dp-mobile-toolbar-height` (73px) only when `body:has(#header .action-btns)` (the standard header; the Quick Order header `dp-qo-header` has no such toolbar and keeps `bottom: 0`); the preferences overlay is raised above the toolbar (z-index 10000); banner buttons get `min-height: 44px`. No selector touches the navigation.

## 4. Staging checklist (nothing here has been done on staging)

Do in this order; stop and report on any unexpected result.

1. **Deploy theme code** (normal flow; then `wp --path=… eval-file` of the legal pages seed per `e6-legal-pages-staging-procedure.md`, drafts only).
2. **APROS exporter dependency (before touching Order Attribution):** the exporter plugin `uncle-dev-importer` (`order.php`) exists only on staging and was never read locally. Read-only `grep -ril "attribution\|sbjs" ` over it; docs mention no use, but that is not proof. If anything references it, stop.
3. **WooCommerce Order Attribution:** (owner approval) Settings → Advanced → Features → Order Attribution off (option `woocommerce_feature_order_attribution_enabled` = `no`; locally done through `FeaturesController::change_feature_enable`). Verify in a fresh logged-out context: no `sbjs_*` cookies on login/registration, no `wc_order_attribution_*` fields, essential cookies unaffected, existing order meta preserved (locally 8 rows on 1 order, unchanged). Do not touch `woocommerce_allow_tracking`.
4. **Cookie inventory on staging** (fresh context, before consent): cookies, localStorage, third-party hosts on login, registration, home, PDP, checkout; Cloudflare cookies if proxied; Back in Stock captcha options (a captcha would load Google/Cloudflare scripts and need consent handling); WooCommerce Tracks. The banner/panel claim "only necessary cookies" is valid only if this matches.
5. **Install plugin 5.3.1 + settings + logo** (section 2; owner approval; plugin is not tracked in Git, install on each environment).
6. **Plugin translation:** staging `WPLANG=hr`: no English left in banner/panel (sr-only title, aria labels, "(otvara se u novoj kartici)", "Pokreće", "Isključeno").
7. **Mobile banner** at 390px logged in: banner sits directly above the toolbar (no overlap), all banner buttons >= 44px and clickable, panel overlay covers the toolbar, toolbar unchanged after dismissing; also 1440px and logged-out login page (no gap under the banner).
8. **Quick Order layout** (`/quick-order/`, QO header variant): check that no other fixed bottom UI (QO sticky/cart bar) overlaps the banner at 390 and 1440. Not verified anywhere yet.
9. **Footer link** opens the panel in place (no navigation) on a normal page, at 1440 and 390.
10. **Legal pages (after client approval):** publish both pages; set `wp_page_for_privacy_policy` to the published privacy page (this also activates the registration privacy checkbox in `inc/my-account.php`, which renders nothing while the privacy page is a draft; confirm its server-side handling once visible); the WooCommerce checkout terms page is a separate owner decision. The footer then switches to the internal pages by itself.

## 5. Open points for the client / legal review

Legal text gaps (listed in the legal-pages procedure): mismatched privacy `mailto`, duplicate "Vaša prava", generic non-B2B wording, no controller identity/legal basis/retention/supervisory authority, implied-consent cookie wording, kuna reference and missing delivery/returns/payment clauses in the terms. Client decisions: whether order-source attribution data is needed (it is off locally), whether a consent log (Premium) is required, final banner/panel wording.

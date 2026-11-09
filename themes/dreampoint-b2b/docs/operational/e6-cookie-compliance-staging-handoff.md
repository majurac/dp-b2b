# E6 — Legal Pages & Cookie Compliance: Final Status, Local Handoff and Staging Record

Status: **COMPLETE ON STAGING (2026-11-09), deployed and verified at commit `b04b16d`; legal pages published on staging.** Only localhost + staging exist (no production environment). Sections 1 and 4 below are the historical pre-deployment record; the current state is section 0. Companion: `docs/operational/e6-legal-pages-staging-procedure.md` (draft legal pages + seed script).

## 0. Final status (end of day 2026-11-09) - E6 STAGING COMPLETE

**Environments: localhost + staging only. There is no production environment.** Everything below was done on staging (`dreampoint.b2b.uncledev.cloud`, site user `dream9399`); nothing was or will be planned for production.

- **Code:** staging, local and `origin/master` all at `b04b16d` (verified clean). E6 commits: `a152f08`, `068fc78`, `8347957`, `9991d9f`, `93d099a`; C3 brands listing and mobile dropdown (`e6b88cd`, `ebe969d`) shipped in the same deploy; `b04b16d` = registration privacy checkbox removed (`inc/my-account.php`) + Rich Text pages `padding-top: 0` (`sass/blocks/wysiwyg.scss`, `css/blocks/wysiwyg.css`).
- **Phases:** A0 (backup) - A5 (final audit) COMPLETE; T1 (Moove font) COMPLETE; D1 / D1.1 (necessary-only consent persistence) COMPLETE; L2 (legal pages published) COMPLETE.
- **GDPR Cookie Compliance 5.3.1 (Moove)** installed from the official package, configured and active on staging (settings are DB data, keyed in `moove_gdpr_plugin_settings`; see section 2). Staging additions after the original configuration: `moove_gdpr_plugin_font_type = 2` (inherits Montserrat), `moove_gdpr_strictly_necessary_cookies_functionality = 2` (necessary category always on, so "Spremi postavke" persists a necessary-only choice), `moove_gdpr_reject_all_button_enable = 1` ("Samo neophodni" inside the preferences modal only; the banner keeps one "Prihvaćam" button plus the inline "postavkama" link; banner reject stays off). Logo = staging ACF `logo` (attachment 23397).
- **WooCommerce Order Attribution disabled** (`woocommerce_feature_order_attribution_enabled = no`); no `sbjs_*` cookies. `woocommerce_allow_tracking` and `woocommerce_analytics_enabled` untouched (`yes`). APROS exporter reads explicit meta keys only; no dependency on attribution data.
- **Legal pages published on staging:** Politika privatnosti (ID 23564, `/politika-privatnosti/`) and Opći uvjeti poslovanja (ID 23565, `/opci-uvjeti-poslovanja/`), text unchanged (verbatim `dream-point.hr`), Rich Text template. `wp_page_for_privacy_policy = 23564`, `woocommerce_terms_page_id = 23565` (checkout now shows the terms checkbox). Footer legal links resolve to the internal pages; "Postavke kolačića" opens the Moove preferences modal.
- **Legal pages are intentionally restricted to authenticated users** (closed B2B system): `dreampoint_b2b_restrict_guest_access()` in `inc/b2b-registration.php` redirects guests to `/my-account` for everything except login, registration and password reset. This is by design and is not to be "fixed".
- **Registration form:** the `accept_toc` privacy/terms checkbox was removed (no validation depended on it); all other fields and validation unchanged. `remove_action(... 'wc_registration_privacy_policy_text' ...)` is kept so WooCommerce's default privacy text does not appear.
- **Legal text** may be edited later by the client in WP Admin. No legal review is required for the current staging phase; the known content gaps listed in `e6-legal-pages-staging-procedure.md` stay informational.
- **Backups / snapshots (staging, outside web root, mode 600):** `/home/dreampoint.b2b/backups/pre-e6-20261109-133729/` holds the A0 DB export, theme archive, option and page inventories, and the Moove option snapshots `…-pre-T1.json` and `…-pre-D1.1.json` (rollback reference: restore keys individually, verify in a fresh WP-CLI process).

**Known future-consent limitation (preserve):** "Prihvaćam" writes `{strict, thirdparty, advanced, performance, preference} = 1` regardless of which categories are enabled (hard-coded in the plugin's `moove_gdpr_save_cookies()`, `main.js`). Nothing activates today (no scripts are defined for any category, no tracking IDs, no consumers of the cookie in theme/plugins). **Before any optional cookie category or tracking script is introduced, consent must be renewed:** add a small theme filter on `gdpr_extend_loc_data` that sets `gdpr_consent_version` (> 1), which makes the plugin discard existing consent cookies (including unversioned ones) and ask again; also reconsider an equally prominent reject option in the banner and a consent log (Premium) at that point.

**Other notes:** Moove settings are DB-only (not in Git); re-apply from section 2 plus the staging additions above if the plugin is reinstalled or the DB is replaced. The two legal pages were created by WP-CLI, so `post_author = 0`. The consent cookie has no `Secure` flag (plugin default; optional hardening via the plugin's `gdpr_cookie_custom_attributes` filter).

## 1. What was done, pending, and blocked (pre-deployment snapshot, 2026-11-09 morning)

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
| Look | `moove_gdpr_colour_scheme` | `1` (dark `#202020`, as dream-point.hr; was `2` before the 2026-11-09 visual alignment) |
| | `moove_gdpr_brand_colour` | `#0d121c` (B2B `$dp-color-btn-primary-bg`; drives the panel buttons/tabs/close icon. The reference blue `#0c4da2` was tried and replaced by the B2B theme colour on 2026-11-09) |
| | `moove_gdpr_button_style` | `rounded` |
| | `moove_gdpr_infobar_position` | `bottom` |
| | `moove_gdpr_floating_button_enable` | `0` |
| Banner text | `moove_gdpr_info_bar_content` | `<p>Ova stranica koristi samo neophodne kolačiće potrebne za prijavu, košaricu i naplatu.<br>Više o kolačićima pogledajte u {setting}postavkama{/setting}.</p>` (the placeholder is paired `{setting}…{/setting}`; it renders the inline underlined "postavkama" link as on dream-point.hr) |
| Banner buttons | `moove_gdpr_accept_button_enable` / `_reject_button_enable` / `_settings_button_enable` | `1` / `0` / `0` (banner = "Prihvaćam" + inline "postavkama" link, as dream-point.hr; the separate reject and Postavke buttons are off. Necessary-only is the only category, so there is no optional cookie to decline; the preferences panel stays reachable from the link and the footer "Postavke kolačića") |
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
- **Banner button CSS** (`sass/components/_header.scss`, same block): the plugin hard-codes white text on the brand colour and its own white `:hover`/`:focus` fill, so on the dark bar the banner `button.mgbutton` is overridden (selector chain + `moove-gdpr-dark-scheme` + `html` prefix) with the B2B secondary button tokens (`button-figma-secondary-colors`: rest `#f9fafb`/`#384250`, hover `#f3f4f6`, pressed `#e5e7eb`) and an inverted two-tone focus ring. Panel buttons stay native.
- **Mobile CSS** (`sass/components/_header.scss`, compiled into `style.css`): at <=767.98px the banner is lifted by `$dp-mobile-toolbar-height` (73px) only when `body:has(#header .action-btns)` (the standard header; the Quick Order header `dp-qo-header` has no such toolbar and keeps `bottom: 0`); the preferences overlay is raised above the toolbar (z-index 10000); banner buttons get `min-height: 44px`. No selector touches the navigation.

## 4. Staging checklist (EXECUTED 2026-11-09 as phases A0-A5; historical record)

Do in this order; stop and report on any unexpected result.

1. **Deploy theme code** (normal flow; then `wp --path=… eval-file` of the legal pages seed per `e6-legal-pages-staging-procedure.md`, drafts only).
2. **APROS exporter dependency (before touching Order Attribution):** the exporter plugin `uncle-dev-importer` (`order.php`) exists only on staging and was never read locally. Read-only `grep -ril "attribution\|sbjs" ` over it; docs mention no use, but that is not proof. If anything references it, stop.
3. **WooCommerce Order Attribution:** (owner approval) Settings → Advanced → Features → Order Attribution off (option `woocommerce_feature_order_attribution_enabled` = `no`; locally done through `FeaturesController::change_feature_enable`). Verify in a fresh logged-out context: no `sbjs_*` cookies on login/registration, no `wc_order_attribution_*` fields, essential cookies unaffected, existing order meta preserved (locally 8 rows on 1 order, unchanged). Do not touch `woocommerce_allow_tracking`.
4. **Cookie inventory on staging** (fresh context, before consent): cookies, localStorage, third-party hosts on login, registration, home, PDP, checkout; Cloudflare cookies if proxied; Back in Stock captcha options (a captcha would load Google/Cloudflare scripts and need consent handling); WooCommerce Tracks. The banner/panel claim "only necessary cookies" is valid only if this matches.
5. **Install plugin 5.3.1 + settings + logo** (section 2; owner approval; plugin is not tracked in Git, install on each environment).
6. **Plugin translation:** staging `WPLANG=hr`: no English left in banner/panel (sr-only title, aria labels, "(otvara se u novoj kartici)", "Pokreće", "Isključeno").
7. **Mobile banner** at 390px logged in: banner sits directly above the toolbar (no overlap), all banner buttons >= 44px and clickable, panel overlay covers the toolbar, toolbar unchanged after dismissing; also 1440px and logged-out login page (no gap under the banner).
8. **Quick Order layout** (`/quick-order/`, QO header variant): check that no other fixed bottom UI (QO sticky/cart bar) overlaps the banner at 390 and 1440. Local result 2026-11-09 (390px, logged in): the QO header variant has no bottom toolbar and no other fixed bottom UI, the banner sits at `bottom: 0` with its content scrollable above it, nothing overlaps; logged-out login page: banner at `bottom: 0` (no toolbar, no gap). Still to be repeated on staging.
9. **Footer link** opens the panel in place (no navigation) on a normal page, at 1440 and 390.
10. **Legal pages (after client approval):** publish both pages; set `wp_page_for_privacy_policy` to the published privacy page (this also activates the registration privacy checkbox in `inc/my-account.php`, which renders nothing while the privacy page is a draft; confirm its server-side handling once visible); the WooCommerce checkout terms page is a separate owner decision. The footer then switches to the internal pages by itself.

## 5. Open points for the client / legal review

Legal text gaps (listed in the legal-pages procedure): mismatched privacy `mailto`, duplicate "Vaša prava", generic non-B2B wording, no controller identity/legal basis/retention/supervisory authority, implied-consent cookie wording, kuna reference and missing delivery/returns/payment clauses in the terms. Client decisions: whether order-source attribution data is needed (it is off locally), whether a consent log (Premium) is required, final banner/panel wording.

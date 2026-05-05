## Handoff — 2026-04-23

### Done
- **Akcija stranica (discounted archive)**: refaktorisan na native WC pristup — `pre_get_posts` (prio 8) pretvara main query u WC product archive filtriran na `wc_get_product_ids_on_sale()`; WPF filter i native WC sorting rade automatski; `template_include` poslužuje `archive-product.php`; `is_woocommerce` filter osigurava WC kontekst
- **`archive-product.php` i `archive-product-discounted.php`**: uklonjen orphan `do_action('woocommerce_after_main_content')`
- **`archive-product-discounted.php`**: više se ne učitava — može se obrisati
- **Select2 na shop/archive stranicama**: dodan `is_shop() || is_product_category() || is_product_tag() || is_tax('product_brand') || dreampoint_b2b_akcija_page_detected()` u `dreampoint_b2b_needs_select2()`
- **Block checkout wrapper**: `the_content` filter dodaje `.container.custom-form` oko WC block checkout-a
- **`checkout-b2b-info.js`** (preimenovan iz `custom-checkout.js`): refaktorisan — `setTimeout` zamjenjen MutationObserverom, svi labeli translatable via `dpCheckoutB2B`, IIFE + `use strict`, guard protiv dvostrukog inseriranja
- **B2B email flow**: `customer-new-account.php` override (pending approval), `inc/emails.php` (WC email klasa `WC_Email_Admin_B2B_New_Registration`), `admin-b2b-new-registration.php` + plain verzija; WP default admin notifikacija suprimirana
- **ERP webhook**: `POST /wp-json/dreampoint-b2b/v1/approve-user`, auth `X-DP-ERP-Token`, prima `email` ili `user_id`, WC logger, piše `_erp_approved_at`
- **Users lista**: dodata kolona "Tvrtka" (`billing_company`), "Odobreno" sad zelena/crvena, obje translatable
- **WC 10.4.0 email compat**: oba template-a imaju `FeaturesUtil` import, `$email_improvements_enabled` i `.email-introduction` wrapper

### Next steps
- Testirati registraciju novog B2B korisnika → provjeriti oba emaila (partner + admin)
- Testirati ERP webhook: `curl -X POST /wp-json/dreampoint-b2b/v1/approve-user -H "X-DP-ERP-Token: SECRET" -d '{"email":"test@test.com"}'`
- Definirati `DP_ERP_WEBHOOK_SECRET` u `wp-config.php` na svakom okruženju
- Testirati `/akcija/` stranicu: WPF filter, sorting, paginacija
- Obrisati `woocommerce/archive-product-discounted.php` nakon potvrde

### Key decisions made
- Native WC main query za akcija stranicu (ne custom WP_Query) — WPF kompatibilnost
- `dreampoint_b2b_akcija_page_detected()` static flag — `is_page()` ne radi nakon `pre_get_posts` (is_page = false)
- `pre_get_posts` prio 8 — WC hookovi (prio 9-10) vide modificiran query
- Admin email klasa prio 20 — nakon `dreampoint_b2b_save_registration_fields` (prio 10)
- `.email-introduction` samo oko uvodnog paragrafa — tablica s podacima je content section

### Files modified
- `inc/woocommerce.php` — akcija page routing (3 hooka + helper funkcija)
- `functions.php` — `dreampoint_b2b_needs_select2()`, block checkout wrapper, `checkout-b2b-info` enqueue
- `js/checkout-b2b-info.js` — potpuni refactor
- `inc/registration-approval.php` — Tvrtka kolona, ERP webhook
- `inc/emails.php` — novi fajl, WC email klasa
- `woocommerce/emails/customer-new-account.php` — novi fajl
- `woocommerce/emails/admin-b2b-new-registration.php` — novi fajl
- `woocommerce/emails/plain/admin-b2b-new-registration.php` — novi fajl
- `woocommerce/archive-product.php` — uklonjen orphan hook
- `CLAUDE.md` — dokumentacija

### Watch out for
- `DP_ERP_WEBHOOK_SECRET` mora biti u `wp-config.php` — bez njega webhook vraća 500
- Stranica `/akcija/` mora postojati u WP adminu s tim slug-om
- `archive-product-discounted.php` još na disku — ne brisati dok se ne potvrdi da `/akcija/` radi
- Multilanguage (WPML): `is_page('akcija')` radi samo za HR — za ostale jezike zamijeniti s `icl_object_id`

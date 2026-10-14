# Contact Form 7 -> sales representative routing: investigation and proposal

Status: **INVESTIGATION ONLY (2026-10-14). NOT IMPLEMENTED. Awaiting owner approval of the business rules below.**
Environments: localhost + staging only (no production). Nothing was sent and no CF7 configuration was changed.

## 1. Facts verified (read-only)

- **Form:** staging CF7 form post `19` "Contact form 1" (hash `88343c0`, which is the value stored in ACF option `contact_form_id`; CF7 6.2.2 accepts the hash as `id`). Its template is still the CF7 sample form (English labels: `your-name`, `your-email`, `your-subject`, `your-message` (optional), "Submit"). Mail: recipient `[_site_admin_email]`, sender `[_site_title] <site admin email>`, `Reply-To: [your-email]`. **The form and its Mail tab live in the database, not in Git.**
- **Theme expectations:** `inc/contact-form.php` pre-fills (client side, after page load, logged-in users only) the fields `your-name`, `last-name`, `your-email`, `your-tel`, `company-name`, `company-oib`, `company-address`, `company-zip`, `company-city`, `company-country`. The staging form only has the first two, so the production-intended form was richer than the staging sample.
- **Assignment source:** user meta `assigned_komercijalist` = post ID of CPT `komercijalist` (`acf-json/post_type_67b45e357b53e.json`); ACF group `group_67b45eed142b8` on that CPT: `phone_commercialist`, `mobile_commercialist`, `email_commercialist` (plain text, not required, no email validation in ACF). Set by the `b2b-partner-importer` plugin (Excel/CSV import auto-match, and the user-profile dropdown "Zaduženi komercijalist"), read by `inc/myaccount-komercijalist.php`, the `[contact_info]` shortcode and the My Account tab.
- **Existing notification logic:** plugin `b2b-komercijalist-notifications` adds the assigned representative as `Cc` to the WooCommerce **new order** admin email only (`woocommerce_email_headers`, `email_id === 'new_order'`), validates `is_email()`, logs an order note, and silently skips when no representative / invalid email. Nothing exists for CF7.
- **Staging data:** 7 users (1 administrator, 6 customers), only 1 user has `assigned_komercijalist`; 1 published `komercijalist` post with a valid email. So 5 of 6 customers would hit the "no representative" branch today.
- **Who can submit:** `/kontakt/` redirects guests to `/my-account/` (the site gates guests), but the CF7 REST endpoint `POST /wp-json/contact-form-7/v1/contact-forms/19/feedback` is a separate, public route: a guest (or a script) can submit to it without visiting the page. Pending (unapproved) B2B users are a further case (`approval-pending.php`).
- **Critical technical constraint:** CF7's front-end script **deletes the `X-WP-Nonce` header** (`delete i["X-WP-Nonce"]` in `includes/js/index.js`), so the feedback request is processed as an UNAUTHENTICATED REST request: `get_current_user_id()` is `0` for everybody, even a logged-in partner. Routing that calls `get_current_user_id()` inside the CF7 hook would never find the partner. The logged-in cookie IS still sent by `fetch` (same origin).

## 2. Proposal (needs owner approval)

**Where:** a new small theme file `inc/contact-form-routing.php` (next to `inc/contact-form.php`), hooked on CF7 `wpcf7_before_send_mail` (server side, only for the Contact form id stored in ACF `contact_form_id`, resolved through `WPCF7_ContactForm::get_by_hash()` / id so the hash and the numeric id both work). No client-supplied recipient, no hidden field read for routing.

**Partner identity (server side):** `wp_validate_auth_cookie( '', 'logged_in' )` (reads the logged-in cookie directly, independent of the missing REST nonce) -> user ID. Not `get_current_user_id()`, not any submitted field, and no signed-token workaround is needed.

**Recipient precedence** (all must be confirmed by the owner):

| # | Situation | Proposed behaviour |
|---|---|---|
| 1 | Logged-in partner, valid `assigned_komercijalist` post, published, valid `email_commercialist` | To = representative; Cc = the existing CF7 default recipient (office) so nothing is lost; `Reply-To` stays the partner's email |
| 2 | Logged-in partner without assignment | To = the existing CF7 default recipient (currently `[_site_admin_email]`): **owner to name the real fallback mailbox** (admin email is only the sample default) |
| 3 | Guest / invalid cookie (direct REST post) | Same default recipient as case 2, mail body marked "guest" OR the submission aborted: **owner decision** (the page is login-gated, so a guest submission is anomalous; abort is the safer option) |
| 4 | Representative post trashed/draft, `email_commercialist` empty or `! is_email()` | Treated as case 2 and logged (no error shown to the user) |
| 5 | Resolution failure (exception, missing post) | Fail safe to the default recipient; never drop the message, never block the form |

**Mail template changes (database, CF7 Mail tab, only after approval):** add partner context to the body (partner name, company, OIB, representative name) from SERVER-side data (not from the form fields), and Croatian labels. The Figma form (CON-01) has only "Vrsta upita" + "Vaš upit": see the conflict below.

**Logging / privacy:** log only the decision (case 1-5, representative post ID) via `error_log` or an order-note-like admin log; never the message body or email addresses; no PII in logs (rules/erp.md style). **Delivery:** staging uses `wp_mail` (a `wp_mail_from` filter is present); deliverability (SPF/DKIM, SMTP plugin) for the production-like sender must be verified before relying on routing. **Testing plan to approve:** (a) a unit-style dry run that calls the resolver function directly for 5 fixture users and asserts the chosen recipient WITHOUT calling `wp_mail`; (b) one controlled staging submission to a mailbox the owner controls (assigned representative email temporarily set to that mailbox), explicitly approved.

## 3. Conflicts and open decisions for the owner

1. **Figma form vs current form:** Figma CON-01 shows only "Vrsta upita" (select) and "Vaš upit" (textarea) and a "Pošalji" button with a send icon; the staging CF7 form asks for name, email, subject, message. If name/email are dropped, they must be added server-side to the mail (partner is known) and the CF7 template/Mail tab (DB) edited. Not done: a destructive DB change, and routing is not approved.
2. **Figma copy** "Vaša poruka bit će proslijeđena vašem prodajnom predstavniku radi bržeg odgovora." is NOT shown on the page: it would promise routing that does not exist yet (the contact intro paragraph is empty / ACF-driven).
3. Fallback mailbox (cases 2-4) and guest behaviour (case 3).
4. Whether the office keeps receiving a copy (Cc) when a representative is assigned.
5. Data quality: 5 of 6 staging customers have no representative; the importer is the source of truth.

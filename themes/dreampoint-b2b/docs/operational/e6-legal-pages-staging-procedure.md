# E6-A — Legal Pages on Staging (Draft Creation Procedure)

Status: **DONE ON STAGING (2026-11-09).** Drafts created by the seed script (IDs 23564, 23565, A2), then published with the privacy/terms assignments (L2); code deployed at `b04b16d`. Only localhost + staging exist (no production environment). Current state: `docs/operational/e6-cookie-compliance-staging-handoff.md` section 0.

Git deploys carry code only, not database content. The two legal pages are DB content, so they must be created on each target by script. Nothing here publishes a page, installs a plugin, or changes a WordPress/WooCommerce setting.

## What E6-A delivers (code, in Git)

| File | Purpose |
|---|---|
| `rich-text-page.php` | Existing "Rich Text" template; now has the `wysiwyg-block` wrapper class so the editor HTML gets the shared WYSIWYG typography |
| `inc/enqueue-block-styles.php` | Loads `css/blocks/wysiwyg.css` for `rich-text-page.php` (same pattern as `contact.php`) |
| `inc/footer.php` | `dreampoint_b2b_legal_page_url()`: footer Privacy/Terms links use the internal page **only when it is published**, otherwise the `dream-point.hr` URL. "Postavke kolačića" stays external |
| `docs/operational/e6-legal-pages/` | `seed-legal-pages.php` + `privacy-policy.html` + `terms.html` (this procedure's inputs) |

The HTML files are verbatim copies of `dream-point.hr/privacy-policy/` and `/opci-uvjeti-poslovanja/` (copied 2026-11-09, public company text only, no personal data). They are a **draft baseline**, not approved legal text.

## Procedure (staging)

1. Deploy the theme code first (normal flow: push, then `sudo -u dream9399 git pull --ff-only`, verify HEAD). The script aborts if `rich-text-page.php` is missing.
2. Run the seed script as the site user (never root; script path is inside the deployed theme):
   ```
   sudo -u dream9399 wp --path=/home/dreampoint.b2b/public_html eval-file \
     /home/dreampoint.b2b/public_html/wp-content/themes/dreampoint-b2b/docs/operational/e6-legal-pages/seed-legal-pages.php
   ```
   Expected: `CREATED politika-privatnosti ID=… status=draft template=rich-text-page.php` and the same for `opci-uvjeti-poslovanja`. Re-running prints `SKIP (exists)` and changes nothing.
3. Verify (read-only): both pages are `draft`, template `rich-text-page.php`, and the footer legal links still point to `dream-point.hr`:
   ```
   sudo -u dream9399 wp --path=/home/dreampoint.b2b/public_html post list --post_type=page --name=politika-privatnosti --fields=ID,post_status
   sudo -u dream9399 wp --path=/home/dreampoint.b2b/public_html post meta get <ID> _wp_page_template
   ```
   Then open the authenticated draft preview (`/?page_id=<ID>&preview=true`) at 1440 and 390 px.
4. Do **not** change `wp_page_for_privacy_policy` or `woocommerce_terms_page_id` yet (see "Not yet done").

Rollback: trash the two draft pages by ID in WP Admin (they are new posts; nothing else references them). The footer falls back to the external URLs automatically.

## Local verification record (2026-11-09)

Local drafts: Privacy ID 2438, Terms ID 2439 (IDs differ per environment). Script tested: idempotent on existing pages (SKIP x2); creation path tested with temporary slugs (draft, correct template, content identical to the HTML files), temporary pages deleted. Responsive preview 1440/390 px passed (headings, lists, spacing, no overflow).

## Client content gaps (flag for legal review; text NOT rewritten)

Privacy: `mailto:` targets `info@dreampoint.hr` while the visible text is `info@dream-point.hr`; "Vaša prava" appears twice (sections 3 and 7); text is generic (offers, transactions, billing) and not B2B/ERP-specific; no controller identity (company name/OIB), legal basis, retention period or supervisory authority; cookie section claims implied consent ("by using the site you agree") and lists analytics/marketing cookies, which conflicts with a consent banner and needs checking against what the shop really sets.
Terms: generic company terms with no delivery, returns/withdrawal, BACS payment, ordering or B2B contract clauses; mentions kuna ("or euro, depending on regulations"); contact `<a>` has no `href`; liability clause needs legal confirmation.

## Status after deployment (2026-11-09; was "Not yet done")

- Published on staging: `/politika-privatnosti/` (23564) and `/opci-uvjeti-poslovanja/` (23565); `wp_page_for_privacy_policy = 23564`, `woocommerce_terms_page_id = 23565`. Legal text may be edited later by the client; no legal review is required in the current staging phase. The pages are only reachable by authenticated users (closed B2B system, guest gate in `inc/b2b-registration.php`).
- The registration `accept_toc` checkbox was removed (`b04b16d`); the points below are the original pre-deployment list and are historical.


- Client review/approval of both texts, then publish (owner action).
- Cookie consent: the same plugin as `dream-point.hr` (GDPR Cookie Compliance) needs owner approval to install; the "Postavke kolačića" footer target depends on how the plugin opens its preferences. Unknown what cookies the shop sets today.
- After publishing: set `wp_page_for_privacy_policy` to the published privacy page (this also activates the registration privacy checkbox in `inc/my-account.php`, which currently renders nothing because the privacy page is a draft). WooCommerce terms page for checkout is a separate owner decision.
- Footer then switches to the internal pages by itself (published pages only).

Cookie consent (plugin configuration, footer integration, full staging checklist): `docs/operational/e6-cookie-compliance-staging-handoff.md`.

Roadmap: `docs/active/master-roadmap.md` E6.

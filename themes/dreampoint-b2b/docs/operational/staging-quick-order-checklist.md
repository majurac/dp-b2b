# Quick Order — Staging Deploy Safety Checklist

Run before first real staging test session.

## Authentication

Staging WP Admin credentials: Canonical Secret Entry "Dreampoint B2B —
Staging — WordPress Admin" in KeePassXC (`~/.claude/docs/server-runbook.md`
§ Secrets). Never ask for these credentials in chat if the entry is
available — open it locally, log in directly.

## DB Snapshot

- [ ] Export local DB: `C:\xampp2\mysql\bin\mysqldump.exe -u root --password="" dp_b2b > dp_b2b_backup_YYYYMMDD.sql`
- [ ] Export staging DB via phpMyAdmin or WP-CLI: `wp db export backup-YYYYMMDD.sql`
- [ ] Keep backup locally and on server until staging test is complete

## Uploads Backup

- [ ] Staging uploads are copied from local (initial deploy) — no user-generated content yet
- [ ] If staging has been used previously: backup `/home/dreampoint.b2b/public_html/wp-content/uploads/` via rsync before overwriting

## Object Cache / Redis Awareness

- [ ] If Redis is active on staging: flush cache after deploy (`wp cache flush` or WP-CLI litespeed-purge)
- [ ] Quick Order REST responses are not cached by default — no extra cache flush needed for API
- [ ] If LiteSpeed Cache is active: purge all after activating plugin (`wp litespeed-purge all`)

## Plugin Activation Check

- [ ] Activate `dp-b2b-quick-order` in WP Admin → Plugins
- [ ] Confirm no red admin notice about WooCommerce or missing JS asset
- [ ] Verify `[dp_quick_order]` shortcode is on a published page

## Smoke Test (manual, pre-Playwright)

> Updated 2026-07-10 for the local-state architecture
> (`docs/frozen/quick-order-local-state-architecture.md`). Quantity changes no
> longer fire network requests — only the explicit submit does.

- [ ] Log in as B2B test user
- [ ] Open Quick Order page — product list loads
- [ ] Change a quantity — footer updates immediately, **no** network request fires (check browser Network tab)
- [ ] Open WooCommerce cart in another tab — confirm it is unaffected by Quick Order quantity changes
- [ ] Click "Dodaj u košaricu" — one (or chunked, if >50 rows) request(s) fire; WooCommerce cart now reflects submitted items; Quick Order state clears
- [ ] Refresh the Quick Order page before submitting — confirm quantities reset to 0 (no persistence, by design)
- [ ] Log in as B2C user — Quick Order page shows login prompt or is inaccessible

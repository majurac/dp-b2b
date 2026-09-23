# Block CSS Cache-Busting Granularity — Accepted Technical Debt

**Severity:** Low
**Priority:** Low
**Status:** Documented
**Confirmed:** 2026-09-23, during local Playwright verification of the Homepage/Segment Landing frontend fix (`docs/decisions.md` ADR-009).

This is not a bug in any specific fix. No action is required right now. Document this only until a trigger condition occurs.

---

## Observation

Per-block CSS files (`css/blocks/*.css`, enqueued conditionally by `inc/enqueue-block-styles.php`) are versioned with the theme's single global `_S_VERSION` constant:

```php
define( '_S_VERSION', (string) max(
    filemtime( get_template_directory() . '/style.css' ),
    filemtime( get_template_directory() . '/js/theme.min.js' )
) );
```

`_S_VERSION` only reflects the mtimes of `style.css` and `js/theme.min.js`. A change to an individual block's SCSS/CSS (e.g. `sass/blocks/company-features.scss` → `css/blocks/company-features.css`, rebuilt via `npm run build:blocks`) does **not** change either of those two files, so the `?ver=` query string on that block's `<link>` tag stays identical across the deploy.

## Why This Was Noticed

While verifying the `company-features.scss` static-layout fix locally, the browser served a stale cached copy of `css/blocks/company-features.css` (missing the new `display:flex` rule) even after a hard reload, because the `?ver=` value had not changed. Confirmed via a manually cache-busted `fetch()` that the correct file content was being served by the server — the staleness was purely a browser-cache artifact of the shared version string, not a deployment or build failure.

## Current Impact

- A visitor whose browser already cached a block CSS file under a given `?ver=` value will keep serving that cached copy until it naturally expires, even after a deploy that changes only that block's styling — unless `style.css` or `theme.min.js` also changed in the same deploy (common in practice, since most deploys touch more than one asset, but not guaranteed).
- No functional/security impact — worst case is a visually stale block until cache expiry or a hard refresh.

## Trigger Conditions for Revisiting

Do not revisit unless ONE OR MORE of the following occurs:

- A block-CSS-only change needs to reach visitors reliably and immediately (can't wait for natural cache expiry or an unrelated `style.css`/`theme.min.js` touch).
- Repeated reports of "changes not showing up" traced back to this exact mechanism.
- The build tooling (`build.md`) is revisited for other reasons and per-asset versioning is already on the table.

## Recommended Resolution (when triggered)

Version each `css/blocks/*.css` handle independently, e.g. `filemtime()` of that specific file instead of the shared `_S_VERSION`, mirroring how `inc/enqueue-block-styles.php` already enqueues them per-block. Keep `_S_VERSION` for the assets it's actually meant to track (`style.css`, `theme.min.js`).

## Files Involved

- `functions.php` — `_S_VERSION` definition
- `inc/enqueue-block-styles.php` — per-block CSS conditional enqueue (`wp_enqueue_style(..., _S_VERSION)`)
- `css/blocks/*.css` / `sass/blocks/*.scss` — the affected assets

# Block CSS Cache-Busting Granularity — Accepted Technical Debt

**Severity:** Low (originally; the cart page-CSS regression found on 2026-10-09 made it real, see Resolution)
**Priority:** Low
**Status:** RESOLVED 2026-10-09 (`fff3d37`), staging-verified — see "Resolution" at the end. The sections below describe the original problem and are kept as history.
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

---

## Resolution (2026-10-09, commit `fff3d37`, staging-verified)

**Trigger:** while deploying the Figma cart remove button (`d77b7d1`, only `css/pages/cart.css` changed) staging kept serving the cached `cart.css?ver=1791542854` (`Cache-Control: public, max-age=604800`) with the new cart markup, because `_S_VERSION` is `max(mtime(style.css), mtime(js/theme.min.js))` and neither file changed. The same mechanism applied to every theme page and block stylesheet, not only block CSS.

**Fix:** the `_S_VERSION` argument of the affected `wp_enqueue_style()` calls was replaced with the existing `dreampoint_b2b_asset_ver( '<theme-relative path>' )` helper (per-file `filemtime()`, falls back to `_S_VERSION` if the file is missing). Nine version arguments in three files, nothing else changed (handles, URLs, dependencies, conditions, order, `_S_VERSION` and the helper are untouched; no Sass, JS or generated CSS):
- `functions.php`: `cart`, `checkout`, `myaccount`, `order-details`, `shop-archive`, `shop-single`, `faq` (`css/pages/*.css`).
- `inc/woocommerce.php`: `css/pages/woocommerce.css` (the real path; there is no `css/woocommerce.css`).
- `inc/enqueue-block-styles.php`: the existing per-block loop now passes `'css/blocks/' . $slug . '.css'`, covering all 11 mapped block stylesheets.

**Staging evidence (HEAD `fff3d37`, clean tree, `php -l` OK on the three files):**
- `/cart/` now emits `cart.css?ver=1791548401`, equal to `stat -c %Y` of the server file; HTTP 200, `Cache-Control` unchanged; the browser loaded the new CSS without a forced refresh (the new URL bypasses the stale entry).
- `woocommerce.css?ver=1786182087` and the four blocks loaded on the homepage (`featured-categories`, `brands`, `contact-info` = `1785484796`, `company-features` = `1790159156`) match their server mtimes; `myaccount.css` and `shop-archive.css` also match.
- Authenticated HTML is `no-cache, private` (no public page cache), so the new `ver` reaches the HTML immediately. Anonymous requests redirect to login (B2B guest rules).
- Cart, shop and homepage smoke tests: no fatals, no console errors, no overflow.

**Known limitations / remaining scope (not part of this fix):**
- `brands.css` keeps its own equivalent inline `filemtime()` versioning (`d95f25f`); aligning it with the helper is optional.
- Vendor CSS (`css/src/*.min.css`, `css/vendors/slick.css`) and some JavaScript enqueues still use `_S_VERSION`.
- `filemtime()` is timestamp-based, not content-hash-based; mtime is not tracked by Git and changes on the server only when `git pull` rewrites a file.
- Separate (closed): the cart remove button's visual staging QA passed and its functional test was manually confirmed as PASS by the user on 2026-10-09 (`docs/active/figma-alignment.md` section 18).

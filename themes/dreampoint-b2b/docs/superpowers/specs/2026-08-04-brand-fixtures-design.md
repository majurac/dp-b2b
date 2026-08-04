# Dev Catalog Generator — Brand Fixtures Phase — Design

**Date:** 2026-08-04
**Status:** Approved, pending implementation

## Problem

A fresh localhost or staging environment has no way to recreate the real (non-`[DEV]`)
`product_brand` catalog data currently used to test the Brands page (segment
navigation, brand hero image/logo, filtering). That data currently exists only as
manually-created terms + manually-uploaded media in one developer's local database.
Recreating it today requires manual ACF/WP-CLI operations and knowledge that lives
only in developer memory (see `project_brand_architecture` memory — the one-time
JekaaStore migration script that originally created part of this data no longer
exists in the repo).

This must become a new phase of the existing Dev Catalog Generator
(`inc/dev/class-dev-catalog-generator.php`), not a separate tool, not a standalone
seeder, not a new WP-CLI command group.

## Constraints

- No separate WP-CLI command, no standalone seeder, no second dev tool. This is
  `--phase=brand-fixtures` on the existing `wp dp-b2b generate-catalog` command.
- Follow the existing architecture, coding style, reporting format, and production
  guard (`guard_production()`) already used by every other phase in the file.
- Fully idempotent: never recreate existing brands, never overwrite existing term
  data, never create duplicate Media Library attachments, skip anything that
  already exists.
- Do not change the existing synthetic product phases (`taxonomies`, `products`,
  `variables`, `ugly`) or `reset-catalog`.
- No production-facing functionality is touched.
- No network access, no external URLs, no dependency on production/corporate site
  availability — must work fully offline.
- Validation via WP-CLI only. No Playwright, no browser testing, no manual ACF
  operations, no database resets.

## Canonical dataset

The Brand Fixtures phase seeds a **fixed, hardcoded list of 21 real brand terms** —
this is a deliberate content decision, not a live mirror of whatever currently
happens to be in the local database. Future additions or removals to this list are
intentional code changes, never automatic reflections of local DB state.

Selection: all real (non-`[DEV]`) `product_brand` terms that currently carry
development value for Brands-page testing — brands with an assigned
`brand_segment`, brands without one, and brands that only ever received a logo.
Extracted directly from the current local database (`wp term list` / ACF field
reads, `--user=admin` to bypass the visibility engine's `get_terms` filter on
unauthenticated WP-CLI context — see `project_brand_architecture` memory).

| Slug | Name | Segment | Has logo | Has brand_image |
|---|---|---|---|---|
| `24bottles` | 24Bottles | lifestyle | yes | yes |
| `a-fan-of` | A Fan Of | lifestyle | yes | yes |
| `chillys` | Chilly's | — | yes | **no** |
| `design-letters-aps` | Design Letters ApS | lifestyle | yes | yes |
| `djeco` | Djeco | — | yes | **no** |
| `dock-bay` | DOCK & BAY | lifestyle | yes | yes |
| `eat-my-socks` | Eat My Socks | — | yes | yes |
| `flow-amsterdam` | Flow Amsterdam | toys | yes | yes |
| `fresk` | Fresk | toys | yes | yes |
| `gaston-luga` | Gaston luga | lifestyle | **no** | yes |
| `go-baby-go` | Go baby go | toys | yes | yes |
| `izipizi` | Izipizi | lifestyle | yes | yes |
| `janod` | Janod | — | yes | **no** |
| `la-coque-francaise` | LA COQUE FRANCAISE | lifestyle | yes | yes |
| `leatherman` | Leatherman | outdoor | yes | yes |
| `ledlenser` | Ledlenser | outdoor | yes | yes |
| `leuchtturm1917` | Leuchtturm1917 | lifestyle | yes | yes |
| `notabag` | Notabag | lifestyle | yes | yes |
| `nuuna` | NUUNA | lifestyle | yes | yes |
| `printworks` | PRINTWORKS | — | yes | yes |
| `secrid` | SECRID | lifestyle | yes | yes |

Exact `name` (raw, unescaped — `wp_insert_term()` applies WordPress's normal KSES/
entity handling on save, e.g. `DOCK & BAY` → stored as `DOCK &amp; BAY`, matching
current DB state) and full `description` text per brand are taken verbatim from the
current database and hardcoded into the generator's data array, the same way
`generate_categories()` / `generate_brands()` already hardcode their structures.

### Reproduce gaps faithfully — do not complete missing data

`gaston-luga` has no logo, and `chillys` / `djeco` / `janod` have no `brand_image`.
This is the real, intentionally-incomplete current state of the canonical dataset,
not an oversight to fix. The Brand Fixtures phase must reproduce these exact gaps:
brands with a missing asset get no `logo.*` or `brand-image.*` file in their fixture
folder, no attachment is created for the missing side, and the corresponding
`thumbnail_id` term meta / ACF `brand_image` field is simply never set for that
brand. Do not source a substitute image, do not duplicate the brand's other image
into the missing slot, and do not treat this as a bug to close while implementing
this phase. Brands without a `brand_segment` (Chilly's, Djeco, Janod, Eat My Socks,
Printworks) likewise get no `brand_segment` write — the ACF field is left unset,
matching current state.

## Fixture assets — canonical, git-committed dataset

```
dev-fixtures/
  brands/
    <slug>/
      logo.<ext>          (omitted where the brand has no logo)
      brand-image.<ext>   (omitted where the brand has no brand_image)
```

Populated by copying the actual current files out of `wp-content/uploads/` (verified
present on disk for this session — e.g. `24bottles` ← `uploads/2026/07/24bottles.webp`
+ `uploads/2026/07/1-1.webp`) into the new fixture folders under the theme, preserving
original extensions (`.webp`/`.jpg`/`.png` as applicable).

These files are **not temporary uploads and not build output** — they are permanent,
version-controlled fixture assets, part of the canonical development dataset in
exactly the same sense as the hardcoded category/brand name arrays already committed
in `class-dev-catalog-generator.php`. They are committed to git and must not be
gitignored, treated as scratch files, or excluded from the repo. `docs/index.md` /
the generator doc gets an explicit note to this effect so a future cleanup pass does
not mistake `dev-fixtures/` for disposable content.

## Design

### Phase wiring

New `brand-fixtures` option added to the existing `--phase=<phase>` enum in
`generate_catalog()`'s docblock and `switch` statement — a sibling to `taxonomies`,
`products`, `variables`, `ugly`. No dependency on any other phase having run first
(unlike `products`/`variables`, which require `taxonomies` first — Brand Fixtures
terms are entirely separate from the `[DEV]`-prefixed synthetic brand terms and
categories).

```php
case 'brand-fixtures':
    $this->run_brand_fixtures();
    break;
```

### Idempotency model — term-level skip, matching `ensure_term()`

For each of the 21 hardcoded brand records, in order:

1. `get_term_by( 'slug', $slug, 'product_brand' )`.
2. **If found → skip entirely.** Log `skip`, increment skip counter, move to the
   next brand. Nothing else is touched — no media check, no field check. This
   mirrors the existing `ensure_term()` behavior exactly (slug-based existence
   check, full skip) and keeps the phase simple and predictable: "already exists"
   is a single, unambiguous state, not a set of partially-repairable field-level
   states.
3. **If not found:**
   a. Resolve fixture asset attachments (logo, brand-image — whichever exist for
      this brand) via `ensure_fixture_attachment()` (below).
   b. `wp_insert_term( $name, 'product_brand', [ 'slug' => $slug, 'description' => $description ] )`.
   c. If a logo attachment was resolved: `update_term_meta( $term_id, 'thumbnail_id', $logo_id )`.
   d. If a brand-image attachment was resolved: `update_field( 'brand_image', $image_id, 'product_brand_' . $term_id )`.
   e. If the fixture defines a `brand_segment`: `update_field( 'brand_segment', $segment, 'product_brand_' . $term_id )`.
   f. `add_term_meta( $term_id, '_dp_brand_fixture', 1, true )` — informational
      marker only (see below).
   g. Log `+ <name> (id=<term_id>)` with a suffix noting which assets/fields were
      set, matching the existing log style.

This is a dedicated new private method for brand term creation — it does **not**
reuse or modify `ensure_term()`. `ensure_term()` has no `description` parameter and
is shared by the `taxonomies` phase (categories + `[DEV]` brands); changing its
signature or behavior is out of scope and risks the existing phases per the
constraints above. Full isolation is cheaper and safer than a shared abstraction
here — two phases, two small methods, no coupling.

### Attachment dedup — `ensure_fixture_attachment()`

```php
private function ensure_fixture_attachment( string $relative_path ): int {
    $existing_id = $this->find_attachment_by_fixture_source( $relative_path );
    if ( $existing_id ) {
        return $existing_id; // reused — no upload, no log as "created"
    }

    $full_path = dirname( __DIR__, 2 ) . '/dev-fixtures/' . $relative_path;
    $filename  = basename( $relative_path );
    $bits      = file_get_contents( $full_path );

    $upload = wp_upload_bits( $filename, null, $bits );
    // ... wp_insert_attachment() + wp_generate_attachment_metadata() +
    //     wp_update_attachment_metadata(), the standard native media workflow
    //     (the same one media_sideload_image() uses internally, driven from a
    //     local file's bytes instead of a downloaded remote URL).

    update_post_meta( $attachment_id, '_dp_brand_fixture_source', $relative_path );

    return $attachment_id;
}
```

**Dedup key is the relative fixture path** (e.g. `brands/24bottles/logo.webp`), not
the resulting filename in `wp-content/uploads/` and not the WordPress attachment
title. This is deliberate:

- It is stable and portable — the same relative path resolves to the same fixture
  file whether the phase runs on localhost or staging, regardless of what the
  environment's upload directory structure or existing media library contents look
  like.
- It sidesteps `wp_unique_filename()` collisions entirely: WordPress may rename the
  uploaded file (`logo.webp` → `logo-2.webp`) if a same-named file already exists in
  that month's upload folder for an unrelated reason, but the dedup lookup never
  depends on the resulting filename — only on the marker meta value written at
  creation time.
- It directly mirrors the proven pattern from the historical JekaaStore migration
  (`_dp_brand_import_source_url` term meta, see `project_brand_architecture`
  memory), applied here at the attachment level with a fixture-relative path instead
  of a source URL.

Lookup implementation: a direct `get_posts()` / `WP_Query` meta query on
`post_type => 'attachment'`, `meta_key => '_dp_brand_fixture_source'`,
`meta_value => $relative_path` — attachment queries are not subject to the
`product_brand` visibility filter (`inc/visibility/class-query-filter.php`), so no
`--user=admin` / direct-`$wpdb` workaround is needed here, unlike the term lookups
elsewhere in this file.

### Marker meta — `_dp_brand_fixture`, not a generic `_dp_fixture_type`

The term-level marker stays **brand-specific** (`_dp_brand_fixture = 1`) rather than
becoming a generic `_dp_fixture_type => 'brand'` key. Reasoning: this is the first
and, as of this spec, only fixture-type phase in the generator — there is no second
concrete fixture type to design a shared taxonomy of marker values against, and
generalizing now would be speculative (the project's own coding rules reject
premature abstraction: "three similar lines is better than a premature
abstraction"). The marker is purely informational — nothing in this phase, in
`reset-catalog`, or anywhere else in the file queries or branches on it; it exists
only so a human inspecting term meta in wp-admin/WP-CLI can tell a fixture-created
brand apart from a manually-created one. Because nothing depends on its exact key
name, renaming it later (if a second fixture phase is ever added and a shared
convention becomes justified by actual duplication, not anticipation of it) is a
trivial, zero-risk change. Keeping it specific now costs nothing and avoids
designing an abstraction for a category of one.

### Explicitly not using `_dp_generated` / `_dp_generation_batch`

Brand Fixtures terms are **not** marked with the existing `GENERATED_KEY`/
`BATCH_KEY` constants used by the synthetic `[DEV]`-prefixed data. `reset-catalog`
deletes every term carrying `_dp_generated = 1`; several Brand Fixtures brands
(Chilly's, Djeco, Janod) have real, non-`[DEV]` products currently assigned to them
in the local catalog. Tagging them as `_dp_generated` would put real catalog data
one `wp dp-b2b reset-catalog` away from deletion. Brand Fixtures data is therefore
entirely outside the synthetic-data reset lifecycle — `reset-catalog` is not
modified and does not touch, count, or report on Brand Fixtures terms.

### Reporting

New `run_brand_fixtures()` (no `Batch:` line — batch IDs are part of the
`_dp_generation_batch` reset-tracking lifecycle, which this phase deliberately does
not participate in). Illustrative format only — actual counts depend on how many of
the 21 already exist at run time (a genuinely fresh environment: 21 created, 0
skipped, 34 assets created, 0 reused; a re-run: 0 created, 21 skipped, 0 assets
touched):

```
Brand Fixtures:
  +     24Bottles (id=218)  logo+image+segment=lifestyle
  +     Chilly's (id=16)  logo
  skip  Djeco
  ...

Brand Fixtures done — brands: 18 created, 3 skipped | assets: 34 created, 4 reused.
```

Summary line reports brand created/skipped counts and asset created/reused counts,
consistent with the two-metric summary style already used by `run_products()` /
`run_variables()`.

### Out of scope

- No changes to `taxonomies`, `products`, `variables`, `ugly`, `--refresh-metadata`,
  or `reset-catalog`.
- No new WP-CLI command group, no new file for the command itself (only the new
  `dev-fixtures/` asset directory).
- No production-facing functionality.

## Files touched

- `inc/dev/class-dev-catalog-generator.php` — new `brand-fixtures` phase option,
  `run_brand_fixtures()`, brand data array, term-creation method, fixture-attachment
  helper/dedup method, attachment-lookup method.
- `dev-fixtures/brands/<slug>/logo.<ext>` / `brand-image.<ext>` — new, git-committed
  binary fixture assets (~34 files, copied from current `wp-content/uploads/`
  content).
- `docs/historical/synthetic-b2b-catalog.md` — new "Phase 4 — Brand Fixtures"
  section (brand table, asset layout, idempotency note, explicit "these assets are
  permanent, committed fixture data" note), updated WP-CLI Usage block, Data
  Markers section, File Locations section.

## Validation plan

Localhost only, WP-CLI only, no Playwright:

1. `wp dp-b2b generate-catalog --phase=brand-fixtures` on the current local DB
   (which already has all 21 terms) — confirm all 21 report `skip`, 0 created, 0
   assets touched. This is the closest available proxy for "fresh environment"
   behavior on the create path, validated indirectly: the per-brand creation logic
   is exercised by temporarily deleting one non-critical fixture-scope term (e.g.
   via `wp term delete product_brand <id>` on a brand with no real products
   attached) and re-running the phase to confirm it recreates that single brand
   correctly (term, description, segment, both/one image as applicable, no
   duplicate attachment created), then confirming a second immediate re-run
   reports `skip` for it.
2. `wp eval` spot checks after the single-brand recreate: term description matches
   source data exactly, `thumbnail_id` term meta resolves to a valid attachment,
   ACF `brand_image` resolves to a valid attachment, `brand_segment` matches (or is
   correctly absent for segment-less brands).
3. Confirm attachment dedup: re-running `--phase=brand-fixtures` a second time
   after the single-brand recreate does not create a second attachment for that
   brand's images (`_dp_brand_fixture_source` meta query returns exactly one match
   per relative path).
4. Confirm `reset-catalog` (full, no `--batch`) does not delete any Brand Fixtures
   term — run it in a disposable/verifiable way or reason from code review, since a
   full reset-catalog run against real brand data with real product associations
   must not be executed carelessly on this shared local DB.

Report to the user: new phase name, WP-CLI usage, created/skipped counts from the
recreate-and-reskip validation, confirmation of idempotency, and the list of
updated documentation files.

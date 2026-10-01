# Dev Fixtures

Canonical development fixture data consumed by the Dev Catalog Generator
(`inc/dev/class-dev-catalog-generator.php`), never used by any production code path.

## `brands/`

Logo and brand-image assets for the 18-brand canonical development `product_brand`
dataset (Brand Fixtures phase — `wp dp-b2b generate-catalog --phase=brand-fixtures`).
See `docs/superpowers/specs/2026-08-04-brand-fixtures-design.md` and the "Phase 4 —
Brand Fixtures" section of `docs/historical/synthetic-b2b-catalog.md` for the full
dataset and rationale.

## Environment guard

The generator is WP-CLI-only and fail-closed: it runs only when `wp_get_environment_type()` is `local`, `development` or `staging`; `production` (including unset/invalid values) is blocked. Both the local installation and staging currently resolve `wp_get_environment_type() === 'production'` unless an environment is explicitly configured, so the generator is intentionally blocked there by default. Intentional fixture work must explicitly configure an allowed WordPress environment (`local`, `development` or `staging`) through the canonical WordPress mechanism (`WP_ENVIRONMENT_TYPE` constant or environment variable).

The 2026-10-01 fixture cleanup removed `chillys`, `flow-amsterdam` and `go-baby-go` (stale: the canonical ERP-backed Chilly's is `chillys-2`; the other two were deliberately deleted from staging) together with their asset directories.

## Rules

- These files are **intentionally committed to git** — permanent fixture data, not
  temporary uploads, scratch files, or build output. Never gitignore this directory.
- They exist so a fresh localhost/staging environment can recreate the canonical
  development dataset **fully offline and deterministically** — no network access,
  no dependency on production or corporate-site availability.
- Additions, removals, or replacements here must be intentional project changes
  (i.e. reviewed alongside the corresponding generator code change), never ad-hoc
  manual edits made outside that workflow.

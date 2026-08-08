# Dev Fixtures

Canonical development fixture data consumed by the Dev Catalog Generator
(`inc/dev/class-dev-catalog-generator.php`), never used by any production code path.

## `brands/`

Logo and brand-image assets for the 21-brand canonical development `product_brand`
dataset (Brand Fixtures phase — `wp dp-b2b generate-catalog --phase=brand-fixtures`).
See `docs/superpowers/specs/2026-08-04-brand-fixtures-design.md` and the "Phase 4 —
Brand Fixtures" section of `docs/historical/synthetic-b2b-catalog.md` for the full
dataset and rationale.

## Rules

- These files are **intentionally committed to git** — permanent fixture data, not
  temporary uploads, scratch files, or build output. Never gitignore this directory.
- They exist so a fresh localhost/staging environment can recreate the canonical
  development dataset **fully offline and deterministically** — no network access,
  no dependency on production or corporate-site availability.
- Additions, removals, or replacements here must be intentional project changes
  (i.e. reviewed alongside the corresponding generator code change), never ad-hoc
  manual edits made outside that workflow.

# Dev Catalog Metadata Refresh — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Execution note for this session:** task is single-file + docs, well under the subagent-dispatch threshold (global CLAUDE.md subagent budget rule) — executed inline in the current session instead of dispatching subagents.

**Goal:** Let the synthetic DEV catalog generator refresh deterministic `total_sales` and publish-date tiers on existing DEV-#### simple products, without creating duplicates or touching manually-adjusted fields.

**Architecture:** New opt-in `--refresh-metadata` flag on the existing `generate-catalog` WP-CLI command, checked before the `--phase` switch. Reuses the existing `deterministic_total_sales()` / `deterministic_publish_offset_days()` seed logic unchanged. A new SQL lookup scopes the refresh to `_dp_generated=1` products whose SKU matches the `DEV-####` (4-digit, simple-product) pattern only — this excludes `DEV-VAR-*` and `DEV-UGLY-*` SKUs by length, not by string matching, so no separate exclusion list is needed.

**Tech Stack:** PHP 8.3, WP-CLI, WooCommerce `WC_Product` API, direct `$wpdb` for the read-only lookup query (consistent with the file's existing pattern for generated-term/product lookups).

## Global Constraints

- Do not change default `generate-catalog` behavior — `--refresh-metadata` is strictly opt-in and does not alter the existing `--phase` dispatch when absent.
- Never use `--count=250` or any other workaround in place of this feature.
- Refresh only `total_sales` and `date_created` (post_date) — no other product field may be written.
- Never touch `DEV-VAR-*` or `DEV-UGLY-*` products, categories, brands, variations, users, orders, or terms.
- Preserve the existing `current_time('timestamp') + gmdate()` site-local date semantics and its explanatory comment — do not simplify or replace it.
- No new products may be created by refresh mode; product count must be identical before and after.
- Running refresh twice must produce identical `total_sales` per SKU and dates relative to each run's execution time (idempotent, not static).
- No PHPUnit suite exists in this repo — validation is `php -l` + manual local WP-CLI execution against the existing DEV catalog, per the project's WP-CLI-native (never raw MySQL) debugging convention.

---

### Task 1: Add `--refresh-metadata` / `--dry-run` flags and refresh logic

**Files:**
- Modify: `inc/dev/class-dev-catalog-generator.php`

**Interfaces:**
- Consumes: `self::GENERATED_KEY` (`'_dp_generated'`), `$this->deterministic_total_sales(int $seed): int`, `$this->deterministic_publish_offset_days(int $seed): int` — all already defined in this file, unchanged signatures.
- Produces: `private function run_refresh_metadata( array $assoc_args ): void`, `private function get_refreshable_product_ids(): array` (`int[]`), `private function count_generated_products(): int` — used only within this file.

- [ ] **Step 1: Add the CLI doc block entries and dispatch branch**

Modify the `generate_catalog()` docblock (top of file, inside the existing `## OPTIONS` block) — insert after the `[--count=<count>]` entry and before `## EXAMPLES`:

```php
	 * [--refresh-metadata]
	 * : Refresh deterministic total_sales and publish-date tiers on existing
	 *   DEV-#### simple products (SKU pattern DEV-0001..DEV-9999). Does not
	 *   create products. Ignores --phase when set — this is a standalone mode.
	 *
	 * [--dry-run]
	 * : With --refresh-metadata, report what would change without saving anything.
```

Add two new example lines to the existing `## EXAMPLES` block, after the `--phase=variables --count=10` line:

```php
	 *     wp dp-b2b generate-catalog --refresh-metadata
	 *     wp dp-b2b generate-catalog --refresh-metadata --dry-run
```

Modify `generate_catalog()` itself — insert the refresh branch immediately after `$this->guard_production();` and before `$phase = $assoc_args['phase'] ?? 'taxonomies';`:

```php
	public function generate_catalog( array $args, array $assoc_args ): void {
		$this->guard_production();

		if ( isset( $assoc_args['refresh-metadata'] ) ) {
			$this->run_refresh_metadata( $assoc_args );
			return;
		}

		$phase = $assoc_args['phase'] ?? 'taxonomies';
```

- [ ] **Step 2: Run `php -l` to confirm the docblock/dispatch edit is syntactically valid**

Run: `"C:\xampp2\php83\php.exe" -l "C:\xampp2\htdocs\dp-b2b\wp-content\themes\dreampoint-b2b\inc\dev\class-dev-catalog-generator.php"`
Expected: `No syntax errors detected`

- [ ] **Step 3: Add `run_refresh_metadata()` and its two helper lookups**

Add a new section right after the existing `// Phase: products` section closes (after `generate_simple_products()`, before `// Deterministic randomness` section), so refresh logic sits next to the product-generation logic it reuses seeding from:

```php
	// -------------------------------------------------------------------------
	// Phase-independent: metadata refresh
	// -------------------------------------------------------------------------

	/**
	 * Refreshes total_sales and date_created on existing DEV-#### simple
	 * products using the same deterministic seed logic as generation.
	 * Never creates products; never touches non-DEV-#### SKUs (DEV-VAR-*,
	 * DEV-UGLY-*) or any field other than total_sales/date_created.
	 */
	private function run_refresh_metadata( array $assoc_args ): void {
		$dry_run = isset( $assoc_args['dry-run'] );

		WP_CLI::log( $dry_run
			? 'Refreshing metadata (dry run — no changes will be saved)...'
			: 'Refreshing metadata on existing DEV catalog products...'
		);
		WP_CLI::log( '' );

		$product_ids     = $this->get_refreshable_product_ids();
		$generated_total = $this->count_generated_products();
		$qualifying      = count( $product_ids );
		$skipped         = $generated_total - $qualifying;

		WP_CLI::log( sprintf( 'Qualifying products (DEV-#### simple, _dp_generated=1): %d', $qualifying ) );
		WP_CLI::log( sprintf( 'Skipped (generated but not DEV-#### simple products, e.g. DEV-VAR-*/DEV-UGLY-*): %d', $skipped ) );
		WP_CLI::log( 'Fields refreshed: total_sales, date_created (post_date)' );
		WP_CLI::log( '' );

		if ( empty( $product_ids ) ) {
			WP_CLI::warning( 'No qualifying products found. Run --phase=products first.' );
			return;
		}

		$updated = 0;
		$failed  = 0;

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product instanceof WC_Product ) {
				$failed++;
				continue;
			}

			$sku  = $product->get_sku();
			$seed = abs( crc32( $sku ) );

			$total_sales  = $this->deterministic_total_sales( $seed );
			$publish_days = $this->deterministic_publish_offset_days( $seed );
			// current_time('timestamp') + gmdate(): deliberate — see the same
			// note in generate_simple_products(). Do not change without
			// re-verifying WC_Data::set_date_prop() site-local semantics.
			$publish_date = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $publish_days * DAY_IN_SECONDS ) );

			if ( $dry_run ) {
				WP_CLI::log( sprintf(
					'  would-update  %s  total_sales=%d  date_created=%s',
					$sku, $total_sales, $publish_date
				) );
				$updated++;
				continue;
			}

			$product->set_total_sales( $total_sales );
			$product->set_date_created( $publish_date );
			$product->save();

			WP_CLI::log( sprintf(
				'  updated       %s  total_sales=%d  date_created=%s',
				$sku, $total_sales, $publish_date
			) );
			$updated++;
		}

		WP_CLI::log( '' );

		if ( $dry_run ) {
			WP_CLI::success( sprintf( 'Dry run done — %d product(s) would be updated, %d failed to load.', $updated, $failed ) );
			return;
		}

		WP_CLI::success( sprintf( 'Refresh done — %d product(s) updated, %d failed to load.', $updated, $failed ) );
	}

	/**
	 * @return int[] Product IDs: post_type=product, _dp_generated=1, SKU
	 *               matches DEV-#### exactly (8 chars total — excludes
	 *               DEV-VAR-* and DEV-UGLY-* by length, not by name matching).
	 */
	private function get_refreshable_product_ids(): array {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm_gen ON p.ID = pm_gen.post_id
			 INNER JOIN {$wpdb->postmeta} pm_sku ON p.ID = pm_sku.post_id
			 WHERE p.post_type = 'product'
			   AND pm_gen.meta_key = %s AND pm_gen.meta_value = %s
			   AND pm_sku.meta_key = '_sku' AND pm_sku.meta_value LIKE 'DEV-____'
			 ORDER BY p.ID",
			self::GENERATED_KEY,
			'1'
		) );
		return array_map( 'intval', $ids ?: [] );
	}

	/** @return int Count of _dp_generated=1 posts of type 'product' (any SKU pattern). */
	private function count_generated_products(): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			 WHERE p.post_type = 'product'
			   AND pm.meta_key = %s AND pm.meta_value = %s",
			self::GENERATED_KEY,
			'1'
		) );
	}
```

- [ ] **Step 4: Run `php -l` again to confirm the full file is valid**

Run: `"C:\xampp2\php83\php.exe" -l "C:\xampp2\htdocs\dp-b2b\wp-content\themes\dreampoint-b2b\inc\dev\class-dev-catalog-generator.php"`
Expected: `No syntax errors detected`

- [ ] **Step 5: Dry-run against the local DEV catalog to confirm wiring**

Run (from theme root, adjust wp-cli.phar path if different):
`& "C:\xampp2\php83\php.exe" "C:\xampp2\htdocs\dp-b2b\wp-cli.phar" dp-b2b generate-catalog --refresh-metadata --dry-run --path="C:\xampp2\htdocs\dp-b2b"`
Expected: summary lines showing qualifying/skipped counts and a `would-update` line per DEV-#### product, `Dry run done` success line, and **no** product count change (verify separately per Task 3).

- [ ] **Step 6: Commit**

Do not commit yet — Task 3 covers full local validation before the single focused commit, per the spec's Git section (create commit only after successful validation).

---

### Task 2: Update synthetic catalog documentation

**Files:**
- Modify: `docs\historical\synthetic-b2b-catalog.md`

**Interfaces:**
- Consumes: nothing (documentation only).
- Produces: nothing consumed by other tasks.

- [ ] **Step 1: Add a "Metadata Refresh" section**

Insert a new section after the existing `## Idempotency` section (before `## Cleanup / Reset`) in `docs\historical\synthetic-b2b-catalog.md`:

```markdown
---

## Metadata Refresh

Products created by an older generator run do not retroactively receive
newly-added deterministic metadata (e.g. `total_sales` tiers, publish-date
tiers) — the default `--phase=products` run skips any SKU that already
exists. `--refresh-metadata` closes that idempotency gap without creating
new products or touching anything else.

```bash
# Report + apply
wp dp-b2b generate-catalog --refresh-metadata

# Report only, no writes
wp dp-b2b generate-catalog --refresh-metadata --dry-run
```

**Scope:** only products with `_dp_generated=1` whose SKU matches the
Phase 2 `DEV-####` pattern (e.g. `DEV-0001`) — this excludes `DEV-VAR-*`
and `DEV-UGLY-*` products by SKU length, not by name matching. `--phase`
is ignored when `--refresh-metadata` is set — it is a standalone mode, not
a fifth phase value.

**Fields refreshed (only these two):**

| Field | Recomputed from |
|-------|------------------|
| `total_sales` | `deterministic_total_sales(seed)` — same tiers as generation |
| `date_created` (post_date) | `deterministic_publish_offset_days(seed)` relative to **refresh execution time**, not original generation time |

**Fields deliberately preserved** (never written by refresh): name,
description, images, stock (manual or generated), price (manual or
generated), categories, brands, attributes, variations, visibility, and
any other meta not listed above. This is what makes refresh safe to run
against a catalog with manually-prepared QA scenarios.

**Why refresh is time-relative:** the New-product filter compares
`date_created` against "now" at query time. A catalog generated weeks ago
drifts out of the New window even though the fixture's *intent* (some
products clearly new, some clearly not) hasn't changed. Refresh
re-anchors the same deterministic tier logic to the current moment so the
New-filter fixtures stay meaningful without a full `reset-catalog` +
regenerate cycle.

**Idempotent:** running refresh twice in a row reproduces the same
`total_sales` per SKU (seed is derived from the SKU, not from time) and
recomputes `date_created` relative to whichever run executed last —
running it repeatedly never creates, duplicates, or deletes anything.
```

- [ ] **Step 2: Add a persistence/reset-safety note**

Insert immediately above the existing `## Testing Purpose` section (i.e. right after the `## Cleanup / Reset` section) in the same file:

```markdown
---

## Staging Persistence

The staging synthetic catalog is persistent QA fixture data, not
disposable scratch data. `reset-catalog` must not be run against staging
without explicit instruction — it deletes generated products, variations,
and (in non-batch mode) categories and brands. Use `--refresh-metadata`
to bring time-relative fields up to date instead of resetting and
regenerating.
```

- [ ] **Step 3: Commit**

Covered by Task 3's single combined commit — do not commit separately here.

---

### Task 3: Local validation and commit

**Files:** none created — this task runs verification commands and produces the git commit covering Tasks 1–2.

**Interfaces:**
- Consumes: the WP-CLI command from Task 1, the local WordPress/WooCommerce install already running under XAMPP.
- Produces: nothing consumed by later tasks — this is the terminal task.

- [ ] **Step 1: Record before-state**

Run (adjust path if `wp-cli.phar` lives elsewhere in this project):
`& "C:\xampp2\php83\php.exe" "C:\xampp2\htdocs\dp-b2b\wp-cli.phar" post list --post_type=product --meta_key=_dp_generated --meta_value=1 --format=count --path="C:\xampp2\htdocs\dp-b2b"`
Record the returned count as `BEFORE_COUNT`.

Pick one DEV-#### SKU from the existing catalog (e.g. `DEV-0001`) and record its current `total_sales` and `post_date`:
`& "C:\xampp2\php83\php.exe" "C:\xampp2\htdocs\dp-b2b\wp-cli.phar" eval 'PRODUCT_ID=wc_get_product_id_by_sku("DEV-0001"); print(json_encode(["total_sales"=>get_post_meta(PRODUCT_ID,"total_sales",true),"post_date"=>get_post($PRODUCT_ID)->post_date]));' --path="C:\xampp2\htdocs\dp-b2b"`
(Use a heredoc if inline quoting misbehaves — see `~/.claude/CLAUDE.md` WP-CLI note on avoiding complex inline `wp eval` quoting.)

- [ ] **Step 2: Manually alter a non-refreshed field on one DEV product**

Pick a different DEV-#### product (e.g. `DEV-0002`) and manually change its stock quantity via WP-CLI:
`& "C:\xampp2\php83\php.exe" "C:\xampp2\htdocs\dp-b2b\wp-cli.phar" wc product update <PRODUCT_ID> --stock_quantity=777 --user=1 --path="C:\xampp2\htdocs\dp-b2b"`
(If `wp wc` is unavailable, use `wp eval` with `wc_get_product()->set_stock_quantity(777)->save()` via heredoc instead — do not guess an unavailable command silently, verify first.)
Record `PRODUCT_ID` and the value `777` for the Step 5 preservation check.

- [ ] **Step 3: Run the refresh (not dry-run)**

`& "C:\xampp2\php83\php.exe" "C:\xampp2\htdocs\dp-b2b\wp-cli.phar" dp-b2b generate-catalog --refresh-metadata --path="C:\xampp2\htdocs\dp-b2b"`
Expected: summary + one `updated` line per qualifying DEV-#### product, `Refresh done` success line.

- [ ] **Step 4: Confirm product count unchanged and no new SKU created**

Re-run the Step 1 count command — expected: identical to `BEFORE_COUNT`.
`& "C:\xampp2\php83\php.exe" "C:\xampp2\htdocs\dp-b2b\wp-cli.phar" post list --post_type=product --meta_key=_dp_generated --meta_value=1 --format=count --path="C:\xampp2\htdocs\dp-b2b"`

- [ ] **Step 5: Confirm `total_sales`/date changed, manually-altered stock preserved**

Re-run the Step 1 eval for `DEV-0001` — expected: `total_sales` and `post_date` values present and (for `post_date`) reflecting an offset from the Step 3 execution time, not the original generation time.

Check the Step 2 product's stock quantity is still `777`:
`& "C:\xampp2\php83\php.exe" "C:\xampp2\htdocs\dp-b2b\wp-cli.phar" eval 'echo get_post_meta(<PRODUCT_ID>, "_stock", true);' --path="C:\xampp2\htdocs\dp-b2b"`
Expected: `777` — unchanged.

- [ ] **Step 6: Run the refresh a second time and confirm idempotency**

`& "C:\xampp2\php83\php.exe" "C:\xampp2\htdocs\dp-b2b\wp-cli.phar" dp-b2b generate-catalog --refresh-metadata --path="C:\xampp2\htdocs\dp-b2b"`
Re-check `DEV-0001`'s `total_sales` — expected: identical value to Step 5 (seed is SKU-derived, not time-derived). `post_date` is expected to shift slightly (re-anchored to this run's execution time) — this is correct per the time-relative design, not a bug.
Re-run the Step 4 count command — expected: still identical to `BEFORE_COUNT`.

- [ ] **Step 7: Verify New and Best Seller filters against the refreshed dataset**

Using the project's existing Playwright-based verification approach (per `~/.claude/rules/playwright.md` and this project's `CLAUDE.md` "use browser / Playwright only" rule): load the Quick Order page, apply the New filter and the Best Seller filter, and confirm both return non-empty, plausible result sets against the refreshed `DEV-####` products.

- [ ] **Step 8: Run PHP syntax check on the final file state**

`"C:\xampp2\php83\php.exe" -l "C:\xampp2\htdocs\dp-b2b\wp-content\themes\dreampoint-b2b\inc\dev\class-dev-catalog-generator.php"`
Expected: `No syntax errors detected`

- [ ] **Step 9: Confirm no unrelated files changed**

`git status`
Expected: only `inc/dev/class-dev-catalog-generator.php` and `docs/historical/synthetic-b2b-catalog.md` modified, plus this plan file as a new untracked file. No other files touched.

- [ ] **Step 10: Commit**

```bash
git add "wp-content/themes/dreampoint-b2b/inc/dev/class-dev-catalog-generator.php" "wp-content/themes/dreampoint-b2b/docs/historical/synthetic-b2b-catalog.md"
git commit -m "feat(dev-tools): add --refresh-metadata mode to synthetic catalog generator

Older-generator DEV products never receive newly-added deterministic
metadata because SKU-skip idempotency treats an existing SKU as fully
up to date. --refresh-metadata closes that gap by recomputing
total_sales and date_created on existing DEV-#### simple products only,
using the same seed logic as generation, without creating products or
touching any other field."
```

(Do not `git add` the plan file in the same commit unless the user asks — it lives under `docs/superpowers/plans/` as working documentation, consistent with the other untracked plan/spec files already present in `git status`.)

Report the resulting commit hash. Do not push or deploy.

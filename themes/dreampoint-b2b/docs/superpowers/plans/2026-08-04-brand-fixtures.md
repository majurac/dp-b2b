# Brand Fixtures Phase Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a new `--phase=brand-fixtures` to the existing Dev Catalog Generator (`inc/dev/class-dev-catalog-generator.php`) that recreates the canonical 21-brand development `product_brand` dataset (real names, descriptions, `brand_segment`, logo, `brand_image`) on any fresh localhost/staging environment, fully idempotently, from git-committed fixture assets.

**Architecture:** One new phase, wired into the existing `generate_catalog()` switch, following the file's established patterns exactly: a hardcoded PHP data array (like `generate_categories()`/`generate_brands()`), slug-based existence checks via direct `$wpdb` SQL (like `get_generated_brand_ids()`, to bypass the `get_terms` visibility filter — see Global Constraints), and the standard native WordPress media-upload recipe (`wp_upload_bits()` → `wp_insert_attachment()` → `wp_generate_attachment_metadata()`) driven from local fixture files instead of a remote URL. Brand Fixtures data is deliberately kept outside the `_dp_generated`/`reset-catalog` lifecycle.

**Tech Stack:** PHP 8.3, WP-CLI (`WP_CLI_Command`), WordPress core Terms/Media APIs, ACF `update_field()`, no PHPUnit in this repo (verification is WP-CLI-driven, matching every other phase in this file and the approved spec's "WP-CLI only" validation constraint).

## Global Constraints

- This is `--phase=brand-fixtures` on the existing `wp dp-b2b generate-catalog` command — no new command, no new file for the command itself, no new WP-CLI command group.
- Reuse `guard_production()` (already called at the top of `generate_catalog()`, covers this phase automatically — no per-phase guard needed).
- Fully idempotent: existence check is by `product_brand` **slug**; if a fixture's slug already exists, skip that entire brand (term + assets + fields) — no partial repair, no overwrite.
- Attachment dedup is by a `_dp_brand_fixture_source` postmeta marker holding the **relative path inside `dev-fixtures/`** (e.g. `brands/24bottles/logo.webp`), not by filename or attachment title — portable across environments, immune to `wp_unique_filename()` renaming.
- Existence checks against `product_brand` terms MUST use direct `$wpdb` SQL, never `get_term_by()`/`get_terms()`. Reason: `inc/visibility/class-query-filter.php` hooks `get_terms` and filters `product_brand` results to empty for any WP-CLI context without `manage_options` (i.e. anonymous/no `--user=admin`) — already documented in this exact class (`get_generated_child_cat_ids()`, `get_generated_brand_ids()`) and in project memory (`project_brand_architecture`). Relying on `get_term_by()` here would make the phase attempt to recreate every already-existing brand and fail on WordPress's own internal `term_exists()` duplicate check. This plan follows the file's own established defensive pattern.
- Brand Fixtures terms are **not** tagged with `_dp_generated` / `_dp_generation_batch`. Several fixture brands (Chilly's, Djeco, Janod) have real, non-`[DEV]` products currently assigned in the local catalog — tagging them would put real catalog data one `wp dp-b2b reset-catalog` away from deletion. `reset-catalog` is not modified.
- Faithfully reproduce intentionally incomplete data: `gaston-luga` has no logo, `chillys`/`djeco`/`janod` have no `brand_image`, five brands have no `brand_segment`. Never substitute, duplicate, or invent a value for a missing field.
- `dev-fixtures/brands/**` files are permanent, git-committed fixture data — not build output, not scratch files, never gitignored.
- No network access, no external URLs, no dependency on production/corporate-site availability. Must work fully offline.
- No changes to `taxonomies`, `products`, `variables`, `ugly`, `--refresh-metadata`, or `reset-catalog` phases/logic.
- Validation is WP-CLI only — no Playwright, no browser testing, no manual ACF operations, no database resets (`wp db reset` etc. — never run).
- Do not delete or modify any real brand term with real product associations (`chillys`, `djeco`, `janod` — all have `count > 0` in the current local DB). Any live delete/recreate validation step must target a brand with `count = 0`.
- Commit messages: `feat:` for functional code, `chore:` for fixture data, `docs:` for documentation-only changes — per `~/.claude/rules/git.md`.

---

### Task 1: Bundle the canonical fixture assets under `dev-fixtures/brands/`

**Files:**
- Create: `dev-fixtures/brands/<slug>/logo.<ext>` and `dev-fixtures/brands/<slug>/brand-image.<ext>` for the 21 brands below (38 files total — 20 logos, gaston-luga has none; 18 brand-images, chillys/djeco/janod have none).

**Interfaces:**
- Produces: a fixed, verifiable file tree under `dev-fixtures/brands/` that Task 2/3's PHP code reads by exact relative path (`brands/<slug>/logo.<ext>` / `brands/<slug>/brand-image.<ext>`, relative to the theme's `dev-fixtures/` directory).

Source files currently exist on disk in this environment's `wp-content/uploads/`. Copy (never move) each one into its normalized fixture slot, preserving the original extension.

- [ ] **Step 1: Create the directory tree and copy every asset**

Run via PowerShell (Windows filesystem mutation — per this project's shell discipline, PowerShell owns `C:\...` path operations):

```powershell
$root = "C:\xampp2\htdocs\dp-b2b"
$uploads = Join-Path $root "wp-content\uploads"
$fixtures = Join-Path $root "wp-content\themes\dreampoint-b2b\dev-fixtures\brands"

$brands = @(
    @{ slug='24bottles';           logo='2026\07\24bottles.webp';                                             image='2026\07\1-1.webp' }
    @{ slug='a-fan-of';            logo='2026\07\a-fan-of.webp';                                               image='2026\07\13.webp' }
    @{ slug='chillys';             logo='2026\04\chillys.jpg';                                                 image=$null }
    @{ slug='design-letters-aps';  logo='2026\07\DESIGN-LETTERS-logo-png.webp';                                image='2026\07\8-1.webp' }
    @{ slug='djeco';               logo='2026\04\How-to-Clean-and-Disinfect-Childrens-Toys-1024x575-1.jpg';    image=$null }
    @{ slug='dock-bay';            logo='2026\07\DockBay.webp';                                                image='2026\07\13-1.webp' }
    @{ slug='eat-my-socks';        logo='2026\07\eat-my-socks.webp';                                           image='2026\07\3.webp' }
    @{ slug='flow-amsterdam';      logo='2026\08\Flow-Amsterdam.png';                                          image='2026\08\Segmenti-naslovna-13.png' }
    @{ slug='fresk';               logo='2026\08\fresk.png';                                                   image='2026\08\Segmenti-naslovna-12.png' }
    @{ slug='gaston-luga';         logo=$null;                                                                 image='2026\07\17.webp' }
    @{ slug='go-baby-go';          logo='2026\08\Go-baby-go.png';                                              image='2026\08\Segmenti-naslovna-14.png' }
    @{ slug='izipizi';             logo='2026\07\IZIPIZI_2024_1.webp';                                         image='2026\07\Izipizi-suncane-naocale-.webp' }
    @{ slug='janod';               logo='2026\07\untitled-design-72-_6814700eddfdb_705x550c.webp';             image=$null }
    @{ slug='la-coque-francaise';  logo='2026\07\La-Coque-Francaise.webp';                                     image='2026\07\25-1.webp' }
    @{ slug='leatherman';          logo='2026\08\Leatherman.png';                                              image='2026\08\Segmenti-naslovna-6.png' }
    @{ slug='ledlenser';           logo='2026\08\Ledlenser.png';                                               image='2026\08\Segmenti-naslovna-5.png' }
    @{ slug='leuchtturm1917';      logo='2026\07\leuchtturm1917.webp';                                         image='2026\07\31.webp' }
    @{ slug='notabag';             logo='2026\07\Notabag.webp';                                                image='2026\07\34-1.webp' }
    @{ slug='nuuna';               logo='2026\07\Nuuna.webp';                                                  image='2026\07\39.webp' }
    @{ slug='printworks';          logo='2026\07\2025_Printworks.webp';                                        image='2026\07\11.webp' }
    @{ slug='secrid';              logo='2026\07\Secrid.webp';                                                 image='2026\07\44.webp' }
)

foreach ($b in $brands) {
    $destDir = Join-Path $fixtures $b.slug
    New-Item -ItemType Directory -Force -Path $destDir | Out-Null

    if ($b.logo) {
        $src = Join-Path $uploads $b.logo
        $ext = [System.IO.Path]::GetExtension($b.logo)
        Copy-Item -Path $src -Destination (Join-Path $destDir "logo$ext") -Force
    }
    if ($b.image) {
        $src = Join-Path $uploads $b.image
        $ext = [System.IO.Path]::GetExtension($b.image)
        Copy-Item -Path $src -Destination (Join-Path $destDir "brand-image$ext") -Force
    }
}

Write-Output "Done. File count:"
(Get-ChildItem -Path $fixtures -Recurse -File).Count
```

Expected output: `34`.

- [ ] **Step 2: Verify the tree matches the spec exactly**

Run:

```powershell
Get-ChildItem -Path "C:\xampp2\htdocs\dp-b2b\wp-content\themes\dreampoint-b2b\dev-fixtures\brands" -Recurse -File | Select-Object -ExpandProperty FullName
```

Confirm: 21 subdirectories, 34 files total, `gaston-luga` has only `brand-image.webp` (no `logo.*`), `chillys`/`djeco`/`janod` each have only `logo.*` (no `brand-image.*`), all other 17 brands have exactly one `logo.*` and one `brand-image.*`.

- [ ] **Step 3: Commit**

```bash
git add dev-fixtures/
git commit -m "$(cat <<'EOF'
chore: add canonical Brand Fixtures dataset assets

38 logo/brand-image files for the 21-brand canonical development
dataset, copied from current local uploads. Permanent, git-committed
fixture data consumed by the new Dev Catalog Generator brand-fixtures
phase (added in a following commit) — not build output, not scratch.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Fixture attachment helper — dedup-safe native media upload

**Files:**
- Modify: `inc/dev/class-dev-catalog-generator.php` — add two new private methods after the existing `// Phase: ugly` section (after `ugly_cartesian()`, i.e. immediately before the class's closing `}`).

**Interfaces:**
- Produces:
  - `find_attachment_by_fixture_source( string $relative_path ): int` — returns attachment ID or `0`.
  - `ensure_fixture_attachment( string $relative_path ): array` — returns `[ int $attachment_id, bool $reused ]`; `$attachment_id` is `0` on failure (missing file or upload error).
- Consumes: nothing from other tasks.

- [ ] **Step 1: Write the verification script and confirm it fails**

Run (this calls a method that does not exist yet — expected to fail):

```bash
"/c/xampp2/php83/php.exe" "/c/xampp2/htdocs/dp-b2b/wp-cli.phar" --path="/c/xampp2/htdocs/dp-b2b" eval '
$gen = new Dreampoint_B2B_Dev_Catalog_Generator();
$m = new ReflectionMethod($gen, "ensure_fixture_attachment");
$m->setAccessible(true);
[$id1, $reused1] = $m->invoke($gen, "brands/24bottles/logo.webp");
echo "id1={$id1} reused1=" . ($reused1 ? "yes" : "no") . "\n";
' --user=admin 2>&1
```

Expected: PHP Fatal error / `ReflectionException` — "Method Dreampoint_B2B_Dev_Catalog_Generator::ensure_fixture_attachment() does not exist" (or similar). This is the RED step.

- [ ] **Step 2: Implement the two methods**

Insert immediately before the class's final closing `}` (currently line 1425 of `inc/dev/class-dev-catalog-generator.php`), after the `// Phase: ugly` section's last method (`ugly_cartesian()`):

```php
	// -------------------------------------------------------------------------
	// Phase: brand-fixtures — fixture attachment helper
	// -------------------------------------------------------------------------

	/**
	 * Looks up a Media Library attachment previously created from a specific
	 * fixture file, by the relative-path marker set in
	 * ensure_fixture_attachment(). Not subject to the visibility engine's
	 * get_terms filter (that filter only touches product_brand term queries
	 * and product-post-type queries — see class-query-filter.php
	 * should_filter(), which checks $query->get('post_type') === 'product').
	 *
	 * @return int Attachment ID, or 0 if no matching attachment exists.
	 */
	private function find_attachment_by_fixture_source( string $relative_path ): int {
		$posts = get_posts( [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_dp_brand_fixture_source',
			'meta_value'     => $relative_path,
			'no_found_rows'  => true,
		] );

		return $posts ? (int) $posts[0] : 0;
	}

	/**
	 * Ensures a Media Library attachment exists for a given fixture file
	 * under dev-fixtures/, reusing a prior upload (matched by relative-path
	 * marker) instead of re-uploading. Uses the standard native WordPress
	 * media-upload recipe (wp_upload_bits -> wp_insert_attachment ->
	 * wp_generate_attachment_metadata) — the same one media_sideload_image()
	 * uses internally, driven from a local file's bytes instead of a
	 * downloaded remote URL, so this works fully offline.
	 *
	 * @return array{0: int, 1: bool} [attachment_id (0 on failure), reused]
	 */
	private function ensure_fixture_attachment( string $relative_path ): array {
		$existing_id = $this->find_attachment_by_fixture_source( $relative_path );

		if ( $existing_id ) {
			return [ $existing_id, true ];
		}

		$full_path = dirname( __DIR__, 2 ) . '/dev-fixtures/' . $relative_path;

		if ( ! file_exists( $full_path ) ) {
			WP_CLI::warning( "  FAIL  fixture asset missing on disk: {$relative_path}" );
			return [ 0, false ];
		}

		$filename = basename( $relative_path );
		$bits     = file_get_contents( $full_path );
		$upload   = wp_upload_bits( $filename, null, $bits );

		if ( ! empty( $upload['error'] ) ) {
			WP_CLI::warning( "  FAIL  upload failed for {$relative_path}: {$upload['error']}" );
			return [ 0, false ];
		}

		$filetype = wp_check_filetype( $upload['file'] );

		$attachment_id = wp_insert_attachment( [
			'post_mime_type' => $filetype['type'],
			'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_status'    => 'inherit',
		], $upload['file'] );

		if ( ! $attachment_id || is_wp_error( $attachment_id ) ) {
			WP_CLI::warning( "  FAIL  attachment insert failed for {$relative_path}" );
			return [ 0, false ];
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		update_post_meta( $attachment_id, '_dp_brand_fixture_source', $relative_path );

		return [ (int) $attachment_id, false ];
	}
```

- [ ] **Step 3: Lint the file**

```bash
"/c/xampp2/php83/php.exe" -l "/c/xampp2/htdocs/dp-b2b/wp-content/themes/dreampoint-b2b/inc/dev/class-dev-catalog-generator.php"
```

Expected: `No syntax errors detected`.

- [ ] **Step 4: Re-run the verification script — confirm it now passes, and confirm dedup on a second call**

```bash
"/c/xampp2/php83/php.exe" "/c/xampp2/htdocs/dp-b2b/wp-cli.phar" --path="/c/xampp2/htdocs/dp-b2b" eval '
$gen = new Dreampoint_B2B_Dev_Catalog_Generator();
$m = new ReflectionMethod($gen, "ensure_fixture_attachment");
$m->setAccessible(true);

[$id1, $reused1] = $m->invoke($gen, "brands/24bottles/logo.webp");
[$id2, $reused2] = $m->invoke($gen, "brands/24bottles/logo.webp");

echo "id1={$id1} reused1=" . ($reused1 ? "yes" : "no") . "\n";
echo "id2={$id2} reused2=" . ($reused2 ? "yes" : "no") . "\n";
echo "same_id=" . ($id1 === $id2 ? "yes" : "no") . "\n";

global $wpdb;
$count = $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
    "_dp_brand_fixture_source", "brands/24bottles/logo.webp"
) );
echo "attachment_meta_rows={$count}\n";
' --user=admin 2>&1
```

Expected: `id1` is a positive integer, `reused1=no` (first call creates), `reused2=yes` (second call reuses), `same_id=yes`, `attachment_meta_rows=1` (exactly one attachment tagged for this fixture path — no duplicate).

Note: this intentionally creates the real `24bottles` logo attachment in the local Media Library ahead of Task 3/4 (tagged `_dp_brand_fixture_source`). This is expected and harmless — it is the same attachment the full phase would create for that brand; Task 3's validation run will simply never reach it (the `24bottles` term already exists locally, so the term-level skip in Task 3 short-circuits before any asset step for that brand).

- [ ] **Step 5: Commit**

```bash
git add inc/dev/class-dev-catalog-generator.php
git commit -m "$(cat <<'EOF'
feat: add fixture attachment dedup helper to Dev Catalog Generator

find_attachment_by_fixture_source() + ensure_fixture_attachment() —
native WP media-upload recipe (wp_upload_bits -> wp_insert_attachment
-> wp_generate_attachment_metadata) driven from local dev-fixtures/
files, deduplicated by a _dp_brand_fixture_source relative-path
marker. Building block for the brand-fixtures phase (next commit).

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Brand fixture data, term creation, and phase wiring

**Files:**
- Modify: `inc/dev/class-dev-catalog-generator.php`
  - Docblock `## OPTIONS` block of `generate_catalog()` (~lines 44–52): add `brand-fixtures` to the `--phase=<phase>` options list.
  - `switch ( $phase )` in `generate_catalog()` (~lines 88–103): add `case 'brand-fixtures':`.
  - New methods appended after Task 2's methods, before the class's closing `}`.

**Interfaces:**
- Consumes: `ensure_fixture_attachment( string $relative_path ): array{0:int,1:bool}` (Task 2).
- Produces:
  - `get_brand_fixtures(): array` — the 21-record canonical dataset.
  - `find_brand_term_id_by_slug( string $slug ): int`.
  - `create_brand_fixture_term( array $fixture ): array{0:int,1:int,2:int}` — `[term_id (0 on failure), assets_created, assets_reused]`.
  - `generate_brand_fixtures(): array{created:int, skipped:int, assets_created:int, assets_reused:int}`.
  - `run_brand_fixtures(): void`.

- [ ] **Step 1: Write the failing verification command**

```bash
"/c/xampp2/php83/php.exe" "/c/xampp2/htdocs/dp-b2b/wp-cli.phar" --path="/c/xampp2/htdocs/dp-b2b" dp-b2b generate-catalog --phase=brand-fixtures --user=admin 2>&1
```

Expected: `Unknown phase: brand-fixtures` (from the existing `WP_CLI::error()` default branch) — this is the RED step, confirming the phase isn't wired yet.

- [ ] **Step 2: Add the docblock option and switch case**

In the `## OPTIONS` docblock (inside `generate_catalog()`), change:

```php
	 * ---
	 * default: taxonomies
	 * options:
	 *   - taxonomies
	 *   - products
	 *   - variables
	 *   - ugly
	 * ---
```

to:

```php
	 * ---
	 * default: taxonomies
	 * options:
	 *   - taxonomies
	 *   - products
	 *   - variables
	 *   - ugly
	 *   - brand-fixtures
	 * ---
```

Also add an example line near the existing `## EXAMPLES` block:

```php
	 *     wp dp-b2b generate-catalog --phase=brand-fixtures
```

In the `switch ( $phase )` block, change:

```php
			case 'ugly':
				$this->run_ugly();
				break;
			default:
				WP_CLI::error( "Unknown phase: {$phase}" );
```

to:

```php
			case 'ugly':
				$this->run_ugly();
				break;
			case 'brand-fixtures':
				$this->run_brand_fixtures();
				break;
			default:
				WP_CLI::error( "Unknown phase: {$phase}" );
```

- [ ] **Step 3: Add the brand fixture data array**

Append after Task 2's two methods (before the class's closing `}`):

```php
	// -------------------------------------------------------------------------
	// Phase: brand-fixtures
	// -------------------------------------------------------------------------

	/**
	 * Canonical Brand Fixtures dataset — 21 real (non-[DEV]) product_brand
	 * records reproducing the current Brands-page development dataset,
	 * including its intentionally incomplete state (missing logo/brand_image
	 * on some brands, missing brand_segment on others — see the approved
	 * design spec, docs/superpowers/specs/2026-08-04-brand-fixtures-design.md).
	 * This is a deliberate, fixed content decision, not a live mirror of
	 * local DB state — additions/removals here must be intentional edits.
	 *
	 * @return array<int, array{slug:string,name:string,description:string,segment:?string,logo:?string,image:?string}>
	 */
	private function get_brand_fixtures(): array {
		return [
			[
				'slug'        => '24bottles',
				'name'        => '24Bottles',
				'description' => '24Bottles je talijanski brend koji spaja moderan dizajn, održivost i funkcionalnost. Njihove boce, termosice i dodatci izrađeni su od visokokvalitetnih, dugotrajnih materijala i osmišljeni kako bi smanjili upotrebu plastike za jednokratnu upotrebu. Svaki proizvod kombinira estetiku i praktičnost, potičući ekološki osviješten način života uz dozu stila.',
				'segment'     => 'lifestyle',
				'logo'        => 'brands/24bottles/logo.webp',
				'image'       => 'brands/24bottles/brand-image.webp',
			],
			[
				'slug'        => 'a-fan-of',
				'name'        => 'A Fan Of',
				'description' => 'A FAN OF je brend iz Barcelone koji spaja tradiciju i suvremeni dizajn kroz elegantne ručno rađene lepeze. Svaka lepeza izrađena je u Španjolskoj od održivih materijala poput prirodnog drveta i 100% pamuka s eko-certificiranim bojama. Brend njeguje lokalnu proizvodnju, kvalitetu i odgovoran pristup, donoseći dašak mediteranskog šarma u svakodnevne trenutke.',
				'segment'     => 'lifestyle',
				'logo'        => 'brands/a-fan-of/logo.webp',
				'image'       => 'brands/a-fan-of/brand-image.webp',
			],
			[
				'slug'        => 'chillys',
				'name'        => 'Chilly\'s',
				'description' => '',
				'segment'     => null,
				'logo'        => 'brands/chillys/logo.jpg',
				'image'       => null,
			],
			[
				'slug'        => 'design-letters-aps',
				'name'        => 'Design Letters ApS',
				'description' => 'Design Letters je danski brend koji unosi skandinavsku estetiku u svakodnevne predmete kroz minimalistički dizajn i prepoznatljiva slova inspirirana tipografijom Arne Jacobsena.',
				'segment'     => 'lifestyle',
				'logo'        => 'brands/design-letters-aps/logo.webp',
				'image'       => 'brands/design-letters-aps/brand-image.webp',
			],
			[
				'slug'        => 'djeco',
				'name'        => 'Djeco',
				'description' => '',
				'segment'     => null,
				'logo'        => 'brands/djeco/logo.jpg',
				'image'       => null,
			],
			[
				'slug'        => 'dock-bay',
				'name'        => 'DOCK & BAY',
				'description' => 'Dock & Bay je britanski brend poznat po šarenim, ekološki prihvatljivim proizvodima za plažu i putovanja. Njihovi ručnici, torbe i ostali dodatci izrađeni su od 100 % recikliranih plastičnih boca, lagani su, brzosušeći i otporni na pijesak. Dock & Bay spaja praktičnost, održivost i veseo dizajn, čineći svaki trenutak na plaži ili putovanju jednostavnijim i ugodnijim.',
				'segment'     => 'lifestyle',
				'logo'        => 'brands/dock-bay/logo.webp',
				'image'       => 'brands/dock-bay/brand-image.webp',
			],
			[
				'slug'        => 'eat-my-socks',
				'name'        => 'Eat My Socks',
				'description' => 'Eat My Socks je kreativni modni brend iz Barcelone poznat po zabavnim i originalnim čarapama i modnim dodacima koji svakodnevne stvari pretvaraju u neočekivane i vizualno privlačne proizvode. Brend je osnovan 2021. godine i njegov fokus je na jedinstvenom dizajnu - čarape se često presavijaju i pakiraju tako da oponašaju oblike poput hrane (sushi, burger, pizza i sl.), što ih čini idealnim kao smiješan, šarolik i upečatljiv modni detalj ili poklon.',
				'segment'     => null,
				'logo'        => 'brands/eat-my-socks/logo.webp',
				'image'       => 'brands/eat-my-socks/brand-image.webp',
			],
			[
				'slug'        => 'flow-amsterdam',
				'name'        => 'Flow Amsterdam',
				'description' => '',
				'segment'     => 'toys',
				'logo'        => 'brands/flow-amsterdam/logo.png',
				'image'       => 'brands/flow-amsterdam/brand-image.png',
			],
			[
				'slug'        => 'fresk',
				'name'        => 'Fresk',
				'description' => '',
				'segment'     => 'toys',
				'logo'        => 'brands/fresk/logo.png',
				'image'       => 'brands/fresk/brand-image.png',
			],
			[
				'slug'        => 'gaston-luga',
				'name'        => 'Gaston luga',
				'description' => 'Gaston Luga je švedski lifestyle brend iz Stockholma koji spaja skandinavsku estetiku, funkcionalnost i održivost. Njihove torbe i dodatci izrađeni su od recikliranog PET materijala. Kombinirajući minimalistički dizajn s praktičnim detaljima, proizvodi Gaston Luga savršeni su za urbani život i svakodnevne potrebe.',
				'segment'     => 'lifestyle',
				'logo'        => null,
				'image'       => 'brands/gaston-luga/brand-image.webp',
			],
			[
				'slug'        => 'go-baby-go',
				'name'        => 'Go baby go',
				'description' => '',
				'segment'     => 'toys',
				'logo'        => 'brands/go-baby-go/logo.png',
				'image'       => 'brands/go-baby-go/brand-image.png',
			],
			[
				'slug'        => 'izipizi',
				'name'        => 'Izipizi',
				'description' => 'Izipizi je francuski brend poznat po modernim, šarenim i udobnim naočalama za sunce i čitanje koje spajaju stil i pristupačnost za sve uzraste. Njihovi modeli odlikuju se laganim i fleksibilnim okvirima, unisex dizajnom te raznolikošću boja i stilova. Izipizi naočale pružaju zaštitu, udobnost i veseli estetski dojam u svakodnevnim situacijama, bilo da čitate, uživate na suncu ili radite za računalom.',
				'segment'     => 'lifestyle',
				'logo'        => 'brands/izipizi/logo.webp',
				'image'       => 'brands/izipizi/brand-image.webp',
			],
			[
				'slug'        => 'janod',
				'name'        => 'Janod',
				'description' => '',
				'segment'     => null,
				'logo'        => 'brands/janod/logo.webp',
				'image'       => null,
			],
			[
				'slug'        => 'la-coque-francaise',
				'name'        => 'LA COQUE FRANCAISE',
				'description' => 'La Coque Française je francuski brend koji spaja modu i tehnologiju, nudeći elegantne i funkcionalne dodatke za pametne telefone. Njihovi proizvodi uključuju zaštite za telefone s modernim uzorcima, praktične lance i vezice za nošenje, dodatke poput zaštite za ekran te modne sitnice poput torbica i nakita za naočale. Svi proizvodi dizajnirani su i tiskani u Francuskoj, a brend se ponosi kreativnim, šarenim i personaliziranim kolekcijama koje kombiniraju stil i praktičnost.',
				'segment'     => 'lifestyle',
				'logo'        => 'brands/la-coque-francaise/logo.webp',
				'image'       => 'brands/la-coque-francaise/brand-image.webp',
			],
			[
				'slug'        => 'leatherman',
				'name'        => 'Leatherman',
				'description' => '',
				'segment'     => 'outdoor',
				'logo'        => 'brands/leatherman/logo.png',
				'image'       => 'brands/leatherman/brand-image.png',
			],
			[
				'slug'        => 'ledlenser',
				'name'        => 'Ledlenser',
				'description' => '',
				'segment'     => 'outdoor',
				'logo'        => 'brands/ledlenser/logo.png',
				'image'       => 'brands/ledlenser/brand-image.png',
			],
			[
				'slug'        => 'leuchtturm1917',
				'name'        => 'Leuchtturm1917',
				'description' => 'Leuchtturm1917 je njemački brend s više od stoljeća tradicije, poznat po vrhunski izrađenim bilježnicama, rokovnicima i planerima. Njihovi proizvodi odlikuju se kvalitetnim papirom, promišljenim detaljima poput numeriranih stranica i džepića te bogatom paletom boja. Leuchtturm1917 potiče ideju da je pisanje rukom oblik razmišljanja i stvaranja, nudeći savršenu ravnotežu između funkcionalnosti i inspiracije.',
				'segment'     => 'lifestyle',
				'logo'        => 'brands/leuchtturm1917/logo.webp',
				'image'       => 'brands/leuchtturm1917/brand-image.webp',
			],
			[
				'slug'        => 'notabag',
				'name'        => 'Notabag',
				'description' => 'Notabag je inovativan brend koji spaja praktičnost torbe i udobnost ruksaka u jednom proizvodu. Jednostavnim potezom traka torba se pretvara u ruksak, što je čini idealnom za svakodnevnu upotrebu, vožnju biciklom ili putovanja. Izrađena je od izdržljivih, laganih i vodootpornih materijala, kombinirajući pamuk i najlon visoke kvalitete. Notabag promiče održiv način života, potičući smanjenje upotrebe jednokratnih vrećica.',
				'segment'     => 'lifestyle',
				'logo'        => 'brands/notabag/logo.webp',
				'image'       => 'brands/notabag/brand-image.webp',
			],
			[
				'slug'        => 'nuuna',
				'name'        => 'NUUNA',
				'description' => 'Nuuna je njemački brend koji stvara premium bilježnice koje spajaju vrhunski dizajn, kvalitetu i osobni izraz. Svaka bilježnica izrađena je od pažljivo odabranih materijala poput reciklirane kože ili veganskih alternativa, s koricama koje krase jedinstveni umjetnički motivi otisnuti sitotiskom. Zahvaljujući šivanom uvezu, stranice ravno leže, a visokokvalitetni papir s diskretnom sivom mrežom idealan je za pisanje, crtanje ili planiranje.',
				'segment'     => 'lifestyle',
				'logo'        => 'brands/nuuna/logo.webp',
				'image'       => 'brands/nuuna/brand-image.webp',
			],
			[
				'slug'        => 'printworks',
				'name'        => 'PRINTWORKS',
				'description' => 'Printworks je švedski dizajnerski lifestyle brend osnovan 2017. godine u Stockholmu, poznat po svom prepoznatljivom spoju estetike i funkcionalnosti. Fokus brenda je stvaranje lijepih i pažljivo dizajniranih predmeta za dom i svakodnevni život - od foto‑albuma i društvenih igara, do slagalica, dekorativnih predmeta i praktičnih dodataka - koji potiču druženja, stvaranje uspomena i kvalitetno provođenje vremena s obitelji i prijateljima.',
				'segment'     => null,
				'logo'        => 'brands/printworks/logo.webp',
				'image'       => 'brands/printworks/brand-image.webp',
			],
			[
				'slug'        => 'secrid',
				'name'        => 'SECRID',
				'description' => 'Secrid je nizozemski brend poznat po inovativnim novčanicima s RFID zaštitom, koji štite kartice od krađe podataka i oštećenja. Njihovi proizvodi spajaju funkcionalnost, vrhunsku sigurnost i moderan dizajn, nudeći praktično i elegantno rješenje za svakodnevnu upotrebu.',
				'segment'     => 'lifestyle',
				'logo'        => 'brands/secrid/logo.webp',
				'image'       => 'brands/secrid/brand-image.webp',
			],
		];
	}
```

- [ ] **Step 4: Add the existence check, term-creation method, orchestrator, and CLI entry point**

```php
	/**
	 * Term ID for a product_brand slug, or 0 if not found. Direct SQL:
	 * bypasses get_terms filter hooks (visibility engine filters
	 * unauthenticated WP-CLI user to []) — same technique as
	 * get_generated_brand_ids() elsewhere in this file.
	 */
	private function find_brand_term_id_by_slug( string $slug ): int {
		global $wpdb;
		$term_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT t.term_id FROM {$wpdb->terms} t
			 INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
			 WHERE tt.taxonomy = %s AND t.slug = %s",
			'product_brand',
			$slug
		) );
		return (int) $term_id;
	}

	/**
	 * Creates one Brand Fixtures term, including its media and ACF fields.
	 * Only called when find_brand_term_id_by_slug() has already confirmed
	 * the slug does not exist — no existence re-check here.
	 *
	 * @param array{slug:string,name:string,description:string,segment:?string,logo:?string,image:?string} $fixture
	 * @return array{0:int,1:int,2:int} [term_id (0 on failure), assets_created, assets_reused]
	 */
	private function create_brand_fixture_term( array $fixture ): array {
		$assets_created = 0;
		$assets_reused  = 0;
		$logo_id        = 0;
		$image_id       = 0;

		if ( $fixture['logo'] !== null ) {
			[ $logo_id, $reused ] = $this->ensure_fixture_attachment( $fixture['logo'] );
			$reused ? $assets_reused++ : $assets_created++;
		}

		if ( $fixture['image'] !== null ) {
			[ $image_id, $reused ] = $this->ensure_fixture_attachment( $fixture['image'] );
			$reused ? $assets_reused++ : $assets_created++;
		}

		$result = wp_insert_term( $fixture['name'], 'product_brand', [
			'slug'        => $fixture['slug'],
			'description' => $fixture['description'],
		] );

		if ( is_wp_error( $result ) ) {
			WP_CLI::warning( "  FAIL  {$fixture['name']}: " . $result->get_error_message() );
			return [ 0, $assets_created, $assets_reused ];
		}

		$term_id = (int) $result['term_id'];
		$applied = [];

		if ( $logo_id ) {
			update_term_meta( $term_id, 'thumbnail_id', $logo_id );
			$applied[] = 'logo';
		}
		if ( $image_id ) {
			update_field( 'brand_image', $image_id, 'product_brand_' . $term_id );
			$applied[] = 'image';
		}
		if ( $fixture['segment'] !== null ) {
			update_field( 'brand_segment', $fixture['segment'], 'product_brand_' . $term_id );
			$applied[] = 'segment=' . $fixture['segment'];
		}

		add_term_meta( $term_id, '_dp_brand_fixture', 1, true );

		WP_CLI::log( sprintf(
			'  +     %s (id=%d)  %s',
			$fixture['name'], $term_id, $applied ? implode( '+', $applied ) : '(no logo/image/segment)'
		) );

		return [ $term_id, $assets_created, $assets_reused ];
	}

	/**
	 * @return array{created:int, skipped:int, assets_created:int, assets_reused:int}
	 */
	private function generate_brand_fixtures(): array {
		$created        = 0;
		$skipped        = 0;
		$assets_created = 0;
		$assets_reused  = 0;

		foreach ( $this->get_brand_fixtures() as $fixture ) {
			if ( $this->find_brand_term_id_by_slug( $fixture['slug'] ) ) {
				WP_CLI::log( "  skip  {$fixture['name']}" );
				$skipped++;
				continue;
			}

			[ $term_id, $brand_assets_created, $brand_assets_reused ] = $this->create_brand_fixture_term( $fixture );

			if ( ! $term_id ) {
				continue; // failure already logged inside create_brand_fixture_term()
			}

			$created++;
			$assets_created += $brand_assets_created;
			$assets_reused  += $brand_assets_reused;
		}

		return [
			'created'        => $created,
			'skipped'        => $skipped,
			'assets_created' => $assets_created,
			'assets_reused'  => $assets_reused,
		];
	}

	private function run_brand_fixtures(): void {
		WP_CLI::log( 'Brand Fixtures — canonical development dataset:' );
		WP_CLI::log( '' );

		$result = $this->generate_brand_fixtures();

		WP_CLI::log( '' );
		WP_CLI::success( sprintf(
			'Brand Fixtures done — brands: %d created, %d skipped | assets: %d created, %d reused.',
			$result['created'], $result['skipped'], $result['assets_created'], $result['assets_reused']
		) );
	}
```

- [ ] **Step 5: Lint the file**

```bash
"/c/xampp2/php83/php.exe" -l "/c/xampp2/htdocs/dp-b2b/wp-content/themes/dreampoint-b2b/inc/dev/class-dev-catalog-generator.php"
```

Expected: `No syntax errors detected`.

- [ ] **Step 6: Run the phase against the current local DB — confirm all 21 skip**

```bash
"/c/xampp2/php83/php.exe" "/c/xampp2/htdocs/dp-b2b/wp-cli.phar" --path="/c/xampp2/htdocs/dp-b2b" dp-b2b generate-catalog --phase=brand-fixtures --user=admin 2>&1
```

Expected: 21 `skip` lines (one per fixture slug — every brand in `get_brand_fixtures()` already exists in this local DB), summary line `Brand Fixtures done — brands: 0 created, 21 skipped | assets: 0 created, 0 reused.`

This is a meaningful correctness check on its own: if any fixture slug in Step 3's array has a typo that doesn't match the real DB slug, that brand will show `+` (attempted create) instead of `skip`, and `wp_insert_term()` will fail with a `term_exists` `WP_Error` (logged as `FAIL`) since the slug genuinely already exists — surfacing the typo immediately instead of silently creating a near-duplicate.

- [ ] **Step 7: Commit**

```bash
git add inc/dev/class-dev-catalog-generator.php
git commit -m "$(cat <<'EOF'
feat: add brand-fixtures phase to Dev Catalog Generator

New --phase=brand-fixtures on the existing generate-catalog command:
recreates the 21-brand canonical development product_brand dataset
(name, description, brand_segment, logo, brand_image) from the
committed dev-fixtures/brands/ assets. Slug-based term existence
check (direct SQL, bypasses the visibility engine's get_terms
filter), never overwrites existing data, kept outside the
_dp_generated/reset-catalog lifecycle since some fixture brands have
real product associations.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: Validate the create path and the faithful-gap behavior

**Files:** none (validation only — no code changes).

**Interfaces:** none produced; exercises Task 2/3's methods end-to-end.

- [ ] **Step 1: Recreate a real, safe (zero-product) fixture brand — `gaston-luga`**

`gaston-luga` has `count = 0` real products (safe to delete/recreate) and is the one canonical brand missing a **logo** — this exercises both the create path and the faithful-incomplete-data requirement in one real, in-scope brand.

```bash
"/c/xampp2/php83/php.exe" "/c/xampp2/htdocs/dp-b2b/wp-cli.phar" --path="/c/xampp2/htdocs/dp-b2b" term list product_brand --slug=gaston-luga --field=term_id --user=admin
```

Note the returned ID, then delete it:

```bash
"/c/xampp2/php83/php.exe" "/c/xampp2/htdocs/dp-b2b/wp-cli.phar" --path="/c/xampp2/htdocs/dp-b2b" term delete product_brand <term_id> --user=admin
```

- [ ] **Step 2: Re-run the phase — confirm `gaston-luga` is recreated correctly**

```bash
"/c/xampp2/php83/php.exe" "/c/xampp2/htdocs/dp-b2b/wp-cli.phar" --path="/c/xampp2/htdocs/dp-b2b" dp-b2b generate-catalog --phase=brand-fixtures --user=admin 2>&1
```

Expected: 20 `skip` lines, one `+     Gaston luga (id=<new_id>)  image+segment=lifestyle` line (no `logo` in the applied list — faithful gap reproduction), summary `brands: 1 created, 20 skipped | assets: 1 created, 0 reused` (only the `brand-image` asset is created; no `logo` asset step ever runs for this brand since `fixture['logo'] === null`).

- [ ] **Step 3: Inspect the recreated term's data directly**

```bash
"/c/xampp2/php83/php.exe" "/c/xampp2/htdocs/dp-b2b/wp-cli.phar" --path="/c/xampp2/htdocs/dp-b2b" eval '
$t = get_term_by("slug", "gaston-luga", "product_brand");
$new_id = $t->term_id;
echo "term_id={$new_id}\n";
echo "description=" . $t->description . "\n";
echo "thumbnail_id=" . get_term_meta($new_id, "thumbnail_id", true) . "\n"; // expect empty
$img = get_field("brand_image", "product_brand_" . $new_id);
echo "brand_image_id=" . (is_array($img) ? $img["id"] : "none") . "\n"; // expect a valid attachment ID
echo "brand_segment=" . get_field("brand_segment", "product_brand_" . $new_id) . "\n"; // expect lifestyle
echo "fixture_marker=" . get_term_meta($new_id, "_dp_brand_fixture", true) . "\n"; // expect 1
' --user=admin 2>&1
```

Expected: `description` matches the fixture text verbatim, `thumbnail_id` is empty (no logo — faithful gap), `brand_image_id` is a valid positive integer, `brand_segment=lifestyle`, `fixture_marker=1`.

- [ ] **Step 4: Re-run the phase once more — confirm the recreated brand now skips**

```bash
"/c/xampp2/php83/php.exe" "/c/xampp2/htdocs/dp-b2b/wp-cli.phar" --path="/c/xampp2/htdocs/dp-b2b" dp-b2b generate-catalog --phase=brand-fixtures --user=admin 2>&1
```

Expected: 21 `skip` lines, `brands: 0 created, 21 skipped | assets: 0 created, 0 reused` — confirms the phase is idempotent across the full create→skip cycle and the dataset is back to its original 21-brand state.

- [ ] **Step 5: Isolated check of the missing-`brand_image` branch, without touching the real `chillys`/`djeco`/`janod` terms**

These three real brands have live product associations (`count > 0`) and must never be deleted for testing (see Global Constraints). Instead, exercise the "logo-only, no brand_image" branch via `create_brand_fixture_term()` directly, using a throwaway slug that cannot collide with any real brand:

```bash
"/c/xampp2/php83/php.exe" "/c/xampp2/htdocs/dp-b2b/wp-cli.phar" --path="/c/xampp2/htdocs/dp-b2b" eval '
$gen = new Dreampoint_B2B_Dev_Catalog_Generator();
$m = new ReflectionMethod($gen, "create_brand_fixture_term");
$m->setAccessible(true);

$fixture = [
    "slug"        => "dp-fixture-test-logo-only",
    "name"        => "DP Fixture Test Logo Only",
    "description" => "",
    "segment"     => null,
    "logo"        => "brands/chillys/logo.jpg", // reuses the real chillys logo file — read-only, no term touched
    "image"       => null,
];

[$term_id] = $m->invoke($gen, $fixture);
echo "term_id={$term_id}\n";
echo "thumbnail_id=" . get_term_meta($term_id, "thumbnail_id", true) . "\n"; // expect a valid attachment ID
$img = get_field("brand_image", "product_brand_" . $term_id);
echo "brand_image_id=" . (is_array($img) ? ($img["id"] ?? "none") : "none") . "\n"; // expect "none" — no field written

wp_delete_term( $term_id, "product_brand" );
echo "cleanup=deleted\n";
' --user=admin 2>&1
```

Expected: `thumbnail_id` is a valid positive integer (the `chillys` logo file, uploaded/reused as its own attachment — separate from the real `chillys` term, which is never touched), `brand_image_id=none` (field correctly left unset), `cleanup=deleted` (throwaway term removed — this test slug is scaffolding, not part of the canonical 21).

- [ ] **Step 6: Confirm no duplicate attachments exist for any fixture asset**

```bash
"/c/xampp2/php83/php.exe" "/c/xampp2/htdocs/dp-b2b/wp-cli.phar" --path="/c/xampp2/htdocs/dp-b2b" eval '
global $wpdb;
$rows = $wpdb->get_results(
    "SELECT meta_value, COUNT(*) as cnt FROM {$wpdb->postmeta}
     WHERE meta_key = \"_dp_brand_fixture_source\"
     GROUP BY meta_value HAVING cnt > 1"
);
echo "duplicate_sources=" . count($rows) . "\n";
' --user=admin 2>&1
```

Expected: `duplicate_sources=0`.

No commit for this task — validation only, no file changes.

---

### Task 5: Documentation — canonical Dev Catalog Generator doc

**Files:**
- Modify: `docs/historical/synthetic-b2b-catalog.md` (canonical doc per `docs/index.md`).

**Interfaces:** none — documentation only.

- [ ] **Step 1: Update the WP-CLI Usage block**

In the `## WP-CLI Usage` section, after the Phase 3 example block, add:

```
# Phase 4 — brand fixtures (canonical development brand dataset)
wp dp-b2b generate-catalog --phase=brand-fixtures
```

- [ ] **Step 2: Add the "Phase 4 — Brand Fixtures" section**

Insert a new subsection after `### Phase 3 — Variable Products (implemented)` and before the `## Data Markers` section:

```markdown
### Phase 4 — Brand Fixtures (implemented)

Recreates the canonical 21-brand real (non-`[DEV]`) `product_brand` development
dataset used to test the Brands page (segment navigation, brand hero image/logo).
Unlike Phases 1–3, this is a **fixed, hardcoded list** — not randomly generated,
not derived from live DB state at run time. See
`docs/superpowers/specs/2026-08-04-brand-fixtures-design.md` for the full design
rationale.

**21 brands**, each with `name`, `description`, optional `brand_segment` (ACF),
optional logo (`thumbnail_id` term meta), optional `brand_image` (ACF). Some brands
intentionally lack one or more of these fields — this is the real, current state of
the dataset and is reproduced faithfully, not "completed":

| Slug | Segment | Has logo | Has brand_image |
|---|---|---|---|
| `24bottles` | lifestyle | yes | yes |
| `a-fan-of` | lifestyle | yes | yes |
| `chillys` | — | yes | no |
| `design-letters-aps` | lifestyle | yes | yes |
| `djeco` | — | yes | no |
| `dock-bay` | lifestyle | yes | yes |
| `eat-my-socks` | — | yes | yes |
| `flow-amsterdam` | toys | yes | yes |
| `fresk` | toys | yes | yes |
| `gaston-luga` | lifestyle | no | yes |
| `go-baby-go` | toys | yes | yes |
| `izipizi` | lifestyle | yes | yes |
| `janod` | — | yes | no |
| `la-coque-francaise` | lifestyle | yes | yes |
| `leatherman` | outdoor | yes | yes |
| `ledlenser` | outdoor | yes | yes |
| `leuchtturm1917` | lifestyle | yes | yes |
| `notabag` | lifestyle | yes | yes |
| `nuuna` | lifestyle | yes | yes |
| `printworks` | — | yes | yes |
| `secrid` | lifestyle | yes | yes |

**Fixture assets — permanent, git-committed data:**

```
dev-fixtures/
  brands/
    <slug>/
      logo.<ext>          (omitted where the brand has no logo)
      brand-image.<ext>   (omitted where the brand has no brand_image)
```

These files are part of the canonical development dataset, in the same sense as the
hardcoded category/brand name arrays in Phase 1 — they are **not** temporary
uploads, not build output, and must not be gitignored or treated as disposable.
Media Library attachments are created from them using the native WordPress
media-upload recipe (`wp_upload_bits()` → `wp_insert_attachment()` →
`wp_generate_attachment_metadata()`), fully offline — no network access, no
dependency on production/corporate-site availability.

**Idempotency:** existence check is by `product_brand` slug (direct SQL, bypassing
the visibility engine's `get_terms` filter — same technique as
`get_generated_brand_ids()`). If a fixture's slug already exists, the entire brand
is skipped — term, media, and ACF fields are left untouched. Attachment creation is
separately deduplicated via a `_dp_brand_fixture_source` postmeta marker holding the
asset's path relative to `dev-fixtures/`, so re-running the phase never creates
duplicate Media Library attachments.

**Deliberately outside the `_dp_generated`/`reset-catalog` lifecycle:** Brand
Fixtures terms are real brand data — three of them (`chillys`, `djeco`, `janod`)
have real, non-`[DEV]` products currently assigned. They are **not** tagged with
`_dp_generated`/`_dp_generation_batch` and `reset-catalog` never touches them.
```

- [ ] **Step 3: Update the Data Markers section**

In `## Data Markers`, after the existing table, add a short note:

```markdown
**Brand Fixtures (Phase 4) uses a separate, informational-only marker:**
`_dp_brand_fixture` term meta (`= 1`). This is *not* part of the
`_dp_generated`/`_dp_generation_batch` cleanup-targeting system above — it exists
only so a human can identify a fixture-created brand in wp-admin/WP-CLI.
`reset-catalog` does not read it.
```

- [ ] **Step 4: Update the File Locations section**

Change:

```
inc/dev/dev-tools.php                  — WP-CLI bootstrap (loaded only in CLI context)
inc/dev/class-dev-catalog-generator.php  — generator class
```

to:

```
inc/dev/dev-tools.php                    — WP-CLI bootstrap (loaded only in CLI context)
inc/dev/class-dev-catalog-generator.php  — generator class
dev-fixtures/brands/<slug>/              — Phase 4 fixture assets (permanent, git-committed)
```

- [ ] **Step 5: Update the Testing Purpose table**

Add a row to the `## Testing Purpose` table:

```
| Brands page segment navigation / hero image / logo | Phase 4 — 21-brand canonical fixture dataset |
```

- [ ] **Step 6: Commit**

```bash
git add docs/historical/synthetic-b2b-catalog.md
git commit -m "$(cat <<'EOF'
docs: document the brand-fixtures phase in the catalog generator doc

Phase 4 section (brand table, fixture asset layout, idempotency,
reset-catalog exclusion), updated WP-CLI usage, Data Markers, File
Locations, and Testing Purpose sections.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 6: Final full-suite check and report

**Files:** none.

**Interfaces:** none.

- [ ] **Step 1: Confirm the dataset is fully idempotent end-to-end**

```bash
"/c/xampp2/php83/php.exe" "/c/xampp2/htdocs/dp-b2b/wp-cli.phar" --path="/c/xampp2/htdocs/dp-b2b" dp-b2b generate-catalog --phase=brand-fixtures --user=admin 2>&1
```

Expected: `Brand Fixtures done — brands: 0 created, 21 skipped | assets: 0 created, 0 reused.`

- [ ] **Step 2: Confirm attachment count integrity one more time**

```bash
"/c/xampp2/php83/php.exe" "/c/xampp2/htdocs/dp-b2b/wp-cli.phar" --path="/c/xampp2/htdocs/dp-b2b" eval '
global $wpdb;
$total = $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = \"_dp_brand_fixture_source\""
);
echo "total_fixture_attachments={$total}\n"; // expect 38 (20 logos + 18 images)
' --user=admin 2>&1
```

- [ ] **Step 3: Report to the user**

Compose the final report (in chat, Serbian latinica per this project's language rule) covering:
- New phase name: `brand-fixtures`.
- WP-CLI usage: `wp dp-b2b generate-catalog --phase=brand-fixtures`.
- Created/skipped counts observed during validation (Task 3 Step 6: 0/21 on the pre-existing DB; Task 4: 1 created/20 skipped on the deliberate recreate, then 0/21 again).
- Explicit confirmation that the phase is fully idempotent (Task 4 Step 4, Task 6 Step 1) and that attachment dedup holds (Task 4 Step 6, Task 6 Step 2 — 0 duplicate `_dp_brand_fixture_source` values, 38 total fixture attachments).
- Updated documentation files: `docs/historical/synthetic-b2b-catalog.md`, plus the two planning documents already committed (`docs/superpowers/specs/2026-08-04-brand-fixtures-design.md`, this plan file).

No commit for this task.

---

## Self-Review

**Spec coverage:**
- New phase, not a separate tool/command → Task 3 (switch case), Global Constraints.
- Follows existing architecture/style/reporting/production guard → Task 3 (mirrors `generate_categories()`/`generate_brands()` array style, existing log/summary format); production guard already covers all phases via the shared `guard_production()` call at the top of `generate_catalog()` — no new code needed.
- Fully idempotent (terms + attachments) → Task 2 (attachment dedup), Task 3 Step 4 (slug-based skip), validated in Task 3 Step 6 and Task 4 Steps 2/4/6.
- Creates the 21 dev terms, `brand_segment`, both images, reuse-first, never overwrite, never duplicate, skip existing → all covered in Task 3 Step 4 (`create_brand_fixture_term()`, `ensure_fixture_attachment()` reuse-first logic).
- Reproduce exact current dataset / corporate-site mappings → Task 3 Step 3 (data verbatim from live DB read during brainstorming).
- Doesn't change existing synthetic phases → Global Constraints; no task touches `generate_categories()`, `generate_brands()`, `generate_simple_products()`, `generate_variable_products()`, `generate_ugly_products()`, or `ensure_term()`.
- No production-facing changes → Global Constraints; entire feature is WP-CLI dev tooling behind the existing `guard_production()`.
- Documentation updated → Task 5.
- WP-CLI-only validation, no Playwright/browser/manual ACF/DB resets → Task 4 and Task 6 use only `wp-cli.phar` invocations and `wp eval`.
- Fixture assets bundled + committed, native media workflow, no network → Task 1 (assets), Task 2 (`wp_upload_bits`/`wp_insert_attachment`/`wp_generate_attachment_metadata`, no `media_sideload_image`/URL fetch anywhere).
- Faithful reproduction of intentional gaps → Task 3 Step 3 (`logo`/`image`/`segment` are `null` for the 6 known-incomplete brands), verified concretely in Task 4 Steps 2–3 (`gaston-luga`, real, in-scope) and Step 5 (isolated logo-only branch, throwaway slug — `chillys`/`djeco`/`janod` themselves are never touched, per Global Constraints).
- Generic `_dp_fixture_type` vs `_dp_brand_fixture` marker decision → resolved in the approved spec and Global Constraints (kept specific; not reopened here).
- Relative-path-based attachment dedup portability → Task 2 (`_dp_brand_fixture_source` holds the `brands/<slug>/...` relative path, not filename/title).

**Placeholder scan:** No "TBD"/"TODO"/"handle appropriately" language. All code blocks contain complete, real implementations. All 21 data records are fully populated (no elided fields).

**Type consistency:** `ensure_fixture_attachment()` returns `array{0:int,1:bool}` (Task 2) — consumed identically in `create_brand_fixture_term()` (Task 3) via `[ $logo_id, $reused ] = $this->ensure_fixture_attachment(...)`. `create_brand_fixture_term()` returns `array{0:int,1:int,2:int}` (Task 3) — consumed identically in both `generate_brand_fixtures()` (`[ $term_id, $brand_assets_created, $brand_assets_reused ] = ...`) and the Task 4 Step 5 reflection call (`[$term_id] = $m->invoke(...)`). `get_brand_fixtures()` record shape (`slug`/`name`/`description`/`segment`/`logo`/`image`) is identical between its definition (Task 3 Step 3) and every consumer (`create_brand_fixture_term()`, the Task 4 Step 5 throwaway fixture array).

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-08-04-brand-fixtures.md`. Two execution options:

1. **Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration.
2. **Inline Execution** — Execute tasks in this session using executing-plans, batch execution with checkpoints.

Which approach?

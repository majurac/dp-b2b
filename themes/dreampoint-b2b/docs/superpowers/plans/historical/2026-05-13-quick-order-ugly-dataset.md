# Quick Order Ugly Dataset Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the existing WP-CLI catalog generator with `--phase=ugly` producing 10 curated edge-case products, then run the full aggressive dataset and verify Quick Order against all regression areas.

**Architecture:** Minimal addition of one `case 'ugly'` branch in the existing `generate_catalog()` switch plus four private methods (`run_ugly`, `generate_ugly_products`, `ugly_simple`, `ugly_attributes`, `ugly_cartesian`). All products follow the existing tag/batch/cleanup lifecycle. No new files, no new commands, no new cleanup logic.

**Tech Stack:** PHP 8.x, WooCommerce, WP-CLI, Playwright (browser verification)

---

## Files

| File | Change |
|------|--------|
| `inc/dev/class-dev-catalog-generator.php` | Add `ugly` option to docblock + `case 'ugly'` + 5 private methods |

---

## Task 1: Add ugly phase to `class-dev-catalog-generator.php`

**File:** `C:\xampp2\htdocs\dp-b2b\wp-content\themes\dreampoint-b2b\inc\dev\class-dev-catalog-generator.php`

### Step 1 — Update docblock options list (lines 47–51)

Add `- ugly` to the options list so WP-CLI help reflects the new phase.

Find:
```php
	 * options:
	 *   - taxonomies
	 *   - products
	 *   - variables
	 * ---
```

Replace with:
```php
	 * options:
	 *   - taxonomies
	 *   - products
	 *   - variables
	 *   - ugly
	 * ---
```

- [ ] **Step 1: Apply docblock edit**

### Step 2 — Add `case 'ugly'` to the switch (around line 83, before `default`)

Find:
```php
			default:
				WP_CLI::error( "Unknown phase: {$phase}" );
```

Replace with:
```php
			case 'ugly':
				$this->run_ugly();
				break;
			default:
				WP_CLI::error( "Unknown phase: {$phase}" );
```

- [ ] **Step 2: Apply switch edit**

### Step 3 — Append five new private methods before the final closing `}` of the class (after line 826)

Find the last line of the class:
```php
	private function count_result( string $action, int &$created, int &$skipped ): void {
		if ( $action === 'created' ) {
			$created++;
		} elseif ( $action === 'skipped' ) {
			$skipped++;
		}
	}
}
```

Replace with:
```php
	private function count_result( string $action, int &$created, int &$skipped ): void {
		if ( $action === 'created' ) {
			$created++;
		} elseif ( $action === 'skipped' ) {
			$skipped++;
		}
	}

	// -------------------------------------------------------------------------
	// Phase: ugly
	// -------------------------------------------------------------------------

	private function run_ugly(): void {
		WP_CLI::log( "Batch: {$this->batch_id}" );
		WP_CLI::log( 'Generating ugly edge-case products...' );
		WP_CLI::log( '' );

		$result = $this->generate_ugly_products();

		WP_CLI::log( '' );
		WP_CLI::success( sprintf(
			'Ugly done — %d simple, %d variable (%d variations) created.',
			$result['simple'], $result['variable'], $result['variations']
		) );
	}

	private function generate_ugly_products(): array {
		$cat_ids   = $this->get_generated_child_cat_ids();
		$brand_ids = $this->get_generated_brand_ids();

		if ( empty( $cat_ids ) ) {
			WP_CLI::error( 'No generated categories found. Run --phase=taxonomies first.' );
		}
		if ( empty( $brand_ids ) ) {
			WP_CLI::error( 'No generated brands found. Run --phase=taxonomies first.' );
		}

		$cat_id   = $cat_ids[0];
		$brand_id = $brand_ids[0];
		$simple   = 0;
		$variable = 0;
		$vars     = 0;

		// DEV-UGLY-001: 160-char name — layout overflow test.
		$simple += (int) $this->ugly_simple(
			'DEV-UGLY-001',
			'[DEV] Artikal s Ekstremno Dugačkim Nazivom Koji Testira Prelamanje Teksta u Svim Stupcima Tablice Quick Order Sučelja 001',
			'49.99', 'instock', $cat_id, $brand_id
		);

		// DEV-UGLY-002: Croatian unicode chars — encoding/escaping test.
		$simple += (int) $this->ugly_simple(
			'DEV-UGLY-002',
			'[DEV] Artikal: Čačak, Šibenik, Đakovo, Žuta Školjka — Ćevapi 002',
			'29.99', 'instock', $cat_id, $brand_id
		);

		// DEV-UGLY-003: HTML-ish chars — XSS escape test.
		$simple += (int) $this->ugly_simple(
			'DEV-UGLY-003',
			"[DEV] Artikal <Poseban> & \"Navodnici\" 'Apostrof' 003",
			'39.99', 'instock', $cat_id, $brand_id
		);

		// DEV-UGLY-004: Price 0.01 EUR — edge low price format.
		$simple += (int) $this->ugly_simple(
			'DEV-UGLY-004',
			'[DEV] Artikal Mikro Cijena 004',
			'0.01', 'instock', $cat_id, $brand_id
		);

		// DEV-UGLY-005: Price 9999.99 EUR — edge high price format.
		$simple += (int) $this->ugly_simple(
			'DEV-UGLY-005',
			'[DEV] Artikal Makro Cijena 005',
			'9999.99', 'instock', $cat_id, $brand_id
		);

		// DEV-UGLY-006: onbackorder + no image — stock badge + thumbnail fallback.
		$simple += (int) $this->ugly_simple(
			'DEV-UGLY-006',
			'[DEV] Artikal Na Čekanju Bez Slike 006',
			'59.99', 'onbackorder', $cat_id, $brand_id, true
		);

		// DEV-UGLY-007: No description, no image — placeholder image test.
		$simple += (int) $this->ugly_simple(
			'DEV-UGLY-007',
			'[DEV] Artikal Bez Opisa i Slike 007',
			'19.99', 'instock', $cat_id, $brand_id
		);

		// DEV-UGLY-008: Solo variation (1 var only) — minimal variation UI test.
		if ( ! wc_get_product_id_by_sku( 'DEV-UGLY-008' ) ) {
			$p = new WC_Product_Variable();
			$p->set_name( '[DEV] Artikal Solo Varijanta 008' );
			$p->set_sku( 'DEV-UGLY-008' );
			$p->set_status( 'publish' );
			$p->set_catalog_visibility( 'visible' );
			$p->set_category_ids( [ $cat_id ] );
			$p->set_attributes( $this->ugly_attributes( [ [ 'name' => 'Pack Size', 'options' => [ '1pc' ] ] ] ) );
			$pid = $p->save();

			if ( $pid ) {
				wp_set_object_terms( $pid, [ $brand_id ], 'product_brand' );
				update_post_meta( $pid, self::GENERATED_KEY, 1 );
				update_post_meta( $pid, self::BATCH_KEY, $this->batch_id );

				$v = new WC_Product_Variation();
				$v->set_parent_id( $pid );
				$v->set_attributes( [ 'pack-size' => '1pc' ] );
				$v->set_sku( 'DEV-UGLY-008-01' );
				$v->set_regular_price( '14.99' );
				$v->set_stock_status( 'instock' );
				$v->set_manage_stock( false );
				$vid = $v->save();
				if ( $vid ) {
					update_post_meta( $vid, self::GENERATED_KEY, 1 );
					update_post_meta( $vid, self::BATCH_KEY, $this->batch_id );
					$vars++;
				}

				WC_Product_Variable::sync( $pid );
				WP_CLI::log( '  +     DEV-UGLY-008  [1 var]   [DEV] Artikal Solo Varijanta 008' );
				$variable++;
			}
		} else {
			WP_CLI::log( '  skip  DEV-UGLY-008' );
		}

		// DEV-UGLY-009: Dense attributes (3×3×3 = 27 vars) — variation selector + CartSync stress.
		if ( ! wc_get_product_id_by_sku( 'DEV-UGLY-009' ) ) {
			$attr_data_009 = [
				[ 'name' => 'Velicina', 'options' => [ 'S', 'M', 'L' ] ],
				[ 'name' => 'Boja',     'options' => [ 'Crna', 'Bijela', 'Plava' ] ],
				[ 'name' => 'Material', 'options' => [ 'Pamuk', 'Vuna', 'Najlon' ] ],
			];

			$p = new WC_Product_Variable();
			$p->set_name( '[DEV] Artikal Gusta Atributa 009' );
			$p->set_sku( 'DEV-UGLY-009' );
			$p->set_status( 'publish' );
			$p->set_catalog_visibility( 'visible' );
			$p->set_category_ids( [ $cat_id ] );
			$p->set_attributes( $this->ugly_attributes( $attr_data_009 ) );
			$pid = $p->save();

			if ( $pid ) {
				wp_set_object_terms( $pid, [ $brand_id ], 'product_brand' );
				update_post_meta( $pid, self::GENERATED_KEY, 1 );
				update_post_meta( $pid, self::BATCH_KEY, $this->batch_id );

				$combos = $this->ugly_cartesian( $attr_data_009 );
				foreach ( $combos as $j => $combo ) {
					$var_sku = sprintf( 'DEV-UGLY-009-%02d', $j + 1 );
					$v = new WC_Product_Variation();
					$v->set_parent_id( $pid );
					$v->set_attributes( array_combine(
						array_map( 'sanitize_title', array_keys( $combo ) ),
						array_values( $combo )
					) );
					$v->set_sku( $var_sku );
					$v->set_regular_price( '24.99' );
					$v->set_stock_status( 'instock' );
					$v->set_manage_stock( false );
					$vid = $v->save();
					if ( $vid ) {
						update_post_meta( $vid, self::GENERATED_KEY, 1 );
						update_post_meta( $vid, self::BATCH_KEY, $this->batch_id );
						$vars++;
					}
				}

				WC_Product_Variable::sync( $pid );
				WP_CLI::log( sprintf( '  +     DEV-UGLY-009  [27 vars]  [DEV] Artikal Gusta Atributa 009' ) );
				$variable++;
			}
		} else {
			WP_CLI::log( '  skip  DEV-UGLY-009' );
		}

		// DEV-UGLY-010: Mixed stock (instock / outofstock / onbackorder) — stock badge accuracy.
		if ( ! wc_get_product_id_by_sku( 'DEV-UGLY-010' ) ) {
			$p = new WC_Product_Variable();
			$p->set_name( '[DEV] Artikal Mješoviti Stok 010' );
			$p->set_sku( 'DEV-UGLY-010' );
			$p->set_status( 'publish' );
			$p->set_catalog_visibility( 'visible' );
			$p->set_category_ids( [ $cat_id ] );
			$p->set_attributes( $this->ugly_attributes( [ [ 'name' => 'Tip', 'options' => [ 'A', 'B', 'C' ] ] ] ) );
			$pid = $p->save();

			if ( $pid ) {
				wp_set_object_terms( $pid, [ $brand_id ], 'product_brand' );
				update_post_meta( $pid, self::GENERATED_KEY, 1 );
				update_post_meta( $pid, self::BATCH_KEY, $this->batch_id );

				$mixed = [
					[ 'sku' => 'DEV-UGLY-010-01', 'attr' => 'A', 'status' => 'instock',     'backorder' => false ],
					[ 'sku' => 'DEV-UGLY-010-02', 'attr' => 'B', 'status' => 'outofstock',  'backorder' => false ],
					[ 'sku' => 'DEV-UGLY-010-03', 'attr' => 'C', 'status' => 'onbackorder', 'backorder' => true  ],
				];

				foreach ( $mixed as $ms ) {
					$v = new WC_Product_Variation();
					$v->set_parent_id( $pid );
					$v->set_attributes( [ 'tip' => $ms['attr'] ] );
					$v->set_sku( $ms['sku'] );
					$v->set_regular_price( '34.99' );
					$v->set_stock_status( $ms['status'] );
					$v->set_manage_stock( false );
					if ( $ms['backorder'] ) {
						$v->set_backorders( 'yes' );
					}
					$vid = $v->save();
					if ( $vid ) {
						update_post_meta( $vid, self::GENERATED_KEY, 1 );
						update_post_meta( $vid, self::BATCH_KEY, $this->batch_id );
						$vars++;
					}
				}

				WC_Product_Variable::sync( $pid );
				WP_CLI::log( '  +     DEV-UGLY-010  [3 vars]   [DEV] Artikal Mješoviti Stok 010' );
				$variable++;
			}
		} else {
			WP_CLI::log( '  skip  DEV-UGLY-010' );
		}

		return [ 'simple' => $simple, 'variable' => $variable, 'variations' => $vars ];
	}

	/**
	 * Create a single ugly simple product. Returns true on success, false if skipped or failed.
	 */
	private function ugly_simple(
		string $sku,
		string $title,
		string $price,
		string $stock_status,
		int    $cat_id,
		int    $brand_id,
		bool   $backorder = false
	): bool {
		if ( wc_get_product_id_by_sku( $sku ) ) {
			WP_CLI::log( "  skip  {$sku}" );
			return false;
		}

		$product = new WC_Product_Simple();
		$product->set_name( $title );
		$product->set_sku( $sku );
		$product->set_regular_price( $price );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_category_ids( [ $cat_id ] );
		$product->set_stock_status( $stock_status );
		$product->set_manage_stock( false );

		if ( $backorder ) {
			$product->set_backorders( 'yes' );
		}

		$id = $product->save();

		if ( ! $id ) {
			WP_CLI::warning( "  FAIL  {$sku}" );
			return false;
		}

		wp_set_object_terms( $id, [ $brand_id ], 'product_brand' );
		update_post_meta( $id, self::GENERATED_KEY, 1 );
		update_post_meta( $id, self::BATCH_KEY, $this->batch_id );

		WP_CLI::log( "  +     {$sku}  {$title}" );
		return true;
	}

	/**
	 * Build WC_Product_Attribute[] from a plain data array.
	 * Isolated from build_product_attributes() to avoid coupling ugly phase to tier system.
	 *
	 * @param  array<int, array{name: string, options: string[]}> $attr_data
	 * @return WC_Product_Attribute[]  Keyed by sanitize_title(name).
	 */
	private function ugly_attributes( array $attr_data ): array {
		$attrs = [];
		foreach ( $attr_data as $position => $cfg ) {
			$attr = new WC_Product_Attribute();
			$attr->set_id( 0 );
			$attr->set_name( $cfg['name'] );
			$attr->set_options( $cfg['options'] );
			$attr->set_position( $position );
			$attr->set_visible( true );
			$attr->set_variation( true );
			$attrs[ sanitize_title( $cfg['name'] ) ] = $attr;
		}
		return $attrs;
	}

	/**
	 * Cartesian product of attribute option arrays.
	 * Isolated from get_tier_combinations() to avoid coupling ugly phase to tier system.
	 *
	 * @param  array<int, array{name: string, options: string[]}> $attr_data
	 * @return array<int, array<string, string>>
	 */
	private function ugly_cartesian( array $attr_data ): array {
		$combos = [ [] ];
		foreach ( $attr_data as $attr ) {
			$expanded = [];
			foreach ( $combos as $combo ) {
				foreach ( $attr['options'] as $option ) {
					$expanded[] = array_merge( $combo, [ $attr['name'] => $option ] );
				}
			}
			$combos = $expanded;
		}
		return $combos;
	}
}
```

- [ ] **Step 3: Apply the method block edit**

### Step 4 — Verify PHP syntax

Run from PowerShell:

```powershell
& "C:\xampp2\php\php.exe" -l "C:\xampp2\htdocs\dp-b2b\wp-content\themes\dreampoint-b2b\inc\dev\class-dev-catalog-generator.php"
```

Expected output:
```
No syntax errors detected in ...class-dev-catalog-generator.php
```

If error: fix syntax before continuing.

- [ ] **Step 4: Verify PHP syntax**

### Step 5 — Commit

```powershell
git -C "C:\xampp2\htdocs\dp-b2b\wp-content" add themes/dreampoint-b2b/inc/dev/class-dev-catalog-generator.php
git -C "C:\xampp2\htdocs\dp-b2b\wp-content" commit -m "feat(dev): add --phase=ugly to catalog generator — 10 curated edge-case products"
```

- [ ] **Step 5: Commit**

---

## Task 2: Generate full stress-test dataset

Run all four generation phases in sequence. Each command must complete without error before proceeding to the next.

**WP-CLI wrapper** (use for all commands below):
```powershell
& "C:\xampp2\php\php.exe" "C:\xampp2\htdocs\dp-b2b\wp-cli.phar" --path="C:\xampp2\htdocs\dp-b2b" <command>
```

### Step 1 — Generate taxonomies

```powershell
& "C:\xampp2\php\php.exe" "C:\xampp2\htdocs\dp-b2b\wp-cli.phar" --path="C:\xampp2\htdocs\dp-b2b" dp-b2b generate-catalog --phase=taxonomies
```

Expected: `Success: Taxonomies done — categories: N created, N skipped | brands: N created, N skipped.`

Acceptable: categories/brands already exist → all skipped (idempotent).

- [ ] **Step 1: Run taxonomy generation**

### Step 2 — Generate 700 simple products

```powershell
& "C:\xampp2\php\php.exe" "C:\xampp2\htdocs\dp-b2b\wp-cli.phar" --path="C:\xampp2\htdocs\dp-b2b" dp-b2b generate-catalog --phase=products --count=700
```

Expected: `Success: Products done — 700 created, 0 skipped.`

Log will show each SKU from `DEV-0001` to `DEV-0700` with stock status and price.

- [ ] **Step 2: Run simple product generation**

### Step 3 — Generate 40 variable products

```powershell
& "C:\xampp2\php\php.exe" "C:\xampp2\htdocs\dp-b2b\wp-cli.phar" --path="C:\xampp2\htdocs\dp-b2b" dp-b2b generate-catalog --phase=variables --count=40
```

Expected output summary:
```
Tier distribution — small: 20 | medium: 12 | stress: 8
Total variations — 732
Success: Variables done — 40 created, 0 skipped.
```

Explanation: 20 small × 5 vars = 100, 12 medium × 20 vars = 240, 8 stress × 49 vars = 392 → 732 total.

- [ ] **Step 3: Run variable product generation**

### Step 4 — Generate ugly edge-case products

```powershell
& "C:\xampp2\php\php.exe" "C:\xampp2\htdocs\dp-b2b\wp-cli.phar" --path="C:\xampp2\htdocs\dp-b2b" dp-b2b generate-catalog --phase=ugly
```

Expected:
```
  +     DEV-UGLY-001  [DEV] Artikal s Ekstremno Dugačkim Nazivom...
  +     DEV-UGLY-002  [DEV] Artikal: Čačak, Šibenik...
  +     DEV-UGLY-003  [DEV] Artikal <Poseban> & "Navodnici"...
  +     DEV-UGLY-004  [DEV] Artikal Mikro Cijena 004
  +     DEV-UGLY-005  [DEV] Artikal Makro Cijena 005
  +     DEV-UGLY-006  [DEV] Artikal Na Čekanju Bez Slike 006
  +     DEV-UGLY-007  [DEV] Artikal Bez Opisa i Slike 007
  +     DEV-UGLY-008  [1 var]   [DEV] Artikal Solo Varijanta 008
  +     DEV-UGLY-009  [27 vars]  [DEV] Artikal Gusta Atributa 009
  +     DEV-UGLY-010  [3 vars]   [DEV] Artikal Mješoviti Stok 010

Success: Ugly done — 7 simple, 3 variable (31 variations) created.
```

- [ ] **Step 4: Run ugly generation**

### Step 5 — Verify product counts via WP-CLI

```powershell
& "C:\xampp2\php\php.exe" "C:\xampp2\htdocs\dp-b2b\wp-cli.phar" --path="C:\xampp2\htdocs\dp-b2b" post list --post_type=product --post_status=publish --fields=ID --format=count
```

Expected: `750` (700 simple + 40 variable + 10 ugly = 750 product parents).

```powershell
& "C:\xampp2\php\php.exe" "C:\xampp2\htdocs\dp-b2b\wp-cli.phar" --path="C:\xampp2\htdocs\dp-b2b" post list --post_type=product_variation --post_status=publish --fields=ID --format=count
```

Expected: `763` (732 standard + 31 ugly variations).

- [ ] **Step 5: Verify product and variation counts**

---

## Task 3: Playwright regression verification

**Login:** Use `vis_full` / `TestVis2025!` — this user has full catalog visibility.

**Base URL:** `http://localhost:8080/dp-b2b`

**Quick Order URL:** `http://localhost:8080/dp-b2b/quick-order/`

For each area below: navigate, interact, report finding as one of:
- ✅ PASS — works correctly
- ⚠️ FRICTION — works but UX is weak
- ❌ REGRESSION — broken, must fix before ERP import

### Step 1 — Login as vis_full

```
navigate: http://localhost:8080/dp-b2b/wp-login.php
fill: #user_login = vis_full
fill: #user_pass = TestVis2025!
click: #wp-submit
wait for: URL contains /dp-b2b/ (not /wp-login)
```

- [ ] **Step 1: Login**

### Step 2 — Navigate to Quick Order and wait for initial load

```
navigate: http://localhost:8080/dp-b2b/quick-order/
wait for: selector .dp-qo-table tbody tr (at least 1 row visible)
take screenshot: quick-order-initial-load.png
```

Check: table renders, products visible, no JS console errors.

- [ ] **Step 2: Initial load check**

### Step 3 — Long name layout (DEV-UGLY-001)

```
search / filter to surface DEV-UGLY-001 (use SKU search or scroll to find it)
take screenshot: ugly-001-long-name.png
```

Check: product name is in one cell, does not break table layout, wraps or truncates cleanly. No horizontal scroll on desktop viewport (1280×800).

- [ ] **Step 3: Long name layout**

### Step 4 — Special character rendering (DEV-UGLY-002, DEV-UGLY-003)

```
locate rows for DEV-UGLY-002 and DEV-UGLY-003 in the table
take screenshot: ugly-002-unicode.png
take screenshot: ugly-003-html-chars.png
```

Check:
- DEV-UGLY-002: Croatian chars Č Ć Š Đ Ž display correctly — no ? or mojibake
- DEV-UGLY-003: `<`, `>`, `"`, `'` display as literal characters — no raw HTML tags visible, no broken cells

- [ ] **Step 4: Special character rendering**

### Step 5 — Thumbnail fallback (DEV-UGLY-006, DEV-UGLY-007)

```
locate rows for DEV-UGLY-006 and DEV-UGLY-007
take screenshot: ugly-006-007-thumbnail.png
```

Check: both rows show an image element. The image src should be a WooCommerce placeholder (e.g., `woocommerce-placeholder`), not a broken `<img>` with no src or 404 response.

- [ ] **Step 5: Thumbnail fallback**

### Step 6 — Price display at extremes (DEV-UGLY-004, DEV-UGLY-005)

```
locate rows for DEV-UGLY-004 and DEV-UGLY-005
take screenshot: ugly-004-005-prices.png
```

Check: DEV-UGLY-004 shows `0,01` (or similar locale-formatted), DEV-UGLY-005 shows `9.999,99`. Neither shows as `0` or blank.

- [ ] **Step 6: Price display at extremes**

### Step 7 — Solo variation CartSync (DEV-UGLY-008)

```
locate row for DEV-UGLY-008
expand variation row / open variation selector
verify: only 1 option available for Pack Size (1pc)
select: 1pc
set quantity: 2
trigger cart sync (tab out or click sync)
wait for: cart count to update
take screenshot: ugly-008-solo-variation.png
```

Check: cart count increments, no JS error, variation selector does not show empty state or "please choose" loop.

- [ ] **Step 7: Solo variation CartSync**

### Step 8 — Dense variation rendering (DEV-UGLY-009)

```
locate row for DEV-UGLY-009
expand variation selector
verify: 3 attribute dropdowns render (Velicina, Boja, Material)
select: Velicina=M, Boja=Bijela, Material=Vuna
set quantity: 1
trigger cart sync
wait for: cart update
take screenshot: ugly-009-dense-variation.png
```

Check: 3 dropdowns present, selection works, CartSync stable, no console errors.

- [ ] **Step 8: Dense variation CartSync**

### Step 9 — Mixed stock display (DEV-UGLY-010)

```
locate row for DEV-UGLY-010
expand variation selector (Tip attribute)
select: Tip=A → check stock badge shows instock
select: Tip=B → check stock badge shows outofstock
select: Tip=C → check stock badge shows onbackorder/na čekanju
take screenshot: ugly-010-mixed-stock.png
```

Check: stock badge updates dynamically per selected variation, correct state for each.

- [ ] **Step 9: Mixed stock display**

### Step 10 — Pagination integrity

```
navigate to page 2: http://localhost:8080/dp-b2b/quick-order/?qo_page=2
wait for: table rows to load
take screenshot: pagination-page2.png
navigate to page 3: http://localhost:8080/dp-b2b/quick-order/?qo_page=3
wait for: table rows to load
take screenshot: pagination-page3.png
```

Check: different products on each page, no duplicate SKUs between page 1 and 2, row count consistent with per-page setting.

- [ ] **Step 10: Pagination integrity**

### Step 11 — Mobile overflow (375px viewport)

```
browser resize to: width=375, height=812
navigate: http://localhost:8080/dp-b2b/quick-order/
wait for: table rows visible
take screenshot: mobile-375-initial.png
scroll to: row with DEV-UGLY-001 (long name)
take screenshot: mobile-375-long-name.png
```

Check: no horizontal scroll visible on body, long name row does not break layout, quantity input remains usable.

- [ ] **Step 11: Mobile overflow**

### Step 12 — Filter compatibility (brand filter)

```
browser resize to: width=1280, height=800
navigate: http://localhost:8080/dp-b2b/quick-order/
apply brand filter: select first available [DEV] brand
wait for: table to reload with filtered results
take screenshot: filter-brand-applied.png
```

Check: results update, ugly products assigned to that brand appear if expected, product count decreases, no console errors.

```
remove brand filter
wait for: table reloads to full set
take screenshot: filter-brand-cleared.png
```

Check: full set returns, count matches pre-filter count.

- [ ] **Step 12: Brand filter compatibility**

### Step 13 — Empty state scenario

```
apply a filter combination that produces 0 results
(e.g., brand = [DEV] ZenoBuild AND category = [DEV] Toneri i Tinte — use a brand/category combo with no overlap)
wait for: table to show empty state
take screenshot: empty-state.png
```

Check: empty state row renders, `colspan` covers all columns (no orphaned cells), no JS errors, pagination hides or shows "0 results" correctly.

- [ ] **Step 13: Empty state**

### Step 14 — Console cleanliness check

```
open browser console
navigate: http://localhost:8080/dp-b2b/quick-order/
interact: apply filter, change page, trigger cart sync, remove filter
take screenshot: console-after-interactions.png
```

Check: zero unhandled JS errors or warnings. Note any errors found.

- [ ] **Step 14: Console cleanliness**

---

## Task 4: Record findings and classify

After completing all Playwright steps, write a findings summary directly in chat covering:

1. **Generated dataset summary**
   - Exact counts: simple / variable / variations / ugly products
   - Confirm idempotency (re-running ugly phase skips existing SKUs)

2. **Real regressions** — broken behavior that MUST be fixed before ERP import
   - List each item: what failed, which SKU/scenario triggered it

3. **UX friction** — works but is uncomfortable or visually weak
   - List each item: describe the friction, affected scenario

4. **Theoretical edge cases** — did not break today but worth noting
   - List each: what could go wrong, under what conditions

5. **Future polish ideas** — nice-to-have improvements, low urgency
   - List each: brief description

6. **Clear before ERP import** — explicit list of items from categories 2 and 3 that should be resolved

---

## Cleanup (when done testing)

```powershell
& "C:\xampp2\php\php.exe" "C:\xampp2\htdocs\dp-b2b\wp-cli.phar" --path="C:\xampp2\htdocs\dp-b2b" dp-b2b reset-catalog
```

Expected: all generated products, variations, categories, and brands deleted. Ugly products cleaned up by the same `_dp_generated` tag as all others.

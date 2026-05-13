<?php
/**
 * Dev Catalog Generator
 *
 * WP-CLI command group for generating synthetic B2B catalog data.
 * Development and stress-testing only — never runs in production.
 *
 * All generated terms and products carry:
 *   - _dp_generated = 1      (meta key for cleanup targeting)
 *   - _dp_generation_batch   (YYYYMMDD_HHmm batch identifier)
 *   - [DEV] name prefix      (visual identification in WP admin)
 *
 * @package Dreampoint_B2B
 */

defined( 'ABSPATH' ) || exit;

class Dreampoint_B2B_Dev_Catalog_Generator extends WP_CLI_Command {

	const GENERATED_KEY     = '_dp_generated';
	const BATCH_KEY         = '_dp_generation_batch';
	const PRODUCT_CAP       = 1000;
	const CAT_CAP           = 100;
	const BRAND_CAP         = 100;
	const VAR_PRODUCT_CAP   = 50;
	const VAR_VARIATION_CAP = 50;

	private string $batch_id;

	public function __construct() {
		$this->batch_id = date( 'Ymd_Hi' );
	}

	// -------------------------------------------------------------------------
	// Commands
	// -------------------------------------------------------------------------

	/**
	 * Generate synthetic B2B catalog data for development and stress-testing.
	 *
	 * ## OPTIONS
	 *
	 * [--phase=<phase>]
	 * : Generation phase to run.
	 * ---
	 * default: taxonomies
	 * options:
	 *   - taxonomies
	 *   - products
	 *   - variables
	 *   - ugly
	 * ---
	 *
	 * [--count=<count>]
	 * : Number of items to generate. Defaults: products=200, variables=10. Hard caps: products=1000, variables=50.
	 *
	 * ## EXAMPLES
	 *
	 *     wp dp-b2b generate-catalog --phase=taxonomies
	 *     wp dp-b2b generate-catalog --phase=products
	 *     wp dp-b2b generate-catalog --phase=products --count=500
	 *     wp dp-b2b generate-catalog --phase=variables
	 *     wp dp-b2b generate-catalog --phase=variables --count=10
	 *
	 * @subcommand generate-catalog
	 * @when after_wp_load
	 */
	public function generate_catalog( array $args, array $assoc_args ): void {
		$this->guard_production();

		$phase = $assoc_args['phase'] ?? 'taxonomies';

		switch ( $phase ) {
			case 'taxonomies':
				$this->run_taxonomies();
				break;
			case 'products':
				$this->run_products( $assoc_args );
				break;
			case 'variables':
				$this->run_variables( $assoc_args );
				break;
			case 'ugly':
				$this->run_ugly();
				break;
			default:
				WP_CLI::error( "Unknown phase: {$phase}" );
		}
	}

	/**
	 * Remove all generated synthetic catalog data.
	 *
	 * Uses _dp_generated meta as the authoritative source of truth.
	 * Never touches real catalog data.
	 *
	 * ## OPTIONS
	 *
	 * [--batch=<batch_id>]
	 * : Remove only products/variations from a specific batch (YYYYMMDD_HHmm).
	 *   Taxonomy terms are NOT deleted in batch mode (terms are shared across batches).
	 *
	 * ## EXAMPLES
	 *
	 *     wp dp-b2b reset-catalog
	 *     wp dp-b2b reset-catalog --batch=20260511_1430
	 *
	 * @subcommand reset-catalog
	 * @when after_wp_load
	 */
	public function reset_catalog( array $args, array $assoc_args ): void {
		$this->guard_production();
		$batch = $assoc_args['batch'] ?? null;
		$this->run_reset( $batch );
	}

	// -------------------------------------------------------------------------
	// Production guard
	// -------------------------------------------------------------------------

	private function guard_production(): void {
		if ( defined( 'WP_ENVIRONMENT_TYPE' ) && WP_ENVIRONMENT_TYPE === 'production' ) {
			WP_CLI::error( 'Catalog generator is disabled in production.' );
		}
	}

	// -------------------------------------------------------------------------
	// Phase: taxonomies
	// -------------------------------------------------------------------------

	private function run_taxonomies(): void {
		WP_CLI::log( "Batch: {$this->batch_id}" );
		WP_CLI::log( '' );

		[ 'created' => $cat_created, 'skipped' => $cat_skipped ]     = $this->generate_categories();
		[ 'created' => $brand_created, 'skipped' => $brand_skipped ] = $this->generate_brands();

		WP_CLI::log( '' );
		WP_CLI::success( sprintf(
			'Taxonomies done — categories: %d created, %d skipped | brands: %d created, %d skipped.',
			$cat_created, $cat_skipped, $brand_created, $brand_skipped
		) );
	}

	/**
	 * @return array{created: int, skipped: int}
	 */
	private function generate_categories(): array {
		$structure = [
			'[DEV] Elektronika'        => [ '[DEV] Kabeli i Konektori',   '[DEV] Baterije i Napajanje',  '[DEV] LED Rasvjeta'        ],
			'[DEV] Alati i Oprema'     => [ '[DEV] Ručni Alati',          '[DEV] Električni Alati',      '[DEV] Mjerni Uređaji'      ],
			'[DEV] Kemikalije'         => [ '[DEV] Čistila i Detergenti', '[DEV] Maziva i Ulja',         '[DEV] Zaštitni Premazi'    ],
			'[DEV] Zaštitna Oprema'    => [ '[DEV] Radna Odjeća',         '[DEV] Zaštita Glave i Lica',  '[DEV] Zaštita Ruku'        ],
			'[DEV] Uredski Materijal'  => [ '[DEV] Papir i Karton',       '[DEV] Pisaći Pribor',         '[DEV] Toneri i Tinte'      ],
			'[DEV] Prehrambeni Dodaci' => [ '[DEV] Vitamini i Minerali',  '[DEV] Proteini',              '[DEV] Energetski Napitci'  ],
		];

		$created = 0;
		$skipped = 0;

		WP_CLI::log( 'Categories:' );

		foreach ( $structure as $parent_name => $children ) {
			$parent_result = $this->ensure_term( $parent_name, 'product_cat', 0 );
			$this->count_result( $parent_result['action'], $created, $skipped );
			$parent_id = $parent_result['term_id'];

			foreach ( $children as $child_name ) {
				$child_result = $this->ensure_term( $child_name, 'product_cat', $parent_id );
				$this->count_result( $child_result['action'], $created, $skipped );
			}
		}

		return [ 'created' => $created, 'skipped' => $skipped ];
	}

	/**
	 * @return array{created: int, skipped: int}
	 */
	private function generate_brands(): array {
		$brands = [
			'[DEV] AluTech',   '[DEV] BestForce',  '[DEV] CargoLine',
			'[DEV] DuraPro',   '[DEV] EcoShield',  '[DEV] FlexDrive',
			'[DEV] GripMax',   '[DEV] HiTech Pro', '[DEV] IsoCore',
			'[DEV] JetFlow',   '[DEV] KraftBau',   '[DEV] LumiTech',
			'[DEV] MaxiForce', '[DEV] NanoCoat',   '[DEV] OptiChem',
			'[DEV] ProGard',   '[DEV] QuickBuild', '[DEV] RigidForm',
			'[DEV] SafeGuard', '[DEV] TechBase',   '[DEV] UniPower',
			'[DEV] VoltEdge',  '[DEV] WeldMaster', '[DEV] XtraWeld',
			'[DEV] YieldMax',  '[DEV] ZenoBuild',  '[DEV] AlphaGrade',
			'[DEV] BetaWorks', '[DEV] ClearFlow',  '[DEV] DeltaForge',
		];

		$created = 0;
		$skipped = 0;

		WP_CLI::log( 'Brands:' );

		foreach ( $brands as $brand_name ) {
			$r = $this->ensure_term( $brand_name, 'product_brand', 0 );
			$this->count_result( $r['action'], $created, $skipped );
		}

		return [ 'created' => $created, 'skipped' => $skipped ];
	}

	// -------------------------------------------------------------------------
	// Phase: products
	// -------------------------------------------------------------------------

	private function run_products( array $assoc_args ): void {
		$count = (int) ( $assoc_args['count'] ?? 200 );

		if ( $count < 1 ) {
			WP_CLI::error( 'Count must be at least 1.' );
		}
		if ( $count > self::PRODUCT_CAP ) {
			WP_CLI::error( sprintf( 'Count %d exceeds hard cap of %d.', $count, self::PRODUCT_CAP ) );
		}

		WP_CLI::log( "Batch: {$this->batch_id}" );
		WP_CLI::log( "Generating {$count} simple products..." );
		WP_CLI::log( '' );

		[ 'created' => $created, 'skipped' => $skipped, 'stock' => $stock_summary ] = $this->generate_simple_products( $count );

		WP_CLI::log( '' );
		WP_CLI::log( sprintf(
			'Stock distribution — instock: %d | low stock: %d | outofstock: %d | backorder: %d',
			$stock_summary['instock'], $stock_summary['low_stock'],
			$stock_summary['outofstock'], $stock_summary['backorder']
		) );
		WP_CLI::success( sprintf( 'Products done — %d created, %d skipped.', $created, $skipped ) );
	}

	/**
	 * @return array{created: int, skipped: int, stock: array{instock: int, low_stock: int, outofstock: int, backorder: int}}
	 */
	private function generate_simple_products( int $count ): array {
		$cat_ids   = $this->get_generated_child_cat_ids();
		$brand_ids = $this->get_generated_brand_ids();

		if ( empty( $cat_ids ) ) {
			WP_CLI::error( 'No generated categories found. Run --phase=taxonomies first.' );
		}
		if ( empty( $brand_ids ) ) {
			WP_CLI::error( 'No generated brands found. Run --phase=taxonomies first.' );
		}

		$cat_count   = count( $cat_ids );
		$brand_count = count( $brand_ids );
		$created     = 0;
		$skipped     = 0;
		$stock_tally = [ 'instock' => 0, 'low_stock' => 0, 'outofstock' => 0, 'backorder' => 0 ];

		for ( $i = 1; $i <= $count; $i++ ) {
			$sku = sprintf( 'DEV-%04d', $i );

			if ( wc_get_product_id_by_sku( $sku ) ) {
				WP_CLI::log( "  skip  {$sku}" );
				$skipped++;
				continue;
			}

			// Deterministic assignment — cycles evenly across all categories and brands.
			$cat_id   = $cat_ids[ ( $i - 1 ) % $cat_count ];
			$brand_id = $brand_ids[ ( $i - 1 ) % $brand_count ];
			$cat_name = $this->clean_dev_name( get_term( $cat_id, 'product_cat' )->name ?? 'Artikal' );

			$seed  = abs( crc32( $sku ) );
			$stock = $this->deterministic_stock( $seed );
			$price = $this->deterministic_price( $seed );
			$title = sprintf( '[DEV] %s — %04d', $cat_name, $i );

			$product = new WC_Product_Simple();
			$product->set_name( $title );
			$product->set_sku( $sku );
			$product->set_regular_price( $price );
			$product->set_status( 'publish' );
			$product->set_catalog_visibility( 'visible' );
			$product->set_category_ids( [ $cat_id ] );
			$product->set_manage_stock( $stock['managed'] );
			$product->set_stock_status( $stock['status'] );

			if ( $stock['managed'] && $stock['qty'] !== null ) {
				$product->set_stock_quantity( $stock['qty'] );
			}
			if ( $stock['backorder'] ) {
				$product->set_backorders( 'yes' );
			}

			$id = $product->save();

			if ( ! $id ) {
				WP_CLI::warning( "  FAIL  {$sku}" );
				continue;
			}

			wp_set_object_terms( $id, [ $brand_id ], 'product_brand' );
			update_post_meta( $id, self::GENERATED_KEY, 1 );
			update_post_meta( $id, self::BATCH_KEY, $this->batch_id );

			WP_CLI::log( sprintf( '  +     %s  %s  %s  %s EUR', $sku, str_pad( $stock['status'], 12 ), str_pad( $price, 8 ), $title ) );

			$this->count_result( 'created', $created, $skipped );

			if ( $stock['status'] === 'instock' && $stock['qty'] !== null && $stock['qty'] <= 4 ) {
				$stock_tally['low_stock']++;
			} elseif ( $stock['status'] === 'instock' ) {
				$stock_tally['instock']++;
			} elseif ( $stock['status'] === 'outofstock' ) {
				$stock_tally['outofstock']++;
			} else {
				$stock_tally['backorder']++;
			}
		}

		return [ 'created' => $created, 'skipped' => $skipped, 'stock' => $stock_tally ];
	}

	// -------------------------------------------------------------------------
	// Deterministic randomness (seeded per SKU)
	// -------------------------------------------------------------------------

	/**
	 * @return array{status: string, managed: bool, qty: int|null, backorder: bool}
	 */
	private function deterministic_stock( int $seed ): array {
		mt_srand( $seed );
		$roll = mt_rand( 1, 10 );

		if ( $roll <= 6 ) {
			// 60% — normal in-stock, qty 5–100
			return [ 'status' => 'instock', 'managed' => true, 'qty' => mt_rand( 5, 100 ), 'backorder' => false ];
		} elseif ( $roll <= 8 ) {
			// 20% — low stock, qty 1–4
			return [ 'status' => 'instock', 'managed' => true, 'qty' => mt_rand( 1, 4 ), 'backorder' => false ];
		} elseif ( $roll <= 9 ) {
			// 10% — out of stock
			return [ 'status' => 'outofstock', 'managed' => true, 'qty' => 0, 'backorder' => false ];
		} else {
			// 10% — on backorder
			return [ 'status' => 'onbackorder', 'managed' => false, 'qty' => null, 'backorder' => true ];
		}
	}

	private function deterministic_price( int $seed ): string {
		// Offset seed to produce independent value from stock seed.
		mt_srand( $seed + 7919 );
		$tier = mt_rand( 1, 10 );

		if ( $tier <= 3 ) {
			$cents = mt_rand( 299, 1999 );   // 2.99 – 19.99
		} elseif ( $tier <= 8 ) {
			$cents = mt_rand( 2000, 9999 );  // 20.00 – 99.99
		} else {
			$cents = mt_rand( 10000, 49999 ); // 100.00 – 499.99
		}

		return number_format( $cents / 100, 2, '.', '' );
	}

	// -------------------------------------------------------------------------
	// Term ID lookups (called once per run, results used throughout loop)
	// -------------------------------------------------------------------------

	/** @return int[] Child category IDs (parent > 0) marked _dp_generated. */
	private function get_generated_child_cat_ids(): array {
		global $wpdb;
		// Direct SQL: bypasses get_terms filter hooks (visibility engine filters unauthenticated WP-CLI user to []).
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT t.term_id FROM {$wpdb->terms} t
			 INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
			 INNER JOIN {$wpdb->termmeta} tm ON t.term_id = tm.term_id
			 WHERE tt.taxonomy = %s AND tt.parent > 0
			   AND tm.meta_key = %s AND tm.meta_value = %s",
			'product_cat',
			self::GENERATED_KEY,
			'1'
		) );
		return array_map( 'intval', $ids ?: [] );
	}

	/** @return int[] Brand term IDs marked _dp_generated. */
	private function get_generated_brand_ids(): array {
		global $wpdb;
		// Direct SQL: bypasses get_terms filter hooks (visibility engine filters unauthenticated WP-CLI user to []).
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT t.term_id FROM {$wpdb->terms} t
			 INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
			 INNER JOIN {$wpdb->termmeta} tm ON t.term_id = tm.term_id
			 WHERE tt.taxonomy = %s
			   AND tm.meta_key = %s AND tm.meta_value = %s",
			'product_brand',
			self::GENERATED_KEY,
			'1'
		) );
		return array_map( 'intval', $ids ?: [] );
	}

	// -------------------------------------------------------------------------
	// Phase: variables
	// -------------------------------------------------------------------------

	private function run_variables( array $assoc_args ): void {
		$count = (int) ( $assoc_args['count'] ?? 10 );

		if ( $count < 1 ) {
			WP_CLI::error( 'Count must be at least 1.' );
		}
		if ( $count > self::VAR_PRODUCT_CAP ) {
			WP_CLI::error( sprintf( 'Count %d exceeds hard cap of %d.', $count, self::VAR_PRODUCT_CAP ) );
		}

		WP_CLI::log( "Batch: {$this->batch_id}" );
		WP_CLI::log( "Generating {$count} variable products..." );
		WP_CLI::log( '' );

		$result = $this->generate_variable_products( $count );

		WP_CLI::log( '' );
		WP_CLI::log( sprintf(
			'Tier distribution — small: %d | medium: %d | stress: %d',
			$result['tiers']['small'], $result['tiers']['medium'], $result['tiers']['stress']
		) );
		WP_CLI::log( sprintf( 'Total variations — %d', $result['total_variations'] ) );
		WP_CLI::log( sprintf(
			'Variation stock — instock: %d | low stock: %d | outofstock: %d | backorder: %d',
			$result['stock']['instock'], $result['stock']['low_stock'],
			$result['stock']['outofstock'], $result['stock']['backorder']
		) );
		WP_CLI::success( sprintf( 'Variables done — %d created, %d skipped.', $result['created'], $result['skipped'] ) );
	}

	/**
	 * @return array{created: int, skipped: int, total_variations: int, tiers: array{small: int, medium: int, stress: int}, stock: array{instock: int, low_stock: int, outofstock: int, backorder: int}}
	 */
	private function generate_variable_products( int $count ): array {
		$cat_ids   = $this->get_generated_child_cat_ids();
		$brand_ids = $this->get_generated_brand_ids();

		if ( empty( $cat_ids ) ) {
			WP_CLI::error( 'No generated categories found. Run --phase=taxonomies first.' );
		}
		if ( empty( $brand_ids ) ) {
			WP_CLI::error( 'No generated brands found. Run --phase=taxonomies first.' );
		}

		$cat_count   = count( $cat_ids );
		$brand_count = count( $brand_ids );
		$created     = 0;
		$skipped     = 0;
		$total_vars  = 0;
		$tiers       = [ 'small' => 0, 'medium' => 0, 'stress' => 0 ];
		$stock_tally = [ 'instock' => 0, 'low_stock' => 0, 'outofstock' => 0, 'backorder' => 0 ];

		for ( $i = 1; $i <= $count; $i++ ) {
			$sku = sprintf( 'DEV-VAR-%03d', $i );

			if ( wc_get_product_id_by_sku( $sku ) ) {
				WP_CLI::log( "  skip  {$sku}" );
				$skipped++;
				continue;
			}

			$cat_id     = $cat_ids[ ( $i - 1 ) % $cat_count ];
			$brand_id   = $brand_ids[ ( $i - 1 ) % $brand_count ];
			$cat_name   = $this->clean_dev_name( get_term( $cat_id, 'product_cat' )->name ?? 'Artikal' );
			$tier       = $this->resolve_tier( $i, $count );
			$combos     = $this->get_tier_combinations( $tier );
			$var_count  = count( $combos );
			$title      = sprintf( '[DEV] %s — VAR %03d', $cat_name, $i );
			$base_seed  = abs( crc32( $sku ) );
			$base_price = $this->deterministic_price( $base_seed );

			$product = new WC_Product_Variable();
			$product->set_name( $title );
			$product->set_sku( $sku );
			$product->set_status( 'publish' );
			$product->set_catalog_visibility( 'visible' );
			$product->set_category_ids( [ $cat_id ] );
			$product->set_attributes( $this->build_product_attributes( $tier ) );

			$product_id = $product->save();

			if ( ! $product_id ) {
				WP_CLI::warning( "  FAIL  {$sku}" );
				continue;
			}

			wp_set_object_terms( $product_id, [ $brand_id ], 'product_brand' );
			update_post_meta( $product_id, self::GENERATED_KEY, 1 );
			update_post_meta( $product_id, self::BATCH_KEY, $this->batch_id );

			foreach ( $combos as $j => $combo ) {
				$var_sku   = sprintf( '%s-%02d', $sku, $j + 1 );
				$var_seed  = abs( crc32( $var_sku ) );
				$stock     = $this->deterministic_stock( $var_seed );
				$var_price = $this->deterministic_variation_price( $var_sku, $base_price );

				$variation = new WC_Product_Variation();
				$variation->set_parent_id( $product_id );
				$variation->set_attributes( $this->build_variation_attributes( $combo ) );
				$variation->set_sku( $var_sku );
				$variation->set_regular_price( $var_price );
				$variation->set_manage_stock( $stock['managed'] );
				$variation->set_stock_status( $stock['status'] );

				if ( $stock['managed'] && $stock['qty'] !== null ) {
					$variation->set_stock_quantity( $stock['qty'] );
				}
				if ( $stock['backorder'] ) {
					$variation->set_backorders( 'yes' );
				}

				$var_id = $variation->save();

				if ( ! $var_id ) {
					continue;
				}

				update_post_meta( $var_id, self::GENERATED_KEY, 1 );
				update_post_meta( $var_id, self::BATCH_KEY, $this->batch_id );
				$total_vars++;

				if ( $stock['status'] === 'instock' && $stock['qty'] !== null && $stock['qty'] <= 4 ) {
					$stock_tally['low_stock']++;
				} elseif ( $stock['status'] === 'instock' ) {
					$stock_tally['instock']++;
				} elseif ( $stock['status'] === 'outofstock' ) {
					$stock_tally['outofstock']++;
				} else {
					$stock_tally['backorder']++;
				}
			}

			WC_Product_Variable::sync( $product_id );

			$tiers[ $tier ]++;
			$created++;

			WP_CLI::log( sprintf(
				'  +     %s  [%-6s]  %2d vars   %s',
				$sku, $tier, $var_count, $title
			) );
		}

		return [
			'created'          => $created,
			'skipped'          => $skipped,
			'total_variations' => $total_vars,
			'tiers'            => $tiers,
			'stock'            => $stock_tally,
		];
	}

	/** Attribute config (name + option list) per variation tier. */
	private function variation_tier_data(): array {
		return [
			'small'  => [
				[ 'name' => 'Pack Size', 'options' => [ '1pc', '5pcs', '10pcs', '25pcs', '50pcs' ] ],
			],
			'medium' => [
				[ 'name' => 'Size',  'options' => [ 'S', 'M', 'L', 'XL', 'XXL' ] ],
				[ 'name' => 'Color', 'options' => [ 'Black', 'White', 'Blue', 'Red' ] ],
			],
			'stress' => [
				[ 'name' => 'Size',  'options' => [ 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL' ] ],
				[ 'name' => 'Color', 'options' => [ 'Black', 'White', 'Blue', 'Red', 'Yellow', 'Green', 'Silver' ] ],
			],
		];
	}

	/** 50% → small (5 vars) | 30% → medium (20 vars) | 20% → stress (49 vars). */
	private function resolve_tier( int $product_num, int $total ): string {
		$ratio = $product_num / $total;
		if ( $ratio <= 0.5 ) return 'small';
		if ( $ratio <= 0.8 ) return 'medium';
		return 'stress';
	}

	/**
	 * Cartesian product of all attribute option arrays for the given tier.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function get_tier_combinations( string $tier ): array {
		$combos = [ [] ];

		foreach ( $this->variation_tier_data()[ $tier ] as $attr ) {
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

	/**
	 * Builds WC_Product_Attribute objects for the variable product (local attributes, no taxonomy).
	 *
	 * @return WC_Product_Attribute[]  Keyed by sanitize_title(name).
	 */
	private function build_product_attributes( string $tier ): array {
		$attrs = [];

		foreach ( $this->variation_tier_data()[ $tier ] as $position => $cfg ) {
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
	 * Maps one combination to the format WC_Product_Variation::set_attributes() expects:
	 * keyed by sanitize_title(attr_name) (WC prefixes with 'attribute_' on save).
	 *
	 * @param  array<string, string> $combo
	 * @return array<string, string>
	 */
	private function build_variation_attributes( array $combo ): array {
		$attrs = [];
		foreach ( $combo as $name => $value ) {
			$attrs[ sanitize_title( $name ) ] = $value;
		}
		return $attrs;
	}

	/** Price ±15% delta around base price, seeded deterministically per variation SKU. */
	private function deterministic_variation_price( string $var_sku, string $base_price ): string {
		mt_srand( abs( crc32( $var_sku . '_price' ) ) );
		$base      = (float) $base_price;
		$delta_pct = mt_rand( -15, 15 );
		$price     = $base * ( 1.0 + $delta_pct / 100.0 );
		return number_format( max( 0.99, $price ), 2, '.', '' );
	}

	// -------------------------------------------------------------------------
	// Phase: reset
	// -------------------------------------------------------------------------

	private function run_reset( ?string $batch ): void {
		WP_CLI::log( $batch
			? "Resetting generated catalog data (batch: {$batch})..."
			: 'Resetting ALL generated catalog data...'
		);
		WP_CLI::log( '' );

		[ $variation_ids, $product_ids ] = $this->get_generated_product_ids( $batch );

		$var_deleted     = $this->delete_generated_posts( $variation_ids );
		$product_deleted = $this->delete_generated_posts( $product_ids );

		WP_CLI::log( sprintf( '  Variations:  %d deleted.', $var_deleted ) );
		WP_CLI::log( sprintf( '  Products:    %d deleted.', $product_deleted ) );

		$cat_deleted   = 0;
		$brand_deleted = 0;

		if ( $batch === null ) {
			$cat_ids   = $this->get_generated_term_ids( 'product_cat' );
			$brand_ids = $this->get_generated_term_ids( 'product_brand' );

			$cat_deleted   = $this->delete_generated_terms( $cat_ids, 'product_cat' );
			$brand_deleted = $this->delete_generated_terms( $brand_ids, 'product_brand' );

			WP_CLI::log( sprintf( '  Categories:  %d deleted.', $cat_deleted ) );
			WP_CLI::log( sprintf( '  Brands:      %d deleted.', $brand_deleted ) );
		} else {
			WP_CLI::log( '  Taxonomies:  skipped (batch mode — terms shared across batches).' );
		}

		WP_CLI::log( '' );
		WP_CLI::success( sprintf(
			'Reset done — %d variations, %d products, %d categories, %d brands deleted.',
			$var_deleted, $product_deleted, $cat_deleted, $brand_deleted
		) );
	}

	/**
	 * Finds generated product and variation IDs.
	 * Variations are returned first so callers can delete children before parents.
	 *
	 * @return array{int[], int[]}  [variation_ids, product_ids]
	 */
	private function get_generated_product_ids( ?string $batch ): array {
		global $wpdb;

		if ( $batch !== null ) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT p.ID, p.post_type
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id
				 INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
				 WHERE pm1.meta_key = %s AND pm1.meta_value = %s
				   AND pm2.meta_key = %s AND pm2.meta_value = %s
				   AND p.post_type IN ('product', 'product_variation')",
				self::GENERATED_KEY, '1',
				self::BATCH_KEY, $batch
			) );
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT p.ID, p.post_type
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s AND pm.meta_value = %s
				   AND p.post_type IN ('product', 'product_variation')",
				self::GENERATED_KEY, '1'
			) );
		}

		$variation_ids = [];
		$product_ids   = [];

		foreach ( (array) $rows as $row ) {
			if ( $row->post_type === 'product_variation' ) {
				$variation_ids[] = (int) $row->ID;
			} else {
				$product_ids[] = (int) $row->ID;
			}
		}

		return [ $variation_ids, $product_ids ];
	}

	/** @param int[] $ids */
	private function delete_generated_posts( array $ids ): int {
		$deleted = 0;
		foreach ( $ids as $id ) {
			if ( wp_delete_post( $id, true ) ) {
				$deleted++;
			}
		}
		return $deleted;
	}

	/** @return int[] Term IDs marked _dp_generated for the given taxonomy. */
	private function get_generated_term_ids( string $taxonomy ): array {
		global $wpdb;
		// Children first (parent > 0) so wp_delete_term() re-parenting is predictable.
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT t.term_id FROM {$wpdb->terms} t
			 INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
			 INNER JOIN {$wpdb->termmeta} tm ON t.term_id = tm.term_id
			 WHERE tt.taxonomy = %s
			   AND tm.meta_key = %s AND tm.meta_value = %s
			 ORDER BY tt.parent DESC",
			$taxonomy,
			self::GENERATED_KEY,
			'1'
		) );
		return array_map( 'intval', $ids ?: [] );
	}

	/**
	 * @param int[]  $ids
	 * @param string $taxonomy
	 */
	private function delete_generated_terms( array $ids, string $taxonomy ): int {
		$deleted = 0;
		foreach ( $ids as $id ) {
			$result = wp_delete_term( $id, $taxonomy );
			if ( $result !== false && ! is_wp_error( $result ) ) {
				$deleted++;
			}
		}
		return $deleted;
	}

	// -------------------------------------------------------------------------
	// Phase 1 helpers (unchanged)
	// -------------------------------------------------------------------------

	/**
	 * Creates a taxonomy term if it does not exist. Slug-based idempotency.
	 *
	 * @return array{action: 'created'|'skipped'|'failed', term_id: int}
	 */
	private function ensure_term( string $name, string $taxonomy, int $parent = 0 ): array {
		$slug     = sanitize_title( $name );
		$existing = get_term_by( 'slug', $slug, $taxonomy );

		if ( $existing instanceof WP_Term ) {
			WP_CLI::log( "  skip  {$name}" );
			return [ 'action' => 'skipped', 'term_id' => $existing->term_id ];
		}

		$insert_args = [ 'slug' => $slug ];
		if ( $parent > 0 ) {
			$insert_args['parent'] = $parent;
		}

		$result = wp_insert_term( $name, $taxonomy, $insert_args );

		if ( is_wp_error( $result ) ) {
			WP_CLI::warning( "  FAIL  {$name}: " . $result->get_error_message() );
			return [ 'action' => 'failed', 'term_id' => 0 ];
		}

		$term_id = (int) $result['term_id'];
		add_term_meta( $term_id, self::GENERATED_KEY, 1, true );
		add_term_meta( $term_id, self::BATCH_KEY, $this->batch_id, true );

		WP_CLI::log( "  +     {$name} (id={$term_id})" );

		return [ 'action' => 'created', 'term_id' => $term_id ];
	}

	private function clean_dev_name( string $name ): string {
		return trim( str_replace( '[DEV] ', '', $name ) );
	}

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
				WP_CLI::log( '  +     DEV-UGLY-009  [27 vars]  [DEV] Artikal Gusta Atributa 009' );
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

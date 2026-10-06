<?php
defined( 'ABSPATH' ) || exit;

/**
 * Server-authoritative validation of parsed Excel/CSV import rows. READ-ONLY.
 *
 * The browser parses the file and sends untrusted `{row, identifier, quantity}` triples. This class
 * derives every authoritative value itself — it never trusts a browser-supplied product/variation ID,
 * name, stock figure or final quantity — and never mutates the cart, stock or any other state.
 *
 * Pipeline (the order is security-critical, see docs: no stock/name/type/ambiguity information is read
 * or derived for a candidate before the B2B visibility check has passed):
 *   1. syntactic row parsing (identifier shape, strict quantity)
 *   2. ONE batch lookup of both `_ARTICLE_CODE` and `_sku` (published unit + published parent)
 *   3. B2B authorization on the unit's parent (`dp_b2b_product_accessible`, same contract as /cart/sync)
 *   4. ONLY among authorized candidates: ambiguity → variable parent → purchasable
 *   5. duplicate rows merged per orderable unit (first occurrence order), cap re-checked
 *   6. ONLY now: stock / orderable-quantity clamp (WooCommerce APIs, current cart + earlier import rows)
 *   7. product name and other display data for authorized rows only
 *
 * Identifier contract (Strategy C): trimmed, case-insensitive (DB collation), no fuzzy matching, no
 * zero-padding, no numeric coercion, no precedence between the two namespaces. A row is accepted only
 * when its identifier resolves to exactly ONE authorized orderable unit.
 *
 * Result per row group: status `ready` | `adjusted` | `error` plus a stable machine-readable code.
 * Hidden / nonexistent / unpublished / orphan identifiers are all `identifier_not_found`.
 *
 * Validation is NOT a reservation: the final cart write still goes through /cart/sync, which validates
 * again.
 */
class DP_Quick_Order_Import_Validator {

	const STATUS_READY    = 'ready';
	const STATUS_ADJUSTED = 'adjusted';
	const STATUS_ERROR    = 'error';

	// Row-level (syntactic) codes — no catalog information involved.
	const CODE_INVALID_IDENTIFIER = 'invalid_identifier';
	const CODE_INVALID_QUANTITY   = 'invalid_quantity';
	const CODE_QUANTITY_LIMIT     = 'quantity_limit';

	// Resolution codes. `identifier_not_found` deliberately covers nonexistent, unpublished, orphan AND
	// inaccessible identifiers — one public result, no existence oracle.
	const CODE_NOT_FOUND = 'identifier_not_found';
	const CODE_AMBIGUOUS = 'ambiguous_identifier';

	// Codes below are only ever produced for an AUTHORIZED unit.
	const CODE_VARIABLE_PARENT = 'variable_parent';
	const CODE_NOT_PURCHASABLE = 'not_purchasable';
	const CODE_UNAVAILABLE     = 'unavailable';

	/**
	 * @param array<int, mixed> $raw_rows  Decoded JSON rows: {row, identifier, quantity}.
	 * @return array{rows: list<array<string, mixed>>, summary: array<string, int>}
	 */
	public function validate( array $raw_rows, int $user_id ): array {
		$entries = $this->parse_entries( $raw_rows );

		// 2. Batch candidate lookup (one query for every identifier in the file).
		$identifiers = [];
		foreach ( $entries as $entry ) {
			if ( null === $entry['code'] ) {
				$identifiers[ $entry['key'] ] = $entry['identifier'];
			}
		}
		$candidates = $identifiers ? $this->lookup_candidates( array_values( $identifiers ) ) : [];

		// 3. Authorization — BEFORE ambiguity, type, purchasability, stock or name.
		$authorized = $this->filter_authorized( $candidates, $user_id );

		// Hydrate the (authorized, unambiguous) units in batches instead of one post/meta/term query per row.
		$this->prime_units( $authorized );

		// 4. Resolve each entry to exactly one authorized unit, merging duplicate units.
		$units = []; // unit_key => group
		foreach ( $entries as $index => $entry ) {
			if ( null !== $entry['code'] ) {
				continue;
			}

			$matches = $authorized[ $entry['key'] ] ?? [];
			if ( ! $matches ) {
				$entries[ $index ]['code'] = self::CODE_NOT_FOUND;
				continue;
			}
			if ( count( $matches ) > 1 ) {
				// Ambiguity is judged among AUTHORIZED candidates only, so it cannot reveal a hidden unit.
				$entries[ $index ]['code'] = self::CODE_AMBIGUOUS;
				continue;
			}

			$unit_id = (int) array_key_first( $matches );
			$product = wc_get_product( $unit_id );
			if ( ! $product instanceof WC_Product ) {
				$entries[ $index ]['code'] = self::CODE_NOT_FOUND;
				continue;
			}

			// An authorized parent of variations is not an orderable unit; never pick a variation for it.
			if ( $product->is_type( 'variable' ) ) {
				$entries[ $index ]['code'] = self::CODE_VARIABLE_PARENT;
				$entries[ $index ]['name'] = $this->display_name( $product );
				continue;
			}

			$is_variation = $product instanceof WC_Product_Variation;
			$product_id   = $is_variation ? (int) $product->get_parent_id() : $unit_id;
			$variation_id = $is_variation ? $unit_id : 0;

			if ( ! $product->is_purchasable() ) {
				$entries[ $index ]['code'] = self::CODE_NOT_PURCHASABLE;
				$entries[ $index ]['name'] = $this->display_name( $product );
				continue;
			}

			$unit_key = $product_id . '_' . $variation_id;
			if ( ! isset( $units[ $unit_key ] ) ) {
				$units[ $unit_key ] = [
					'product'      => $product,
					'product_id'   => $product_id,
					'variation_id' => $variation_id,
					'first_index'  => $index,
					'rows'         => [],
					'identifier'   => $entry['identifier'],
					'requested'    => 0,
				];
			}
			$units[ $unit_key ]['rows'][]     = $entry['row'];
			$units[ $unit_key ]['requested'] += (int) $entry['requested'];
			$entries[ $index ]['unit_key']    = $unit_key;
		}

		// 5–6. Stock normalization per merged unit, in first-occurrence order.
		$outcomes = $this->normalize_quantities( $units );

		// 7. Assemble the response in first-occurrence order.
		$rows    = [];
		$summary = [ self::STATUS_READY => 0, self::STATUS_ADJUSTED => 0, self::STATUS_ERROR => 0 ];

		foreach ( $entries as $index => $entry ) {
			if ( isset( $entry['unit_key'] ) ) {
				$unit_key = $entry['unit_key'];
				if ( $units[ $unit_key ]['first_index'] !== $index ) {
					continue; // merged into the group emitted at its first occurrence
				}
				$rows[] = $this->unit_row( $units[ $unit_key ], $outcomes[ $unit_key ] );
			} else {
				$rows[] = $this->error_row( $entry );
			}
			++$summary[ end( $rows )['status'] ];
		}

		$summary['input_rows'] = count( $entries );

		return [
			'rows'    => $rows,
			'summary' => $summary,
		];
	}

	// ── 1. Syntactic parsing ──────────────────────────────────────────────────

	/**
	 * @param array<int, mixed> $raw_rows
	 * @return list<array{row:int, identifier:string, key:string, requested:?int, code:?string, name:?string}>
	 */
	private function parse_entries( array $raw_rows ): array {
		$entries = [];
		$seq     = 0;

		foreach ( array_values( $raw_rows ) as $raw ) {
			++$seq;
			$raw = is_array( $raw ) ? $raw : [];

			// The row number is only a label for the user; it carries no authority.
			$row = isset( $raw['row'] ) && is_int( $raw['row'] ) && $raw['row'] > 0 ? $raw['row'] : $seq;

			$identifier = $this->normalize_identifier( $raw['identifier'] ?? '' );
			$code       = null;
			if ( '' === $identifier
				|| mb_strlen( $identifier, 'UTF-8' ) > DP_Quick_Order_Config::IMPORT_MAX_IDENTIFIER_LENGTH
				|| preg_match( '/[\x00-\x1F\x7F]/', $identifier )
			) {
				$code = self::CODE_INVALID_IDENTIFIER;
			}

			$requested = null;
			$q_code    = $this->parse_quantity( $raw['quantity'] ?? null, $requested );
			if ( null === $code ) {
				$code = $q_code;
			}

			$entries[] = [
				'row'        => $row,
				// Echoed back to the user's own screen; length-bounded so a hostile payload cannot bloat the response.
				'identifier' => mb_substr( $identifier, 0, DP_Quick_Order_Config::IMPORT_MAX_IDENTIFIER_LENGTH + 1, 'UTF-8' ),
				'key'        => mb_strtolower( $identifier, 'UTF-8' ),
				'requested'  => $requested,
				'code'       => $code,
				'name'       => null,
			];
		}

		return $entries;
	}

	/**
	 * Conservative normalization: trim surrounding whitespace (incl. NBSP and a stray BOM). Nothing else.
	 */
	private function normalize_identifier( mixed $value ): string {
		if ( is_int( $value ) ) {
			$value = (string) $value;
		}
		if ( ! is_string( $value ) ) {
			return '';
		}
		return (string) preg_replace( '/^[\s\x{00A0}\x{FEFF}]+|[\s\x{00A0}\x{FEFF}]+$/u', '', $value );
	}

	/**
	 * Strict positive whole number. No rounding, no coercion: blank, zero, negative, decimal, scientific
	 * notation, thousands separators, leading zeros, floats and arbitrary strings are all rejected.
	 *
	 * @param int|null $out Parsed quantity (set only on success).
	 * @return string|null  Error code, or null when valid.
	 */
	private function parse_quantity( mixed $value, ?int &$out ): ?string {
		$out = null;

		if ( is_int( $value ) ) {
			$text = (string) $value;
		} elseif ( is_string( $value ) ) {
			$text = $this->normalize_identifier( $value );
		} else {
			return self::CODE_INVALID_QUANTITY;
		}

		if ( ! preg_match( '/^[1-9][0-9]*$/', $text ) ) {
			return self::CODE_INVALID_QUANTITY;
		}

		// Digit-count guard first: nothing near PHP_INT_MAX is ever cast.
		if ( strlen( $text ) > 9 || (int) $text > DP_Quick_Order_Config::IMPORT_MAX_QUANTITY ) {
			return self::CODE_QUANTITY_LIMIT;
		}

		$out = (int) $text;
		return null;
	}

	// ── 2. Batch lookup ───────────────────────────────────────────────────────

	/**
	 * One query for the whole file. Returns only PUBLISHED units; a variation additionally needs a
	 * published parent product. Matching is exact and relies on the DB collation (case-insensitive);
	 * the PHP side re-keys with the same trim + lowercase normalization, so any looser DB match (e.g.
	 * accent folding) that does not equal the input after normalization is dropped — fail closed.
	 *
	 * @param list<string> $identifiers Normalized identifiers (not lowercased).
	 * @return array<string, array<int, array{parent:int, type:string}>> lowercase identifier => unit_id => meta
	 */
	private function lookup_candidates( array $identifiers ): array {
		global $wpdb;

		$identifiers  = array_values( array_unique( $identifiers ) );
		$placeholders = implode( ',', array_fill( 0, count( $identifiers ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names and %s placeholder list only; every value goes through prepare().
		$sql = $wpdb->prepare(
			"SELECT pm.post_id AS unit_id, p.post_type AS unit_type, p.post_parent AS parent_id, pm.meta_value AS identifier
			   FROM {$wpdb->postmeta} pm
			  INNER JOIN {$wpdb->posts} p  ON p.ID = pm.post_id
			   LEFT JOIN {$wpdb->posts} pp ON pp.ID = p.post_parent AND p.post_type = 'product_variation'
			  WHERE pm.meta_key IN ( '_ARTICLE_CODE', '_sku' )
			    AND pm.meta_value IN ( {$placeholders} )
			    AND p.post_status = 'publish'
			    AND ( p.post_type = 'product'
			          OR ( p.post_type = 'product_variation' AND pp.post_type = 'product' AND pp.post_status = 'publish' ) )",
			$identifiers
		);
		// phpcs:enable

		$found = [];
		foreach ( (array) $wpdb->get_results( $sql, ARRAY_A ) as $hit ) {
			$key = mb_strtolower( $this->normalize_identifier( $hit['identifier'] ), 'UTF-8' );
			if ( '' === $key ) {
				continue;
			}
			// Keyed by unit ID: the same unit matched through both namespaces counts once.
			$found[ $key ][ (int) $hit['unit_id'] ] = [
				'parent' => (int) $hit['parent_id'],
				'type'   => (string) $hit['unit_type'],
			];
		}

		return $found;
	}

	// ── 3. Authorization ──────────────────────────────────────────────────────

	/**
	 * Drop every candidate the current user may not see, using the canonical visibility contract that
	 * /cart/sync uses. A variation is judged by its parent. Nothing else about a dropped candidate is
	 * read afterwards.
	 *
	 * @param array<string, array<int, array{parent:int, type:string}>> $candidates
	 * @return array<string, array<int, array{parent:int, type:string}>>
	 */
	private function filter_authorized( array $candidates, int $user_id ): array {
		$decisions  = []; // access id => bool
		$authorized = [];

		foreach ( $candidates as $key => $units ) {
			foreach ( $units as $unit_id => $meta ) {
				$access_id = 'product_variation' === $meta['type'] ? $meta['parent'] : $unit_id;
				if ( ! isset( $decisions[ $access_id ] ) ) {
					$decisions[ $access_id ] = (bool) apply_filters( 'dp_b2b_product_accessible', true, $access_id, $user_id );
				}
				if ( $decisions[ $access_id ] ) {
					$authorized[ $key ][ $unit_id ] = $meta;
				}
			}
		}

		return $authorized;
	}

	/**
	 * Warm the post/meta/term object caches for every authorized candidate (and the parents of variations)
	 * with a handful of queries, so the later wc_get_product() calls do not each hit the database.
	 * Only AUTHORIZED candidates are ever primed — nothing about a dropped candidate is read.
	 *
	 * @param array<string, array<int, array{parent:int, type:string}>> $authorized
	 */
	private function prime_units( array $authorized ): void {
		$ids = [];
		foreach ( $authorized as $units ) {
			if ( count( $units ) !== 1 ) {
				continue; // ambiguous identifiers are rejected before any product is loaded
			}
			foreach ( $units as $unit_id => $meta ) {
				$ids[ $unit_id ] = true;
				if ( $meta['parent'] ) {
					$ids[ $meta['parent'] ] = true;
				}
			}
		}

		if ( $ids && function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( array_keys( $ids ), true, true );
		}
	}

	// ── 5–6. Stock normalization ──────────────────────────────────────────────

	/**
	 * Additional-orderable-quantity clamp, WooCommerce semantics, per merged unit in first-occurrence order.
	 * Runs only for authorized, orderable units.
	 *
	 * "Additional" means: what can still be ADDED on top of the current cart (the sync is additive) and on
	 * top of what earlier rows of this same import already took from the same stock pool
	 * (get_stock_managed_by_id()), never raw warehouse stock.
	 *
	 * @param array<string, array<string, mixed>> $units
	 * @return array<string, array{code:?string, quantity:int}>
	 */
	private function normalize_quantities( array $units ): array {
		$cart_pool = [];
		$cart_line = [];
		$cart      = function_exists( 'WC' ) ? WC()->cart : null;

		if ( $cart instanceof WC_Cart ) {
			// Same aggregate WooCommerce's own add_to_cart() stock check uses (keyed by managed-by ID).
			$cart_pool = $cart->get_cart_item_quantities();
			foreach ( $cart->get_cart() as $item ) {
				$line_key               = (int) ( $item['product_id'] ?? 0 ) . '_' . (int) ( $item['variation_id'] ?? 0 );
				$cart_line[ $line_key ] = ( $cart_line[ $line_key ] ?? 0 ) + (int) ( $item['quantity'] ?? 0 );
			}
		}

		$allocated = []; // managed-by ID => quantity already granted to earlier rows of this import
		$outcomes  = [];

		foreach ( $units as $unit_key => $unit ) {
			/** @var WC_Product $product */
			$product   = $unit['product'];
			$requested = (int) $unit['requested'];

			if ( $requested > DP_Quick_Order_Config::IMPORT_MAX_QUANTITY ) {
				// Duplicate rows cannot be used to bypass the per-unit cap.
				$outcomes[ $unit_key ] = [ 'code' => self::CODE_QUANTITY_LIMIT, 'quantity' => 0 ];
				continue;
			}

			if ( ! $product->is_in_stock() ) {
				$outcomes[ $unit_key ] = [ 'code' => self::CODE_UNAVAILABLE, 'quantity' => 0 ];
				continue;
			}

			$pool_id  = $product->get_stock_managed_by_id();
			$consumed = (int) ( $cart_pool[ $pool_id ] ?? 0 ) + (int) ( $allocated[ $pool_id ] ?? 0 );
			$limit    = null; // null = WooCommerce imposes no finite limit (unmanaged / backorders)

			if ( $product->is_sold_individually() ) {
				$limit = max( 0, 1 - (int) ( $cart_line[ $unit_key ] ?? 0 ) );
			}

			if ( $product->managing_stock() && ! $product->backorders_allowed() ) {
				$additional = max( 0, (int) $product->get_stock_quantity() - $consumed );
				$limit      = null === $limit ? $additional : min( $limit, $additional );
			}

			$final = null === $limit ? $requested : min( $requested, $limit );

			// Final authority: WooCommerce's own predicate against the resulting pool total.
			if ( $final <= 0 || ! $product->has_enough_stock( $consumed + $final ) ) {
				$outcomes[ $unit_key ] = [ 'code' => self::CODE_UNAVAILABLE, 'quantity' => 0 ];
				continue;
			}

			$allocated[ $pool_id ] = (int) ( $allocated[ $pool_id ] ?? 0 ) + $final;
			$outcomes[ $unit_key ] = [ 'code' => null, 'quantity' => $final ];
		}

		return $outcomes;
	}

	// ── Response rows ─────────────────────────────────────────────────────────

	/**
	 * @param array<string, mixed>               $unit
	 * @param array{code:?string, quantity:int}  $outcome
	 * @return array<string, mixed>
	 */
	private function unit_row( array $unit, array $outcome ): array {
		/** @var WC_Product $product */
		$product = $unit['product'];
		$base    = [
			'rows'       => $unit['rows'],
			'identifier' => $unit['identifier'],
			'name'       => $this->display_name( $product ),
			'requested'  => (int) $unit['requested'],
		];

		if ( null !== $outcome['code'] ) {
			return $base + [
				'status'       => self::STATUS_ERROR,
				'code'         => $outcome['code'],
				'product_id'   => null,
				'variation_id' => null,
				'quantity'     => 0,
			];
		}

		return $base + [
			'status'       => $outcome['quantity'] < $unit['requested'] ? self::STATUS_ADJUSTED : self::STATUS_READY,
			'code'         => null,
			// Needed only so the later cart step can submit to /cart/sync, which re-verifies them server-side.
			'product_id'   => $unit['product_id'],
			'variation_id' => $unit['variation_id'],
			'quantity'     => $outcome['quantity'],
		];
	}

	/**
	 * Row that never reached stock normalization. Unresolved / inaccessible / unpublished identifiers all
	 * produce an identical shape; a name appears only for an authorized unit that failed a later check.
	 *
	 * @param array<string, mixed> $entry
	 * @return array<string, mixed>
	 */
	private function error_row( array $entry ): array {
		return [
			'rows'         => [ $entry['row'] ],
			'identifier'   => $entry['identifier'],
			'status'       => self::STATUS_ERROR,
			'code'         => $entry['code'],
			'product_id'   => null,
			'variation_id' => null,
			'name'         => $entry['name'],
			'requested'    => $entry['requested'],
			'quantity'     => 0,
		];
	}

	private function display_name( WC_Product $product ): string {
		return wp_specialchars_decode( wp_strip_all_tags( $product->get_name() ), ENT_QUOTES );
	}
}

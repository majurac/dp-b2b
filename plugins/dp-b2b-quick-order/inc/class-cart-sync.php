<?php
defined( 'ABSPATH' ) || exit;

/**
 * Batched, additive WooCommerce cart sync.
 *
 * Per-item result contract (one result per submitted item — never silently dropped):
 *   success: { product_id, variation_id, action: 'added'|'updated'|'removed', quantity? }
 *   no-op:   { product_id, variation_id, action: 'skipped' }   (quantity 0 and no cart line)
 *   failure: { product_id, variation_id, action: 'failed', error: <code> }
 *
 * Public failure codes (stable, non-sensitive — no stock amount is ever returned):
 *   out_of_stock         the product/variation itself is currently not in stock
 *   quantity_unavailable in stock, but the resulting quantity cannot be fulfilled
 *   product_unavailable  missing / unpublished / not purchasable / inaccessible / a variation
 *                        that does not belong to the submitted parent — deliberately ONE code so a
 *                        guessed ID cannot reveal whether a hidden product exists
 *   not_addable          WooCommerce rejected add_to_cart for a reason not classified further
 */
class DP_Quick_Order_Cart_Sync {

	const ERR_OUT_OF_STOCK         = 'out_of_stock';
	const ERR_QUANTITY_UNAVAILABLE = 'quantity_unavailable';
	const ERR_PRODUCT_UNAVAILABLE  = 'product_unavailable';
	const ERR_NOT_ADDABLE          = 'not_addable';

	/**
	 * Sync a batch of items into the WooCommerce cart.
	 * WC cart/session is the single source of truth — no custom persistence.
	 *
	 * @param array<array{product_id: int, variation_id?: int, quantity: int, variation?: array<string,string>}> $items
	 */
	public function sync( array $items ): array {
		$cart    = WC()->cart;
		$results = [];

		// WooCommerce's add_to_cart() queues an error notice in the session for every rejection. Those
		// notices would surface on the next cart/checkout page load, duplicating (or contradicting) the
		// row-level message Quick Order shows. Restore the notice queue to its pre-request state.
		$notices_before = function_exists( 'wc_get_notices' ) ? wc_get_notices() : null;

		// Build cart index keyed by "product_id_variation_id" for O(1) lookups.
		$cart_index = [];
		foreach ( $cart->get_cart() as $key => $cart_item ) {
			$index_key                = $cart_item['product_id'] . '_' . $cart_item['variation_id'];
			$cart_index[ $index_key ] = $key;
		}

		foreach ( $items as $item ) {
			$item            = is_array( $item ) ? $item : [];
			$product_id      = absint( $item['product_id'] ?? 0 );
			$variation_id    = absint( $item['variation_id'] ?? 0 );
			$quantity        = absint( $item['quantity'] ?? 0 );
			// Variation attribute key=>value pairs (e.g. ['attribute_pa_color' => 'red']).
			// Sanitized here; WC resolves and validates them at add_to_cart().
			$variation_attrs = is_array( $item['variation'] ?? null )
				? array_map( 'sanitize_text_field', $item['variation'] )
				: [];

			// Every submitted item gets exactly one result (a missing product_id is simply an
			// unavailable product) so the client can always map result → row.
			$results[] = $this->sync_item(
				$cart,
				$cart_index,
				$product_id,
				$variation_id,
				$quantity,
				$variation_attrs
			);
		}

		$cart->calculate_totals();

		if ( null !== $notices_before && function_exists( 'wc_set_notices' ) ) {
			wc_set_notices( $notices_before );
		}

		return [
			'synced' => $results,
			'total'  => $cart->get_cart_contents_count(),
			'totals' => [
				'subtotal' => (float) $cart->get_subtotal(),
				'total'    => (float) $cart->get_total( 'float' ),
				'currency' => get_woocommerce_currency(),
			],
		];
	}

	/**
	 * Resolve the purchasable unit for a submitted (product_id, variation_id) pair, or null.
	 *
	 * Ownership is verified BEFORE anything stock-related is read: a variation must belong to the
	 * submitted parent, otherwise the caller could pair an accessible parent with a hidden product's
	 * variation and learn that variation's stock state from the response.
	 */
	private function resolve_unit( int $product_id, int $variation_id ): ?WC_Product {
		if ( ! $product_id ) {
			return null;
		}

		$product = wc_get_product( $variation_id ?: $product_id );
		if ( ! $product instanceof WC_Product ) {
			return null;
		}

		if ( $variation_id ) {
			if ( ! $product instanceof WC_Product_Variation || (int) $product->get_parent_id() !== $product_id ) {
				return null;
			}
		} elseif ( $product instanceof WC_Product_Variation ) {
			// A variation ID submitted as a parent product ID.
			return null;
		}

		return $product->is_purchasable() ? $product : null;
	}

	/**
	 * @param array{product_id:int, variation_id:int} $base
	 */
	private function fail( array $base, string $error ): array {
		return array_merge( $base, [ 'action' => 'failed', 'error' => $error ] );
	}

	/**
	 * Sync a single item into the WooCommerce cart.
	 *
	 * One wc_get_product() call per item, no get_available_variations(), no recursive hydration.
	 * WC is authoritative: attribute validation and add_to_cart acceptance happen inside WC.
	 *
	 * @param array<string, string> $cart_index     Map of "pid_vid" => cart_item_key.
	 * @param array<string, string> $variation_attrs WC variation attribute key=>value pairs.
	 */
	private function sync_item(
		WC_Cart $cart,
		array &$cart_index,
		int $product_id,
		int $variation_id,
		int $quantity,
		array $variation_attrs = []
	): array {
		$base = [ 'product_id' => $product_id, 'variation_id' => $variation_id ];

		$product = $this->resolve_unit( $product_id, $variation_id );
		if ( null === $product ) {
			return $this->fail( $base, self::ERR_PRODUCT_UNAVAILABLE );
		}

		$index_key    = $product_id . '_' . $variation_id;
		$existing_key = $cart_index[ $index_key ] ?? null;

		if ( null !== $existing_key ) {
			if ( 0 === $quantity ) {
				$cart->remove_cart_item( $existing_key );
				unset( $cart_index[ $index_key ] );
				return array_merge( $base, [ 'action' => 'removed' ] );
			}

			// Quick Order is independent of the WC cart until submit — an existing cart
			// line is a quantity the user already committed to, not a value Quick Order
			// owns. Submitting must therefore ADD the requested quantity on top of
			// whatever is already in the cart, never overwrite it.
			$current_quantity = (int) ( $cart->get_cart_item( $existing_key )['quantity'] ?? 0 );
			$new_quantity     = $current_quantity + $quantity;

			// A line that is already in the cart may have gone out of stock since it was added —
			// that must not let its quantity grow. Typed results only: the stock amount is never
			// returned to the client. (No access re-check here: existing lines are intentionally
			// not retroactively revalidated — see docs/active/status.md, Visibility integration.)
			if ( ! $product->is_in_stock() ) {
				return $this->fail( $base, self::ERR_OUT_OF_STOCK );
			}
			// WooCommerce's own predicate: handles managed stock, parent-managed variations and
			// backorders. Checked against the resulting total, not the requested increment alone.
			if ( ! $product->has_enough_stock( $new_quantity ) ) {
				return $this->fail( $base, self::ERR_QUANTITY_UNAVAILABLE );
			}

			$cart->set_quantity( $existing_key, $new_quantity );
			return array_merge( $base, [ 'action' => 'updated', 'quantity' => $new_quantity ] );
		}

		if ( $quantity > 0 ) {
			// Visibility gate: reject new-item adds for products the user cannot access.
			// Uses a WP filter contract so the plugin stays decoupled from theme classes.
			// Falls back to true (allow) if no filter is attached. $product_id is the verified
			// parent of the unit (resolve_unit), so a variation is judged by its real parent.
			$user_id = get_current_user_id();
			if ( ! (bool) apply_filters( 'dp_b2b_product_accessible', true, $product_id, $user_id ) ) {
				// Same external code as a nonexistent product — no existence oracle.
				return $this->fail( $base, self::ERR_PRODUCT_UNAVAILABLE );
			}

			// Stock guards before add_to_cart(): WC's add_to_cart() silently returns false for these
			// cases, which would otherwise collapse into a generic failure.
			if ( ! $product->is_in_stock() ) {
				return $this->fail( $base, self::ERR_OUT_OF_STOCK );
			}
			if ( ! $product->has_enough_stock( $quantity ) ) {
				return $this->fail( $base, self::ERR_QUANTITY_UNAVAILABLE );
			}

			// WC handles attribute→variation resolution and all remaining validation
			// (sold individually, min/max qty, etc.) inside add_to_cart().
			$new_key = $cart->add_to_cart( $product_id, $quantity, $variation_id, $variation_attrs );
			if ( $new_key ) {
				$cart_index[ $index_key ] = $new_key;
				return array_merge( $base, [ 'action' => 'added', 'quantity' => $quantity ] );
			}
			return $this->fail( $base, self::ERR_NOT_ADDABLE );
		}

		return array_merge( $base, [ 'action' => 'skipped' ] );
	}
}

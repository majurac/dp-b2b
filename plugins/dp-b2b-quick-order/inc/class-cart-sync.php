<?php
defined( 'ABSPATH' ) || exit;

class DP_Quick_Order_Cart_Sync {

	/**
	 * Sync a batch of items into the WooCommerce cart.
	 * WC cart/session is the single source of truth — no custom persistence.
	 *
	 * @param array<array{product_id: int, variation_id?: int, quantity: int, variation?: array<string,string>}> $items
	 */
	public function sync( array $items ): array {
		$cart    = WC()->cart;
		$results = [];

		// Build cart index keyed by "product_id_variation_id" for O(1) lookups.
		$cart_index = [];
		foreach ( $cart->get_cart() as $key => $cart_item ) {
			$index_key                = $cart_item['product_id'] . '_' . $cart_item['variation_id'];
			$cart_index[ $index_key ] = $key;
		}

		foreach ( $items as $item ) {
			$product_id      = absint( $item['product_id'] ?? 0 );
			$variation_id    = absint( $item['variation_id'] ?? 0 );
			$quantity        = absint( $item['quantity'] ?? 0 );
			// Variation attribute key=>value pairs (e.g. ['attribute_pa_color' => 'red']).
			// Sanitized here; WC resolves and validates them at add_to_cart().
			$variation_attrs = is_array( $item['variation'] ?? null )
				? array_map( 'sanitize_text_field', $item['variation'] )
				: [];

			if ( ! $product_id ) {
				continue;
			}

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
	 * Sync a single item into the WooCommerce cart.
	 *
	 * Validates existence and purchasability via wc_get_product() — one call per item,
	 * no get_available_variations(), no recursive hydration.
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

		// Load the specific variation if supplied, otherwise load the parent/simple product.
		// wc_get_product() uses the WC object cache — no redundant DB hits per request.
		$check_id = $variation_id ?: $product_id;
		$product  = wc_get_product( $check_id );

		if ( ! $product instanceof WC_Product || ! $product->is_purchasable() ) {
			return array_merge( $base, [ 'action' => 'failed', 'error' => 'invalid_product' ] );
		}

		$index_key    = $product_id . '_' . $variation_id;
		$existing_key = $cart_index[ $index_key ] ?? null;

		if ( null !== $existing_key ) {
			if ( $quantity === 0 ) {
				$cart->remove_cart_item( $existing_key );
				unset( $cart_index[ $index_key ] );
				return array_merge( $base, [ 'action' => 'removed' ] );
			}

			// Stock check for quantity updates on managed-stock products.
			// get_manage_stock() returns true/'parent'/false; get_stock_quantity() resolves
			// parent delegation automatically for variations.
			if ( $product->get_manage_stock() ) {
				$stock_qty = (int) $product->get_stock_quantity();
				if ( $stock_qty < $quantity ) {
					return array_merge( $base, [
						'action'           => 'out_of_stock',
						'quantity_allowed' => max( 0, $stock_qty ),
					] );
				}
			}

			$cart->set_quantity( $existing_key, $quantity );
			return array_merge( $base, [ 'action' => 'updated', 'quantity' => $quantity ] );
		}

		if ( $quantity > 0 ) {
			// Visibility gate: reject new-item adds for products the user cannot access.
			// Uses a WP filter contract so the plugin stays decoupled from theme classes.
			// Falls back to true (allow) if no filter is attached.
			$user_id = get_current_user_id();
			if ( ! (bool) apply_filters( 'dp_b2b_product_accessible', true, $product_id, $user_id ) ) {
				return array_merge( $base, [ 'action' => 'failed', 'error' => 'access_denied' ] );
			}

			// Quick in-stock guard before calling add_to_cart — avoids WC error notices
			// for clearly out-of-stock items and returns a typed error code.
			if ( ! $product->is_in_stock() ) {
				return array_merge( $base, [ 'action' => 'out_of_stock' ] );
			}

			// Managed-stock quantity check for new adds — mirrors the existing check for
			// updates. WC's add_to_cart() silently returns false when qty > stock, which
			// would produce action:failed with no quantity_allowed hint. This pre-check
			// returns a typed out_of_stock response so the frontend can correct the input.
			if ( $product->get_manage_stock() ) {
				$stock_qty = (int) $product->get_stock_quantity();
				if ( $stock_qty < $quantity ) {
					return array_merge( $base, [
						'action'           => 'out_of_stock',
						'quantity_allowed' => max( 0, $stock_qty ),
					] );
				}
			}

			// WC handles attribute→variation resolution and all remaining validation
			// (sold individually, min/max qty, etc.) inside add_to_cart().
			$new_key = $cart->add_to_cart( $product_id, $quantity, $variation_id, $variation_attrs );
			if ( $new_key ) {
				$cart_index[ $index_key ] = $new_key;
				return array_merge( $base, [ 'action' => 'added', 'quantity' => $quantity ] );
			}
			return array_merge( $base, [ 'action' => 'failed', 'error' => 'add_failed' ] );
		}

		return array_merge( $base, [ 'action' => 'skipped' ] );
	}
}

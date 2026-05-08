<?php
defined( 'ABSPATH' ) || exit;

class DP_Quick_Order_Cart_Sync {

	/**
	 * Sync a batch of items into the WooCommerce cart.
	 * WC cart/session is the single source of truth — no custom persistence.
	 *
	 * @param array<array{product_id: int, variation_id?: int, quantity: int}> $items
	 */
	public function sync( array $items ): array {
		$cart    = WC()->cart;
		$results = [];

		// Build cart index keyed by product_id+variation_id for O(1) lookups.
		$cart_index = [];
		foreach ( $cart->get_cart() as $key => $cart_item ) {
			$index_key              = $cart_item['product_id'] . '_' . $cart_item['variation_id'];
			$cart_index[ $index_key ] = $key;
		}

		foreach ( $items as $item ) {
			$product_id   = absint( $item['product_id'] ?? 0 );
			$variation_id = absint( $item['variation_id'] ?? 0 );
			$quantity     = absint( $item['quantity'] ?? 0 );

			if ( ! $product_id ) {
				continue;
			}

			$results[] = $this->sync_item( $cart, $cart_index, $product_id, $variation_id, $quantity );
		}

		return [
			'synced' => $results,
			'total'  => $cart->get_cart_contents_count(),
		];
	}

	/**
	 * @param array<string, string> $cart_index Map of "product_id_variation_id" => cart_item_key.
	 */
	private function sync_item(
		WC_Cart $cart,
		array &$cart_index,
		int $product_id,
		int $variation_id,
		int $quantity
	): array {
		$index_key      = $product_id . '_' . $variation_id;
		$existing_key   = $cart_index[ $index_key ] ?? null;

		if ( null !== $existing_key ) {
			if ( $quantity === 0 ) {
				$cart->remove_cart_item( $existing_key );
				unset( $cart_index[ $index_key ] );
				return [ 'product_id' => $product_id, 'variation_id' => $variation_id, 'action' => 'removed' ];
			}
			$cart->set_quantity( $existing_key, $quantity );
			return [ 'product_id' => $product_id, 'variation_id' => $variation_id, 'action' => 'updated', 'quantity' => $quantity ];
		}

		if ( $quantity > 0 ) {
			$new_key = $cart->add_to_cart( $product_id, $quantity, $variation_id );
			if ( $new_key ) {
				$cart_index[ $index_key ] = $new_key;
				return [ 'product_id' => $product_id, 'variation_id' => $variation_id, 'action' => 'added', 'quantity' => $quantity ];
			}
			return [ 'product_id' => $product_id, 'variation_id' => $variation_id, 'action' => 'failed' ];
		}

		return [ 'product_id' => $product_id, 'variation_id' => $variation_id, 'action' => 'skipped' ];
	}
}

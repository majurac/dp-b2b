<?php
defined( 'ABSPATH' ) || exit;

/**
 * Resolves which PARENT product IDs the current customer has already
 * ordered (qualifying statuses only) — parent-level roll-up: ordering any
 * variation of a variable product qualifies the whole parent. Cached per
 * user in the WP object cache. Never scans full order history on every
 * request — only on a cache miss. Returns product IDs only; does not build
 * or touch any WP_Query arguments (see Task 5).
 */
class DP_Quick_Order_Already_Ordered_Resolver {

	/**
	 * @return list<int> parent product IDs
	 */
	public function get_ordered_product_ids( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return [];
		}

		$cache_key = "dp_qo_already_ordered_{$user_id}";
		$cached    = wp_cache_get( $cache_key, DP_Quick_Order_Config::ALREADY_ORDERED_CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		$product_ids = $this->query_ordered_product_ids( $user_id );

		wp_cache_set(
			$cache_key,
			$product_ids,
			DP_Quick_Order_Config::ALREADY_ORDERED_CACHE_GROUP,
			DP_Quick_Order_Config::ALREADY_ORDERED_CACHE_TTL
		);

		return $product_ids;
	}

	/**
	 * wc_get_orders() — WooCommerce's own order-query abstraction, HPOS and
	 * legacy-storage agnostic by construction (respects
	 * woocommerce_custom_orders_table_usage_enabled internally). No raw
	 * $wpdb SQL, no custom tables, no duplicated order/customer metadata.
	 *
	 * @return list<int>
	 */
	private function query_ordered_product_ids( int $user_id ): array {
		$statuses = apply_filters( 'dp_qo_already_ordered_statuses', [ 'processing', 'completed' ] );

		$orders = wc_get_orders( [
			'customer_id' => $user_id,
			'status'      => $statuses,
			'limit'       => -1,
			'return'      => 'objects',
		] );

		$product_ids = [];

		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			foreach ( $order->get_items() as $item ) {
				if ( ! $item instanceof WC_Order_Item_Product ) {
					continue;
				}
				// get_product_id() always returns the PARENT product ID, even
				// for a variation line item (WC_Abstract_Order::add_product()
				// sets it that way on every order item) — this one call IS
				// the parent-level roll-up; no separate "collapse to parent"
				// step is needed or added.
				$product_id = $item->get_product_id();
				if ( ! $product_id ) {
					continue;
				}
				$product_ids[] = $product_id;
			}
		}

		return array_values( array_unique( $product_ids ) );
	}

	/**
	 * Registered on woocommerce_order_status_changed — invalidates the
	 * ordering customer's cache entry on ANY status transition (entering or
	 * leaving the qualifying set), so a later refund correctly drops a
	 * product from "already ordered" and a later payment confirmation
	 * correctly adds it, without waiting for TTL expiry.
	 */
	public function invalidate_for_order( int $order_id, string $from_status, string $to_status, WC_Order $order ): void {
		$user_id = $order->get_customer_id();
		if ( $user_id <= 0 ) {
			return;
		}
		wp_cache_delete( "dp_qo_already_ordered_{$user_id}", DP_Quick_Order_Config::ALREADY_ORDERED_CACHE_GROUP );
	}
}

<?php
defined( 'ABSPATH' ) || exit;

/**
 * Guards Quick Order WP_Query instances against unintended WOOF/WBW mutations.
 *
 * WOOF (woo-product-filter) hooks into pre_get_posts at priority 9999 via
 * forceProductFilter(). In the REST context isFiltered() returns false (no wpf_/orderby
 * GET params), so WOOF does not inject state into Quick Order queries. This guard runs
 * at priority 10000 as defensive coding: if WOOF ever sets wpf_query on a QO query,
 * addFilterClausesRequest() would apply WOOF SQL clause modifications — the guard
 * strips that flag before it takes effect.
 */
class DP_Quick_Order_Filter_Bridge {

	public function __construct() {
		add_action( 'pre_get_posts', [ $this, 'guard_query' ], 10000 );
	}

	/**
	 * Remove any wpf_query flag that WOOF may have set on a Quick Order WP_Query.
	 * Runs after WOOF's forceProductFilter (priority 9999).
	 */
	public function guard_query( WP_Query $query ): void {
		if ( empty( $query->query_vars['dp_quick_order'] ) ) {
			return;
		}
		if ( ! empty( $query->query_vars['wpf_query'] ) ) {
			$query->set( 'wpf_query', null );
		}
	}
}

<?php
defined( 'ABSPATH' ) || exit;

/**
 * Integrates Quick Order queries with the existing Dreampoint B2B visibility system.
 *
 * Does NOT duplicate visibility logic. The theme's visibility engine operates via
 * pre_get_posts and WP_Query filters — passing suppress_filters=false is sufficient
 * for those hooks to fire on Quick Order queries.
 *
 * If the visibility system exposes a programmatic API in the future, inject
 * additional constraints here instead of duplicating logic.
 */
class DP_Quick_Order_Visibility_Integration {

	/**
	 * Apply visibility constraints to a WP_Query args array.
	 * Ensures the existing visibility engine's hooks are not suppressed.
	 *
	 * @param array<string, mixed> $query_args Passed by reference.
	 */
	public function apply_to_query( array &$query_args ): void {
		$query_args['suppress_filters'] = false;
	}
}

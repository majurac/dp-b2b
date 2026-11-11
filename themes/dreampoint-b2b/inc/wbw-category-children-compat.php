<?php
/**
 * WBW Product Filter — parent category must include its descendants.
 *
 * PURPOSE
 * -------
 * Choosing a parent product category in the WBW sidebar (e.g. "Naocale", whose 53 products all live in sub-categories)
 * returns no products, while the native category archive lists all of them. Example from staging:
 * `/shop/?wpf_fbv=1&product_brand_list=izipizi&wpf_filter_cat_list_1s=naocale` -> 0 instead of 53.
 *
 * ROOT CAUSE (WBW 3.4.8, verified on identical vendor files local + staging)
 * ----------------------------------------------------------------------------
 * `WooBeWoo_PF_Woofilters::addCustomTaxQuery()` builds the `product_cat` clause for every `wpf_filter_cat_list*`
 * request key with `'include_children' => false` (the `wpf_filter_cat_*` keys without `_list` use `true`). The sidebar
 * (Multi type, flat list, `f_show_hierarchical` off) always emits the `_list` form, so a parent only matches products
 * assigned DIRECTLY to it. WBW's "Filter with children" setting does not help: for the Multi type it is ignored
 * (`findTaxonomyIncludeChildrenStatus()`), and the hierarchical + "Extend parent select" configuration was rejected by
 * the owner because it changes the approved flat B2/T9 sidebar.
 *
 * QUERY LIFECYCLE (proved with a temporary local diagnostic, see docs/active/status.md)
 * -------------------------------------------------------------------------------------
 * WBW writes the clause during `pre_get_posts` priority 10, inside WooCommerce's `woocommerce_product_query` action
 * (WBW callback priority 999), via `$query->set( 'tax_query', ... )`. From `pre_get_posts` priority 11 up to
 * `parse_tax_query` the main query's `tax_query` var holds, unchanged, a WBW group
 * `[ 0 => [ taxonomy => product_cat, field => slug|term_id, terms => [...], operator => IN|AND|NOT IN,
 * include_children => false ], 'wpf_group' => 'product_cat', 'relation' => AND|OR ]` and the executed SQL contains only
 * the parent term_taxonomy_id. WBW also sets the query var `wpf_query = 1` on every query it filtered.
 *
 * FIX
 * ---
 * `pre_get_posts` priority 10001: after WBW's own 9999 handler and after Quick Order's guard (10000,
 * `dp-b2b-quick-order/inc/class-filter-bridge.php`), which clears `wpf_query` on Quick Order queries, so those stay
 * untouched regardless of load order. Only for non-admin queries that WBW marked
 * (`wpf_query`) on a request that carries a `wpf_filter_cat_list*` key, and only inside WBW's `product_cat`
 * `wpf_group`s, clauses with `include_children = false` are corrected:
 *  - IN (OR of categories)  -> same clause with `include_children => true` (parent OR any descendant).
 *  - NOT IN (exclusion)     -> same clause with `include_children => true` (excluding a parent excludes its
 *                              descendants, i.e. the complement of the descendant-inclusive set).
 *  - AND (all categories)   -> core WP_Tax_Query would expand `terms` into parent + every descendant and then require
 *                              ALL of them, which is wrong. The clause is replaced by a nested `AND` group with one
 *                              single-term `IN` clause per category, each `include_children => true`
 *                              ("in A-or-below AND in B-or-below"). The group's own relation is untouched.
 * Terms, `field`, group structure and every other clause are preserved. Idempotent (corrected clauses already have
 * `include_children => true`). Uses WordPress's native tax-query child expansion; no manual descendant lookups.
 * No vendor file, no WBW setting, no URL change.
 *
 * LIMITS
 * ------
 *  - WBW's own AJAX refresh endpoint (admin-ajax) builds its query itself and is not covered; it is not the active
 *    path locally (the sidebar navigates). Re-check if WBW AJAX filtering is ever enabled for the archive.
 *  - A WBW `preselect` category clause (dashboard "preselect") on a request that also carries the URL key would be
 *    corrected as well; no preselects are configured.
 *
 * REMOVAL
 * -------
 * Delete this file (and its require in functions.php) once a WBW update makes a parent category include its
 * descendants for `wpf_filter_cat_list*`; re-test `/shop/?wpf_fbv=1&wpf_filter_cat_list_1s=<parent slug>` without it.
 *
 * @package Dreampoint_B2B
 */

defined( 'ABSPATH' ) || exit;

/**
 * Does the current request carry a WBW category "list" filter key (`wpf_filter_cat_list*`)?
 */
function dreampoint_b2b_wbw_cat_children_request_has_param(): bool {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only inspection of public filter parameter names.
	foreach ( array_keys( $_GET ) as $key ) {
		if ( str_starts_with( (string) $key, 'wpf_filter_cat_list' ) ) {
			return true;
		}
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	return false;
}

/**
 * Correct one WBW `product_cat` clause. Returns the clause unchanged when it is not a WBW "direct only" clause.
 *
 * @param array $clause Tax-query clause.
 * @return array Corrected clause, or a nested AND group for a multi-term AND clause.
 */
function dreampoint_b2b_wbw_cat_children_fix_clause( array $clause ): array {
	if ( ( $clause['taxonomy'] ?? '' ) !== 'product_cat' || ! array_key_exists( 'include_children', $clause ) || ! empty( $clause['include_children'] ) ) {
		return $clause;
	}

	$operator = strtoupper( (string) ( $clause['operator'] ?? 'IN' ) );
	$terms    = array_values( (array) ( $clause['terms'] ?? [] ) );

	if ( 'AND' === $operator && count( $terms ) > 1 ) {
		$group = [ 'relation' => 'AND' ];
		foreach ( $terms as $term ) {
			$group[] = [
				'taxonomy'         => 'product_cat',
				'field'            => $clause['field'] ?? 'term_id',
				'terms'            => [ $term ],
				'operator'         => 'IN',
				'include_children' => true,
			];
		}
		return $group;
	}

	$clause['include_children'] = true;
	return $clause;
}

/**
 * Make WBW's parent-category filter descendant-inclusive on the queries WBW filtered.
 *
 * @param WP_Query $query Query being prepared.
 * @return void
 */
function dreampoint_b2b_wbw_cat_children_fix_query( $query ): void {
	if ( is_admin() || ! $query instanceof WP_Query || ! $query->get( 'wpf_query' ) || ! dreampoint_b2b_wbw_cat_children_request_has_param() ) {
		return;
	}

	$tax_query = $query->get( 'tax_query' );
	if ( ! is_array( $tax_query ) ) {
		return;
	}

	$changed = false;
	foreach ( $tax_query as $index => $group ) {
		// Only WBW's own product_cat group (`wpf_group`), never core / WooCommerce / other plugin clauses.
		if ( ! is_array( $group ) || ( $group['wpf_group'] ?? '' ) !== 'product_cat' ) {
			continue;
		}
		foreach ( $group as $key => $clause ) {
			if ( ! is_int( $key ) || ! is_array( $clause ) ) {
				continue;
			}
			$fixed = dreampoint_b2b_wbw_cat_children_fix_clause( $clause );
			if ( $fixed !== $clause ) {
				$tax_query[ $index ][ $key ] = $fixed;
				$changed                     = true;
			}
		}
	}

	if ( $changed ) {
		$query->set( 'tax_query', $tax_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- adjusting WBW's existing clause only.
	}
}
// Priority 10001: after WBW's pre_get_posts handler (9999) and Quick Order's wpf_query guard (10000); WBW's clause is
// already in place from priority 10 on.
add_action( 'pre_get_posts', 'dreampoint_b2b_wbw_cat_children_fix_query', 10001 );

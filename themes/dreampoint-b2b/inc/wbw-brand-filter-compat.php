<?php
/**
 * WBW Product Filter — brand-only URL filter compatibility.
 *
 * PURPOSE
 * -------
 * Selecting a brand in the WBW sidebar writes `?product_brand_list=<slug>&wpf_fbv=1` and the shop shows
 * "no products found", although the brand has products (the brand archive works).
 *
 * ROOT CAUSE (WBW 3.4.8, verified on identical vendor files local + staging)
 * ----------------------------------------------------------------------------
 * `WooBeWoo_PF_Woofilters::getQueryVars()` picks the tax-query mode from `isFiltered()`. `isFiltered()` only
 * recognises the request keys `orderby`, `wpf_*` (except wpf_count / wpf_fbv / wpf_dpv / wpf_skip), `product_tag*`
 * and `pr_*`. The brand parameter `product_brand_list` is none of those, so a brand-only URL is treated as "not
 * filtered": mode `default` instead of `url`. In `addCustomTaxQuery()` the brand clause then uses
 * `field => 'id'` (not `'slug'`) with the slug as the term value, WP casts it to 0 and the SQL becomes `0 = 1`.
 * Any additional recognised key (a category, stock or attribute filter, `orderby`, `wpf_order`) flips the mode back
 * to `url` and the same brand works. It is NOT an indexing problem: WBW's meta index has no brand entries.
 *
 * FIX
 * ---
 * When `product_brand_list` is present and WBW would not otherwise see a filter, add the neutral marker
 * `wpf_dp_brand=1` to the in-memory `$_GET` (never to the URL). WBW then treats the request as filtered and builds
 * the slug-based clause. No vendor file is changed. `wpf_dp_brand` is not read by WBW itself.
 *
 * REMOVAL
 * -------
 * Delete this file (and its require in functions.php) once a WBW update recognises `product_brand_list` in
 * `isFiltered()`; re-test `/shop/?product_brand_list=<slug>&wpf_fbv=1` without this file.
 *
 * @package Dreampoint_B2B
 */

defined( 'ABSPATH' ) || exit;

/**
 * Make WBW treat a brand-only product archive request as filtered.
 *
 * Runs on `parse_request` (before the main query and WBW's `pre_get_posts` handling, after the query vars are known),
 * only on the frontend and only for product archives (shop, product taxonomies, product search). Quick Order (a page),
 * REST, admin and admin-ajax requests, and WP-CLI (no `$_GET`) never match.
 *
 * @param WP $wp Current request.
 * @return void
 */
function dreampoint_b2b_wbw_brand_filter_mark_request( $wp ): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only inspection of public filter parameters.
	if ( is_admin() || ! isset( $_GET['product_brand_list'] ) || ! is_string( $_GET['product_brand_list'] ) || isset( $_GET['wpf_dp_brand'] ) ) {
		return;
	}

	// Product archives only: shop (post type archive), product taxonomies, product search.
	$qv = isset( $wp->query_vars ) && is_array( $wp->query_vars ) ? $wp->query_vars : [];
	$is_product_archive = ( isset( $qv['post_type'] ) && 'product' === $qv['post_type'] )
		|| ! empty( $qv['product_cat'] ) || ! empty( $qv['product_tag'] ) || ! empty( $qv['product_brand'] );
	if ( ! $is_product_archive ) {
		return;
	}

	// A valid brand value is one or more slugs separated by "," (OR) or "|" (AND), as WBW writes them.
	$value = wp_unslash( $_GET['product_brand_list'] );
	if ( ! preg_match( '/^[\p{L}\p{N}_\-]+(?:[,|][\p{L}\p{N}_\-]+)*$/u', $value ) ) {
		return;
	}

	// Keys that WooBeWoo_PF_Woofilters::isFiltered() already recognises (mirrors its rule).
	$ignored = [ 'wpf_count', 'wpf_fbv', 'wpf_dpv', 'wpf_skip', '_' ];
	foreach ( array_keys( $_GET ) as $key ) {
		$key = (string) $key;
		if ( in_array( $key, $ignored, true ) ) {
			continue;
		}
		if ( 'orderby' === $key || str_starts_with( $key, 'wpf_' ) || str_starts_with( $key, 'product_tag' ) || str_starts_with( $key, 'pr_' ) ) {
			return;
		}
	}

	$_GET['wpf_dp_brand'] = '1';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
}
add_action( 'parse_request', 'dreampoint_b2b_wbw_brand_filter_mark_request', 1 );

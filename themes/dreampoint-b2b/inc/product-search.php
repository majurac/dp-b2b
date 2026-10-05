<?php
/**
 * Product search — small extension of the native WP product search.
 *
 * Adds three identifier fields to the native `s` search, nothing else:
 *   - `_sku`              (WC SKU; ERP parents: "P-<articleId>", variations: variationId)
 *   - `_ARTICLE_CODE`     (ERP catalog number — "Kataloški broj")
 *   - `_global_unique_id` (EAN/GTIN; ERP variable products carry it on the variations)
 *
 * A match on a variation resolves to its parent product (the search lists parents).
 * Title/content search stays native. The extension is opt-in through the
 * `dp_search_extended` query var, so it only affects the AJAX live search and the
 * frontend product search results page — never admin, REST/Store API or Quick Order.
 *
 * B2B visibility is NOT handled here and is not bypassed: this only widens the
 * `posts_search` clause; the visibility engine keeps enforcing its own
 * `pre_get_posts` / `posts_clauses` restrictions on the same query.
 *
 * @package Dreampoint_B2B
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Shortest term that triggers the identifier lookup (same threshold as the live search). */
const DREAMPOINT_B2B_SEARCH_MIN_CHARS = 2;

/** Upper bound for ids pulled from the identifier lookup. */
const DREAMPOINT_B2B_SEARCH_MAX_IDS = 200;

/**
 * Opt the frontend product search results page (main query) into the extension.
 */
add_action( 'pre_get_posts', 'dreampoint_b2b_flag_product_search_query' );

function dreampoint_b2b_flag_product_search_query( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}

	if ( 'product' !== $query->get( 'post_type' ) ) {
		return;
	}

	$query->set( 'dp_search_extended', true );
}

add_filter( 'posts_search', 'dreampoint_b2b_extend_product_search', 10, 2 );

/**
 * Widens the native search clause with `OR ID IN (matching product ids)`.
 *
 * @param string   $search Native " AND (...)" search clause.
 * @param WP_Query $query  Current query.
 */
function dreampoint_b2b_extend_product_search( string $search, WP_Query $query ): string {
	global $wpdb;

	if ( '' === $search || ! $query->get( 'dp_search_extended' ) ) {
		return $search;
	}

	// WP_Query::parse_search() je već uradio stripslashes/urldecode nad `s` — ne ponavljati.
	$term = trim( (string) $query->get( 's' ) );

	if ( mb_strlen( $term, 'UTF-8' ) < DREAMPOINT_B2B_SEARCH_MIN_CHARS ) {
		return $search;
	}

	$ids = dreampoint_b2b_search_identifier_matches( $term );

	if ( ! $ids ) {
		return $search;
	}

	// WP appends a post_password clause for logged-out users — keep it outside the OR.
	$password_clause = " AND ({$wpdb->posts}.post_password = '') ";
	$has_password    = str_ends_with( $search, $password_clause );
	$core            = $has_password ? substr( $search, 0, -strlen( $password_clause ) ) : $search;

	// $core is " AND (...) " — strip the leading " AND " and widen it.
	$core = trim( preg_replace( '/^\s*AND\s+/i', '', $core, 1 ) );

	$id_list = implode( ',', array_map( 'absint', $ids ) );

	return " AND ( {$core} OR {$wpdb->posts}.ID IN ({$id_list}) ) " . ( $has_password ? $password_clause : '' );
}

/**
 * Parent product ids whose SKU / catalog number / EAN (or a variation's) contains $term.
 *
 * Known limitation: at most DREAMPOINT_B2B_SEARCH_MAX_IDS ids are collected. For an unusually broad
 * (e.g. two-character) identifier term with more matches, ORDER BY pid makes the collected set
 * deterministic (the lowest ids). This is NOT relevance ranking: it only decides which ids join the
 * candidate set — the final result order is still WordPress'/WooCommerce's native search order.
 *
 * @return int[]
 */
function dreampoint_b2b_search_identifier_matches( string $term ): array {
	global $wpdb;

	$like = '%' . $wpdb->esc_like( $term ) . '%';

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names only, values go through prepare().
	$sql = $wpdb->prepare(
		"SELECT DISTINCT IF( p.post_type = 'product_variation', p.post_parent, p.ID ) AS pid
		   FROM {$wpdb->postmeta} pm
		  INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		  WHERE pm.meta_key IN ( '_sku', '_ARTICLE_CODE', '_global_unique_id' )
		    AND pm.meta_value LIKE %s
		    AND p.post_status = 'publish'
		    AND p.post_type IN ( 'product', 'product_variation' )
		  ORDER BY pid ASC
		  LIMIT %d",
		$like,
		DREAMPOINT_B2B_SEARCH_MAX_IDS
	);
	// phpcs:enable

	return array_filter( array_map( 'absint', $wpdb->get_col( $sql ) ) );
}

/**
 * Popular searches — the configured search terms shown as chips in the header search panel and in
 * Quick Order. Single source: ACF option repeater `search_popular_terms` (sub-field `term`) on the
 * `theme-settings` options page. No hard-coded terms; returns [] when ACF or the option is unavailable.
 *
 * Quick Order (plugin) consumes this through the `dp_qo_popular_searches` filter so it never needs to
 * know about ACF or the option name.
 *
 * @return string[]
 */
function dreampoint_b2b_get_popular_searches(): array {
	if ( ! function_exists( 'get_field' ) ) {
		return array();
	}

	$rows = get_field( 'search_popular_terms', 'option' );
	if ( ! is_array( $rows ) ) {
		return array();
	}

	$terms = array();
	foreach ( $rows as $row ) {
		$term = trim( (string) ( is_array( $row ) ? ( $row['term'] ?? '' ) : '' ) );
		if ( '' !== $term ) {
			$terms[] = $term;
		}
	}

	return $terms;
}

add_filter( 'dp_qo_popular_searches', 'dreampoint_b2b_get_popular_searches' );

<?php
/**
 * Query Filter
 *
 * Enforces visibility rules on all WP_Query-based product queries:
 * shop, category archive, search, WooFilter Pro AJAX, WC REST API.
 *
 * Hooks at priority 8 — before WooCommerce core (priority 9-10).
 *
 * PHP 8.2+ note: context is stored in a static class property keyed by
 * spl_object_id($query) rather than as a dynamic WP_Query property, to
 * avoid deprecated dynamic property assignments.
 *
 * @package Dreampoint_B2B
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dreampoint_B2B_Query_Filter {

	/**
	 * Pending contexts indexed by spl_object_id of the WP_Query instance.
	 * Populated in filter_product_query, consumed and cleared in add_access_join.
	 *
	 * @var array<int, Dreampoint_B2B_Visibility_Context>
	 */
	private static array $pending_contexts = [];

	public function __construct( private readonly Dreampoint_B2B_Visibility_Engine $engine ) {}

	public function register_hooks(): void {
		add_action( 'pre_get_posts',      [ $this, 'filter_product_query' ], 8 );
		add_filter( 'posts_clauses',      [ $this, 'add_access_join' ], 10, 2 );
		add_filter( 'rest_product_query', [ $this, 'filter_rest_query' ], 10, 2 );
		add_filter( 'get_terms',          [ $this, 'filter_brand_terms' ], 10, 3 );
	}

	// -------------------------------------------------------------------------
	// Hooks
	// -------------------------------------------------------------------------

	public function filter_product_query( WP_Query $query ): void {
		if ( ! $this->should_filter( $query ) ) {
			if ( defined( 'DP_VISIBILITY_DEBUG' ) && DP_VISIBILITY_DEBUG ) {
				error_log( '[dp_visibility] filter_product_query | should_filter=false | query skipped' );
			}
			return;
		}

		$user_id = get_current_user_id();
		$context = $this->engine->get_context( $user_id );

		if ( defined( 'DP_VISIBILITY_DEBUG' ) && DP_VISIBILITY_DEBUG ) {
			error_log( sprintf(
				'[dp_visibility] filter_product_query | user_id=%d | access_type=%s',
				$user_id,
				$context->access_type
			) );
		}

		if ( $context->is_full_access() ) {
			return;
		}

		if ( $context->is_no_access() ) {
			if ( defined( 'DP_VISIBILITY_DEBUG' ) && DP_VISIBILITY_DEBUG ) {
				error_log( '[dp_visibility] filter_product_query | no_access → post__in=[0] + SQL block queued' );
			}
			$query->set( 'post__in', [ 0 ] );
			// Backup: queue SQL-level AND 1=0 via posts_clauses in case post__in
			// is overridden by a later hook (WooCommerce core, WPF, etc.).
			self::$pending_contexts[ spl_object_id( $query ) ] = $context;
			return;
		}

		if ( $context->is_rule_based() ) {
			// posts_clauses (add_access_join) will build the WHERE OR conditions.
			self::$pending_contexts[ spl_object_id( $query ) ] = $context;
			return;
		}

		if ( $context->is_custom_offer() ) {
			// posts_clauses will INNER JOIN on wp_dp_product_access.
			self::$pending_contexts[ spl_object_id( $query ) ] = $context;

			// override_remove: user-level explicit deny, wins over everything.
			if ( ! empty( $context->override_remove ) ) {
				$existing = (array) $query->get( 'post__not_in' );
				$query->set( 'post__not_in', array_unique( array_merge( $existing, $context->override_remove ) ) );
			}
		}
	}

	/**
	 * Handles both custom_offer (INNER JOIN) and rule_based (WHERE OR conditions).
	 *
	 * @param array    $clauses SQL clause array.
	 * @param WP_Query $query   Current WP_Query instance.
	 * @return array
	 */
	public function add_access_join( array $clauses, WP_Query $query ): array {
		$qid = spl_object_id( $query );

		if ( ! isset( self::$pending_contexts[ $qid ] ) ) {
			return $clauses;
		}

		$context = self::$pending_contexts[ $qid ];
		unset( self::$pending_contexts[ $qid ] ); // clean up

		global $wpdb;

		// Absolute SQL block — no_access must survive any post__in override by WC/WPF.
		if ( $context->is_no_access() ) {
			if ( defined( 'DP_VISIBILITY_DEBUG' ) && DP_VISIBILITY_DEBUG ) {
				error_log( '[dp_visibility] add_access_join | no_access → AND 1=0 applied' );
			}
			$clauses['where'] .= ' AND 1=0';
			return $clauses;
		}

		if ( $context->is_custom_offer() ) {
			$user_id = get_current_user_id();
			$table   = Dreampoint_B2B_Access_Table::get_name();

			$clauses['join']  .= " INNER JOIN {$table} dp_pa ON {$wpdb->posts}.ID = dp_pa.product_id";
			$clauses['where'] .= $wpdb->prepare(
				" AND dp_pa.user_id = %d AND dp_pa.access = 'allow'",
				$user_id
			);
			return $clauses;
		}

		if ( $context->is_rule_based() ) {
			$clauses['where'] .= $this->build_rule_based_where( $context );
		}

		return $clauses;
	}

	/**
	 * Visibility for REST product queries is handled by pre_get_posts + posts_clauses above
	 * (WP_Query fires these for all query types including REST).
	 * This hook exists to propagate the ERP skip flag; the dp_skip_visibility arg passes
	 * through to WP_Query where should_filter() detects it.
	 */
	public function filter_rest_query( array $args, WP_REST_Request $request ): array {
		return $args;
	}

	/**
	 * Filters product_brand terms to match the current user's visibility context.
	 *
	 * Covers shop brand widgets, WooFilter Pro brand filter UI, and any
	 * get_terms( 'product_brand' ) call on the frontend.
	 *
	 * @param WP_Term[]|int[]|string[] $terms
	 * @param string[]                 $taxonomies
	 * @param array<string, mixed>     $args
	 * @return WP_Term[]|int[]|string[]
	 */
	public function filter_brand_terms( array $terms, array $taxonomies, array $args ): array {
		if ( ! in_array( 'product_brand', (array) $taxonomies, true ) ) {
			return $terms;
		}

		// Skip admin pages; WooFilter Pro AJAX goes through admin-ajax.php and must be filtered.
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return $terms;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return $terms;
		}

		$user_id = get_current_user_id();
		$context = $this->engine->get_context( $user_id );

		if ( defined( 'DP_VISIBILITY_DEBUG' ) && DP_VISIBILITY_DEBUG ) {
			error_log( sprintf(
				'[dp_visibility] filter_brand_terms | user_id=%d | access_type=%s | term_count=%d',
				$user_id,
				$context->access_type,
				count( $terms )
			) );
		}

		if ( $context->is_full_access() ) {
			return $terms;
		}

		if ( $context->is_no_access() ) {
			return [];
		}

		if ( $context->is_rule_based() ) {
			// No brand rules → visibility is by category/explicit only; don't filter brand list.
			if ( empty( $context->allowed_brand_ids ) ) {
				return $terms;
			}
			$allowed = $context->allowed_brand_ids;
			return array_values( array_filter( $terms, static function ( $term ) use ( $allowed ): bool {
				return in_array( self::term_id_from( $term ), $allowed, true );
			} ) );
		}

		if ( $context->is_custom_offer() ) {
			$allowed = $this->get_brand_ids_for_custom_offer( $user_id );
			if ( empty( $allowed ) ) {
				return [];
			}
			return array_values( array_filter( $terms, static function ( $term ) use ( $allowed ): bool {
				return in_array( self::term_id_from( $term ), $allowed, true );
			} ) );
		}

		return $terms;
	}

	// -------------------------------------------------------------------------
	// SQL builders
	// -------------------------------------------------------------------------

	/**
	 * Builds the WHERE clause addition for rule_based users.
	 *
	 * Final SQL shape (appended to existing WHERE):
	 *   AND ( <brand EXISTS> OR <cat EXISTS> OR <explicit_allow IN> )
	 *   AND posts.ID NOT IN ( <deny_ids> )          -- only if deny list non-empty
	 */
	private function build_rule_based_where( Dreampoint_B2B_Visibility_Context $ctx ): string {
		global $wpdb;

		$conditions = [];

		if ( ! empty( $ctx->allowed_brand_ids ) ) {
			$ids          = implode( ',', array_map( 'intval', $ctx->allowed_brand_ids ) );
			$conditions[] = "(EXISTS (
				SELECT 1 FROM {$wpdb->term_relationships} dp_tr1
				INNER JOIN {$wpdb->term_taxonomy} dp_tt1 ON dp_tr1.term_taxonomy_id = dp_tt1.term_taxonomy_id
				WHERE dp_tr1.object_id = {$wpdb->posts}.ID
				AND dp_tt1.taxonomy = 'product_brand'
				AND dp_tt1.term_id IN ({$ids})
			))";
		}

		if ( ! empty( $ctx->allowed_cat_ids ) ) {
			$ids          = implode( ',', array_map( 'intval', $ctx->allowed_cat_ids ) );
			$conditions[] = "(EXISTS (
				SELECT 1 FROM {$wpdb->term_relationships} dp_tr2
				INNER JOIN {$wpdb->term_taxonomy} dp_tt2 ON dp_tr2.term_taxonomy_id = dp_tt2.term_taxonomy_id
				WHERE dp_tr2.object_id = {$wpdb->posts}.ID
				AND dp_tt2.taxonomy = 'product_cat'
				AND dp_tt2.term_id IN ({$ids})
			))";
		}

		// explicit_include + override_add: allowed regardless of brand/cat matching.
		$allow_explicit = array_unique( array_merge( $ctx->explicit_include_ids, $ctx->override_add ) );
		if ( ! empty( $allow_explicit ) ) {
			$ids          = implode( ',', array_map( 'intval', $allow_explicit ) );
			$conditions[] = "({$wpdb->posts}.ID IN ({$ids}))";
		}

		// Should not happen (empty bucket → no_access upstream), but fail closed if it does.
		if ( empty( $conditions ) ) {
			return $wpdb->prepare( " AND {$wpdb->posts}.ID = %d", 0 );
		}

		$allow_clause = ' AND (' . implode( ' OR ', $conditions ) . ')';

		// Priority rules:
		// Layer 2 (override_add) beats layer 3 (explicit_exclude) → subtract override_add from deny.
		// Layer 1 (override_remove) beats all → always in deny list.
		$effective_exclude = array_diff( $ctx->explicit_exclude_ids, $ctx->override_add );
		$deny_ids          = array_unique( array_merge( $ctx->override_remove, $effective_exclude ) );

		if ( empty( $deny_ids ) ) {
			return $allow_clause;
		}

		$deny_ids_str = implode( ',', array_map( 'intval', $deny_ids ) );
		return $allow_clause . " AND {$wpdb->posts}.ID NOT IN ({$deny_ids_str})";
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns brand term IDs that contain at least one product visible to a
	 * custom_offer user (derived from wp_dp_product_access).
	 *
	 * @return int[]
	 */
	private function get_brand_ids_for_custom_offer( int $user_id ): array {
		global $wpdb;
		$access_table = Dreampoint_B2B_Access_Table::get_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name built from $wpdb->prefix + class constant
		$results = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT tt.term_id
			 FROM {$wpdb->term_relationships} tr
			 INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			 INNER JOIN {$access_table} pa ON tr.object_id = pa.product_id
			 WHERE tt.taxonomy = 'product_brand'
			 AND pa.user_id = %d
			 AND pa.access = 'allow'",
			$user_id
		) );

		return $results ? array_map( 'intval', $results ) : [];
	}

	/**
	 * Extracts a term ID from a WP_Term object, integer, or numeric string.
	 * Returns 0 for non-numeric values (names/slugs) so they pass through unfiltered.
	 */
	private static function term_id_from( mixed $term ): int {
		if ( $term instanceof WP_Term ) {
			return (int) $term->term_id;
		}
		if ( is_int( $term ) || is_numeric( $term ) ) {
			return (int) $term;
		}
		return 0; // slug/name queries — cannot match by ID, let them pass
	}

	private function should_filter( WP_Query $query ): bool {
		// Skip regular admin pages; allow AJAX (WooFilter Pro AJAX goes through admin-ajax.php).
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return false;
		}

		// Admins and shop managers bypass visibility.
		if ( current_user_can( 'manage_options' ) ) {
			return false;
		}

		// ERP integration sets this flag to see the full catalog.
		if ( ! empty( $query->get( 'dp_skip_visibility' ) ) ) {
			return false;
		}

		$post_type = $query->get( 'post_type' );

		if ( is_array( $post_type ) ) {
			return in_array( 'product', $post_type, true );
		}

		if ( '' !== $post_type ) {
			return 'product' === $post_type;
		}

		// post_type not explicitly set — apply to main query on WC pages
		// (covers shop, category archive, brand archive, search).
		return $query->is_main_query()
			&& function_exists( 'is_woocommerce' )
			&& is_woocommerce();
	}
}

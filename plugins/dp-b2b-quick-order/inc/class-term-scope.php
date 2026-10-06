<?php
defined( 'ABSPATH' ) || exit;

/**
 * Visibility-safe taxonomy vocabulary for the Quick Order filter sidebar.
 *
 * WBW (and WooCommerce) build filter option lists from get_terms(). The raw
 * vocabulary of `product_brand` and the global `pa_*` attribute taxonomies is NOT
 * B2B visibility-safe: a term may belong only to products the current user cannot
 * see (or to unpublished products), and hide_empty / term counts are global.
 *
 * Invariant enforced here, ONLY inside the Quick Order scope:
 *
 *   a product_brand / pa_* term is returned only if at least one product in the
 *   current user's permitted, published Quick Order universe carries it.
 *
 * The universe is a WP_Query for published products with the same base args as the
 * Quick Order product query, so the canonical B2B visibility engine (pre_get_posts +
 * posts_clauses) decides what is permitted — no rule is re-implemented here.
 *
 * Data model: WooCommerce attaches pa_* terms to the PARENT product (simple and
 * variable alike); product_variation posts carry no term relationships and only
 * store `attribute_pa_*` meta. Taking terms from permitted parents therefore means
 * an inaccessible parent can never contribute vocabulary through its variations.
 *
 * Scope (inert everywhere else):
 *  - while the Quick Order template renders (enter()/leave()), and
 *  - the WBW frontend AJAX action when it was sent from the Quick Order page URL.
 * Not applied to wp-admin screens, normal archives, REST, cron or CLI.
 *
 * No shared cache: the allowed set is memoized per request and per user id only.
 */
class DP_Quick_Order_Term_Scope {

	private const WBW_AJAX_ACTION = 'woobewoo_pf_filters_frontend';

	private static int $render_depth = 0;
	private static ?bool $ajax_scope = null;

	/** @var array<int, array<int, true>> user id => [ term_id => true ] */
	private array $allowed = [];

	/** Re-entrancy guard: the universe query may itself call get_terms() (visibility engine). */
	private bool $computing = false;

	public function __construct() {
		add_filter( 'get_terms', [ $this, 'filter_terms' ], 20, 3 );

		if ( self::is_scoped_ajax() ) {
			// WBW's AJAX handler calls remove_all_filters( 'pre_get_posts' ) when its
			// "remove actions" option is on, which would strip the visibility engine
			// from any query issued later. Resolve the permitted universe first.
			add_action( 'wp_loaded', [ $this, 'prime' ], 20 );
		}
	}

	public static function enter(): void {
		++self::$render_depth;
	}

	public static function leave(): void {
		self::$render_depth = max( 0, self::$render_depth - 1 );
	}

	private static function is_active(): bool {
		return self::$render_depth > 0 || self::is_scoped_ajax();
	}

	/** WBW frontend AJAX request that originates from the Quick Order page. */
	private static function is_scoped_ajax(): bool {
		if ( null !== self::$ajax_scope ) {
			return self::$ajax_scope;
		}

		self::$ajax_scope = false;

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
		if ( ! wp_doing_ajax() || ( $_REQUEST['action'] ?? '' ) !== self::WBW_AJAX_ACTION ) {
			return false;
		}

		$current = isset( $_POST['currenturl'] ) ? esc_url_raw( wp_unslash( (string) $_POST['currenturl'] ) ) : '';
		// phpcs:enable
		if ( '' === $current ) {
			return false;
		}

		$request_path = (string) wp_parse_url( $current, PHP_URL_PATH );
		$page_path    = (string) wp_parse_url( home_url( '/' . DP_Quick_Order_Config::PAGE_SLUG . '/' ), PHP_URL_PATH );

		self::$ajax_scope = untrailingslashit( $request_path ) === untrailingslashit( $page_path );

		return self::$ajax_scope;
	}

	private static function is_scoped_taxonomy( string $taxonomy ): bool {
		return 'product_brand' === $taxonomy || str_starts_with( $taxonomy, 'pa_' );
	}

	public function prime(): void {
		$this->allowed_term_ids();
	}

	/**
	 * @param mixed                $terms
	 * @param string[]|string      $taxonomies
	 * @param array<string, mixed> $args
	 * @return mixed
	 */
	public function filter_terms( $terms, $taxonomies, $args ) {
		if ( $this->computing || ! is_array( $terms ) || ! self::is_active() ) {
			return $terms;
		}

		$taxonomies = (array) $taxonomies;
		$scoped     = array_filter( $taxonomies, [ self::class, 'is_scoped_taxonomy' ] );
		if ( ! $scoped ) {
			return $terms;
		}

		$solely_scoped = count( $scoped ) === count( $taxonomies );
		$fields        = (string) ( is_array( $args ) ? ( $args['fields'] ?? 'all' ) : 'all' );
		$allowed       = $this->allowed_term_ids();

		// Result shapes that carry no term id cannot be checked: fail closed.
		if ( $solely_scoped && ! in_array( $fields, [ 'all', 'all_with_object_id', 'ids', 'id=>name', 'id=>slug', 'id=>parent' ], true ) ) {
			return [];
		}

		$keyed = in_array( $fields, [ 'id=>name', 'id=>slug', 'id=>parent' ], true );
		$out   = [];

		foreach ( $terms as $key => $term ) {
			if ( $term instanceof WP_Term ) {
				$keep = ! self::is_scoped_taxonomy( $term->taxonomy ) || isset( $allowed[ (int) $term->term_id ] );
			} elseif ( $keyed ) {
				$keep = isset( $allowed[ (int) $key ] );
			} elseif ( $solely_scoped && is_numeric( $term ) ) {
				$keep = isset( $allowed[ (int) $term ] );
			} else {
				// Mixed-taxonomy scalar results carry no taxonomy identity: only
				// the scoped-only case above is decidable, anything else is kept.
				$keep = ! $solely_scoped;
			}

			if ( $keep ) {
				$out[ $key ] = $term;
			}
		}

		return $keyed ? $out : array_values( $out );
	}

	/**
	 * term_id => true for every scoped term carried by a permitted published product
	 * (plus ancestors, so hierarchical lists stay reachable).
	 *
	 * @return array<int, true>
	 */
	private function allowed_term_ids(): array {
		$user_id = get_current_user_id();
		if ( isset( $this->allowed[ $user_id ] ) ) {
			return $this->allowed[ $user_id ];
		}

		global $wpdb;

		$this->computing = true;
		$product_ids     = ( new WP_Query( [
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'fields'                 => 'ids',
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'suppress_filters'       => false,
			'dp_quick_order'         => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		] ) )->posts;
		$this->computing = false;

		$allowed = [];
		$pa_like = $wpdb->esc_like( 'pa_' ) . '%';

		foreach ( array_chunk( array_map( 'intval', $product_ids ), 1000 ) as $chunk ) {
			$in = implode( ',', $chunk );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ids cast to int above
			$rows = $wpdb->get_col( $wpdb->prepare(
				"SELECT DISTINCT tt.term_id
				 FROM {$wpdb->term_relationships} tr
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				 WHERE tr.object_id IN ({$in})
				 AND ( tt.taxonomy = 'product_brand' OR tt.taxonomy LIKE %s )",
				$pa_like
			) );
			foreach ( $rows as $term_id ) {
				$allowed[ (int) $term_id ] = true;
			}
		}

		// Ancestors of allowed terms (hierarchical brands): one query, then walk in PHP.
		if ( $allowed ) {
			$parents = $wpdb->get_results( $wpdb->prepare(
				"SELECT term_id, parent FROM {$wpdb->term_taxonomy}
				 WHERE parent > 0 AND ( taxonomy = 'product_brand' OR taxonomy LIKE %s )",
				$pa_like
			), OBJECT_K );
			foreach ( array_keys( $allowed ) as $term_id ) {
				$cursor = $term_id;
				while ( isset( $parents[ $cursor ] ) ) {
					$cursor             = (int) $parents[ $cursor ]->parent;
					$allowed[ $cursor ] = true;
				}
			}
		}

		$this->allowed[ $user_id ] = $allowed;

		return $allowed;
	}
}

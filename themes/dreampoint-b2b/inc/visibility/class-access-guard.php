<?php
/**
 * Access Guard
 *
 * Prevents direct access to restricted products via URL and scrubs WC REST responses.
 * Complements the query filter — handles cases that bypass WP_Query.
 *
 * @package Dreampoint_B2B
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dreampoint_B2B_Access_Guard {

	public function __construct( private readonly Dreampoint_B2B_Visibility_Engine $engine ) {}

	public function register_hooks(): void {
		add_action( 'template_redirect', [ $this, 'guard_single_product' ] );
		add_filter( 'woocommerce_rest_prepare_product_object', [ $this, 'scrub_rest_response' ], 10, 3 );

		// Quick Order integration contracts — loosely coupled via WP filters.
		add_filter( 'dp_b2b_product_accessible',          [ $this, 'handle_product_accessible' ], 10, 3 );
		add_filter( 'dp_b2b_quick_order_user_allowed',    [ $this, 'handle_user_allowed' ],        10, 2 );
	}

	// -------------------------------------------------------------------------
	// Hooks
	// -------------------------------------------------------------------------

	public function guard_single_product(): void {
		if ( ! is_product() ) {
			return;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		$product_id = get_queried_object_id();
		$user_id    = get_current_user_id();

		if ( ! $this->is_product_visible( $product_id, $user_id ) ) {
			wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
			exit;
		}
	}

	public function scrub_rest_response( WP_REST_Response $response, WC_Product $product, WP_REST_Request $request ): WP_REST_Response {
		// ERP bypass: authenticated ERP integration must see all products.
		if ( ! empty( $request->get_param( 'dp_skip_visibility' ) ) ) {
			return $response;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return $response;
		}

		$user_id = get_current_user_id();
		if ( ! $this->is_product_visible( $product->get_id(), $user_id ) ) {
			return new WP_REST_Response( null, 404 );
		}

		return $response;
	}

	// -------------------------------------------------------------------------
	// Visibility check (per-product, used by guard and scrub)
	// -------------------------------------------------------------------------

	public function handle_product_accessible( bool $default, int $product_id, int $user_id ): bool {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return $this->is_product_visible( $product_id, $user_id );
	}

	public function handle_user_allowed( bool $default, int $user_id ): bool {
		if ( ! $user_id ) {
			return false;
		}
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}
		// B2B users are identified by a bucket assignment — lightweight meta lookup.
		return ! empty( get_user_meta( $user_id, 'dp_bucket_id', true ) );
	}

	public function is_product_visible( int $product_id, int $user_id ): bool {
		$context = $this->engine->get_context( $user_id );

		if ( $context->is_full_access() ) {
			return true;
		}

		if ( $context->is_no_access() ) {
			return false;
		}

		// Layer 1: override_remove → DENY (highest priority, beats everything).
		if ( in_array( $product_id, $context->override_remove, true ) ) {
			return false;
		}

		// Layer 2: override_add → ALLOW (beats bucket explicit_exclude).
		if ( in_array( $product_id, $context->override_add, true ) ) {
			return true;
		}

		if ( $context->is_custom_offer() ) {
			// custom_offer: allowed set is the access table; override_add/remove already handled above.
			return ( new Dreampoint_B2B_Access_Table() )->user_can_see( $user_id, $product_id );
		}

		// rule_based from here:

		// Layer 3: explicit_exclude → DENY.
		if ( in_array( $product_id, $context->explicit_exclude_ids, true ) ) {
			return false;
		}

		// Layer 4: explicit_include → ALLOW.
		if ( in_array( $product_id, $context->explicit_include_ids, true ) ) {
			return true;
		}

		// Layer 5: brand / category terms → ALLOW (OR logic).
		if ( ! empty( $context->allowed_brand_ids ) || ! empty( $context->allowed_cat_ids ) ) {
			$terms = $this->get_product_term_ids( $product_id );

			if ( ! empty( $context->allowed_brand_ids )
				&& array_intersect( $context->allowed_brand_ids, $terms['brand'] )
			) {
				return true;
			}

			if ( ! empty( $context->allowed_cat_ids )
				&& array_intersect( $context->allowed_cat_ids, $terms['cat'] )
			) {
				return true;
			}
		}

		// Layer 6: default → DENY.
		return false;
	}

	/**
	 * Returns the product's brand and category term IDs.
	 *
	 * @return array{ brand: int[], cat: int[] }
	 */
	private function get_product_term_ids( int $product_id ): array {
		$brand_terms = wp_get_object_terms( $product_id, 'product_brand', [ 'fields' => 'ids' ] );
		$cat_terms   = wp_get_object_terms( $product_id, 'product_cat',   [ 'fields' => 'ids' ] );

		return [
			'brand' => is_wp_error( $brand_terms ) ? [] : array_map( 'intval', $brand_terms ),
			'cat'   => is_wp_error( $cat_terms )   ? [] : array_map( 'intval', $cat_terms ),
		];
	}
}

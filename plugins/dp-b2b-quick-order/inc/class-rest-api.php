<?php
defined( 'ABSPATH' ) || exit;

class DP_Quick_Order_Rest_Api {

	public function __construct(
		private readonly DP_Quick_Order_Product_Query $product_query,
		private readonly DP_Quick_Order_Cart_Sync $cart_sync
	) {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route( DP_Quick_Order_Config::REST_NAMESPACE, '/' . DP_Quick_Order_Config::REST_BASE . '/products', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_products' ],
			'permission_callback' => [ $this, 'is_b2b_user' ],
			'args'                => [
				'page'     => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
				'per_page' => [ 'type' => 'integer', 'default' => DP_Quick_Order_Config::PRODUCTS_PER_PAGE_DEFAULT, 'minimum' => 1, 'maximum' => DP_Quick_Order_Config::PRODUCTS_PER_PAGE_MAX ],
				'search'   => [ 'type' => 'string', 'default' => '' ],
				'category' => [ 'type' => 'string', 'default' => '' ],
				'brand'    => [ 'type' => 'string', 'default' => '' ],
				'qo_orderby' => [ 'type' => 'string', 'enum' => [ 'title', 'price' ], 'default' => 'title' ],
				'qo_order'   => [ 'type' => 'string', 'enum' => [ 'asc', 'desc' ], 'default' => 'asc' ],
				'price_min'    => [ 'type' => 'number', 'minimum' => 0, 'default' => null ],
				'price_max'    => [ 'type' => 'number', 'minimum' => 0, 'default' => null ],
				'stock_status' => [ 'type' => 'string', 'enum' => [ 'instock', 'outofstock', 'onbackorder', '' ], 'default' => '' ],
				'attributes'   => [ 'type' => 'string', 'default' => '' ],
			],
		] );

		register_rest_route( DP_Quick_Order_Config::REST_NAMESPACE, '/' . DP_Quick_Order_Config::REST_BASE . '/cart/sync', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'sync_cart' ],
			'permission_callback' => [ $this, 'is_b2b_user' ],
		] );

		register_rest_route( DP_Quick_Order_Config::REST_NAMESPACE, '/' . DP_Quick_Order_Config::REST_BASE . '/products/(?P<id>\d+)/variations', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_variations' ],
			'permission_callback' => [ $this, 'is_b2b_user' ],
			'args'                => [
				'id' => [ 'type' => 'integer', 'minimum' => 1 ],
			],
		] );
	}

	public function get_products( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$raw_attrs  = $request->get_param( 'attributes' );
		$attributes = [];
		if ( '' !== $raw_attrs ) {
			$decoded = json_decode( $raw_attrs, true );
			if ( is_array( $decoded ) ) {
				$attributes = $decoded;
			}
		}

		// Category and brand both accept a JSON array of mixed term IDs/slugs
		// (current frontend format), or a bare numeric string (legacy single-
		// term-ID callers) — resolution to concrete term IDs happens in
		// DP_Quick_Order_Product_Query::resolve_taxonomy_term_ids().
		$category = $this->decode_term_filter_param( $request->get_param( 'category' ) );
		$brand    = $this->decode_term_filter_param( $request->get_param( 'brand' ) );

		$results = $this->product_query->query( [
			'page'         => $request->get_param( 'page' ),
			'per_page'     => $request->get_param( 'per_page' ),
			'search'       => $request->get_param( 'search' ),
			'category'     => $category,
			'brand'        => $brand,
			'orderby'      => $request->get_param( 'qo_orderby' ),
			'order'        => $request->get_param( 'qo_order' ),
			'price_min'    => $request->get_param( 'price_min' ),
			'price_max'    => $request->get_param( 'price_max' ),
			'stock_status' => $request->get_param( 'stock_status' ),
			'attributes'   => $attributes,
		] );

		return rest_ensure_response( $results );
	}

	/**
	 * Decode a category/brand REST param: a JSON array of mixed term IDs/slugs
	 * (current frontend format), or a bare numeric string (legacy single-term-ID
	 * callers). Returns [] for an empty or unparsable value.
	 *
	 * @return list<int|string>
	 */
	private function decode_term_filter_param( string $raw ): array {
		if ( '' === $raw ) {
			return [];
		}
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}
		return is_numeric( $raw ) ? [ $raw ] : [];
	}

	public function get_variations( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$product_id = absint( $request->get_param( 'id' ) );
		$product    = wc_get_product( $product_id );

		if ( ! $product instanceof WC_Product_Variable ) {
			return new WP_Error(
				'not_variable',
				__( 'Product is not a variable product.', 'dp-b2b-quick-order' ),
				[ 'status' => 404 ]
			);
		}

		return rest_ensure_response( $this->product_query->get_variation_details( $product_id ) );
	}

	public function sync_cart( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		// WC cart/session are not initialized in REST context — load them explicitly.
		// wc_load_cart() is idempotent (guards against double-init via did_action check).
		if ( function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}

		$body = $request->get_json_params();

		// Accept both formats:
		//   {items: [...], token: N}  — batched format with stale-response token
		//   [...]                     — legacy flat array (backwards compat)
		if ( is_array( $body ) && array_key_exists( 'items', $body ) ) {
			$items = $body['items'] ?? [];
			$token = isset( $body['token'] ) ? absint( $body['token'] ) : null;
		} elseif ( is_array( $body ) && array_is_list( $body ) ) {
			$items = $body;
			$token = null;
		} else {
			return new WP_Error(
				'invalid_payload',
				__( 'Invalid cart sync payload.', 'dp-b2b-quick-order' ),
				[ 'status' => 400 ]
			);
		}

		if ( ! is_array( $items ) ) {
			return new WP_Error(
				'invalid_items',
				__( 'Items must be an array.', 'dp-b2b-quick-order' ),
				[ 'status' => 400 ]
			);
		}

		if ( count( $items ) > DP_Quick_Order_Config::CART_SYNC_MAX_BATCH ) {
			return new WP_Error(
				'payload_too_large',
				sprintf(
					/* translators: %d: max allowed items */
					__( 'Sync request exceeds maximum of %d items.', 'dp-b2b-quick-order' ),
					DP_Quick_Order_Config::CART_SYNC_MAX_BATCH
				),
				[ 'status' => 400 ]
			);
		}

		$result = $this->cart_sync->sync( $items );

		if ( null !== $token ) {
			$result['token'] = $token;
		}

		return rest_ensure_response( $result );
	}

	public function is_b2b_user(): bool {
		return (bool) apply_filters(
			'dp_b2b_quick_order_user_allowed',
			is_user_logged_in(),
			get_current_user_id()
		);
	}
}

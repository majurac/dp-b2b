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
				'category' => [ 'type' => 'integer', 'default' => 0 ],
				'brand'    => [ 'type' => 'integer', 'default' => 0 ],
			],
		] );

		register_rest_route( DP_Quick_Order_Config::REST_NAMESPACE, '/' . DP_Quick_Order_Config::REST_BASE . '/cart/sync', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'sync_cart' ],
			'permission_callback' => [ $this, 'is_b2b_user' ],
		] );
	}

	public function get_products( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$results = $this->product_query->query( [
			'page'     => $request->get_param( 'page' ),
			'per_page' => $request->get_param( 'per_page' ),
			'search'   => $request->get_param( 'search' ),
			'category' => $request->get_param( 'category' ),
			'brand'    => $request->get_param( 'brand' ),
		] );

		return rest_ensure_response( $results );
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
		return is_user_logged_in();
	}
}

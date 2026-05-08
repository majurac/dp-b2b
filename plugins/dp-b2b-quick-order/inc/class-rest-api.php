<?php
defined( 'ABSPATH' ) || exit;

class DP_Quick_Order_Rest_Api {

	private const NAMESPACE = 'dreampoint-b2b/v1';

	public function __construct(
		private readonly DP_Quick_Order_Product_Query $product_query,
		private readonly DP_Quick_Order_Cart_Sync $cart_sync
	) {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route( self::NAMESPACE, '/quick-order/products', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_products' ],
			'permission_callback' => [ $this, 'is_b2b_user' ],
			'args'                => [
				'page'     => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
				'per_page' => [ 'type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 200 ],
				'search'   => [ 'type' => 'string', 'default' => '' ],
				'category' => [ 'type' => 'integer', 'default' => 0 ],
				'brand'    => [ 'type' => 'integer', 'default' => 0 ],
			],
		] );

		register_rest_route( self::NAMESPACE, '/quick-order/cart/sync', [
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
		$items = $request->get_json_params();
		if ( ! is_array( $items ) ) {
			return new WP_Error(
				'invalid_payload',
				__( 'Invalid cart payload.', 'dp-b2b-quick-order' ),
				[ 'status' => 400 ]
			);
		}
		return rest_ensure_response( $this->cart_sync->sync( $items ) );
	}

	public function is_b2b_user(): bool {
		return is_user_logged_in();
	}
}

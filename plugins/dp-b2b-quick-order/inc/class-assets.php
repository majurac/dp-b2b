<?php
defined( 'ABSPATH' ) || exit;

class DP_Quick_Order_Assets {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function enqueue(): void {
		if ( ! $this->is_quick_order_page() ) {
			return;
		}

		wp_enqueue_style(
			'dp-quick-order',
			DP_QUICK_ORDER_URL . 'assets/dist/quick-order.css',
			[],
			DP_QUICK_ORDER_VERSION
		);

		wp_enqueue_script(
			'dp-quick-order',
			DP_QUICK_ORDER_URL . 'assets/dist/quick-order.js',
			[],
			DP_QUICK_ORDER_VERSION,
			true
		);

		wp_localize_script( 'dp-quick-order', 'dpQuickOrder', [
			'restUrl'          => esc_url_raw( rest_url( DP_Quick_Order_Config::REST_NAMESPACE . '/' ) ),
			'cartSyncUrl'      => esc_url_raw( rest_url(
				DP_Quick_Order_Config::REST_NAMESPACE . '/' .
				DP_Quick_Order_Config::REST_BASE . '/cart/sync'
			) ),
			'productsUrl'      => esc_url_raw( rest_url(
				DP_Quick_Order_Config::REST_NAMESPACE . '/' .
				DP_Quick_Order_Config::REST_BASE . '/products'
			) ),
			'storeUrl'         => esc_url_raw( rest_url( 'wc/store/v1/' ) ),
			'nonce'            => wp_create_nonce( DP_Quick_Order_Config::NONCE_ACTION ),
			'wpNonce'          => wp_create_nonce( 'wp_rest' ),
			'timeoutMs'        => DP_Quick_Order_Config::CART_SYNC_TIMEOUT_MS,
			'cartSyncMaxBatch' => DP_Quick_Order_Config::CART_SYNC_MAX_BATCH,
			'currency'         => get_woocommerce_currency(),
			'placeholderImg'   => esc_url( wc_placeholder_img_src() ),
			'i18n'             => [
				'skuLabel'           => __( 'Kataloški broj:', 'dp-b2b-quick-order' ),
				'itemsSuffix'        => __( 'artikala', 'dp-b2b-quick-order' ),
				'rowsSuffix'         => __( 'varijacija', 'dp-b2b-quick-order' ),
				'loadingVariations'  => __( 'Učitavanje varijacija...', 'dp-b2b-quick-order' ),
				'variationLoadError' => __( 'Greška pri učitavanju varijacija.', 'dp-b2b-quick-order' ),
				'adding'             => __( 'Dodavanje...', 'dp-b2b-quick-order' ),
				'partialFailure'     => __( 'Neki artikli nisu dodani u košaricu — provjerite stanje na skladištu.', 'dp-b2b-quick-order' ),
			],
		] );
	}

	public function is_quick_order_page(): bool {
		return is_page( DP_Quick_Order_Config::PAGE_SLUG );
	}
}

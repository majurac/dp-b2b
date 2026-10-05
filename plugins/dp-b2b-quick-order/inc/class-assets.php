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
			'searchMinChars'   => DP_Quick_Order_Config::SEARCH_MIN_CHARS,
			'currency'         => get_woocommerce_currency(),
			// BCP-47 form of the site locale (hr_HR -> hr-HR) so the footer subtotal is formatted like the prices.
			'locale'           => str_replace( '_', '-', get_locale() ),
			'placeholderImg'   => esc_url( wc_placeholder_img_src() ),
			'i18n'             => [
				'skuLabel'           => __( 'Kataloški broj:', 'dp-b2b-quick-order' ),
				// Croatian declension forms for the footer counts: [1, 2-4, 5+].
				'itemForms'          => [
					__( 'artikl', 'dp-b2b-quick-order' ),
					__( 'artikla', 'dp-b2b-quick-order' ),
					__( 'artikala', 'dp-b2b-quick-order' ),
				],
				'skuForms'           => [
					__( 'različiti SKU', 'dp-b2b-quick-order' ),
					__( 'različita SKU-a', 'dp-b2b-quick-order' ),
					__( 'različitih SKU-a', 'dp-b2b-quick-order' ),
				],
				'priceLabel'         => __( 'Cijena:', 'dp-b2b-quick-order' ),
				'qtyLabel'           => __( 'Količina', 'dp-b2b-quick-order' ),
				'qtyDecrease'        => __( 'Smanji količinu', 'dp-b2b-quick-order' ),
				'qtyIncrease'        => __( 'Povećaj količinu', 'dp-b2b-quick-order' ),
				'loadingVariations'  => __( 'Učitavanje varijacija...', 'dp-b2b-quick-order' ),
				'variationLoadError' => __( 'Greška pri učitavanju varijacija.', 'dp-b2b-quick-order' ),
				'adding'             => __( 'Dodavanje...', 'dp-b2b-quick-order' ),
				'noResultsTitle'     => __( 'Nismo pronašli proizvode', 'dp-b2b-quick-order' ),
				/* translators: %s: current search term */
				'noResultsSearchFilters' => __( 'Za pojam “%s” i odabrane filtre nije pronađen nijedan proizvod. Pokušajte sljedeće:', 'dp-b2b-quick-order' ),
				/* translators: %s: current search term */
				'noResultsSearch'    => __( 'Za pojam “%s” nije pronađen nijedan proizvod. Pokušajte sljedeće:', 'dp-b2b-quick-order' ),
				'noResultsFilters'   => __( 'Za odabrane filtre nije pronađen nijedan proizvod. Pokušajte sljedeće:', 'dp-b2b-quick-order' ),
				'noResultsTipSearch' => __( 'Provjeriti pravopis ili koristiti drugi pojam za pretragu', 'dp-b2b-quick-order' ),
				'noResultsTipFilters' => __( 'Ukloniti neke filtre kako biste vidjeli više rezultata', 'dp-b2b-quick-order' ),
				'noResultsTipBrowse' => __( 'Pregledati ostale kategorije ili brendove', 'dp-b2b-quick-order' ),
				'clearAllFilters'    => __( 'Očisti sve filtre', 'dp-b2b-quick-order' ),
				'viewAllProducts'    => __( 'Pogledaj sve proizvode', 'dp-b2b-quick-order' ),
				'emptyCatalog'       => __( 'Nema dostupnih proizvoda.', 'dp-b2b-quick-order' ),
				/* translators: %s: filter name */
				'removeFilter'       => __( 'Ukloni filter: %s', 'dp-b2b-quick-order' ),
				'partialFailure'     =>__( 'Neki artikli nisu dodani u košaricu — provjerite stanje na skladištu.', 'dp-b2b-quick-order' ),
			],
		] );
	}

	public function is_quick_order_page(): bool {
		return is_page( DP_Quick_Order_Config::PAGE_SLUG );
	}
}

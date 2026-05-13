<?php
defined( 'ABSPATH' ) || exit;

class DP_Quick_Order_Plugin {

	private static ?self $instance = null;

	private DP_Quick_Order_Visibility_Integration $visibility;
	private DP_Quick_Order_Filter_Bridge $filter_bridge;
	private DP_Quick_Order_Product_Query $product_query;
	private DP_Quick_Order_Cart_Sync $cart_sync;
	private DP_Quick_Order_Rest_Api $rest_api;
	private DP_Quick_Order_Assets $assets;
	private DP_Quick_Order_Frontend $frontend;

	private function __construct() {
		$this->init();
	}

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function init(): void {
		$this->visibility    = new DP_Quick_Order_Visibility_Integration();
		$this->filter_bridge = new DP_Quick_Order_Filter_Bridge();
		$this->product_query = new DP_Quick_Order_Product_Query( $this->visibility );
		$this->cart_sync     = new DP_Quick_Order_Cart_Sync();
		$this->rest_api      = new DP_Quick_Order_Rest_Api( $this->product_query, $this->cart_sync );
		$this->assets        = new DP_Quick_Order_Assets();
		$this->frontend      = new DP_Quick_Order_Frontend( $this->assets );
	}
}

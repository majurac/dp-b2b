<?php
/**
 * Plugin Name: DP B2B Quick Order
 * Description: Performance-oriented WooCommerce B2B Quick Order system for Dreampoint B2B.
 * Version: 1.0.3
 * Author: Dreampoint
 * Text Domain: dp-b2b-quick-order
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.3
 * WC requires at least: 8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'DP_QUICK_ORDER_VERSION', '1.0.3' );
define( 'DP_QUICK_ORDER_FILE', __FILE__ );
define( 'DP_QUICK_ORDER_PATH', plugin_dir_path( __FILE__ ) );
define( 'DP_QUICK_ORDER_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register( function ( string $class ): void {
	if ( ! str_starts_with( $class, 'DP_Quick_Order_' ) ) {
		return;
	}
	$slug = strtolower( str_replace( [ 'DP_Quick_Order_', '_' ], [ '', '-' ], $class ) );
	$file = DP_QUICK_ORDER_PATH . 'inc/class-' . $slug . '.php';
	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

add_action( 'plugins_loaded', function (): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function (): void {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'DP B2B Quick Order requires WooCommerce to be active.', 'dp-b2b-quick-order' ) .
				'</p></div>';
		} );
		return;
	}

	if ( ! file_exists( DP_QUICK_ORDER_PATH . 'assets/dist/quick-order.js' ) ) {
		add_action( 'admin_notices', function (): void {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'DP B2B Quick Order: compiled JS asset missing. Run `npm run build` in the plugin directory.', 'dp-b2b-quick-order' ) .
				'</p></div>';
		} );
		return;
	}

	DP_Quick_Order_Plugin::get_instance();
} );

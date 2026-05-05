<?php
/**
 * Plugin Name:          SilkyPress Input Field Block
 * Requires Plugins:     woocommerce
 * Description:          A plugin for adding input fields to the WooCommerce Checkout Block.
 * Version:              1.7
 * Author:               SilkyPress
 *
 * Requires at least:    6.0
 * Requires PHP:         7.3
 * WC requires at least: 9.0.0
 * WC tested up to:	     10.4
 *
 * Text Domain:          silkypress-input-field-block
 * Domain Path:          /languages
 */

defined( 'ABSPATH' ) || exit;

// Define constants.
$plugin_data = get_file_data( __FILE__, array( 'version' => 'version' ) );
define( 'SILKYPRESS_INPUT_FIELD_BLOCK_VERSION', $plugin_data['version'] );
define( 'SILKYPRESS_INPUT_FIELD_BLOCK_FILE', __FILE__ );
define( 'SILKYPRESS_INPUT_FIELD_BLOCK_PATH', plugin_dir_path( __FILE__ ) );

class SilkyPress_Input_Field_Block {

	/**
	 * Initialize the plugin.
	 */
	public static function init() {
		
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', [ __CLASS__, 'woocommerce_fallback_notice' ] );
			return;
		}

		add_action( 'woocommerce_blocks_loaded', [ __CLASS__, 'load_block' ] );
		add_action( 'init', [ __CLASS__, 'load_plugin_textdomain' ] );
		add_action( 'before_woocommerce_init', [ __CLASS__, 'compatibility_custom_order_tables' ] );
		add_action( 'woocommerce_blocks_loaded', [ __CLASS__, 'load_blocks_type_package' ] );
		register_uninstall_hook( SILKYPRESS_INPUT_FIELD_BLOCK_FILE, [ __CLASS__, 'uninstall_hook' ] );
	}

	/**
	 * Register the Input Field block. 
	 */
	public static function load_block() {
		require_once SILKYPRESS_INPUT_FIELD_BLOCK_PATH . 'includes/class-blocks-integration.php';

		add_action( 'woocommerce_blocks_checkout_block_registration', [ __CLASS__, 'register_block' ], 10, 1 );
	}

	public static function register_block( $integration_registry ) {

		require_once SILKYPRESS_INPUT_FIELD_BLOCK_PATH . 'includes/class-store-endpoint.php';
		SilkyPress_Input_Field_Block_Extend_Store_Endpoint::init();

		require_once SILKYPRESS_INPUT_FIELD_BLOCK_PATH . 'includes/class-input-settings.php';
		SilkyPress_Input_Field_Block_Input_Settings::init();

		$integration_registry->register( new SilkyPress_Input_Field_Block_Integration() );
	}

	/**
	 * Register the 'silkypress-input-field-block' text domain.
	 */
	public static function load_plugin_textdomain() {
		load_plugin_textdomain( 'silkypress-input-field-block', false, SILKYPRESS_INPUT_FIELD_BLOCK_PATH . 'languages' );
	}

	/**
	 * The uninstall hook.
	 */
	public static function uninstall_hook() {
		delete_option( 'silkypress-input-field-block' );
	}

	/**
	 * Load the BlockTypes package.
	 */
	public static function load_blocks_type_package() {
		if ( class_exists( '\Automattic\WooCommerce\Blocks\Package' ) && class_exists( '\Automattic\WooCommerce\Blocks\BlockTypesController' ) ) { 
			\Automattic\WooCommerce\Blocks\Package::container()->get( \Automattic\WooCommerce\Blocks\BlockTypesController::class );
		}
	}

	/**
	 * Declarate compatibility with WooCommerce Custom Order Tables.
	 */
	public static function compatibility_custom_order_tables() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SILKYPRESS_INPUT_FIELD_BLOCK_FILE, true );
		}
	}

	/**
	 * Fallback notice if the WooCommerce plugin is not activated.
	 */
	public static function woocommerce_fallback_notice() {
		echo '<div class="error"><p>' . wp_kses(
            sprintf(
                /* translators: %s: woocommerce link */
                __( 'The <b>SilkyPress Input Field Block</b> plugin is activated, but it requires %s in order to work.', 'silkypress-input-field-block' ),
                '<a href="http://wordpress.org/plugins/woocommerce/">' . __( 'WooCommerce', 'silkypress-input-field-block' ) . '</a>'
            ), [ 'a' => [ 'href' => [] ], 'b' => [] ]
        ) . '</p></div>';
	}
}

add_action( 'plugins_loaded', function() { 
	SilkyPress_Input_Field_Block::init();
});

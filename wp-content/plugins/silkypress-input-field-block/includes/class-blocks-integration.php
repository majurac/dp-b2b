<?php

use Automattic\WooCommerce\Blocks\BlockTypesController;
use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use Automattic\WooCommerce\Blocks\Package;

defined( 'ABSPATH' ) || exit;

/**
 * Class for integrating with WooCommerce Blocks
 */
class SilkyPress_Input_Field_Block_Integration implements IntegrationInterface {

	var $inputs = [];

	var $sections = [];

	/**
	 * The name of the integration.
	 */
	public function get_name() {
		return 'silkypress-input-field-block';
	}

	/**
	 * The initialization/setup for the integration.
	 */
	public function initialize() {

		// Get the input settings.
		$this->inputs = get_option( 'silkypress-input-field-block', [] );

		// Get the sections where the inputs will show up.
		$sections = [];
		foreach ( $this->inputs as $_input ) {
			$sections[ $_input['section'] ] = '';
		}
		$this->sections = array_keys( $sections );

		$this->register_frontend_scripts();
		$this->register_main_integration();
		$this->register_editor_scripts();


		add_action( 'woocommerce_store_api_checkout_update_order_from_request', [ __CLASS__, 'save_input_values' ], 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', [ __CLASS__, 'show_order' ], 10, 1 );
		add_action( 'woocommerce_order_details_after_customer_details', [ __CLASS__, 'show_order_client' ], 10, 1 );
		add_action( 'woocommerce_thankyou', [ __CLASS__, 'show_order_confirmation' ], 10, 1 );
		add_action( 'woocommerce_email_after_order_table', [ __CLASS__, 'show_order_email' ], 10, 4 );

		add_filter( '__experimental_woocommerce_blocks_add_data_attributes_to_namespace', function() {
			return ['silkypress'];
		}, 10, 0 );

		Package::container()->get( BlockTypesController::class );
	}

	/**
	 * Save the values from the input fields in the database.
	 */
	public static function save_input_values( $order, $request ) {

        $request_data = $request['extensions']['silkypress-input-field-block'];

		$sections = [
			'fields',
			'totals',
			'contact-information',
			'shipping-address',
			'billing-address',
			'shipping-method',
			'shipping-methods',
			'pickup-options',
		//	'payment'
		];

		$wp_kses = array(
			'a'      => array(
				'href'  => array(),
				'title' => array(),
			),
			'br'     => array(),
			'em'     => array(),
			'strong' => array(),
			'b' => array(),
			'i' => array(),
		);

		foreach ( $sections as $_section ) {
			if ( ! isset( $request_data[ $_section ] ) || ! is_array( $request_data[ $_section ] ) ) continue;
			foreach ( $request_data[ $_section ] as $_input => $_value ) {
				$order->update_meta_data( $_input, wp_kses( $_value, $wp_kses ) );
			} 
		}

		$order->save();
    }


    /**
     * Adds the input fields values in the order page in WordPress admin.
     */
	public static function show_order( \WC_Order $order ) {
		self::get_show_order( $order, 'showOrder', 'show-order.php' );
	}


	/**
     * Adds the input fields on the order page in the client's area.
     */
	public static function show_order_client( \WC_Order $order ) {
		global $post;

		if ( isset( $post ) && isset( $post->ID ) && $post->ID !== (int)get_option( 'woocommerce_myaccount_page_id' ) ) {
			return;
		}

		self::get_show_order( $order, 'showOrderConfirmation', 'show-order-client.php' );
	}


	/**
     * Adds the input fields on the order confirmation page. 
     */
	public static function show_order_confirmation( $order_id ) {
		$order = wc_get_order( $order_id );

		self::get_show_order( $order, 'showOrderConfirmation', 'show-order-confirmation.php' );
	}


	/**
	 * Adds the input fields on the order confirmation email.
	 */
	public static function show_order_email( $order, $sent_to_admin, $plain_text, $email ) {
		self::get_show_order( $order, 'showOrderEmail', 'show-order-email.php' );
	}


	/**
	 * Load the appropriate template for the output.
	 */
	public static function get_show_order( $order, $show, $template ) {

		set_query_var( 'input_fields', self::get_input_field_values( $order, $show ) );

		if ( $overridden_template = locate_template( 'silkypress-input-field-block/' . $template ) ) {
			load_template( $overridden_template );
		} else {
			load_template( SILKYPRESS_INPUT_FIELD_BLOCK_PATH . '/templates/' . $template );
		}
	}


	/**
	 * Get the input fields and their respective values for the $order order.
	 */
	public static function get_input_field_values( $order, $show = '' ) {

		// Get the inputs array.
		$inputs	= get_option( 'silkypress-input-field-block', [] );

		if ( ! is_array( $inputs ) || count( $inputs ) === 0 ) return [];

		$values = [];
		foreach ( $inputs as $_key => $_input ) {
			if ( ! empty( $show ) && isset( $_input[ $show ] ) && ! $_input[ $show ] ) {
				unset ( $inputs[ $_key ] );
				continue;
			}
			$values[ $_input['name'] ] = '';
		}

		if ( ! is_array( $inputs ) || count( $inputs ) === 0 ) return [];


		// Get the order's meta data.
		$meta	= $order->get_meta_data();

		foreach ( $meta as $_meta ) {
			$_meta = $_meta->get_data();
			if ( ! isset( $_meta['key'] ) || ! isset( $_meta['value'] ) ) continue;
			if ( ! isset( $values[ $_meta['key'] ] ) ) continue;
			$values[ $_meta['key'] ] = $_meta['value'];
		}

		// Write the input fields value into the inputs array.
		$checkbox_tr = [
			0 => __( 'No', 'silkypress-input-field-block' ),
			1 => __( 'Yes', 'silkypress-input-field-block' )
		];
		foreach ( $inputs as $_key => $_input ) {
			if ( isset( $values[ $_input['name'] ] ) ) {
				$inputs[ $_key ]['value'] = $values[ $_input['name'] ];
			}

			if ( $_input['type'] === 'checkbox' ) {
				$inputs[ $_key ]['value'] = strtr( (int)$inputs[ $_key ]['value'], $checkbox_tr );
			}
		}

		return $inputs;
	}


	/**
	 * Registers the main JS file required to add filters and Slot/Fills.
	 */
	private function register_main_integration() {

		$script_asset = $this->get_asset_data( SILKYPRESS_INPUT_FIELD_BLOCK_PATH . 'build/index.asset.php' );

		wp_enqueue_style(
			'silkypress-input-field-block-main',
			plugins_url( '/build/style-silkypress-input-field-block-block.css', SILKYPRESS_INPUT_FIELD_BLOCK_FILE ),
			[],
			$script_asset['version']	
		);

		wp_register_script(
			'silkypress-input-field-block-main',
			plugins_url( '/build/index.js', SILKYPRESS_INPUT_FIELD_BLOCK_FILE ),
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		wp_set_script_translations(
			'silkypress-input-field-block-main',
			'silkypress-input-field-block',
			SILKYPRESS_INPUT_FIELD_BLOCK_PATH . 'languages'
		);
	}

	/**
	 * Enqueue the script handles for the frontend.
	 */
	public function get_script_handles() {
		return array_merge( ['silkypress-input-field-block-main'] , $this->sections );
	}

	/**
	 * Enqueue the script handles for the editor.
	 */
	public function get_editor_script_handles() {
		return [ 'silkypress-input-field-block-main', 'silkypress-input-field-block' ];
	}

	/**
	 * The array of input settings for the client side.
	 */
	public function get_script_data() {
		$inputs = $this->inputs;

		foreach ( $inputs as $_key => $_value ) {
			if ( $_value['type'] !== 'select' && $_value['type'] !== 'radio' ) continue;

			if ( ! isset( $_value['options'] ) || empty( $_value['options'] ) ) {
				$inputs[ $_key ]['options'] = [];
				continue;
			} 

			$_value['options'] = preg_split( "/[\n]+/", $_value['options'] );

			if ( ! is_array( $_value['options'] ) || count( $_value['options']) === 0 ) { 
				$inputs[ $_key ]['options'] = [];
				continue;
			}

			$options = [];
			$counter = 0;
			foreach ( $_value['options'] as $_option ) {
				if ( $_value['type'] === 'select' ) {
					$options[] = [ 'label' => sanitize_text_field( $_option ), 'key' => sanitize_text_field( $_option ) ];
				}
				if ( $_value['type'] === 'radio' ) {
					$options[] = [ 'label' => sanitize_text_field( $_option ), 'value' => sanitize_text_field( $_option ) ];
				}
				$counter ++;
			}

			$inputs[ $_key ]['options'] = $options;
		}

		return $inputs;
	}


	/**
	 * Register the scripts for the editor.
	 */
	public function register_editor_scripts() {
		$script_path       = '/build/silkypress-input-field-block-block.js';
		$script_asset = $this->get_asset_data( SILKYPRESS_INPUT_FIELD_BLOCK_PATH . 'build/silkypress-input-field-block-block.asset.php' );

		wp_register_script(
			'silkypress-input-field-block',
			plugins_url( $script_path, SILKYPRESS_INPUT_FIELD_BLOCK_FILE ),
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		wp_set_script_translations(
			'silkypress-input-field-block',
			'silkypress-input-field-block',
			SILKYPRESS_INPUT_FIELD_BLOCK_PATH . 'languages/'
		);
	}

	/**
	 * Register the scripts for the frontend.
	 */
	public function register_frontend_scripts() {

		if ( ! is_array( $this->sections ) || count( $this->sections ) === 0 ) return;

		foreach ( $this->sections as $_block ) { 
			$script_asset = $this->get_asset_data( SILKYPRESS_INPUT_FIELD_BLOCK_PATH . 'build/'. $_block .'.asset.php' );

			wp_register_script(
				$_block,
				plugins_url( '/build/'. $_block .'.js', SILKYPRESS_INPUT_FIELD_BLOCK_FILE ),
				$script_asset['dependencies'],
				$script_asset['version'],
				true
			);
		}
	}


	/**
	 * Get the data from the asset file. 
	 */
	function get_asset_data( $file ) {
		if ( file_exists( $file ) ) {
			return require $file;
		}

		$version = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG && file_exists( $file ) ? filemtime( $file ) : SILKYPRESS_INPUT_FIELD_BLOCK_VERSION;

		return [
			'dependencies' => [],
			'version'      => $version 
		];
	}
}

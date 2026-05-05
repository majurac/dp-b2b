<?php
use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\StoreApi\Schemas\CartSchema;
use Automattic\WooCommerce\Blocks\StoreApi\Schemas\CheckoutSchema;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\StoreApi;

defined( 'ABSPATH' ) || exit;

/**
 * SilkyPress Input Field Block Extend Store API.
 */
class SilkyPress_Input_Field_Block_Extend_Store_Endpoint {

	/**
	 * Stores Rest Extending instance.
	 */
	private static $extend;

	/**
	 * Plugin Identifier, unique to each plugin.
	 */
	const IDENTIFIER = 'silkypress-input-field-block';

	/**
	 * Bootstrap the class and hooks required data.
	 */
	public static function init() {
		$store_api = StoreApi::container()->get( ExtendSchema::class );

		if ( ! is_object( $store_api ) || ! is_callable( [ $store_api, 'register_endpoint_data' ] ) ) {
			return;
		}

		$store_api->register_endpoint_data(
			[
				'endpoint'        => CheckoutSchema::IDENTIFIER,
				'namespace'       => self::IDENTIFIER,
				'schema_callback' => [ __CLASS__, 'extend_checkout_schema' ],
				'schema_type'     => ARRAY_A,
			]
		);
	}

	/**
	 * Register custom inputs schema into the Checkout endpoint.
	 */
	public static function extend_checkout_schema() {

		$block = [
			'type'        => 'object',
			'context'     => [ 'view', 'edit' ],
			'readonly'    => true,
			'optional'    => true,
		];

		$inputs = get_option( 'silkypress-input-field-block', [] );

		if ( ! is_array( $inputs ) || count( $inputs ) === 0 ) {
			return [];
		}

		$sections = [];
		foreach ( $inputs as $_input ) {
			$sections[ $_input['section'] ] = $block;
		}

		return $sections;
    }
}

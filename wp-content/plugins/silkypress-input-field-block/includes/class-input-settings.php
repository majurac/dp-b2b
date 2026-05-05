<?php

defined( 'ABSPATH' ) || exit;

/**
 * Class for getting the Inputs array from the database. 
 */
class SilkyPress_Input_Field_Block_Input_Settings {

	static $default_input_field = [ 
		'section'				=> '',
		'type'					=> 'text',
		'label'					=> '',
		'name'					=> '',
		'defaultValue'			=> '',
		'extra'					=> '',
		'required'				=> true,
		'showOrder'				=> true,
		'showOrderConfirmation'	=> true,
		'showOrderEmail'		=> true,
		];


	/**
	 * Initialize the class.
	 */
	public static function init() {
		add_action( 'save_post', [ __CLASS__, 'save_post' ], 20, 2 );
	}


	/**
	 * Save the 'silkypress-input-field-block' option when saving the checkout page.
	 */
	public static function save_post( $id, $post ) {

		if ( $id !== (int)get_option( 'woocommerce_checkout_page_id' ) ) {
			return;
		}

		$blocks = parse_blocks( $post->post_content );
		$extender_blocks = self::find_checkout_block( $blocks );
		$extender_blocks = self::extender_blocks_settings( $extender_blocks );

		update_option( 'silkypress-input-field-block', $extender_blocks, false );
	}


	/**
	 * Add the defaults to the input field attributes.
	 */
	public static function extender_blocks_settings( $blocks ) {
		
		if ( ! is_array( $blocks ) || count( $blocks ) === 0 ) return [];

		foreach ( $blocks as $_key => $_block ) {
			$blocks[ $_key ] = array_merge( self::$default_input_field, $_block );
			$blocks[ $_key ]['section'] = str_replace( ['woocommerce/checkout-', '-block'], '', $blocks[ $_key ]['section'] );
		}

		return $blocks;
	}


	/**
	 * Find the 'checkout' block on the page, then extract the information about the 'silkypress-input-field-block' blocks.
	 */
	public static function find_checkout_block( $blocks ) {
		if ( ! is_array( $blocks ) || count( $blocks ) === 0 ) return false;

		while ( count( $blocks) > 0 ) {
			$next_round = [];
			foreach ( $blocks as $_block ) {
				if ( $_block['blockName'] === 'woocommerce/checkout' ) {
					return self::find_input_field_blocks( $_block );
				}

				if ( isset( $_block['innerBlocks'] ) && is_array( $_block['innerBlocks'] ) && count( $_block['innerBlocks'] ) > 0 ) {
					$next_round = array_merge( $next_round, $_block['innerBlocks'] );
				}
			}
			$blocks = $next_round;
		}

		return false;
	}


	/**
	 * Extract the information about 'silkypress-input-field-block' blocks on the checkout page.
	 */
	public static function find_input_field_blocks( $checkout_block ) {

		if ( ! is_array( $checkout_block ) ) return [];

		// Filter the Input Field blocks
		$extender_blocks = [];
		foreach ( $checkout_block['innerBlocks'] as $_block ) {
			if ( ! isset( $_block['innerBlocks'] ) || ! is_array( $_block['innerBlocks'] ) ) continue;

			$previous_block = [];
			foreach ( $_block['innerBlocks'] as $__block ) {

				if ( $__block['blockName'] === 'silkypress/input-field' ) {
					$__block['attrs']['section'] = $_block['blockName'];
					$__block['attrs']['previous'] = $previous_block;
					$extender_blocks[] = $__block['attrs'];
					$previous_block = 'silkypress/' . $__block['attrs']['name'];
					continue;
				} else {
					$previous_block = $__block['blockName'];
				}

				if ( ! isset( $__block['innerBlocks'] ) || ! is_array( $__block['innerBlocks'] ) ) continue;

				foreach ( $__block['innerBlocks'] as $___block ) {
					if ( $___block['blockName'] === 'silkypress/input-field' ) {
						$___block['attrs']['section'] = $__block['blockName'];
						$extender_blocks[] = $___block['attrs'];
					}
				}
			} 
		}

		$counter = 0;
		foreach ( $extender_blocks as $_id => $_block ) {
			if ( ! isset( $_block['name'] ) || empty( $_block['name'] ) ) {
				$extender_blocks[ $_id ]['name'] = 'id-' . $counter;
				$counter ++;
			} 

			if ( ! preg_match( '/^[a-zA-Z0-9-_]+$/', $_block['name'] ) ) {
				$extender_blocks[ $_id ]['name'] = preg_replace( '/[^a-zA-Z0-9-_]+/', '', $_block['name'] );
			}

			if ( ! isset( $_block['label'] ) || empty( $_block['label'] ) ) {
				$extender_blocks[ $_id ]['label'] = __( 'Label', 'silkypress-input-field-block' );
			}
		}

		return $extender_blocks;
	}
}

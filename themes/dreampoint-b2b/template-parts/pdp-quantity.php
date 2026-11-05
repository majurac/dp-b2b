<?php
/**
 * PDP quantity stepper (C2-4).
 *
 * Shared by woocommerce/single-product/add-to-cart/simple.php and variation-add-to-cart-button.php.
 * The native WooCommerce quantity input stays the source of truth (min/max/step, submitted value);
 * the minus/plus controls are real buttons driven by js/product-single.js.
 *
 * When min equals max (WooCommerce itself renders a hidden input in that case), the stepper shows the
 * value as static text with both buttons disabled. js/product-single.js applies the same state when a
 * selected variation changes the limits.
 *
 * @param array $args {
 *     @type int|float $min_value   Minimum purchase quantity.
 *     @type int|float $max_value   Maximum purchase quantity (-1 / 0 = unlimited).
 *     @type int|float $input_value Initial value.
 * }
 *
 * @package Dreampoint_B2B
 */

defined( 'ABSPATH' ) || exit;

$min_value   = $args['min_value'] ?? 1;
$max_value   = $args['max_value'] ?? -1;
$input_value = $args['input_value'] ?? $min_value;

// Same rule WooCommerce uses to switch the input to type="hidden".
$is_fixed = $min_value > 0 && (float) $min_value === (float) $max_value;
?>
<div class="qty-number">
	<div class="number<?php echo $is_fixed ? ' number--fixed' : ''; ?>">
		<button type="button" class="minus" aria-label="<?php esc_attr_e( 'Smanji količinu', 'dreampoint-b2b' ); ?>"<?php disabled( $is_fixed ); ?>><i class="icon-arrow-left" aria-hidden="true"></i></button>
		<?php
		woocommerce_quantity_input(
			array(
				'min_value'   => $min_value,
				'max_value'   => $max_value,
				'input_value' => $input_value,
			)
		);
		?>
		<span class="qty-static"><span class="qty-static__label"><?php esc_html_e( 'Količina:', 'dreampoint-b2b' ); ?></span> <span class="qty-static__value"><?php echo esc_html( $input_value ); ?></span></span>
		<button type="button" class="plus" aria-label="<?php esc_attr_e( 'Povećaj količinu', 'dreampoint-b2b' ); ?>"<?php disabled( $is_fixed ); ?>><i class="icon-arrow-right" aria-hidden="true"></i></button>
	</div>
</div>
<!-- /.qty-number -->

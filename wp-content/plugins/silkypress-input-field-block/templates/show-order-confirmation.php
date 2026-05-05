<?php
/**
 * Input Fields on the Order Confirmation page 
 *
 * This template can be overridden by copying it to yourtheme/silkypress-input-field-block/show-order-confirmation.php
 *
 * HOWEVER, on occasion the plugin developers will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @version 1.0
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="woocommerce-silkypress-input-field-block">

<h2 class="woocommerce-orer-details__title"><?php esc_html_e( 'Additional Information', 'silkypress-input-field-block' ) ?></h2>

	<?php if ( is_array( $input_fields ) && count( $input_fields ) > 0 ) : ?>

	<ul class="order_details" style="margin: 2em 0">

		<?php foreach( $input_fields as $input ) : ?>

		<li class="order"><?php echo esc_html( $input['label'] ) ?>: <strong><?php echo esc_html( $input['value'] ) ?></strong></li>

		<?php endforeach; ?>

	</ul>

	<?php else: ?>

	<p><?php esc_html_e( 'No values', 'silkypress-input-field-block' ) ?></p>

	<?php endif; ?>

</section>

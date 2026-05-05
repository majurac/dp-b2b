<?php
/**
 * Input Fields in the Order page in the WordPress admin
 *
 * This template can be overridden by copying it to yourtheme/silkypress-input-field-block/show-order.php
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
<div>

<h3><?php esc_html_e( 'Additional Information', 'silkypress-input-field-block' ) ?></h3>

<?php if ( is_array( $input_fields ) && count( $input_fields ) > 0 ) : ?>

	<?php foreach( $input_fields as $input ) : ?>

	<p><strong><?php echo esc_html( $input['label'] ) ?></strong>: <?php echo esc_html( $input['value'] ) ?></p>

	<?php endforeach; ?>

<?php else: ?>

<p><?php esc_html_e( 'No values', 'silkypress-input-field-block' ) ?></p>

<?php endif; ?>

</div>

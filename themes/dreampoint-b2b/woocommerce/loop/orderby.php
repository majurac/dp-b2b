<?php
/**
 * Show options for ordering
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/loop/orderby.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * DreamPoint override (C1-1, Figma CAT-02 11149:63768): the sort bar shows a visible "Sortiraj po:" label next to the
 * Select2 trigger. The label is always rendered (core only renders it when `$use_label` is true) and is bound to the
 * select by `for`/`id`, so it replaces the core `aria-label`. `autocomplete="off"` stops the browser from restoring a
 * stale native value on back/forward while Select2 still shows the label rendered for the URL. Everything else
 * (options, hidden `paged` field, query string passthrough that keeps the active WBW filter parameters) is identical
 * to core.
 *
 * @see         https://woocommerce.com/document/template-structure/
 * @package     WooCommerce\Templates
 * @version     9.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$id_suffix = wp_unique_id();

?>
<form class="woocommerce-ordering" method="get">
	<label class="woocommerce-ordering__label" for="woocommerce-orderby-<?php echo esc_attr( $id_suffix ); ?>"><?php esc_html_e( 'Sortiraj po:', 'dreampoint-b2b' ); ?></label>
	<select
		name="orderby"
		class="orderby"
		id="woocommerce-orderby-<?php echo esc_attr( $id_suffix ); ?>"
		autocomplete="off"
	>
		<?php foreach ( $catalog_orderby_options as $id => $name ) : ?>
			<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $orderby, $id ); ?>><?php echo esc_html( $name ); ?></option>
		<?php endforeach; ?>
	</select>
	<input type="hidden" name="paged" value="1" />
	<?php wc_query_string_form_fields( null, array( 'orderby', 'submit', 'paged', 'product-page' ) ); ?>
</form>

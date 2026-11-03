<?php
/**
 * My Account ERP informational notice (Figma MPR-01 11148:37514 company card, MPR-03 11148:37885
 * billing card). Lock icon + 10px italic note, shown inside the grey .ma-holder card.
 *
 * @param array $args { 'text' => string (already translated) }
 *
 * @package Dreampoint_B2B
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $args['text'] ) ) {
	return;
}
?>
<div class="ma-erp-notice">
	<img src="<?php echo esc_url( get_template_directory_uri() . '/img/ico/lock.svg' ); ?>" width="24" height="24" alt="">
	<p><?php echo esc_html( $args['text'] ); ?></p>
</div>

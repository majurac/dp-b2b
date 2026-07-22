<?php
/**
 * Shared WooCommerce listing toolbar.
 *
 * Fires the woocommerce_before_shop_loop hook — WooCommerce core hooks
 * woocommerce_result_count (20) and woocommerce_catalog_ordering (30)
 * onto it (woocommerce_output_all_notices is also hooked at 10, but each
 * consuming template calls it explicitly beforehand for layout reasons,
 * so it is a harmless no-op here). Reused by any page that renders a
 * WooCommerce product loop, independent of page-specific layout.
 *
 * @package Dreampoint_B2B
 */

if ( ! woocommerce_product_loop() ) {
	return;
}

do_action( 'woocommerce_before_shop_loop' );

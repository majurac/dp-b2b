<?php
/**
 * The template for displaying a single product_brand taxonomy term page.
 *
 * Thin delegator to the shared WooCommerce archive flow — Brand-specific
 * presentation is injected via the dreampoint_b2b_before_product_listing
 * hook (see inc/brand-hero.php), fired from header-shop-archive.php.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Dreampoint_B2B
 */

defined( 'ABSPATH' ) || exit;

$archive_template = locate_template( 'woocommerce/archive-product.php' );

if ( ! $archive_template ) {
    return;
}

require $archive_template;

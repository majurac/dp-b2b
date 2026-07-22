<?php
/**
 * Explicit template entry point for the Product Brand taxonomy archive.
 *
 * Delegates rendering to the shared WooCommerce archive-product.php
 * implementation, so Product Brand archives use the same catalog
 * infrastructure — sidebar, toolbar, product loop, pagination, empty-state —
 * as Shop, Product Category, and Product Tag archives, without duplicating
 * that logic here.
 *
 * Brand-specific presentation (hero, gallery, ACF data) is injected into the
 * shared flow through the dreampoint_b2b_before_product_listing hook
 * (see inc/brand-hero.php), fired from header-shop-archive.php.
 *
 * This explicit entry point is retained deliberately — even though
 * WooCommerce can resolve Product Brand archives through its own taxonomy
 * template fallback — to keep the archive's rendering path discoverable,
 * easy to debug, and maintainable within the theme's own file structure.
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

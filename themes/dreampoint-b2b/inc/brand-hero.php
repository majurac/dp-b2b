<?php
/**
 * Brand Hero — hooks the Brand-specific hero presentation into the shared
 * product listing flow (header-shop-archive.php), so taxonomy-product_brand.php
 * can delegate to the standard archive-product.php without losing brand
 * presentation.
 *
 * @package Dreampoint_B2B
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the Brand hero on product_brand taxonomy archives only.
 */
function dreampoint_b2b_render_brand_hero(): void {
    if ( ! is_tax( 'product_brand' ) ) {
        return;
    }

    $brand = get_queried_object();

    if ( ! $brand instanceof WP_Term ) {
        return;
    }

    get_template_part( 'template-parts/brand-hero' );
}
add_action( 'dreampoint_b2b_before_product_listing', 'dreampoint_b2b_render_brand_hero' );

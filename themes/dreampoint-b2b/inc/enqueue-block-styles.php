<?php
/**
 * Block CSS — Pre-scan and conditional enqueue.
 *
 * Extracted from functions.php — behavior-preserving relocation.
 *
 * @package Dreampoint_B2B
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pre-scan sadržaja posta za ACF blokove i enqueue odgovarajući CSS.
 *
 * Hookano na 'wp' — izvršava se PRIJE wp_head, pa CSS ide u <head>.
 * parse_blocks() rekurzivno pretražuje innerBlocks kako bi pokrio ugniježđene blokove.
 * wp_enqueue_style nativno deduplira — isti handle se učitava samo jednom.
 */
add_action( 'wp', 'dreampoint_b2b_enqueue_block_styles' );
function dreampoint_b2b_enqueue_block_styles(): void {
    if ( ! is_singular() && ! is_front_page() ) {
        return;
    }

    $post = get_queried_object();
    if ( ! $post instanceof WP_Post || empty( $post->post_content ) ) {
        return;
    }

    // Mapa: ACF block name → CSS slug u css/blocks/
    // Block name = 'acf/' + sanitize_title( ime iz acf_register_block_type() )
    $block_css_map = [
        'acf/about-section'                => 'about',
        'acf/brands-section'               => 'brands',
        'acf/company-features-section'     => 'company-features',
        'acf/contact-form-section'         => 'contact-form',
        'acf/contact-info-section'         => 'contact-info',
        'acf/featured-brand-section'       => 'featured-brand',
        'acf/featured-categories-section'  => 'featured-categories',
        'acf/featured-products-section'    => 'featured-products',
        'acf/gallery-section'              => 'gallery',
        'acf/hero-section'                 => 'hero',
        'acf/latest-products-section'      => 'latest-products',
        'acf/testimonials-section'         => 'testimonials',
        'acf/wysiwyg-section'              => 'wysiwyg',
    ];

    $block_names = dreampoint_b2b_collect_block_names( parse_blocks( $post->post_content ) );

    foreach ( $block_names as $block_name ) {
        if ( ! isset( $block_css_map[ $block_name ] ) ) {
            continue;
        }
        $slug = $block_css_map[ $block_name ];
        wp_enqueue_style(
            'dp-block-' . $slug,
            get_template_directory_uri() . '/css/blocks/' . $slug . '.css',
            [ 'dp-style' ],
            _S_VERSION
        );
    }
}

/**
 * Rekurzivno prikuplja sve block name vrijednosti iz parse_blocks() rezultata.
 *
 * @param  array $blocks Rezultat parse_blocks().
 * @return string[]      Deduplicirani niz blockName vrijednosti.
 */
function dreampoint_b2b_collect_block_names( array $blocks ): array {
    $names = [];
    foreach ( $blocks as $block ) {
        if ( ! empty( $block['blockName'] ) ) {
            $names[] = $block['blockName'];
        }
        if ( ! empty( $block['innerBlocks'] ) ) {
            $names = array_merge( $names, dreampoint_b2b_collect_block_names( $block['innerBlocks'] ) );
        }
    }
    return array_unique( $names );
}

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
    // The PDP renders [company_features] directly from its template (no block in post_content),
    // so it needs the Company Features stylesheet explicitly.
    if ( function_exists( 'is_product' ) && is_product() ) {
        dreampoint_b2b_enqueue_block_style( 'company-features' );
    }

    // The Rich Text template (rich-text-page.php) renders post_content as plain editor HTML (no ACF block), so it
    // reuses the WYSIWYG block typography (headings, lists, spacing) via the `wysiwyg-block` wrapper class.
    if ( is_page_template( 'rich-text-page.php' ) ) {
        dreampoint_b2b_enqueue_block_style( 'wysiwyg' );
    }

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
        'acf/featured-section'             => 'featured-section',
        'acf/gallery-section'              => 'gallery',
        'acf/hero-section'                 => 'hero',
        'acf/segments-section'             => 'segments-section',
        'acf/testimonials-section'         => 'testimonials',
        'acf/wysiwyg-section'              => 'wysiwyg',
    ];

    $block_names = dreampoint_b2b_collect_block_names( parse_blocks( $post->post_content ) );

    foreach ( $block_names as $block_name ) {
        if ( ! isset( $block_css_map[ $block_name ] ) ) {
            continue;
        }
        dreampoint_b2b_enqueue_block_style( $block_css_map[ $block_name ] );
    }
}

/**
 * Enqueue css/blocks/{slug}.css under the handle dp-block-{slug} (WordPress dedupes the handle).
 *
 * @param string $slug CSS slug in css/blocks/.
 */
function dreampoint_b2b_enqueue_block_style( string $slug ): void {
    wp_enqueue_style(
        'dp-block-' . $slug,
        get_template_directory_uri() . '/css/blocks/' . $slug . '.css',
        [ 'dp-style' ],
        dreampoint_b2b_asset_ver( 'css/blocks/' . $slug . '.css' )
    );
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

/**
 * The Contact page template (contact.php) includes blocks/templates/contact-form.php directly, so there is no
 * `acf/contact-form-section` block in post_content for the pre-scan above to find. Load the same block CSS
 * for that template (Figma CON-01 two-column layout, CF7 validation tip), plus the contact-info card CSS.
 */
add_action( 'wp', 'dreampoint_b2b_enqueue_contact_template_styles' );
function dreampoint_b2b_enqueue_contact_template_styles(): void {
    if ( ! is_page_template( 'contact.php' ) ) {
        return;
    }
    wp_enqueue_style(
        'dp-block-contact-form',
        get_template_directory_uri() . '/css/blocks/contact-form.css',
        [ 'dp-style' ],
        dreampoint_b2b_asset_ver( 'css/blocks/contact-form.css' )
    );
    // The same template renders the [contact_info] card (Figma CON-01 two-column variant).
    wp_enqueue_style(
        'dp-block-contact-info',
        get_template_directory_uri() . '/css/blocks/contact-info.css',
        [ 'dp-style' ],
        dreampoint_b2b_asset_ver( 'css/blocks/contact-info.css' )
    );
}

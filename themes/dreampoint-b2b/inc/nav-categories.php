<?php
/**
 * Category Navigation
 *
 * Desktop and mobile product category navigation with transient caching.
 *
 * @package Dreampoint_B2B
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================================
// NAVIGACIJA — KATEGORIJE PROIZVODA
// ============================================================================

/**
 * Vraća ID-eve kategorija koje se isključuju iz navigacije, pronalazeći ih po slugu.
 * Slug-based lookup je siguran pri migraciji baze (ID-evi se mogu promijeniti).
 * Static cache sprječava višestruke upite u istom requestu.
 *
 * @return int[]
 */
function dreampoint_b2b_nav_excluded_category_ids(): array {
    static $ids = null;
    if ( $ids !== null ) {
        return $ids;
    }
    $ids = [];
    foreach ( [ 'uncategorized', 'noviteti' ] as $slug ) {
        $term = get_term_by( 'slug', $slug, 'product_cat' );
        if ( $term instanceof WP_Term ) {
            $ids[] = $term->term_id;
        }
    }
    return $ids;
}

/**
 * Gradi i vraća HTML listu top-level kategorija s podkategorijama i thumbnailima za desktop navigaciju.
 * Rezultat se kešira u transient na 6 sati i briše automatski pri izmjeni kategorija.
 *
 * @return string Escaped HTML.
 */
function dreampoint_b2b_nav_categories_desktop(): string {
    $transient_key = 'dreampoint_b2b_nav_categories_html';
    $cached        = get_transient( $transient_key );

    if ( $cached !== false ) {
        return $cached;
    }

    $top_categories = get_terms( [
        'taxonomy'   => 'product_cat',
        'orderby'    => 'menu_order',
        'hide_empty' => false,
        'parent'     => 0,
        'exclude'    => dreampoint_b2b_nav_excluded_category_ids(),
    ] );

    if ( empty( $top_categories ) || is_wp_error( $top_categories ) ) {
        return '';
    }

    $output = '<div class="product-categories-list">';

    foreach ( $top_categories as $category ) {
        $output .= '<div class="product-category-item">'
            . '<span class="category-title"><a href="' . esc_url( get_term_link( $category ) ) . '">'
            . esc_html( $category->name ) . '</a></span>';

        $subcategories = get_terms( [
            'taxonomy'   => 'product_cat',
            'orderby'    => 'menu_order',
            'hide_empty' => false,
            'parent'     => $category->term_id,
        ] );

        if ( ! empty( $subcategories ) && ! is_wp_error( $subcategories ) ) {
            $output .= '<ul class="product-subcategories">';
            foreach ( $subcategories as $subcategory ) {
                $thumbnail_id = (int) get_term_meta( $subcategory->term_id, 'thumbnail_id', true );
                $img          = $thumbnail_id
                    ? wp_get_attachment_image( $thumbnail_id, 'woocommerce_category_thumb_200x200', false, [
                        'alt'     => esc_attr( $subcategory->name ),
                        'loading' => 'lazy',
                        'width'   => '200',
                        'height'  => '200',
                    ] )
                    : '';

                $output .= '<li class="product-subcategory-item">'
                    . '<a href="' . esc_url( get_term_link( $subcategory ) ) . '">'
                    . $img
                    . '<span class="subcategory-title">' . esc_html( $subcategory->name ) . '</span>'
                    . '</a></li>';
            }
            $output .= '</ul>';
        }

        $output .= '</div>';
    }

    $output .= '</div>';

    set_transient( $transient_key, $output, 6 * HOUR_IN_SECONDS );

    return $output;
}

/**
 * Gradi i ispisuje HTML listu kategorija za mobilnu navigaciju.
 * Rezultat se kešira u transient na 6 sati.
 * Koristi echo jer se poziva direktno u template-u bez wrappers.
 *
 * @return void
 */
function dreampoint_b2b_nav_categories_mobile(): void {
    $transient_key = 'dreampoint_b2b_nav_categories_mobile_html';
    $cached        = get_transient( $transient_key );

    if ( $cached !== false ) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped pri generisanju
        echo $cached;
        return;
    }

    $top_categories = get_terms( [
        'taxonomy'   => 'product_cat',
        'orderby'    => 'menu_order',
        'hide_empty' => false,
        'parent'     => 0,
        'exclude'    => dreampoint_b2b_nav_excluded_category_ids(),
    ] );

    if ( empty( $top_categories ) || is_wp_error( $top_categories ) ) {
        return;
    }

    $output = '<ul class="sub-menu product-categories-mobile">';

    foreach ( $top_categories as $category ) {
        $subcategories = get_terms( [
            'taxonomy'   => 'product_cat',
            'orderby'    => 'menu_order',
            'hide_empty' => false,
            'parent'     => $category->term_id,
        ] );
        $has_children  = ! empty( $subcategories ) && ! is_wp_error( $subcategories );

        $output .= '<li class="menu-item menu-item-' . esc_attr( (string) $category->term_id )
            . ( $has_children ? ' menu-item-has-children' : '' ) . '">'
            . '<a href="' . esc_url( get_term_link( $category ) ) . '">'
            . '<span class="category-name">' . esc_html( $category->name ) . '</span>'
            . '</a>';

        if ( $has_children ) {
            $output .= '<ul class="sub-menu">';
            foreach ( $subcategories as $sub ) {
                $output .= '<li class="menu-item menu-item-' . esc_attr( (string) $sub->term_id ) . '">'
                    . '<a href="' . esc_url( get_term_link( $sub ) ) . '">' . esc_html( $sub->name ) . '</a>'
                    . '</li>';
            }
            $output .= '</ul>';
        }

        $output .= '</li>';
    }

    $output .= '</ul>';

    set_transient( $transient_key, $output, 6 * HOUR_IN_SECONDS );

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped pri generisanju
    echo $output;
}

/**
 * Briše transient keš za navigacijske kategorije pri bilo kojoj izmjeni kategorije.
 *
 * @return void
 */
function dreampoint_b2b_flush_nav_categories_cache(): void {
    delete_transient( 'dreampoint_b2b_nav_categories_html' );
    delete_transient( 'dreampoint_b2b_nav_categories_mobile_html' );
}
add_action( 'created_product_cat', 'dreampoint_b2b_flush_nav_categories_cache' );
add_action( 'edited_product_cat',  'dreampoint_b2b_flush_nav_categories_cache' );
add_action( 'delete_product_cat',  'dreampoint_b2b_flush_nav_categories_cache' );

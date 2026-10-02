<?php
/**
 * Catalog menu ("Katalog proizvoda")
 *
 * Header mega-panel and mobile drill-down built from the DreamPoint Figma design
 * (node 11022:51566): category rows with expandable children, a "Prikaži sve kategorije"
 * link, and a separate "Popularni proizvodi" column that does NOT depend on the
 * selected/expanded category.
 *
 * Admin configuration: ACF options page "Company Features" → "Katalog meni"
 * (acf-json/group_dp_catalog_menu.json). Every option has a working fallback.
 *
 * - Category tree is user-independent and cached in a transient.
 * - Popular products are rendered per request (never cached) through a normal
 *   product WP_Query, so the B2B visibility engine and per-user pricing apply.
 *
 * @package Dreampoint_B2B
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const DREAMPOINT_B2B_CATALOG_MENU_TREE_KEY = 'dreampoint_b2b_catalog_menu_tree_v1';

/**
 * Category tree for the menu: [ [ id, name, url, children => [ [ id, name, url ], … ] ], … ].
 *
 * Top-level rows come from the admin-ordered repeater; when it is empty every
 * top-level product category with products is used (default category excluded).
 * Children are the direct, non-empty subcategories in WooCommerce category order.
 *
 * @return array<int,array<string,mixed>>
 */
function dreampoint_b2b_catalog_menu_tree(): array {
    $cached = get_transient( DREAMPOINT_B2B_CATALOG_MENU_TREE_KEY );
    if ( is_array( $cached ) ) {
        return $cached;
    }

    $default_id = (int) get_option( 'default_product_cat' );
    $top_terms  = [];

    $rows = function_exists( 'get_field' ) ? get_field( 'catalog_menu_categories', 'option' ) : null;
    if ( is_array( $rows ) ) {
        foreach ( $rows as $row ) {
            $term = ! empty( $row['category'] ) ? get_term( (int) $row['category'], 'product_cat' ) : null;
            if ( $term instanceof WP_Term && $term->term_id !== $default_id ) {
                $top_terms[ $term->term_id ] = $term;
            }
        }
    }

    if ( ! $top_terms ) {
        $found = get_terms( [
            'taxonomy'   => 'product_cat',
            'parent'     => 0,
            'hide_empty' => true,
            'orderby'    => 'menu_order',
            'exclude'    => $default_id ? [ $default_id ] : [],
        ] );
        foreach ( is_wp_error( $found ) ? [] : $found as $term ) {
            $top_terms[ $term->term_id ] = $term;
        }
    }

    $tree = [];
    foreach ( $top_terms as $term ) {
        $link = get_term_link( $term );
        if ( is_wp_error( $link ) ) {
            continue;
        }

        $children = [];
        $subs     = get_terms( [
            'taxonomy'   => 'product_cat',
            'parent'     => $term->term_id,
            'hide_empty' => true,
            'orderby'    => 'menu_order',
        ] );
        foreach ( is_wp_error( $subs ) ? [] : $subs as $sub ) {
            $sub_link = get_term_link( $sub );
            if ( ! is_wp_error( $sub_link ) ) {
                $children[] = [ 'id' => $sub->term_id, 'name' => $sub->name, 'url' => $sub_link ];
            }
        }

        $tree[] = [ 'id' => $term->term_id, 'name' => $term->name, 'url' => $link, 'children' => $children ];
    }

    set_transient( DREAMPOINT_B2B_CATALOG_MENU_TREE_KEY, $tree, 6 * HOUR_IN_SECONDS );

    return $tree;
}

/**
 * Target of "Prikaži sve kategorije": admin link if set, otherwise the Shop page.
 */
function dreampoint_b2b_catalog_menu_all_url(): string {
    $link = function_exists( 'get_field' ) ? get_field( 'catalog_menu_all_link', 'option' ) : null;
    if ( is_array( $link ) && ! empty( $link['url'] ) ) {
        return (string) $link['url'];
    }

    return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
}

/**
 * Admin-curated popular products (up to 4), independent of any category.
 *
 * Runs a regular product query with the curated IDs: the B2B visibility engine filters
 * products the current customer may not see, and price HTML comes from WooCommerce so
 * per-user pricing applies. Never cache this output across users.
 *
 * @return array<int,array<string,string>>
 */
function dreampoint_b2b_catalog_menu_popular(): array {
    $ids = function_exists( 'get_field' ) ? get_field( 'catalog_menu_popular', 'option' ) : null;
    $ids = is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : [];
    if ( ! $ids || ! function_exists( 'wc_get_product' ) ) {
        return [];
    }

    $query = new WP_Query( [
        'post_type'           => 'product',
        'post_status'         => 'publish',
        'post__in'            => array_slice( $ids, 0, 4 ),
        'orderby'             => 'post__in',
        'posts_per_page'      => 4,
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
    ] );

    $items = [];
    foreach ( $query->posts as $post ) {
        $product = wc_get_product( $post->ID );
        if ( ! $product ) {
            continue;
        }

        $items[] = [
            'name'  => $product->get_name(),
            'url'   => get_permalink( $post->ID ),
            'image' => $product->get_image( 'woocommerce_gallery_thumbnail', [ 'loading' => 'lazy', 'alt' => $product->get_name() ] ),
            'price' => $product->get_price_html(),
        ];
    }

    return $items;
}

/**
 * Popular-products markup shared by the desktop panel and the mobile menu.
 */
function dreampoint_b2b_catalog_menu_popular_html( string $wrapper_tag = 'div' ): string {
    $items = dreampoint_b2b_catalog_menu_popular();
    if ( ! $items ) {
        return '';
    }

    $html  = '<' . $wrapper_tag . ' class="catalog-menu__popular">';
    $html .= '<p class="catalog-menu__popular-title">' . esc_html__( 'Popularni proizvodi', 'dreampoint-b2b' ) . '</p>';
    $html .= '<ul class="catalog-menu__products">';
    foreach ( $items as $item ) {
        $html .= '<li><a class="catalog-menu__product" href="' . esc_url( $item['url'] ) . '">'
            . '<span class="catalog-menu__product-image">' . $item['image'] . '</span>'
            . '<span class="catalog-menu__product-text">'
            . '<span class="catalog-menu__product-name">' . esc_html( $item['name'] ) . '</span>'
            . '<span class="catalog-menu__product-price">' . $item['price'] . '</span>'
            . '</span></a></li>';
    }
    $html .= '</ul></' . $wrapper_tag . '>';

    return $html;
}

/**
 * Desktop panel (moved into the "Katalog proizvoda" menu item by js/mobile-menu.js).
 *
 * @return string HTML, escaped while building.
 */
function dreampoint_b2b_catalog_menu_desktop(): string {
    $tree = dreampoint_b2b_catalog_menu_tree();
    if ( ! $tree ) {
        return '';
    }

    $html  = '<div class="catalog-menu" id="catalog-menu">';
    $html .= '<div class="catalog-menu__categories"><ul class="catalog-menu__list">';

    foreach ( $tree as $row ) {
        $has_children = ! empty( $row['children'] );
        $sub_id       = 'catalog-menu-sub-' . (int) $row['id'];

        $html .= '<li class="catalog-menu__item' . ( $has_children ? ' has-children' : '' ) . '">';
        $html .= '<div class="catalog-menu__row">';
        $html .= '<a class="catalog-menu__link" href="' . esc_url( $row['url'] ) . '">' . esc_html( $row['name'] ) . '</a>';
        if ( $has_children ) {
            $html .= '<button type="button" class="catalog-menu__toggle" aria-expanded="false" aria-controls="' . esc_attr( $sub_id ) . '"'
                . ' aria-label="' . esc_attr( sprintf( __( 'Prikaži podkategorije: %s', 'dreampoint-b2b' ), $row['name'] ) ) . '">'
                . '<i class="icon-chevron-down" aria-hidden="true"></i></button>';
        }
        $html .= '</div>';

        if ( $has_children ) {
            $html .= '<ul class="catalog-menu__sub" id="' . esc_attr( $sub_id ) . '" hidden>';
            foreach ( $row['children'] as $child ) {
                $html .= '<li><a class="catalog-menu__sublink" href="' . esc_url( $child['url'] ) . '">' . esc_html( $child['name'] ) . '</a></li>';
            }
            $html .= '</ul>';
        }

        $html .= '</li>';
    }

    $html .= '</ul>';
    $html .= '<a class="catalog-menu__all" href="' . esc_url( dreampoint_b2b_catalog_menu_all_url() ) . '">'
        . esc_html__( 'Prikaži sve kategorije', 'dreampoint-b2b' ) . '</a>';
    $html .= '</div>';
    $html .= dreampoint_b2b_catalog_menu_popular_html();
    $html .= '</div>';

    return $html;
}

/**
 * Mobile drill-down (reuses the project's existing mobile menu slide pattern:
 * li.menu-item-has-children > a + ul.sub-menu). Moved into the "Katalog proizvoda"
 * item by js/mobile-menu.js.
 *
 * @return void
 */
function dreampoint_b2b_catalog_menu_mobile(): void {
    $tree = dreampoint_b2b_catalog_menu_tree();
    if ( ! $tree ) {
        return;
    }

    $output = '<ul class="sub-menu catalog-menu-mobile">';

    foreach ( $tree as $row ) {
        $has_children = ! empty( $row['children'] );

        $output .= '<li class="menu-item menu-item-' . (int) $row['id'] . ( $has_children ? ' menu-item-has-children' : '' ) . '">'
            . '<a href="' . esc_url( $row['url'] ) . '"><span class="category-name">' . esc_html( $row['name'] ) . '</span></a>';

        if ( $has_children ) {
            $output .= '<ul class="sub-menu">';
            foreach ( $row['children'] as $child ) {
                $output .= '<li class="menu-item menu-item-' . (int) $child['id'] . '"><a href="' . esc_url( $child['url'] ) . '">' . esc_html( $child['name'] ) . '</a></li>';
            }
            $output .= '</ul>';
        }

        $output .= '</li>';
    }

    $output .= '<li class="menu-item catalog-menu-mobile__all"><a href="' . esc_url( dreampoint_b2b_catalog_menu_all_url() ) . '">'
        . esc_html__( 'Prikaži sve kategorije', 'dreampoint-b2b' ) . '</a></li>';

    $popular = dreampoint_b2b_catalog_menu_popular_html( 'div' );
    if ( $popular ) {
        $output .= '<li class="menu-item catalog-menu-mobile__popular-item">' . $popular . '</li>';
    }

    $output .= '</ul>';

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped while building
    echo $output;
}

/**
 * Drops the cached category tree when categories, their order or the menu options change.
 *
 * @return void
 */
function dreampoint_b2b_flush_catalog_menu_cache(): void {
    delete_transient( DREAMPOINT_B2B_CATALOG_MENU_TREE_KEY );
}
add_action( 'created_product_cat', 'dreampoint_b2b_flush_catalog_menu_cache' );
add_action( 'edited_product_cat',  'dreampoint_b2b_flush_catalog_menu_cache' );
add_action( 'delete_product_cat',  'dreampoint_b2b_flush_catalog_menu_cache' );

/**
 * WooCommerce stores category order as term meta "order".
 *
 * @param int|int[] $meta_id   Meta id (unused).
 * @param int       $object_id Term id (unused).
 * @param string    $meta_key  Meta key.
 * @return void
 */
function dreampoint_b2b_flush_catalog_menu_cache_on_order( $meta_id, $object_id, $meta_key ): void {
    if ( 'order' === $meta_key ) {
        dreampoint_b2b_flush_catalog_menu_cache();
    }
}
add_action( 'added_term_meta',   'dreampoint_b2b_flush_catalog_menu_cache_on_order', 10, 3 );
add_action( 'updated_term_meta', 'dreampoint_b2b_flush_catalog_menu_cache_on_order', 10, 3 );

add_action( 'acf/save_post', function ( $post_id ): void {
    if ( 'options' === $post_id ) {
        dreampoint_b2b_flush_catalog_menu_cache();
    }
}, 20 );

/**
 * Marks the "Katalog proizvoda" item of the primary menu (theme location menu-1) so the
 * catalog panel can attach to it without a manually entered CSS class.
 *
 * Deterministic rule: the first top-level custom-link item whose URL is "#" (a menu item
 * that only opens the panel). Applies to every render of menu-1 (desktop and mobile).
 * A manually added "cat-toggler" class is still honoured and never duplicated.
 *
 * @param WP_Post[] $items Sorted menu items.
 * @param stdClass  $args  wp_nav_menu() arguments.
 * @return WP_Post[]
 */
function dreampoint_b2b_mark_catalog_menu_item( $items, $args ) {
    if ( ( $args->theme_location ?? '' ) !== 'menu-1' ) {
        return $items;
    }

    foreach ( $items as $item ) {
        if ( in_array( 'cat-toggler', (array) $item->classes, true ) ) {
            return $items;
        }
    }

    foreach ( $items as $item ) {
        if ( 0 === (int) $item->menu_item_parent && 'custom' === $item->type && '#' === $item->url ) {
            $item->classes[] = 'cat-toggler';
            $item->classes[] = 'menu-item-parent-proizvodi';
            break;
        }
    }

    return $items;
}
add_filter( 'wp_nav_menu_objects', 'dreampoint_b2b_mark_catalog_menu_item', 10, 2 );

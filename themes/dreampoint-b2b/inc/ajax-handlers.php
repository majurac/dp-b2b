<?php
/**
 * AJAX Handlers
 *
 * @package Dreampoint_B2B
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================================
// AJAX — PRETRAGA PROIZVODA
// ============================================================================

add_action( 'wp_ajax_search_products',        'dreampoint_b2b_ajax_search_products' );
add_action( 'wp_ajax_nopriv_search_products', 'dreampoint_b2b_ajax_search_products' );

/**
 * AJAX pretraga proizvoda — kompaktna lista (slika, naziv, cena) + footer sa
 * "Odustani" i "Vidi sve rezultate" (UI preuzet iz Cotra B2B).
 *
 * Namerno BEZ transient keša: rezultati zavise od B2B vidljivosti trenutnog
 * korisnika (SQL-level filteri), pa deljeni keš između korisnika ne sme postojati.
 *
 * Sigurnost:
 *   - Nonce verifikacija (dp_search_nonce) sprečava CSRF i bot flood
 *   - sanitize_text_field + wp_unslash za ulazne podatke
 *   - Sav izlaz je escapovan u ovoj funkciji
 *
 * JS strana treba da šalje: { searchTerm: '...', nonce: dpAjax.nonce }
 * dpAjax se registruje u dreampoint_b2b_scripts() putem wp_localize_script.
 */
function dreampoint_b2b_ajax_search_products(): void {
    if ( ! check_ajax_referer( 'dp_search_nonce', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => 'Invalid request' ], 403 );
    }

    $search_term = sanitize_text_field( wp_unslash( $_GET['searchTerm'] ?? '' ) );

    // Kratki upiti ne prave upit — JS prazni kontejner kad stigne prazan odgovor.
    if ( mb_strlen( $search_term, 'UTF-8' ) < 2 ) {
        wp_die();
    }

    // Nativni upit (+ kataloški broj / SKU / EAN preko dp_search_extended, vidi inc/product-search.php).
    // Broj redova 3 → 5 (kompaktna lista umesto kartica). B2B vidljivost se primenjuje preko pre_get_posts.
    // post_status je obavezan: u admin-ajax.php WP_Query ima is_admin = true i bez eksplicitnog statusa
    // dodaje draft/pending/future za SVAKOG korisnika — živa B2B pretraga sme da vrati samo objavljene proizvode.
    $query = new WP_Query( [
        'post_type'          => 'product',
        'post_status'        => 'publish',
        'posts_per_page'     => 5,
        's'                  => $search_term,
        'dp_search_extended' => true,
    ] );

    if ( $query->have_posts() ) {
        echo '<div class="product-rows">';

        while ( $query->have_posts() ) {
            $query->the_post();
            $product = wc_get_product( get_the_ID() );
            if ( ! $product ) {
                continue;
            }

            $permalink = get_permalink( $product->get_id() );
            $title     = get_the_title( $product->get_id() );

            echo '<div class="product-row">';
            echo '<div class="product-photo">';
            // Slika je dekorativna — naziv u product-title linku je jedini fokusabilni link reda.
            echo '<a href="' . esc_url( $permalink ) . '" tabindex="-1" aria-hidden="true">';
            echo $product->get_image( 'woocommerce_thumbnail', [ 'alt' => '' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WC image markup
            echo '</a>';
            echo '</div>';
            echo '<div class="product-content">';
            echo '<span class="product-title"><a href="' . esc_url( $permalink ) . '">' . esc_html( $title ) . '</a></span>';
            // Kataloški broj (ERP _ARTICLE_CODE) ima prioritet nad SKU-om u prikazu (docs/erp-discovery-findings.md).
            $catalog_no = (string) get_post_meta( $product->get_id(), '_ARTICLE_CODE', true );
            if ( '' === $catalog_no ) {
                $catalog_no = $product->get_sku();
            }
            if ( '' !== $catalog_no ) {
                echo '<span class="product-sku">' . esc_html__( 'Kataloški broj:', 'dreampoint-b2b' ) . ' ' . esc_html( $catalog_no ) . '</span>';
            }
            if ( ! $product->is_in_stock() ) {
                echo '<span class="price out-of-stock-price">' . esc_html__( 'Nema na stanju', 'dreampoint-b2b' ) . '</span>';
            } elseif ( $product->get_price() ) {
                echo '<span class="price' . ( $product->is_on_sale() ? ' onsale' : '' ) . '">' . wp_kses_post( $product->get_price_html() ) . '</span>';
            }
            echo '</div>';
            echo '</div>';
        }

        echo '<div class="live-search-footer">';
        echo '<button type="button" class="button button--sm button--outline cancel">' . esc_html__( 'Odustani', 'dreampoint-b2b' ) . '</button>';
        echo '<form method="get" action="' . esc_url( home_url( '/' ) ) . '">';
        echo '<input type="hidden" name="s" value="' . esc_attr( $search_term ) . '">';
        echo '<input type="hidden" name="post_type" value="product">';
        echo '<button type="submit" class="button button--sm see-all">' . esc_html__( 'Vidi sve rezultate', 'dreampoint-b2b' ) . '</button>';
        echo '</form>';
        echo '</div>';

        echo '</div>'; // .product-rows
    } else {
        echo '<div class="sajx-nofund-prod">';
        echo '<p>' . esc_html__( 'Nismo pronašli nijedan rezultat', 'dreampoint-b2b' ) . '</p>';
        echo '</div>';
    }

    wp_reset_postdata();
    wp_die();
}

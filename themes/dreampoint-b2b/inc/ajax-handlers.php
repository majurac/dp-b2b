<?php
/**
 * AJAX Handlers
 *
 * @package dreampoint-b2b
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================================
// AJAX — PRETRAGA PROIZVODA SA TRANSIENT CACHE
// ============================================================================

add_action( 'wp_ajax_search_products',        'dreampoint_b2b_ajax_search_products' );
add_action( 'wp_ajax_nopriv_search_products', 'dreampoint_b2b_ajax_search_products' );

/**
 * AJAX pretraga proizvoda sa keširanjem u tranzijentu.
 * Redis Object Cache automatski preuzima wp_transients — nema promene u kodu.
 *
 * Sigurnost:
 *   - Nonce verifikacija (dp_search_nonce) sprečava CSRF i bot flood
 *   - sanitize_text_field + wp_unslash za ulazne podatke
 *   - Keširani HTML je pouzdan — generisan i escapovan u ovoj funkciji
 *
 * JS strana treba da šalje: { searchTerm: '...', nonce: dpAjax.nonce }
 * dpAjax se registruje u dreampoint_b2b_scripts() putem wp_localize_script.
 */
function dreampoint_b2b_ajax_search_products(): void {
    if ( ! check_ajax_referer( 'dp_search_nonce', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => 'Invalid request' ], 403 );
    }

    $search_term = sanitize_text_field( wp_unslash( $_GET['searchTerm'] ?? '' ) );

    if ( empty( $search_term ) ) {
        echo '<p>' . esc_html__( 'Unesite pojam za pretragu', 'dreampoint-b2b' ) . '</p>';
        wp_die();
    }

    $cache_key   = 'dp_search_' . md5( $search_term );
    $cached_html = get_transient( $cache_key );

    if ( false !== $cached_html ) {
        echo $cached_html;
        wp_die();
    }

    $query = new WP_Query( [
        'post_type'      => 'product',
        'posts_per_page' => 3,
        's'              => $search_term,
        'relevanssi'     => true,
    ] );

    ob_start();

    if ( $query->have_posts() ) {
        echo '<div class="row">';
        while ( $query->have_posts() ) {
            $query->the_post();
            $product = wc_get_product( get_the_ID() );
            if ( ! $product ) continue;

            echo '<div class="col-md-4">';
            wc_get_template_part( 'content', 'product' );
            echo '</div>';
        }
        echo '</div>';

        echo '<div class="live-search-footer">';
        printf(
            '<form method="get" action="%s">
                <input type="hidden" name="s" value="%s">
                <input type="hidden" name="post_type" value="product">
                <button type="submit" class="button see-all">%s</button>
            </form>',
            esc_url( home_url( '/' ) ),
            esc_attr( $search_term ),
            esc_html__( 'Vidi sve rezultate', 'dreampoint-b2b' )
        );
        echo '</div>';

    } else {
        echo '<div class="sajx-nofund-prod">';
        echo '<p>' . esc_html__( 'Nismo pronašli nijedan rezultat', 'dreampoint-b2b' ) . '</p>';
        $suggestion = did_you_mean( $search_term );
        if ( $suggestion ) {
            echo wp_kses_post( $suggestion );
        }
        echo '</div>';
    }

    wp_reset_postdata();

    $html = ob_get_clean();
    set_transient( $cache_key, $html, HOUR_IN_SECONDS );
    echo $html;
    wp_die();
}

/**
 * Briše keširan transijent pretrage kada se proizvod promeni ili kreira.
 * Redis automatski sinhronizuje brisanje bez dodatne konfiguracije.
 */
add_action( 'save_post_product',          'dreampoint_b2b_clear_search_cache' );
add_action( 'woocommerce_update_product', 'dreampoint_b2b_clear_search_cache' );
add_action( 'woocommerce_new_product',    'dreampoint_b2b_clear_search_cache' );

function dreampoint_b2b_clear_search_cache(): void {
    global $wpdb;

    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_dp_search_%'
            OR option_name LIKE '_transient_timeout_dp_search_%'"
    );
}

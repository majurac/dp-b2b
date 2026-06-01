<?php
/**
 * Plugin Script/Style Governance — Dequeue, stub, and relocation rules.
 *
 * Extracted from functions.php — behavior-preserving relocation.
 * All hook priorities are unchanged.
 *
 * @package Dreampoint_B2B
 */

defined( 'ABSPATH' ) || exit;

// jQuery Migrate — nije potreban; prazan stub sprečava "dependency doesn't exist" obaveštenje
// ⚠️  Testirati sa payment gateway dodacima (npr. CorvusPay) — revertovati ako dođe do greške.
add_action( 'wp_enqueue_scripts', function (): void {
    if ( is_admin() ) return;
    wp_dequeue_script( 'jquery-migrate' );
    wp_deregister_script( 'jquery-migrate' );
    wp_register_script( 'jquery-migrate', '', [], null, false );
}, 9999 );

/**
 * wc-cart-fragments šalje AJAX zahtev na SVAKOM učitavanju stranice (?wc-ajax=get_refreshed_fragments).
 * Učitavamo ga samo tamo gde je stanje korpe relevantno.
 * Prazan stub sprečava "dependency doesn't exist" obaveštenje od zavisnih dodataka.
 */
add_action( 'wp_enqueue_scripts', 'dreampoint_b2b_maybe_disable_cart_fragments', 9999 );
function dreampoint_b2b_maybe_disable_cart_fragments(): void {
    if ( is_cart() || is_checkout() || is_account_page() || is_page( 'quick-order' ) ) return;

    wp_dequeue_script( 'wc-cart-fragments' );
    wp_deregister_script( 'wc-cart-fragments' );
    wp_register_script( 'wc-cart-fragments', '', [], null, true );
}

/**
 * WooCommerce skripte premeštamo u footer da ne blokiraju renderovanje.
 * LSCache JS Defer pokriva skripte dodataka globalno — ovo je WP-level
 * garancija ispravnog redosleda dependency lanca nezavisno od LSCache konfiguracije.
 */
add_action( 'wp_enqueue_scripts', function (): void {
    if ( is_admin() ) return;

    foreach ( [
        'jquery-blockui',
        'js-cookie',
        'wc-add-to-cart',
        'woocommerce',
        'woocommerce-order-attribution',
        'sourcebuster',
        'tinvwl-js',
    ] as $handle ) {
        $script = wp_scripts()->query( $handle );
        if ( $script ) {
            $script->args = 1; // 1 = footer
        }
    }
}, 9999 );

/**
 * Back-in-Stock Notifier — dequeue na svim stranicama osim stranice pojedinačnog proizvoda.
 * Dodatak učitava Bootstrap CSS + sweetalert2 + blockUI globalno bez uslova.
 * Forma se prikazuje samo na is_product() — sve ostalo je nepotrebno.
 */
add_action( 'wp_enqueue_scripts', function (): void {
    if ( is_product() ) return;

    wp_dequeue_style(  'cwginstock_frontend_css'   );
    wp_dequeue_style(  'cwginstock_bootstrap'       );
    wp_dequeue_style(  'cwginstock_frontend_guest'  );
    wp_dequeue_script( 'cwginstock_js'              );
    wp_dequeue_script( 'sweetalert2'                );
    wp_dequeue_script( 'cwginstock_popup'           );
    // jquery-blockui se ne dequeue-uje ovdje jer ga WC re-enqueue-uje
    // kao zavisnost — premeštamo ga u footer u WC scripts bloku iznad.
}, 1000 );

/**
 * WPF (woo-product-filter) dodaje jquery-ui-autocomplete putem wp_footer priority 10.
 * Dequeue-ujemo na priority 15 — samo na stranicama gde filteri nisu potrebni.
 */
add_action( 'wp_footer', function (): void {
    if ( is_shop() || is_product_taxonomy() || is_search() ) return;
    wp_dequeue_script( 'jquery-ui-autocomplete' );
}, 15 );

/**
 * SilkyPress input field block CSS — samo na stranici za plaćanje.
 */
add_action( 'wp_enqueue_scripts', function (): void {
    if ( is_checkout() ) return;
    wp_dequeue_style( 'silkypress-input-field-block-main' );
}, 100 );

/**
 * wc-blocks-style i wc-blocks-vendors-style — samo na checkout-u.
 * WC Notices može da ih ponovo enqueue-uje direktno u wp_head, pa koristimo
 * style_loader_tag kao pouzdaniji filter koji hvata CSS u trenutku ispisa.
 */
add_filter( 'style_loader_tag', function ( string $html, string $handle ): string {
    if (
        in_array( $handle, [ 'wc-blocks-style', 'wc-blocks-vendors-style' ], true )
        && ! is_checkout()
    ) {
        return '';
    }
    return $html;
}, 10, 2 );

/**
 * WooCommerce Brands CSS (brands-styles, ~2.6 kB) — uslovni dequeue.
 * Dodatak ga enqueue-uje globalno; potreban je samo na stranicama gde se
 * brand taxonomy prikazuje. Na stranici pojedinačnog proizvoda nije potreban jer
 * prilagođeni template ne poziva woocommerce_product_meta_end hook.
 */
add_action( 'wp_enqueue_scripts', function (): void {
    if ( is_shop() || is_product_category() || is_tax( 'product_brand' ) ) return;
    wp_dequeue_style( 'brands-styles' );
}, 999 );

/**
 * Contact Form 7 — učitavamo samo na stranicama koje imaju formulare.
 * Uslov je page template — ne zavisi od slug-a koji se menja pri migraciji baze.
 */
add_filter( 'wpcf7_load_js',  '__return_false' );
add_filter( 'wpcf7_load_css', '__return_false' );
add_action( 'wp_enqueue_scripts', function (): void {
    if ( is_page_template( 'contact.php' ) ) {
        if ( function_exists( 'wpcf7_enqueue_scripts' ) ) wpcf7_enqueue_scripts();
        if ( function_exists( 'wpcf7_enqueue_styles' )  ) wpcf7_enqueue_styles();
    }
} );

// CF7 — ukloni automatski <p> omotač oko elemenata formulara
add_filter( 'wpcf7_autop_or_not', '__return_false' );

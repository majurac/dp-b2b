<?php
/**
 * Navigation/UI Assets — Mobile menu and sticky header enqueue.
 *
 * Extracted from functions.php (dreampoint_b2b_scripts) — behavior-preserving relocation.
 *
 * Condition: both assets are excluded on the account login/register page only
 * ( is_account_page() && ! is_user_logged_in() ).
 *
 * @package Dreampoint_B2B
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', function (): void {

    // Mobile meni JS — učitavamo na svim stranicama osim account login stranice
    // (insta-shop-item uslov zakomentiran dok feature ne bude potvrđen)
    // if ( ! is_singular( 'insta-shop-item' ) && ! ( is_account_page() && ! is_user_logged_in() ) ) {
    if ( ! ( is_account_page() && ! is_user_logged_in() ) ) {
        wp_enqueue_script(
            'dreampoint-b2b-mobile-menu',
            get_template_directory_uri() . '/js/mobile-menu.js',
            [ 'jquery' ],
            _S_VERSION,
            true
        );
        wp_script_add_data( 'dreampoint-b2b-mobile-menu', 'strategy', 'defer' );
    }

    // --- JS: Sticky header — vanilla JS, sve stranice osim account login stranice ---
    if ( ! is_account_page() || is_user_logged_in() ) {
        wp_enqueue_script(
            'dreampoint-b2b-sticky-header',
            get_template_directory_uri() . '/js/sticky-header.js',
            [],
            _S_VERSION,
            true
        );
        wp_script_add_data( 'dreampoint-b2b-sticky-header', 'strategy', 'defer' );
    }

} );

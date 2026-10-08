<?php
/**
 * Pending-User Cart & Checkout Guard
 *
 * Server-side enforcement: an authenticated customer who is not activated
 * (dreampoint_b2b_user_is_activated() === false) cannot add to / increase the
 * WooCommerce cart or complete checkout. Frontend redirects alone do not cover
 * wc-ajax (template_redirect priority 0), wp_loaded form handlers or the Store API.
 *
 * Existing carts and sessions are never emptied or altered: items stay stored and the
 * customer can resume once activated. Administrators, shop managers, approved customers,
 * anonymous visitors and DP_BYPASS_APPROVAL behave exactly as in the activation helper.
 *
 * @package Dreampoint_B2B
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Je li trenutni korisnik prijavljen, ali nije aktiviran (blokirati naručivanje).
 */
function dreampoint_b2b_cart_guard_blocks_current_user(): bool {
    return is_user_logged_in() && ! dreampoint_b2b_user_is_activated( get_current_user_id() );
}

/**
 * Poruka za neaktivirane korisnike (bez internih detalja o odobrenju).
 */
function dreampoint_b2b_cart_guard_message(): string {
    return __( 'Vaš račun još nije odobren. Naručivanje će biti moguće nakon odobrenja.', 'dreampoint-b2b' );
}

/**
 * Dodaj error notice samo jednom (dedupe bez static varijabli).
 */
function dreampoint_b2b_cart_guard_notice(): void {
    $message = dreampoint_b2b_cart_guard_message();
    if ( function_exists( 'wc_has_notice' ) && wc_has_notice( $message, 'error' ) ) {
        return;
    }
    wc_add_notice( $message, 'error' );
}

// ----------------------------------------------------------------------------
// Cart additions
// ----------------------------------------------------------------------------

/**
 * Standardni add-to-cart putevi koji primjenjuju filter: ?add-to-cart=, forma,
 * wc-ajax=add_to_cart, wishlist, ponovna narudžba. Prije mutacije košarice.
 *
 * @param bool $passed Rezultat prethodnih validacija.
 */
function dreampoint_b2b_cart_guard_add_validation( $passed ) {
    if ( ! dreampoint_b2b_cart_guard_blocks_current_user() ) {
        return $passed;
    }
    dreampoint_b2b_cart_guard_notice();
    return false;
}
add_filter( 'woocommerce_add_to_cart_validation', 'dreampoint_b2b_cart_guard_add_validation', 5 );

/**
 * Pozadinska zaštita za izravne WC()->cart->add_to_cart() pozive koji ne prolaze
 * woocommerce_add_to_cart_validation (npr. bundle djeca). Količina 0 → add_to_cart() vraća false.
 *
 * @param int|float $quantity Tražena količina.
 * @return int|float
 */
function dreampoint_b2b_cart_guard_add_quantity( $quantity ) {
    return dreampoint_b2b_cart_guard_blocks_current_user() ? 0 : $quantity;
}
add_filter( 'woocommerce_add_to_cart_quantity', 'dreampoint_b2b_cart_guard_add_quantity', 5 );

// ----------------------------------------------------------------------------
// Cart quantity changes
// ----------------------------------------------------------------------------

/**
 * Klasično ažuriranje količina u košarici (wp_loaded → update_cart_action).
 *
 * @param bool $passed Rezultat prethodnih validacija.
 */
function dreampoint_b2b_cart_guard_update_validation( $passed ) {
    if ( ! dreampoint_b2b_cart_guard_blocks_current_user() ) {
        return $passed;
    }
    dreampoint_b2b_cart_guard_notice();
    return false;
}
add_filter( 'woocommerce_update_cart_validation', 'dreampoint_b2b_cart_guard_update_validation', 5 );

/**
 * Store API: dodavanje i promjena količine stavki. Store API nema vlastitu hook točku za
 * update-item / items/{key}, pa se provjera veže na točno te rute (po klasi handlera i metodi),
 * prije izvršenja callbacka. Uklanjanje stavki i čitanje ostaju dopušteni.
 *
 * @param mixed                $response Dosadašnji odgovor (null prije callbacka).
 * @param array<string, mixed> $handler  Odabrani handler rute.
 * @param WP_REST_Request      $request  Zahtjev.
 * @return mixed
 */
function dreampoint_b2b_cart_guard_store_api_mutation( $response, $handler, $request ) {
    if ( null !== $response || ! is_array( $handler ) || ! $request instanceof WP_REST_Request ) {
        return $response;
    }

    $callback = $handler['callback'] ?? null;
    if ( ! is_array( $callback ) || ! is_object( $callback[0] ?? null ) ) {
        return $response;
    }

    $route  = $callback[0];
    $method = $request->get_method();
    $ns     = 'Automattic\\WooCommerce\\StoreApi\\Routes\\V1\\';

    $is_mutation = $route instanceof ( $ns . 'CartAddItem' )
        || $route instanceof ( $ns . 'CartUpdateItem' )
        || ( $route instanceof ( $ns . 'CartItems' ) && 'POST' === $method )
        || ( $route instanceof ( $ns . 'CartItemsByKey' ) && in_array( $method, [ 'POST', 'PUT', 'PATCH' ], true ) )
        // Plaćanje postojeće narudžbe: POST /wc/store/v1/checkout/{id} (CheckoutOrder). Odbija se prije
        // is_authorized(), ažuriranja narudžbe/kupca i gatewaya. Standardni POST /checkout je klasa Checkout.
        || ( $route instanceof ( $ns . 'CheckoutOrder' ) && 'POST' === $method );

    if ( ! $is_mutation || ! dreampoint_b2b_cart_guard_blocks_current_user() ) {
        return $response;
    }

    return new WP_Error(
        'dp_b2b_not_activated',
        dreampoint_b2b_cart_guard_message(),
        [ 'status' => 403 ]
    );
}
add_filter( 'rest_request_before_callbacks', 'dreampoint_b2b_cart_guard_store_api_mutation', 10, 3 );

// ----------------------------------------------------------------------------
// Checkout
// ----------------------------------------------------------------------------

/**
 * Klasična košarica/checkout (WC_Checkout::validate_checkout → check_cart_items; error notice
 * sprječava create_order) i Store API (CartController::validate_cart pretvara notice u grešku
 * prije kreiranja narudžbe). Stavke u košarici se ne diraju.
 */
function dreampoint_b2b_cart_guard_check_cart_items(): void {
    if ( dreampoint_b2b_cart_guard_blocks_current_user() ) {
        dreampoint_b2b_cart_guard_notice();
    }
}
add_action( 'woocommerce_check_cart_items', 'dreampoint_b2b_cart_guard_check_cart_items' );

/**
 * Store API checkout: strukturirana greška (409, kod dp_b2b_not_activated). Ovaj hook se izvršava
 * prije woocommerce_check_cart_items u CartController::validate_cart(), pa nema duplih poruka.
 *
 * @param WP_Error $errors Greške validacije košarice.
 */
function dreampoint_b2b_cart_guard_store_api_cart_errors( $errors ): void {
    if ( $errors instanceof WP_Error && dreampoint_b2b_cart_guard_blocks_current_user() ) {
        $errors->add( 'dp_b2b_not_activated', dreampoint_b2b_cart_guard_message() );
    }
}
add_action( 'woocommerce_store_api_cart_errors', 'dreampoint_b2b_cart_guard_store_api_cart_errors' );

// ----------------------------------------------------------------------------
// Existing-order payment (classic order-pay)
// ----------------------------------------------------------------------------

/**
 * Klasično plaćanje postojeće narudžbe. WC_Form_Handler::pay_action() je registriran na `wp`
 * prioritet 20 (class-wc-form-handler.php:47), dakle izvršava se prije `template_redirect` i prije
 * frontend guarda. Ovdje (prioritet 10) se, samo za podnošenje order-pay forme neaktiviranog
 * korisnika, skida upravo taj handler za trenutni zahtjev, pa nema ažuriranja narudžbe ni poziva
 * gatewaya. Uvjeti odgovaraju onima u pay_action() (`:488`, `:501`). Narudžba, košarica i statusi
 * se ne diraju; callbacki/webhookovi gatewaya nisu zahvaćeni (nemaju prijavljenog korisnika).
 * Prioritet handlera se čita iz registracije (has_action), ne pretpostavlja.
 */
function dreampoint_b2b_cart_guard_order_pay_submission(): void {
    global $wp;

    // phpcs:ignore WordPress.Security.NonceVerification -- samo detekcija; nonce provjerava WC pay_action().
    if ( ! isset( $_POST['woocommerce_pay'], $_GET['key'] ) || empty( $wp->query_vars['order-pay'] ) ) {
        return;
    }

    if ( ! dreampoint_b2b_cart_guard_blocks_current_user() ) {
        return;
    }

    $priority = has_action( 'wp', [ 'WC_Form_Handler', 'pay_action' ] );
    if ( false !== $priority ) {
        remove_action( 'wp', [ 'WC_Form_Handler', 'pay_action' ], $priority );
    }

    dreampoint_b2b_cart_guard_notice();
}
add_action( 'wp', 'dreampoint_b2b_cart_guard_order_pay_submission', 10 );

/**
 * Rezervna linija: ako je pay_action() ipak izvršen, error notice sprječava process_payment()
 * (WC provjerava wc_notice_count('error') prije poziva gatewaya, class-wc-form-handler.php:544).
 */
function dreampoint_b2b_cart_guard_before_pay_action(): void {
    if ( dreampoint_b2b_cart_guard_blocks_current_user() ) {
        dreampoint_b2b_cart_guard_notice();
    }
}
add_action( 'woocommerce_before_pay_action', 'dreampoint_b2b_cart_guard_before_pay_action', 1 );

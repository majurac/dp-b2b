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
 * Je li trenutni korisnik prijavljen, ali nije aktiviran.
 *
 * Koristi se SAMO za plaćanje postojećih narudžbi (Store API CheckoutOrder, classic order-pay):
 * anonimni korisnici tu namjerno nisu obuhvaćeni (politika plaćanja guest narudžbi nije mijenjana).
 * Za košaricu i checkout vidi dreampoint_b2b_cart_guard_block_reason().
 */
function dreampoint_b2b_cart_guard_blocks_current_user(): bool {
    return is_user_logged_in() && ! dreampoint_b2b_user_is_activated( get_current_user_id() );
}

/**
 * Razlog blokade kupovine (košarica + checkout) za trenutnog korisnika, ili null ako je dopušteno.
 *
 * - 'login_required': anonimni posjetitelj (zatvorena B2B platforma). DP_BYPASS_APPROVAL to ne mijenja:
 *   dreampoint_b2b_user_is_activated() za ID 0 uvijek vraća false, a bypass se odnosi samo na odobrenje računa.
 * - 'not_activated': prijavljen, ali neodobren (Phase 1–3).
 *
 * @return 'login_required'|'not_activated'|null
 */
function dreampoint_b2b_cart_guard_block_reason(): ?string {
    if ( ! is_user_logged_in() ) {
        return 'login_required';
    }
    if ( ! dreampoint_b2b_user_is_activated( get_current_user_id() ) ) {
        return 'not_activated';
    }
    return null;
}

/**
 * Poruka za neaktivirane korisnike (bez internih detalja o odobrenju).
 */
function dreampoint_b2b_cart_guard_message( ?string $reason = null ): string {
    if ( 'login_required' === $reason ) {
        return __( 'Za naručivanje se morate prijaviti.', 'dreampoint-b2b' );
    }
    return __( 'Vaš račun još nije odobren. Naručivanje će biti moguće nakon odobrenja.', 'dreampoint-b2b' );
}

/**
 * Strojni kod greške za razlog blokade.
 */
function dreampoint_b2b_cart_guard_code( ?string $reason = null ): string {
    return 'login_required' === $reason ? 'dp_b2b_login_required' : 'dp_b2b_not_activated';
}

/**
 * Dodaj error notice samo jednom (dedupe bez static varijabli).
 */
function dreampoint_b2b_cart_guard_notice( ?string $reason = null ): void {
    dreampoint_b2b_cart_guard_add_notice( dreampoint_b2b_cart_guard_message( $reason ) );
}

/**
 * Dodaj proizvoljan error notice jednom po poruci.
 */
function dreampoint_b2b_cart_guard_add_notice( string $message ): void {
    if ( function_exists( 'wc_has_notice' ) && wc_has_notice( $message, 'error' ) ) {
        return;
    }
    wc_add_notice( $message, 'error' );
}

// ----------------------------------------------------------------------------
// Bucket eligibility (Finding B)
// ----------------------------------------------------------------------------

/**
 * ID roditeljskog proizvoda (varijacija nasljeđuje odluku roditelja); 0 ako ID nije proizvod.
 */
function dreampoint_b2b_cart_guard_parent_id( int $id ): int {
    if ( $id <= 0 ) {
        return 0;
    }
    if ( 'product_variation' === get_post_type( $id ) ) {
        return (int) wp_get_post_parent_id( $id );
    }
    return $id;
}

/**
 * Je li proizvod dopušten trenutnom korisniku po jedinstvenom pravilu vidljivosti (bucket, include/exclude,
 * brand/kategorija, custom offer, overrides). Osoblje je izuzeto unutar samog predikata.
 * Nepostojeći ID se ne ocjenjuje (prepušta se WooCommerceu). Zadana vrijednost je false (fail-closed).
 */
function dreampoint_b2b_cart_guard_product_eligible( int $id ): bool {
    $parent_id = dreampoint_b2b_cart_guard_parent_id( $id );
    if ( $parent_id <= 0 ) {
        return true;
    }
    return (bool) apply_filters( 'dp_b2b_product_accessible', false, $parent_id, get_current_user_id() );
}

/**
 * Stavke trenutne košarice koje korisnik više ne smije kupiti (npr. promjena bucketa).
 * Stavke se ne mijenjaju; jedan zapis po roditeljskom proizvodu.
 *
 * @return array<int, string> Mapa parent ID → naziv.
 */
function dreampoint_b2b_cart_guard_ineligible_items(): array {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return [];
    }

    $ineligible = [];
    $checked    = [];

    foreach ( WC()->cart->get_cart() as $item ) {
        $parent_id = dreampoint_b2b_cart_guard_parent_id( (int) ( $item['product_id'] ?? 0 ) );
        if ( $parent_id <= 0 || isset( $checked[ $parent_id ] ) ) {
            continue;
        }
        $checked[ $parent_id ] = true;

        if ( ! dreampoint_b2b_cart_guard_product_eligible( $parent_id ) ) {
            $ineligible[ $parent_id ] = get_the_title( $parent_id );
        }
    }

    return $ineligible;
}

/**
 * Poruka za proizvod koji više nije dostupan korisniku (stavka ostaje u košarici).
 */
function dreampoint_b2b_cart_guard_ineligible_message( string $name ): string {
    return sprintf(
        /* translators: %s: product name */
        __( 'Proizvod „%s“ nije dostupan za vaš račun. Uklonite ga iz košarice kako biste nastavili.', 'dreampoint-b2b' ),
        esc_html( $name )
    );
}

// ----------------------------------------------------------------------------
// Cart additions
// ----------------------------------------------------------------------------

/**
 * Standardni add-to-cart putevi koji primjenjuju filter: ?add-to-cart=, forma,
 * wc-ajax=add_to_cart, wishlist, ponovna narudžba. Prije mutacije košarice.
 *
 * Osim blokade (anonimni / neodobreni) provjerava i bucket: proizvod izvan korisnikova bucketa se ne dodaje.
 * Hook NIJE univerzalna granica (poziva ga form handler, wc-ajax i Store API, ali ne i WC_Cart::add_to_cart),
 * zato postoji i woocommerce_add_to_cart_quantity ispod.
 *
 * @param bool $passed       Rezultat prethodnih validacija.
 * @param int  $product_id   ID proizvoda (ili varijacije).
 * @param int  $quantity     Količina.
 * @param int  $variation_id ID varijacije, ako je poznat.
 */
function dreampoint_b2b_cart_guard_add_validation( $passed, $product_id = 0, $quantity = 1, $variation_id = 0 ) {
    $reason = dreampoint_b2b_cart_guard_block_reason();
    if ( null !== $reason ) {
        dreampoint_b2b_cart_guard_notice( $reason );
        return false;
    }

    $target = (int) $variation_id ?: (int) $product_id;
    if ( ! dreampoint_b2b_cart_guard_product_eligible( $target ) ) {
        $parent_id = dreampoint_b2b_cart_guard_parent_id( $target );
        dreampoint_b2b_cart_guard_add_notice( dreampoint_b2b_cart_guard_ineligible_message( get_the_title( $parent_id ) ) );
        return false;
    }

    return $passed;
}
add_filter( 'woocommerce_add_to_cart_validation', 'dreampoint_b2b_cart_guard_add_validation', 5, 4 );

/**
 * Središnja granica unutar WC_Cart::add_to_cart() (klasični obrazac, wc-ajax, Store API, Quick Order sync,
 * izravni pozivi, bundle djeca, ponovna narudžba). Količina 0 → add_to_cart() vraća false, bez izmjene košarice.
 * Prima već razriješen roditeljski ID proizvoda.
 *
 * @param int|float $quantity     Tražena količina.
 * @param int       $product_id   Roditeljski ID proizvoda.
 * @param int       $variation_id ID varijacije (0 ako nije varijacija).
 * @return int|float
 */
function dreampoint_b2b_cart_guard_add_quantity( $quantity, $product_id = 0, $variation_id = 0 ) {
    if ( null !== dreampoint_b2b_cart_guard_block_reason() ) {
        return 0;
    }
    if ( ! dreampoint_b2b_cart_guard_product_eligible( (int) $product_id ) ) {
        return 0;
    }
    return $quantity;
}
// Last in line (PHP_INT_MAX): a later-running filter could otherwise turn a rejected 0 back into a positive
// quantity. NOTE: Store API add-item does NOT call WC_Cart::add_to_cart() (CartController::add_to_cart builds
// the cart line / calls set_quantity() itself), so for Store API the boundary is
// woocommerce_add_to_cart_validation (CartController.php, validate_add_to_cart) above.
add_filter( 'woocommerce_add_to_cart_quantity', 'dreampoint_b2b_cart_guard_add_quantity', PHP_INT_MAX, 3 );

// ----------------------------------------------------------------------------
// Cart quantity changes
// ----------------------------------------------------------------------------

/**
 * Klasično ažuriranje količina u košarici (wp_loaded → update_cart_action).
 *
 * @param bool $passed Rezultat prethodnih validacija.
 */
function dreampoint_b2b_cart_guard_update_validation( $passed ) {
    $reason = dreampoint_b2b_cart_guard_block_reason();
    if ( null === $reason ) {
        return $passed;
    }
    dreampoint_b2b_cart_guard_notice( $reason );
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

    $is_cart_mutation = $route instanceof ( $ns . 'CartAddItem' )
        || $route instanceof ( $ns . 'CartUpdateItem' )
        || ( $route instanceof ( $ns . 'CartItems' ) && 'POST' === $method )
        || ( $route instanceof ( $ns . 'CartItemsByKey' ) && in_array( $method, [ 'POST', 'PUT', 'PATCH' ], true ) );

    // Košarica: anonimni (401, dp_b2b_login_required) i neodobreni (403, dp_b2b_not_activated).
    if ( $is_cart_mutation ) {
        $reason = dreampoint_b2b_cart_guard_block_reason();
        if ( null === $reason ) {
            return $response;
        }
        return new WP_Error(
            dreampoint_b2b_cart_guard_code( $reason ),
            dreampoint_b2b_cart_guard_message( $reason ),
            [ 'status' => 'login_required' === $reason ? 401 : 403 ]
        );
    }

    // Standardni checkout (POST /checkout, klasa Checkout): anonimni se odbija prije kreiranja narudžbe.
    // Prijavljeni neodobreni korisnik ide kroz validaciju košarice (409) kao i do sada.
    if ( $route instanceof ( $ns . 'Checkout' ) && in_array( $method, [ 'POST', 'PUT', 'PATCH' ], true )
        && 'login_required' === dreampoint_b2b_cart_guard_block_reason() ) {
        return new WP_Error(
            'dp_b2b_login_required',
            dreampoint_b2b_cart_guard_message( 'login_required' ),
            [ 'status' => 401 ]
        );
    }

    // Plaćanje postojeće narudžbe: POST /wc/store/v1/checkout/{id} (CheckoutOrder). Odbija se prije
    // is_authorized(), ažuriranja narudžbe/kupca i gatewaya. Samo prijavljeni neodobreni korisnici;
    // anonimni (guest narudžbe) nisu obuhvaćeni.
    if ( $route instanceof ( $ns . 'CheckoutOrder' ) && 'POST' === $method
        && dreampoint_b2b_cart_guard_blocks_current_user() ) {
        return new WP_Error(
            'dp_b2b_not_activated',
            dreampoint_b2b_cart_guard_message(),
            [ 'status' => 403 ]
        );
    }

    return $response;
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
    $reason = dreampoint_b2b_cart_guard_block_reason();
    if ( null !== $reason ) {
        dreampoint_b2b_cart_guard_notice( $reason );
        return;
    }

    // Završna revalidacija bucketa: postojeće košarice, vraćene sesije, promjena bucketa, Store API/QO izmjene
    // količine. Stavke ostaju u košarici; korisnik ih sam uklanja, a do tada je checkout blokiran.
    foreach ( dreampoint_b2b_cart_guard_ineligible_items() as $name ) {
        dreampoint_b2b_cart_guard_add_notice( dreampoint_b2b_cart_guard_ineligible_message( $name ) );
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
    if ( ! $errors instanceof WP_Error ) {
        return;
    }

    $reason = dreampoint_b2b_cart_guard_block_reason();
    if ( null !== $reason ) {
        $errors->add( dreampoint_b2b_cart_guard_code( $reason ), dreampoint_b2b_cart_guard_message( $reason ) );
        return;
    }

    foreach ( dreampoint_b2b_cart_guard_ineligible_items() as $parent_id => $name ) {
        $errors->add(
            'dp_b2b_product_not_available',
            dreampoint_b2b_cart_guard_ineligible_message( $name ),
            [ 'product_id' => (int) $parent_id ]
        );
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

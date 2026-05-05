<?php
/**
 * B2B Registration Fields
 *
 * Proširuje WooCommerce registraciju s B2B poljima (OIB, tvrtka, telefon, adresa).
 * Sva polja se kopiraju u billing i shipping meta pri registraciji.
 *
 * @package Dreampoint_B2B
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Spremi custom polja registracije u user meta.
 * Automatski kopira billing → shipping meta.
 *
 * @param int $customer_id
 */
function dreampoint_b2b_save_registration_fields( int $customer_id ): void {
    $fields = [
        'billing_first_name' => 'account_first_name',
        'billing_last_name'  => 'account_last_name',
        'billing_company'    => 'billing_company',
        'billing_oib'        => 'billing_oib',
        'billing_phone'      => 'billing_phone',
        'billing_address_1'  => 'billing_address_1',
        'billing_city'       => 'billing_city',
        'billing_country'    => 'billing_country',
    ];

    foreach ( $fields as $meta_key => $post_key ) {
        if ( isset( $_POST[ $post_key ] ) ) {
            update_user_meta( $customer_id, $meta_key, sanitize_text_field( $_POST[ $post_key ] ) );
        }
    }

    // Posebna obrada poštanskog broja — normalizacija na 5 cifara (HR format)
    if ( isset( $_POST['billing_postcode'] ) ) {
        $postcode = sanitize_text_field( $_POST['billing_postcode'] );
        if ( strlen( $postcode ) === 6 && is_numeric( $postcode ) ) {
            $postcode = substr( $postcode, 0, 5 );
        }
        update_user_meta( $customer_id, 'billing_postcode', $postcode );
    }

    // Kopiraj ime i prezime u display_name i WP core polja
    if ( isset( $_POST['account_first_name'] ) ) {
        $first = sanitize_text_field( $_POST['account_first_name'] );
        update_user_meta( $customer_id, 'shipping_first_name', $first );
        update_user_meta( $customer_id, 'first_name', $first );
    }
    if ( isset( $_POST['account_last_name'] ) ) {
        $last = sanitize_text_field( $_POST['account_last_name'] );
        update_user_meta( $customer_id, 'shipping_last_name', $last );
        update_user_meta( $customer_id, 'last_name', $last );
    }

    // Kopiraj billing → shipping za ostala polja
    $copy_fields = [ 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone' ];
    foreach ( $copy_fields as $field ) {
        $value = get_user_meta( $customer_id, 'billing_' . $field, true );
        if ( ! empty( $value ) ) {
            update_user_meta( $customer_id, 'shipping_' . $field, $value );
        }
    }
}
add_action( 'woocommerce_created_customer', 'dreampoint_b2b_save_registration_fields' );

/**
 * Dodaj OIB polje ispod tvrtke u WP admin billing sekciji.
 *
 * @param array<string, mixed> $fields
 * @return array<string, mixed>
 */
function dreampoint_b2b_oib_admin_field( array $fields ): array {
    $oib_field = [
        'billing_oib' => [
            'label'       => __( 'OIB', 'dreampoint-b2b' ),
            'description' => '',
            'type'        => 'text',
        ],
    ];

    $billing_fields   = $fields['billing']['fields'];
    $company_position = array_search( 'billing_company', array_keys( $billing_fields ), true );

    if ( $company_position !== false ) {
        $billing_fields =
            array_slice( $billing_fields, 0, $company_position + 1, true )
            + $oib_field
            + array_slice( $billing_fields, $company_position + 1, null, true );
    } else {
        $billing_fields += $oib_field;
    }

    $fields['billing']['fields'] = $billing_fields;
    return $fields;
}
add_action( 'woocommerce_customer_meta_fields', 'dreampoint_b2b_oib_admin_field' );

/**
 * Dodaj polja tvrtke, OIB-a i telefona na stranicu uređivanja računa.
 */
function dreampoint_b2b_account_form_fields(): void {
    $user_id         = get_current_user_id();
    $phone           = get_user_meta( $user_id, 'billing_phone', true );
    $billing_company = get_user_meta( $user_id, 'billing_company', true );
    $company_oib     = get_user_meta( $user_id, 'billing_oib', true );
    ?>

    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="account_billing_company"><?php esc_html_e( 'Company', 'woocommerce' ); ?></label>
        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text"
               name="account_billing_company" id="account_billing_company"
               value="<?php echo esc_attr( $billing_company ); ?>" required />
    </p>

    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="account_company_oib"><?php esc_html_e( 'OIB', 'dreampoint-b2b' ); ?></label>
        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text"
               name="account_company_oib" id="account_company_oib"
               value="<?php echo esc_attr( $company_oib ); ?>" required />
    </p>

    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="account_phone"><?php esc_html_e( 'Phone', 'woocommerce' ); ?></label>
        <input type="tel" class="woocommerce-Input woocommerce-Input--text input-text"
               name="account_phone" id="account_phone"
               value="<?php echo esc_attr( $phone ); ?>" required />
    </p>

    <?php
}
add_action( 'woocommerce_edit_account_form_fields', 'dreampoint_b2b_account_form_fields' );

/**
 * Spremi B2B polja iz stranice uređivanja računa.
 *
 * @param int $user_id
 */
function dreampoint_b2b_save_account_fields( int $user_id ): void {
    if ( isset( $_POST['account_billing_company'] ) ) {
        update_user_meta( $user_id, 'billing_company', sanitize_text_field( $_POST['account_billing_company'] ) );
    }
    if ( isset( $_POST['account_company_oib'] ) ) {
        update_user_meta( $user_id, 'billing_oib', sanitize_text_field( $_POST['account_company_oib'] ) );
    }
    if ( isset( $_POST['account_phone'] ) ) {
        update_user_meta( $user_id, 'billing_phone', sanitize_text_field( $_POST['account_phone'] ) );
    }
}
add_action( 'woocommerce_save_account_details', 'dreampoint_b2b_save_account_fields' );

/**
 * Preusmjeri nelogirane korisnike na /my-account.
 * Dopušta pristup: login, registracija, reset lozinke.
 */
function dreampoint_b2b_restrict_guest_access(): void {
    if ( is_user_logged_in() ) {
        return;
    }

    $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';

    // Dopusti reset password flow
    if ( isset( $_GET['key'] ) || isset( $_GET['login'] ) || in_array( $action, [ 'rp', 'resetpass' ], true ) ) {
        return;
    }

    // Dopusti registraciju
    if ( 'register' === $action ) {
        return;
    }

    // Dopusti my-account stranicu (login forma)
    $current_page = get_post_field( 'post_name', get_post() );
    if ( 'my-account' === $current_page ) {
        return;
    }

    wp_safe_redirect( site_url( '/my-account' ) );
    exit;
}
add_action( 'template_redirect', 'dreampoint_b2b_restrict_guest_access' );

/**
 * Preusmjeri na /approval-pending nakon uspješne registracije.
 *
 * @param string $redirect
 * @return string
 */
function dreampoint_b2b_registration_redirect( string $redirect ): string {
    return site_url( '/approval-pending' );
}
add_filter( 'woocommerce_registration_redirect', 'dreampoint_b2b_registration_redirect' );

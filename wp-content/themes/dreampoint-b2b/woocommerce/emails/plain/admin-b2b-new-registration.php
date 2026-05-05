<?php
/**
 * Admin notifikacija — nova B2B registracija (plain text).
 *
 * @package Dreampoint_B2B\Emails
 *
 * @var string   $email_heading
 * @var WP_User  $user
 * @var WC_Email $email
 */

defined( 'ABSPATH' ) || exit;

$user_id = $user->ID;
$country = WC()->countries->get_countries()[ get_user_meta( $user_id, 'billing_country', true ) ] ?? '';

echo esc_html( $email_heading ) . "\n\n";
echo esc_html__( 'Nova B2B registracija zaprimljena. Podaci za unos u ERP:', 'dreampoint-b2b' ) . "\n\n";

$rows = [
    __( 'Kontakt osoba',  'dreampoint-b2b' ) => trim( $user->first_name . ' ' . $user->last_name ),
    __( 'Email',          'dreampoint-b2b' ) => $user->user_email,
    __( 'Tvrtka',         'dreampoint-b2b' ) => get_user_meta( $user_id, 'billing_company',   true ),
    __( 'OIB',            'dreampoint-b2b' ) => get_user_meta( $user_id, 'billing_oib',       true ),
    __( 'Telefon',        'dreampoint-b2b' ) => get_user_meta( $user_id, 'billing_phone',     true ),
    __( 'Adresa',         'dreampoint-b2b' ) => get_user_meta( $user_id, 'billing_address_1', true ),
    __( 'Grad',           'dreampoint-b2b' ) => get_user_meta( $user_id, 'billing_city',      true ),
    __( 'Poštanski broj', 'dreampoint-b2b' ) => get_user_meta( $user_id, 'billing_postcode',  true ),
    __( 'Država',         'dreampoint-b2b' ) => $country,
];

foreach ( $rows as $label => $value ) {
    printf( "%s: %s\n", esc_html( $label ), esc_html( $value ?: '—' ) );
}

echo "\n" . esc_html__( 'Admin URL', 'dreampoint-b2b' ) . ': ';
echo esc_url( admin_url( 'user-edit.php?user_id=' . $user_id ) ) . "\n";
echo "\n" . esc_url( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );

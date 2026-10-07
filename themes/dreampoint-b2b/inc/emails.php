<?php
/**
 * B2B Custom Emails
 *
 * Registrira custom WC email razred za admin notifikaciju o novoj B2B registraciji.
 * WC email za korisnika (customer-new-account) overrideovan je template fajlom
 * u woocommerce/emails/customer-new-account.php.
 *
 * @package Dreampoint_B2B
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Sakrij WP-ov default admin email o novom korisniku — naš WC email ga zamjenjuje
// s punim B2B podacima (tvrtka, OIB, adresa) potrebnim za ERP unos.
add_filter( 'wp_send_new_user_notification_to_admin', '__return_false' );

/**
 * Je li postavka WC emaila neprilagođena (prazna ili jednaka WC zadanoj vrijednosti)?
 * Samo tada se primjenjuje hrvatski zadani tekst; administratorova vlastita
 * vrijednost iz WooCommerce → Postavke → Emailovi uvijek ima prednost.
 */
function dreampoint_b2b_email_setting_is_default( WC_Email $email, string $key, string $default ): bool {
    $saved = get_option( $email->get_option_key(), [] );
    $value = is_array( $saved ) && isset( $saved[ $key ] ) ? trim( (string) $saved[ $key ] ) : '';
    return '' === $value || $value === trim( $default );
}

// Customer new-account email: hrvatski zadani subject/heading/dodatni sadržaj
// (WC zadani tekstovi su na engleskom jer je locale sajta en_US).
add_filter( 'woocommerce_email_subject_customer_new_account', function ( $subject, $user, $email ) {
    if ( $email instanceof WC_Email && dreampoint_b2b_email_setting_is_default( $email, 'subject', $email->get_default_subject() ) ) {
        return $email->format_string( __( 'Vaša registracija na {site_title} je zaprimljena', 'dreampoint-b2b' ) );
    }
    return $subject;
}, 10, 3 );

add_filter( 'woocommerce_email_heading_customer_new_account', function ( $heading, $user, $email ) {
    if ( $email instanceof WC_Email && dreampoint_b2b_email_setting_is_default( $email, 'heading', $email->get_default_heading() ) ) {
        return $email->format_string( __( 'Registracija je zaprimljena', 'dreampoint-b2b' ) );
    }
    return $heading;
}, 10, 3 );

add_filter( 'woocommerce_email_additional_content_customer_new_account', function ( $content, $user, $email ) {
    if ( $email instanceof WC_Email && dreampoint_b2b_email_setting_is_default( $email, 'additional_content', $email->get_default_additional_content() ) ) {
        return $email->format_string( __( 'Radujemo se suradnji s vama.', 'dreampoint-b2b' ) );
    }
    return $content;
}, 10, 3 );

/**
 * Registrira custom WC email razred.
 *
 * @param array<string, WC_Email> $email_classes
 * @return array<string, WC_Email>
 */
add_filter( 'woocommerce_email_classes', function( array $email_classes ): array {
    $email_classes['WC_Email_Admin_B2B_New_Registration'] = new WC_Email_Admin_B2B_New_Registration();
    return $email_classes;
} );

if ( ! class_exists( 'WC_Email_Admin_B2B_New_Registration', false ) ) :

/**
 * Admin notifikacija o novoj B2B registraciji.
 *
 * Prikazuje se u WooCommerce → Postavke → Emailovi.
 * Šalje se na woocommerce_created_customer (prio 20) — nakon što
 * dreampoint_b2b_save_registration_fields (prio 10) spremi B2B meta podatke.
 */
class WC_Email_Admin_B2B_New_Registration extends WC_Email {

    public function __construct() {
        $this->id             = 'admin_b2b_new_registration';
        $this->title          = __( 'Nova B2B registracija (admin)', 'dreampoint-b2b' );
        $this->description    = __( 'Šalje se adminu s podacima nove B2B registracije za ručni unos u ERP.', 'dreampoint-b2b' );
        $this->template_html  = 'emails/admin-b2b-new-registration.php';
        $this->template_plain = 'emails/plain/admin-b2b-new-registration.php';
        $this->template_base  = get_template_directory() . '/woocommerce/';
        $this->placeholders   = [
            '{site_title}'      => $this->get_blogname(),
            '{billing_company}' => '',
        ];

        add_action( 'woocommerce_created_customer', [ $this, 'trigger' ], 20 );

        parent::__construct();

        $this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
    }

    /**
     * @param int $customer_id
     */
    public function trigger( int $customer_id ): void {
        $this->setup_locale();

        if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
            $this->restore_locale();
            return;
        }

        $user = get_userdata( $customer_id );
        if ( ! $user ) {
            $this->restore_locale();
            return;
        }

        $this->object                       = $user;
        $this->placeholders['{billing_company}'] = get_user_meta( $customer_id, 'billing_company', true );

        $this->send(
            $this->get_recipient(),
            $this->get_subject(),
            $this->get_content(),
            $this->get_headers(),
            $this->get_attachments()
        );

        $this->restore_locale();
    }

    public function get_default_subject(): string {
        /* translators: {site_title} and {billing_company} are placeholders */
        return __( '[{site_title}] Nova B2B registracija — {billing_company}', 'dreampoint-b2b' );
    }

    public function get_default_heading(): string {
        return __( 'Nova B2B registracija', 'dreampoint-b2b' );
    }

    public function get_content_html(): string {
        return wc_get_template_html(
            $this->template_html,
            [
                'email_heading' => $this->get_heading(),
                'email'         => $this,
                'user'          => $this->object,
            ],
            '',
            $this->template_base
        );
    }

    public function get_content_plain(): string {
        return wc_get_template_html(
            $this->template_plain,
            [
                'email_heading' => $this->get_heading(),
                'email'         => $this,
                'user'          => $this->object,
            ],
            '',
            $this->template_base
        );
    }

    public function init_form_fields(): void {
        parent::init_form_fields();
        $this->form_fields['recipient'] = [
            'title'       => __( 'Primatelj', 'dreampoint-b2b' ),
            'type'        => 'text',
            'description' => __( 'Email adresa za primanje notifikacija o novim registracijama. Odvojite više adresa zarezom.', 'dreampoint-b2b' ),
            'placeholder' => get_option( 'admin_email' ),
            'default'     => '',
            'desc_tip'    => true,
        ];
    }
}

endif;

<?php
/**
 * Admin notifikacija — nova B2B registracija (HTML).
 *
 * Šalje se adminu odmah po registraciji B2B partnera.
 * Sadrži sve registracijske podatke za ručni unos u ERP.
 *
 * @package Dreampoint_B2B\Emails
 *
 * @var string   $email_heading
 * @var WP_User  $user
 * @var WC_Email $email
 * @var bool     $email_improvements_enabled
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

$email_improvements_enabled = FeaturesUtil::feature_is_enabled( 'email_improvements' );

$user_id  = $user->ID;
$country  = WC()->countries->get_countries()[ get_user_meta( $user_id, 'billing_country', true ) ] ?? '';

$rows = [
    __( 'Kontakt osoba',  'dreampoint-b2b' ) => trim( $user->first_name . ' ' . $user->last_name ),
    __( 'Email',          'dreampoint-b2b' ) => $user->user_email,
    __( 'Tvrtka',         'dreampoint-b2b' ) => get_user_meta( $user_id, 'billing_company',  true ),
    __( 'OIB',            'dreampoint-b2b' ) => get_user_meta( $user_id, 'billing_oib',      true ),
    __( 'Telefon',        'dreampoint-b2b' ) => get_user_meta( $user_id, 'billing_phone',    true ),
    __( 'Adresa',         'dreampoint-b2b' ) => get_user_meta( $user_id, 'billing_address_1', true ),
    __( 'Grad',           'dreampoint-b2b' ) => get_user_meta( $user_id, 'billing_city',     true ),
    __( 'Poštanski broj', 'dreampoint-b2b' ) => get_user_meta( $user_id, 'billing_postcode', true ),
    __( 'Država',         'dreampoint-b2b' ) => $country,
];

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php echo $email_improvements_enabled ? '<div class="email-introduction">' : ''; ?>
<p><?php esc_html_e( 'Nova B2B registracija zaprimljena. Podaci za unos u ERP:', 'dreampoint-b2b' ); ?></p>
<?php echo $email_improvements_enabled ? '</div>' : ''; ?>

<table class="td" cellspacing="0" cellpadding="6"
       style="width:100%;font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif;"
       border="1">
    <?php foreach ( $rows as $label => $value ) : ?>
    <tr>
        <th class="td" style="text-align:left;width:35%;"><?php echo esc_html( $label ); ?></th>
        <td class="td"><?php echo esc_html( $value ?: '—' ); ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<p style="margin-top:16px;">
    <a class="link" href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $user_id ) ); ?>">
        <?php esc_html_e( 'Uredi korisnika u WP adminu', 'dreampoint-b2b' ); ?>
    </a>
</p>

<?php do_action( 'woocommerce_email_footer', $email ); ?>

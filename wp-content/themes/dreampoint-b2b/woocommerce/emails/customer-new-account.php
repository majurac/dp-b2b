<?php
/**
 * Customer new account email — B2B pending approval verzija.
 *
 * Override WC customer-new-account.php template.
 * Umjesto standardnog "dobrodošli, ovo su vaši podaci" šalje
 * "registracija primljena, čekajte odobrenje".
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 10.4.0
 *
 * @var string    $email_heading
 * @var string    $additional_content
 * @var string    $user_login
 * @var string    $blogname
 * @var bool      $password_generated
 * @var string    $set_password_url
 * @var bool      $sent_to_admin
 * @var bool      $plain_text
 * @var WC_Email  $email
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

$email_improvements_enabled = FeaturesUtil::feature_is_enabled( 'email_improvements' );

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php echo $email_improvements_enabled ? '<div class="email-introduction">' : ''; ?>

<p>
	<?php
	printf(
		/* translators: %s: site name */
		esc_html__( 'Zahvaljujemo na registraciji na %s.', 'dreampoint-b2b' ),
		esc_html( $blogname )
	);
	?>
</p>

<p><?php esc_html_e( 'Vaša registracija je uspješno zaprimljena i trenutno čeka odobrenje našeg tima.', 'dreampoint-b2b' ); ?></p>

<p><?php esc_html_e( 'Obavijestit ćemo vas e-poštom čim vaš račun bude aktiviran — tada ćete moći pristupiti svim B2B sadržajima i cijenama.', 'dreampoint-b2b' ); ?></p>

<?php echo $email_improvements_enabled ? '</div>' : ''; ?>

<?php
if ( $additional_content ) {
	echo $email_improvements_enabled ? '<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation"><tr><td class="email-additional-content email-additional-content-aligned">' : '';
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
	echo $email_improvements_enabled ? '</td></tr></table>' : '';
}

do_action( 'woocommerce_email_footer', $email );

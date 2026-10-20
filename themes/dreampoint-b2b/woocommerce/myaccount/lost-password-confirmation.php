<?php
/**
 * Lost password confirmation (shown after the reset e-mail was requested, `?reset-link-sent=1`).
 *
 * Overrides woocommerce/templates/myaccount/lost-password-confirmation.php (@version 3.9.0).
 * Figma AUTH-04: standalone split screen on the shared `.login-register` shell (`--sent` modifier). The native
 * "Password reset email has been sent." notice is replaced by this screen; the reset processing itself is untouched.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.9.0
 */

defined( 'ABSPATH' ) || exit;

$company_logo = get_field( 'logo', 'option' ); // alt text only; the horizontal wordmark is a theme asset.
?>

<div class="login-register login-register--sent">
    <div class="container">
        <div class="lr-content">
            <div id="lr-logo">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>">
                    <img src="<?php echo esc_url( get_template_directory_uri() . '/img/logos/dreampoint-wordmark.svg' ); ?>" width="147" height="56" alt="<?php echo esc_attr( $company_logo['alt'] ?? __( 'Dreampoint Logo', 'dreampoint-b2b' ) ); ?>">
                </a>
            </div>
            <!-- /#lr-logo -->
            <div class="lr-intro">
                <?php // "e-mail" must not break at the hyphen (Figma wraps after "svoju"). ?>
                <h1><?php echo wp_kses( sprintf( __( 'Provjerite svoju %s adresu', 'dreampoint-b2b' ), '<span class="lr-nowrap">e-mail</span>' ), [ 'span' => [ 'class' => [] ] ] ); ?></h1>
                <?php do_action( 'woocommerce_before_lost_password_confirmation_message' ); ?>
                <p><?php echo esc_html( apply_filters( 'woocommerce_lost_password_confirmation_message', __( 'Poslali smo vam e-mail sa linkom za reset lozinke. Ukoliko ga ne pronađete u narednih nekoliko minuta, provjerite Spam ili Junk folder.', 'dreampoint-b2b' ) ) ); ?></p>
                <?php do_action( 'woocommerce_after_lost_password_confirmation_message' ); ?>
            </div>
            <!-- /.lr-intro -->
            <div class="lr-actions">
                <?php // The reset form is the only way to request another e-mail: the confirmation URL deliberately carries no user data. ?>
                <a href="<?php echo esc_url( wc_lostpassword_url() ); ?>" class="button button--xl"><?php esc_html_e( 'Ponovo pošalji mail', 'dreampoint-b2b' ); ?></a>
                <a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="button button--xl button--outline"><?php esc_html_e( 'Nazad na prijavu', 'dreampoint-b2b' ); ?></a>
            </div>
            <!-- /.lr-actions -->
        </div>
        <!-- /.lr-content -->
    </div>
    <!-- /.container -->
    <div class="lr-image">
        <img src="<?php echo esc_url( get_template_directory_uri() . '/img/bg/approval-pending.jpg' ); ?>" alt="">
    </div>
    <!-- /.lr-image -->
</div>
<!-- /.login-register -->

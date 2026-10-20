<?php
/**
 * Template name: Approval Pending
 *
 * Prikazuje se logiranima korisnicima čiji račun još nije odobren od strane admina.
 * Koristi se i kao redirect stranica odmah nakon registracije.
 *
 * Figma AUTH-03: standalone split layout (no site header/footer, see dreampoint_b2b_is_approval_pending_page()),
 * shared `.login-register` shell from sass/pages/myaccount.scss with the `--pending` modifier.
 *
 * @package Dreampoint_B2B
 */

$company_logo  = get_field( 'logo', 'option' );
$support_email = get_field( 'company_email', 'option' );

get_header();
?>

<main id="primary-content" class="login-register login-register--pending">
    <div class="container">
        <div class="lr-content">
            <div id="lr-logo">
                <img src="<?php echo esc_url( get_template_directory_uri() . '/img/logos/dreampoint-wordmark.svg' ); ?>" width="147" height="56" alt="<?php echo esc_attr( $company_logo['alt'] ?? __( 'Dreampoint Logo', 'dreampoint-b2b' ) ); ?>">
            </div>
            <!-- /#lr-logo -->
            <div class="lr-pending">
                <img class="lr-pending__icon" src="<?php echo esc_url( get_template_directory_uri() . '/img/ico/emoji-normal.svg' ); ?>" width="144" height="144" alt="">
                <div class="lr-intro">
                    <h1><?php esc_html_e( 'Vaš račun trenutno čeka odobrenje administratora', 'dreampoint-b2b' ); ?></h1>
                    <p><?php esc_html_e( 'Vaš zahtjev je uspješno zaprimljen. Nakon odobrenja dobit ćete obavijest putem e-maila, nakon čega ćete moći pristupiti B2B platformi.', 'dreampoint-b2b' ); ?></p>
                </div>
                <!-- /.lr-intro -->
                <div class="lr-pending__actions">
                    <a href="<?php echo esc_url( wc_logout_url() ); ?>" class="button button--xl"><?php esc_html_e( 'Nazad na prijavu', 'dreampoint-b2b' ); ?></a>
                    <?php if ( is_string( $support_email ) && is_email( $support_email ) ) : ?>
                        <a href="<?php echo esc_url( 'mailto:' . $support_email ); ?>" class="button button--xl button--outline"><?php esc_html_e( 'Kontaktiraj podršku', 'dreampoint-b2b' ); ?></a>
                    <?php endif; ?>
                </div>
                <!-- /.lr-pending__actions -->
            </div>
            <!-- /.lr-pending -->
        </div>
        <!-- /.lr-content -->
    </div>
    <!-- /.container -->
    <div class="lr-image">
        <img src="<?php echo esc_url( get_template_directory_uri() . '/img/bg/approval-pending.jpg' ); ?>" alt="">
    </div>
    <!-- /.lr-image -->
</main>
<!-- /.login-register -->

<?php get_footer(); ?>

<?php
/**
 * Template name: Approval Pending
 *
 * Prikazuje se logiranima korisnicima čiji račun još nije odobren od strane admina.
 * Koristi se i kao redirect stranica odmah nakon registracije.
 *
 * @package Dreampoint_B2B
 */

get_header();
?>

<div id="thankyou-registration">
    <div class="container">
        <div class="thankyou-holder">
            <div class="nf-icon">
                <img src="<?php echo esc_url( get_template_directory_uri() . '/img/ico/face-sad.svg' ); ?>" alt="">
            </div>
            <h1><?php esc_html_e( 'Hvala na registraciji!', 'dreampoint-b2b' ); ?></h1>
            <p><?php esc_html_e( 'Vaš zahtjev za registraciju je primljen i trenutno se obrađuje. Naše osoblje će vas kontaktirati u najkraćem mogućem roku radi potvrde računa. Obavijest ćete primiti na vašu e-mail adresu.', 'dreampoint-b2b' ); ?></p>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="button button--xl">
                <?php esc_html_e( 'Početna', 'dreampoint-b2b' ); ?> <i class="icon-house-solid"></i>
            </a>
        </div>
        <!-- /.thankyou-holder -->
    </div>
    <!-- /.container -->
</div>
<!-- /#thankyou-registration -->

<?php get_footer(); ?>

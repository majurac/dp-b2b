<?php
/**
 * Lost password form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/form-lost-password.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.2.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_lost_password_form' );

$company_logo = get_field('logo', 'option');
$lr_image = get_field('lr_image', 'option');
?>

<div class="login-register">
    <div class="container">
        <div class="lr-content">
            <?php if (!empty($company_logo) && !empty($company_logo['url'])) : ?>
            <div id="lr-logo">
                <a href="<?php echo esc_url(home_url('/')); ?>">
                    <img src="<?php echo esc_url($company_logo['url']); ?>" alt="<?php echo esc_attr($company_logo['alt'] ?? __('Dreampoint Logo', 'dreampoint-b2b')); ?>">
                </a>
            </div>
            <!-- /#lr-logo -->
            <?php endif; ?>
            <div class="lr-intro">
                <h1><?php esc_html_e( 'Lost your password?', 'woocommerce' ); ?></h1>
                <p><?php esc_html_e( 'Molimo unesite svoje korisničko ime ili adresu e-pošte. E-poštom ćete dobiti poveznicu za izradu nove lozinke.', 'dreampoint-b2b' ); ?></p>
            </div>
            <!-- /.lr-intro -->
            
            <div class="custom-form">

				<form method="post" class="woocommerce-ResetPassword lost_reset_password">

					<p class="woocommerce-form-row woocommerce-form-row--first form-row form-row-first">
						<label for="user_login"><?php esc_html_e( 'Username or email', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
						<input class="woocommerce-Input woocommerce-Input--text input-text" type="text" name="user_login" id="user_login" autocomplete="username" required aria-required="true" />
					</p>

					<div class="clear"></div>

					<?php do_action( 'woocommerce_lostpassword_form' ); ?>

					<p class="woocommerce-form-row form-row">
						<input type="hidden" name="wc_reset_password" value="true" />
						<button type="submit" class="button button--xl woocommerce-Button button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" value="<?php esc_attr_e( 'Reset password', 'woocommerce' ); ?>"><?php esc_html_e( 'Reset password', 'woocommerce' ); ?></button>
					</p>

					<?php wp_nonce_field( 'lost_password', 'woocommerce-lost-password-nonce' ); ?>

				</form>
            </div>
            <!-- /.custom-form -->
        </div>
        <!-- /.lr-content -->
    </div>
    <!-- /.container -->
   
    <?php if (is_array($lr_image) && isset($lr_image['url'])) : ?>
        <div class="lr-image">
            <img src="<?php echo esc_url($lr_image['url']); ?>" alt="<?php echo esc_attr($lr_image['alt']); ?>">
        </div>
        <!-- /.block-image -->
    <?php endif; ?>

</div>
<!-- /.login-register -->
<?php
do_action( 'woocommerce_after_lost_password_form' );

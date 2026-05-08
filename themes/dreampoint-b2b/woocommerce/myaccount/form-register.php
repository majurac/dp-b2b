<?php
/**
 * Login Form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/form-login.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

do_action( 'woocommerce_before_customer_login_form' ); 

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
            <!-- /#llr-logo -->
            <?php endif; ?>
            <div class="lr-intro">
                <h1><?php esc_html_e( 'Register', 'woocommerce' ); ?></h1>
                <p><?php esc_html_e( 'Pozivamo Vas da se registrirate u Dreampoint B2B popunjavanjem obrasca u nastavku.', 'dreampoint-b2b' ); ?></p>
            </div>
            <!-- /.lr-intro -->
            
            <div class="custom-form">
                <form method="post" class="woocommerce-form woocommerce-form-register register" <?php do_action( 'woocommerce_register_form_tag' ); ?> >
                
                    <?php do_action( 'woocommerce_register_form_start' ); ?>
                    <div class="row">
                        <div class="col-lg-6">
                            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                                <label for="reg_billing_first_name"><?php esc_html_e( 'First Name', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
                                <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="billing_first_name" id="reg_billing_first_name" autocomplete="given-name" value="<?php echo ( ! empty( $_POST['billing_first_name'] ) ) ? esc_attr( wp_unslash( $_POST['billing_first_name'] ) ) : ''; ?>" required />
                            </p>
                        </div>
                        <div class="col-lg-6">
                            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                                <label for="reg_billing_last_name"><?php esc_html_e( 'Last Name', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
                                <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="billing_last_name" id="reg_billing_last_name" autocomplete="family-name" value="<?php echo ( ! empty( $_POST['billing_last_name'] ) ) ? esc_attr( wp_unslash( $_POST['billing_last_name'] ) ) : ''; ?>" required />
                            </p>
                        </div>
                    </div>

                
                    <?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>
                
                        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                            <label for="reg_username"><?php esc_html_e( 'Username', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
                            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="reg_username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required aria-required="true" /><?php // @codingStandardsIgnoreLine ?>
                        </p>
                
                    <?php endif; ?>
                
                    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                        <label for="reg_email"><?php esc_html_e( 'Email address', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
                        <input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" autocomplete="email" value="<?php echo ( ! empty( $_POST['email'] ) ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>" required aria-required="true" /><?php // @codingStandardsIgnoreLine ?>
                    </p>
                
                    <?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>
                
                        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                            <label for="reg_password"><?php esc_html_e( 'Password', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
                            <input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" autocomplete="new-password" required aria-required="true" />
                        </p>
                
                    <?php else : ?>
                
                        <p><?php esc_html_e( 'A link to set a new password will be sent to your email address.', 'woocommerce' ); ?></p>
                
                    <?php endif; ?>
                
                    <?php do_action( 'woocommerce_register_form' ); ?>
                
                    <p class="woocommerce-form-row form-row">
                        <?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
                        <button type="submit" class="button button--xl button--icon-after woocommerce-Button woocommerce-button button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?> woocommerce-form-register__submit" name="register" value="<?php esc_attr_e( 'Register', 'woocommerce' ); ?>"><?php esc_html_e( 'Register', 'woocommerce' ); ?></button>
                    </p>
                
                    <div class="lr-footer">
                        <div class="already-registered">
                            <p><?php esc_html_e( 'Već imate kreiran račun?', 'dreampoint-b2b' ); ?> <a href="<?php echo esc_url( get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) ); ?>"><?php esc_html_e( 'Prijavite se', 'dreampoint-b2b' ); ?></a></p>
                        </div>
                        <!-- /.already-registered -->
                                        
                    </div>
                    <!-- /.lr-footer -->
                
                    <?php do_action( 'woocommerce_register_form_end' ); ?>
                
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
<?php do_action( 'woocommerce_after_customer_login_form' ); ?>

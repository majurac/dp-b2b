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
 * @version 9.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

$company_logo = get_field('logo', 'option');
$lr_image = get_field('lr_image', 'option');

do_action( 'woocommerce_before_customer_login_form' ); ?>




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
                <h1><?php esc_html_e( 'Login', 'woocommerce' ); ?></h1>
                <p><?php esc_html_e( 'Dobrodošli natrag. Molimo Vas popunite polja da biste se prijavili na Dreampoint B2B.', 'dreampoint-b2b' ); ?></p>
            </div>
            <!-- /.lr-intro -->
            <div class="custom-form">
                <form class="woocommerce-form woocommerce-form-login login" method="post">
                
                    <?php do_action( 'woocommerce_login_form_start' ); ?>
            

                    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                        <label for="username"><?php esc_html_e( 'Username or email address', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
                        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) && is_string( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required aria-required="true" /><?php // @codingStandardsIgnoreLine ?>
                    </p>
                    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                        <label for="password"><?php esc_html_e( 'Password', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
                        <input class="woocommerce-Input woocommerce-Input--text input-text" type="password" name="password" id="password" autocomplete="current-password" required aria-required="true" />
                    </p>

                    <?php do_action( 'woocommerce_login_form' ); ?>
                
                
                    <p class="form-row">
                        <div class="remember-lost custom-checkbox">
                            <label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">
                                <input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" /> <span><?php esc_html_e( 'Remember me', 'woocommerce' ); ?></span>
                            </label>
                            <p class="woocommerce-LostPassword lost_password">
                                <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Lost your password?', 'woocommerce' ); ?></a>
                            </p>
                        </div>
                        <!-- /.remember-lost -->
                        <?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
                        <button type="submit" class="button button--xl button--icon-after woocommerce-button button woocommerce-form-login__submit<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="login" value="<?php esc_attr_e( 'Log in', 'woocommerce' ); ?>"><?php esc_html_e( 'Log in', 'woocommerce' ); ?></button>
                    </p>
                    <div class="lr-footer">
                        
                        <p><?php esc_html_e( 'Nemaš račun?', 'dreampoint-b2b' ); ?> <a href="<?php echo esc_url( get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) . '?action=register' ); ?>"><?php esc_html_e( 'Kreiraj račun', 'dreampoint-b2b' ); ?></a></p>
                    </div>
                    <!-- /.lr-footer -->
                    
                
                    <?php do_action( 'woocommerce_login_form_end' ); ?>
                
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

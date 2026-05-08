<?php
/**
 * My Account page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/my-account.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woo.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 4.4.0
 */

defined( 'ABSPATH' ) || exit;

$user = wp_get_current_user();

do_action( 'woocommerce_before_edit_account_form' ); 

require get_template_directory() . '/woocommerce/myaccount/user-form.php';

do_action( 'woocommerce_after_edit_account_form' ); ?>


<script>document.addEventListener('DOMContentLoaded', function () {
    var saveAccountDetailsButtons = document.querySelectorAll('.save-account-details');

    saveAccountDetailsButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            // Find the closest form element and submit it
            var form = button.closest('form');
            if (form) {
                form.submit();
            }
        });
    });
});
</script>
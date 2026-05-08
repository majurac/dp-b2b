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
 * @version 3.5.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * My Account navigation.
 *
 * @since 2.6.0
 */

$allowed_html = array(
	'a' => array(
		'href' => array(),
	),
);

 ?>


<div id="account-page">
    <div class="container">
        <div class="inner-content">
        	<div class="row">
                <?php if ( WC()->query->get_current_endpoint() !== 'view-order' ): ?>
                    <div class="col-lg-4 col-xl-4">
                        <?php do_action( 'woocommerce_account_navigation' ); ?>
                    </div>
                    <!-- /.col-lg-3 -->
                <?php endif; ?>

                <?php if ( WC()->query->get_current_endpoint() == 'view-order' ): ?>
                <div class="col-lg-12">
                <?php else: ?>
        		<div class="col-lg-8 col-xl-8">
                <?php endif; ?>
        			<div class="my-acc-content-holder <?php echo ( WC()->query->get_current_endpoint() === 'orders' || WC()->query->get_current_endpoint() === 'view-order' ) ? 'no-padding' : ''; ?>">
                        <?php
                            /**
                             * My Account content.
                             *
                             * @since 2.6.0
                             */
                            do_action( 'woocommerce_account_content' );
                        ?>
                    </div>
                    <!-- /.my-acc-content-holder -->
        		</div>
        		<!-- /.col-lg-9 col-xl-8 -->
        	</div>
        	<!-- /.row -->
        </div>
        <!-- /.account-page-in -->
    </div>
    <!-- /.container -->
    
</div>
<!-- /#account-page -->
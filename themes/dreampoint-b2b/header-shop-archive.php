<?php
/**
 * The header for our theme
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package Dreampoint_B2B
 */

  get_header();

  do_action( 'dreampoint_b2b_before_product_listing' );

?>

<div id="products-page" class="block">
    <div class="container">
        <div class="row pos-r">
             <div class="col-lg-3 products-lhs-filter-wrap">
                <div class="products-lhs-filter">
                    <button class="close-mobile-filters">
                        <i class="icon-xmark"></i>
                    </button>
                    <?php echo do_shortcode('[wpf-filters id=1]'); ?>
                </div>
            </div>
            <!-- /.col-lg-3 products-lhs-filter-wrap -->
            <div class="col-lg-9">
                <div class="product-listing-wrap">
                    <div class="sort-filter">
                        <div class="sort-area">
                            <?php
                            get_template_part( 'template-parts/woocommerce-before-shop-loop' );

                            if ( woocommerce_product_loop() ) {
                                woocommerce_product_loop_start();
                                ?>

                                 <button class="button button--outline button--sm filter-btn"><?php esc_html_e( 'Filtriraj', 'dreampoint-b2b' ); ?></button>
                                <?php
                                woocommerce_product_loop_end();
                            }
                            ?>
                        </div>
                        <!-- /.sort-area -->
                    </div>
                    <!-- /.sort-filter -->

                    
                    <div class="product-listing">
                        <div class="row">
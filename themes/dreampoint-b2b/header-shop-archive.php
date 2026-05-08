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
                    <?php if (category_description()) :?>
                    <div class="category-description">
                        <?php echo category_description(); ?>
                    </div>
                    <!-- /.category-description -->
                    <?php endif; ?>
                    <div class="sort-filter">
                        <?php echo woocommerce_output_all_notices(); ?>

                        <div class="sort-area">
                            <?php
                          

                            if ( woocommerce_product_loop() ) {
                                /**
                                 * Hook: woocommerce_before_shop_loop.
                                 *
                                 * @hooked woocommerce_output_all_notices - 10
                                 * @hooked woocommerce_result_count - 20
                                 * @hooked woocommerce_catalog_ordering - 30
                                 */
                                do_action( 'woocommerce_before_shop_loop' );

                                woocommerce_product_loop_start();
                                ?>
                                
                                 <button class="button button--outline button--sm filter-btn"><?php esc_html_e( 'Filtriraj', 'dreampoint-b2b' ); ?></button>
                                <?php
                                woocommerce_product_loop_end();
                            } else {
                                // Add fallback content or a message for when no products are found
                                ?>
                                
                                <?php
                            }
                            ?>
                        </div>
                        <!-- /.sort-area -->
                    </div>
                    <!-- /.sort-filter -->

                    
                    <div class="product-listing">
                        <div class="row">
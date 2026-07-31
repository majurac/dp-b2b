<?php
if (!defined('ABSPATH')) {
    exit;
}

$title = get_field('title');

// Query products currently on sale (native WooCommerce API — covers simple + variable products)
$sale_ids = wc_get_product_ids_on_sale();

$args = array(
    'post_type'      => 'product',
    'posts_per_page' => 6,
    'post_status'    => 'publish',
    'post__in'       => ! empty( $sale_ids ) ? $sale_ids : array( 0 ),
    'orderby'        => 'post__in',
);
$discounted_products = new WP_Query($args);
?>

<div class="latest-products slider-grid block">
    <div class="container">
        <div class="section-heading">
            <?php if ($title) : ?>
                <h2><?php echo esc_html($title); ?></h2>
            <?php endif; ?>
            <a href="<?php echo esc_url( get_permalink( wc_get_page_id('shop') ) ); ?>" class="link">
                <?php esc_html_e( 'Pogledaj sve', 'dreampoint-b2b' ); ?>
            </a>
        </div>
        <div class="latest-products-content">
            <?php if ($discounted_products->have_posts()) : ?>
                <div class="latest-products-slider">
                    <?php while ($discounted_products->have_posts()) : $discounted_products->the_post(); ?>
                        <?php wc_get_template_part('content', 'product'); ?>
                    <?php endwhile; ?>
                    <?php wp_reset_postdata(); ?>
                </div>
                <!-- /.latest-products-slider -->
            <?php else : ?>
                <p><?php esc_html_e('Nisu pronađeni proizvodi na sniženju.', 'dreampoint-b2b'); ?></p>
            <?php endif; ?>
        </div>
        <!-- /.latest-products-content -->
        <div class="view-all-sm">
            <a href="<?php echo esc_url( get_permalink( wc_get_page_id('shop') ) ); ?>" class="button button--outline button--sm button--icon-after">
                <?php esc_html_e( 'Pogledaj sve', 'dreampoint-b2b' ); ?>
            </a>
        </div>
        <!-- /.view-all sm -->
    </div>
    <!-- /.container -->
</div>
<!-- /.latest-products block -->

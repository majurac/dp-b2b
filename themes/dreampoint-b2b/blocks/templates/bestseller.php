<?php
if (!defined('ABSPATH')) {
    exit;
}

$title = get_field('title');

$bestseller_page = get_page_by_path( 'bestseller' );
if ( $bestseller_page ) {
    $bestseller_page_url = esc_url( get_permalink( $bestseller_page ) );
} else {
    $bestseller_page_url = esc_url(
        add_query_arg(
            'orderby',
            'popularity',
            wc_get_page_permalink( 'shop' )
        )
    );
}

// Query best-selling products
$args = array(
    'post_type'      => 'product',
    'posts_per_page' => 5,
    'post_status'    => 'publish',
    'meta_key'       => 'total_sales',
    'orderby'        => 'meta_value_num',
    'order'          => 'DESC',
);
$bestsellers = new WP_Query($args);
?>

<div class="bestseller slider-grid block">
    <div class="container">
        <div class="section-heading">
            <?php if ($title) : ?>
                <h2><?php echo esc_html($title); ?></h2>
            <?php endif; ?>
            <a href="<?php echo $bestseller_page_url; ?>" class="link"><?php esc_html_e( 'Pogledaj sve', 'dreampoint-b2b' ); ?></a>
        </div>
        <div class="bestseller-content">
            <?php if ($bestsellers->have_posts()) : ?>
                <div class="bestseller-slider">
                    <?php while ($bestsellers->have_posts()) : $bestsellers->the_post(); ?>
                        <?php wc_get_template_part('content', 'product'); ?>
                    <?php endwhile; ?>
                    <?php wp_reset_postdata(); ?>
                </div>
                <!-- /.bestseller-slider -->
            <?php else : ?>
                <p><?php esc_html_e('Nisu pronađeni najprodavaniji proizvodi.', 'dreampoint-b2b'); ?></p>
            <?php endif; ?>
        </div>
        <!-- /.bestseller-content -->
        <div class="view-all-sm">
            <a href="<?php echo $bestseller_page_url; ?>" class="button button--outline button--sm button--icon-after"><?php esc_html_e( 'Pogledaj sve', 'dreampoint-b2b' ); ?></a>
        </div>
        <!-- /.view-all sm -->
    </div>
    <!-- /.container -->
</div>
<!-- /.bestseller block -->


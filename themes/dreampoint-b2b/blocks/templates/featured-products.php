<?php
if (!defined('ABSPATH')) {
    exit;
}

// ADR-009 Phase B: raw ID array (not the formatted relationship value) so
// resolving these products runs through our own WP_Query below instead of
// ACF's acf_get_posts() → filtered WP_Query (confirmed in ADR-009
// investigation, class-acf-field-relationship.php:764-769 + api-helpers.php:1165-1205).
$product_ids = get_field('selected_products', false, false);
$title = get_field('title');

if (empty($product_ids)) {
    return;
}

$args = array(
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'post__in'       => $product_ids,
    'orderby'        => 'post__in',
    'posts_per_page' => count($product_ids),
);
// Homepage/Segment Landing bypass only — no segment tax_query: this section
// is manually curated per page, editors already pick segment-appropriate products.
$args = dreampoint_b2b_shared_surface_query_args( $args, $post_id ?? null );
$products_query = new WP_Query($args);

if (! $products_query->have_posts()) {
    return;
}
?>
<div class="featured-products slider-grid block">
    <div class="container">
        <div class="section-heading">
            <?php if ($title) : ?>
                <h2><?php echo esc_html($title); ?></h2>
            <?php endif; ?>
        </div>
        <?php
        echo '<div class="featured-products-slider">';
        while ( $products_query->have_posts() ) :
            $products_query->the_post();
            wc_get_template_part( 'content', 'product' );
        endwhile;
        wp_reset_postdata();
        echo '</div>';
        ?>
    </div>
    <!-- /.container -->
</div>
<!-- /.featured-products block -->


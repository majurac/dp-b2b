<?php
if (!defined('ABSPATH')) {
    exit;
}

$products = get_field('selected_products');
$title = get_field('title');

if (empty($products)) {
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
        

        if ( $products ):
            echo '<div class="featured-products-slider">'; 
            foreach ( $products as $product_post ) {
                // Setup post data
                global $post;
                $post = $product_post;
                setup_postdata( $post );

                wc_get_template_part( 'content', 'product' );
            }
            wp_reset_postdata(); // Reset global $post
            echo '</div>';
        endif;
        ?>
    </div>
    <!-- /.container -->
</div>
<!-- /.featured-products block -->


<?php
/**
 * Products Tabs Block - Simple version
 * 
 * 2 tabs: "Preporučeno" | "Najnovije"
 * AJAX load, max 8 products
 * Uses: wc_get_template_part('content', 'product')
 */

if (!defined('ABSPATH')) {
    exit;
}

//$title = get_field('section_title') ?: 'Proizvodi';
$default_tab = get_field('default_tab') ?: 'featured';
$block_id = 'products-tabs-' . uniqid();
?>

<div class="products-tabs-block block" id="<?php echo esc_attr($block_id); ?>">
    <div class="container">
        
        <!-- Header with Tabs -->
        <div class="products-tabs-block__header">
            
            <div class="products-tabs-block__tabs">
                <button 
                    class="products-tabs-block__tab <?php echo $default_tab === 'featured' ? 'is-active' : ''; ?>" 
                    data-tab="featured"
                    data-nonce="<?php echo wp_create_nonce('load_products_nonce'); ?>">
                    <?php esc_html_e('Preporučeno', 'dreampoint-b2b'); ?>
                </button>
                <button 
                    class="products-tabs-block__tab <?php echo $default_tab === 'latest' ? 'is-active' : ''; ?>" 
                    data-tab="latest"
                    data-nonce="<?php echo wp_create_nonce('load_products_nonce'); ?>">
                    <?php esc_html_e('Najnovije', 'dreampoint-b2b'); ?>
                </button>
            </div>
        </div>
        
        <!-- Products Grid -->
        <div class="products-tabs-block__content">
            <div class="products-grid" data-products-container>
                
                <?php
                // Initial load - default tab
                $args = array(
                    'post_type'      => 'product',
                    'posts_per_page' => 8,
                    'post_status'    => 'publish',
                );
                
                if ($default_tab === 'featured') {
                    // Featured products - WooCommerce 3.0+ uses taxonomy
                    $args['tax_query'] = array(
                        array(
                            'taxonomy' => 'product_visibility',
                            'field'    => 'name',
                            'terms'    => 'featured',
                        )
                    );
                } else {
                    // Latest products
                    $args['orderby'] = 'date';
                    $args['order'] = 'DESC';
                }
                
                $products_query = new WP_Query($args);
                
                if ($products_query->have_posts()) :
                    while ($products_query->have_posts()) : 
                        $products_query->the_post();
                        wc_get_template_part('content', 'product');
                    endwhile;
                    wp_reset_postdata();
                else :
                    echo '<p class="no-products">' . esc_html__('Nema proizvoda za prikaz.', 'dreampoint-b2b') . '</p>';
                endif;
                ?>
                
            </div>
            
            <!-- Loading Spinner -->
            <div class="products-tabs-block__loader" style="display: none;">
                <div class="spinner"></div>
            </div>
        </div>
        
    </div>
</div>

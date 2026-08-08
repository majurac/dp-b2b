<?php
/**
 * The template for displaying product content within loops
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/content-product.php.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.4.0
 */

defined('ABSPATH') || exit;

global $product;

// Check if the product is a valid WooCommerce product and ensure its visibility before proceeding
if (!is_a($product, WC_Product::class) || !$product->is_visible()) {
    return;
}

// Product data
$product_id = $product->get_id();
$product_name = $product->get_name();
$sku = $product->get_sku();
$out_of_stock = !$product->is_in_stock();
$is_variable = $product->is_type('variable');
$permalink = get_permalink($product_id);
$image_size = 'product-loop';
?>

<?php
if ( is_shop() || is_product_category() || is_tax('product_brand') ) :
?>
    <div class="col-md-4">
<?php else : ?>
    <div <?php wc_product_class('', $product); ?>>
<?php endif; ?>


    <div class="product-item <?php echo $out_of_stock ? 'out-of-stock' : ''; ?> <?php echo $is_variable ? 'variable' : ''; ?>" itemscope itemtype="http://schema.org/Product">
        
        <!-- Hidden meta for Schema.org -->
        <meta itemprop="name" content="<?php echo esc_attr($product_name); ?>">
        <meta itemprop="sku" content="<?php echo esc_attr($sku); ?>">
        <?php if ($product->get_price()) : ?>
            <meta itemprop="price" content="<?php echo esc_attr($product->get_price()); ?>">
            <meta itemprop="priceCurrency" content="<?php echo esc_attr(get_woocommerce_currency()); ?>">
        <?php endif; ?>
        
        <?php 
        /**
         * Product ribbons (New/Discount)
         * Custom function from theme
         */
        if (function_exists('display_new_product_ribbon_and_discount')) {
            display_new_product_ribbon_and_discount();
        }
        ?>
        
        <!-- Product Image -->
        <div class="photo-holder">
     

            <?php if ( has_post_thumbnail() ) : ?>

                <a href="<?php echo esc_url($permalink); ?>" 
                   aria-label="<?php echo esc_attr($product_name); ?>"
                   title="<?php echo esc_attr($product_name); ?>">

                    <?php the_post_thumbnail( $image_size, [
                        'alt'     => esc_attr($product_name),
                        'loading' => 'lazy',
                    ] ); ?>

                </a>

            <?php else : 

                // Placeholder image
                $placeholder_url = wc_placeholder_img_src('woocommerce_thumbnail');
            ?>

                <a href="<?php echo esc_url($permalink); ?>" 
                   aria-label="<?php echo esc_attr($product_name); ?>"
                   title="<?php echo esc_attr($product_name); ?>">

                    <img 
                        src="<?php echo esc_url($placeholder_url); ?>" 
                        alt="<?php echo esc_attr($product_name); ?>" 
                        class="placeholder-img"
                        width="340" 
                        height="462"
                        loading="lazy"
                    />

                </a>

            <?php endif; ?>
        </div>
        <!-- /.photo-holder -->

        <!-- Product Info -->
        <div class="content-holder">
            <a 
                href="<?php echo esc_url($permalink); ?>" 
                class="product-title-link"
                aria-label="<?php echo esc_attr($product_name); ?>"
                title="<?php echo esc_attr($product_name); ?>"
            >
                <span class="product-title" itemprop="name">
                    <?php echo esc_html($product_name); ?>
                </span>
            </a>

            <div class="price-action">
                <!-- Action Buttons (Add to Cart / Wishlist / Inquiry) -->
                <div class="action-holder">
                    <?php 
                    /**
                     * Add to Cart Button Logic
                     * Different buttons for variable/simple/out-of-stock products
                     */
                    if ($out_of_stock) : 
                        // Out of stock - View product link
                    ?>
                        <a 
                            href="<?php echo esc_url($permalink); ?>" 
                            class="add-cart-variable categories-btn out-of-stock-btn" 
                            aria-label="<?php echo esc_attr(sprintf(__('Proizvod %s nije na stanju', 'dreampoint-b2b'), $product_name)); ?>"
                            title="<?php echo esc_attr(__('Nije na stanju', 'dreampoint-b2b')); ?>"
                        >
                            <span class="hide-sm"><?php esc_html_e('Nije na stanju', 'dreampoint-b2b'); ?></span> 
                            <i class="icon-chevron-right" aria-hidden="true"></i>
                        </a>
                        
                    <?php elseif ($is_variable) : 
                        // Variable product - View options link
                    ?>
                        <a 
                            href="<?php echo esc_url($permalink); ?>" 
                            class="add-cart-variable categories-btn" 
                            aria-label="<?php echo esc_attr(sprintf(__('Pogledaj opcije za %s', 'dreampoint-b2b'), $product_name)); ?>"
                            title="<?php echo esc_attr(__('Pogledaj opcije', 'dreampoint-b2b')); ?>"
                        >
                            <span class="hide-sm"><?php esc_html_e('Pogledaj opcije', 'dreampoint-b2b'); ?></span> 
                            <i class="icon-chevron-right hide-lg" aria-hidden="true"></i>
                        </a>
                        
                    <?php else : 
                        // Simple product - AJAX add to cart
                    ?>
                        <a 
                            href="<?php echo esc_url($product->add_to_cart_url()); ?>" 
                            class="ajax_add_to_cart add_to_cart_button add-to-cart categories-btn" 
                            data-product_id="<?php echo esc_attr($product_id); ?>" 
                            data-product_sku="<?php echo esc_attr($sku); ?>" 
                            data-quantity="1"
                            aria-label="<?php echo esc_attr(sprintf(__('Dodaj %s u košaricu', 'dreampoint-b2b'), $product_name)); ?>"
                            title="<?php echo esc_attr(__('Dodaj u košaricu', 'dreampoint-b2b')); ?>"
                            rel="nofollow"
                        >
                            <span class="hide-sm" style="display: none;"><?php esc_html_e('Dodaj u košaricu', 'dreampoint-b2b'); ?></span>
                            <span class="add_to_cart_text screen-reader-text"><?php esc_html_e('Dodaj u košaricu', 'dreampoint-b2b'); ?></span>
                            <i class="icon-shopping-bag" aria-hidden="true"></i>
                        </a>
                    <?php endif; ?>

                    <!-- Wishlist Button -->
                    <?php if (function_exists('tinv_get_option')) : ?>
                        <div class="add-to-fav">
                            <?php 
                            echo do_shortcode('[ti_wishlists_addtowishlist product_id="' . absint($product_id) . '"]'); 
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- /.action-holder --> 
                 <div class="price-holder">
                    <?php if ($product->get_price() && $product->is_in_stock()) : ?>
                        <span class="price-container <?php echo $product->is_on_sale() ? 'onsale' : ''; ?>" itemprop="offers" itemscope itemtype="http://schema.org/Offer">
                            <?php echo wp_kses_post($product->get_price_html()); ?>
                            <meta itemprop="availability" content="https://schema.org/InStock">
                        </span>
                    <?php elseif (!$product->is_in_stock()) : ?>
                        <span class="price-container out-of-stock-price" itemprop="offers" itemscope itemtype="http://schema.org/Offer">
                            <span class="out-of-stock-label"><?php esc_html_e('Nema na stanju', 'dreampoint-b2b'); ?></span>
                            <meta itemprop="availability" content="https://schema.org/OutOfStock">
                        </span>
                    <?php endif; ?>
                 </div>
                 <!-- /.price-holder --> 
            </div>
            <!-- /.price-action -->
            
        </div>
        <!-- /.content-holder -->

        <!-- Accessible link overlay (for screen readers) -->
        <a 
            href="<?php echo esc_url($permalink); ?>" 
            class="url-wrapper" 
            aria-label="<?php echo esc_attr(sprintf(__('Pogledaj proizvod %s', 'dreampoint-b2b'), $product_name)); ?>"
            title="<?php echo esc_attr(sprintf(__('Pogledaj proizvod %s', 'dreampoint-b2b'), $product_name)); ?>"
            tabindex="-1"
        >
            <span class="screen-reader-text">
                <?php echo esc_html(sprintf(__('Pogledaj proizvod %s', 'dreampoint-b2b'), $product_name)); ?>
            </span>
        </a>
    </div>
    <!-- /.product-item -->
    
</div>
<!-- /.col or wc_product_class -->

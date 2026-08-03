<?php
/**
 * The template for displaying product content in the single-product.php template
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/content-single-product.php.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.6.0
 */

defined('ABSPATH') || exit;

global $product;

/**
 * Hook: woocommerce_before_single_product.
 *
 * @hooked woocommerce_output_all_notices - 10
 */
do_action('woocommerce_before_single_product');

if (post_password_required()) {
    echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    return;
}

// Cache product data
$product_id = $product->get_id();
$product_name = $product->get_name();
$sku = $product->get_sku();
$is_variable = $product->is_type('variable');
$is_in_stock = $product->is_in_stock();
$permalink = get_permalink($product_id);
?>

<div id="product-single" class="block" itemscope itemtype="http://schema.org/Product">
    
    <!-- Hidden Schema.org markup -->
    <meta itemprop="name" content="<?php echo esc_attr($product_name); ?>">
    <meta itemprop="sku" content="<?php echo esc_attr($sku); ?>">
    <?php if ($product->get_price()) : ?>
        <meta itemprop="price" content="<?php echo esc_attr($product->get_price()); ?>">
        <meta itemprop="priceCurrency" content="<?php echo esc_attr(get_woocommerce_currency()); ?>">
    <?php endif; ?>
    
    <div id="product-single-main">
        <div class="container">
            <div class="product-main-info">
                <div class="row pos-relative">
                    
                    <!-- Product Images Column -->
                    <div class="col-md-6 pos-sticky">
                        <?php
                        // Get main image
                        $main_image_id = $product->get_image_id();
                        $main_full_url = $main_image_id ? wp_get_attachment_url($main_image_id) : wc_placeholder_img_src('large');
                        
                        // Get gallery images (excluding main/featured image)
                        $attachment_ids = $product->get_gallery_image_ids();
                        
                        // Remove main image from gallery if it exists there
                        if ($main_image_id && !empty($attachment_ids)) {
                            $attachment_ids = array_diff($attachment_ids, [$main_image_id]);
                        }
                        
                        // Add variation images for variable products
                        if ($is_variable) {
                            foreach ($product->get_children() as $variation_id) {
                                $variation = wc_get_product($variation_id);
                                if ($variation) {
                                    $variation_img_id = $variation->get_image_id();
                                    
                                    // Only add if:
                                    // 1. Variation has an image
                                    // 2. It's different from main/featured image (loose comparison for type safety)
                                    // 3. It's not already in the gallery
                                    if ($variation_img_id && 
                                        $variation_img_id != $main_image_id && 
                                        !in_array($variation_img_id, $attachment_ids)) {
                                        $attachment_ids[] = $variation_img_id;
                                    }
                                }
                            }
                        }
                        
                        // Final cleanup - ensure no duplicates
                        $attachment_ids = array_unique($attachment_ids);
                        ?>
                        
                        <div class="product-slider" id="product-single-main">
                            <?php 
                            if (function_exists('display_product_brand')) {
                                display_product_brand();
                            }
                            ?>
                            
                            <!-- Main Slider -->
                            <div class="slider-for" itemprop="image" itemscope itemtype="http://schema.org/ImageObject">
                                <!-- Main Image -->
                                <div>
                                    <a 
                                        href="<?php echo esc_url($main_full_url); ?>" 
                                        data-fancybox="gallery" 
                                        aria-label="<?php echo esc_attr(sprintf(__('Uvećaj sliku proizvoda %s', 'dreampoint-b2b'), $product_name)); ?>"
                                    >
                                        <img
                                            src="<?php echo esc_url($main_full_url); ?>"
                                            data-attachment-id="<?php echo esc_attr($main_image_id); ?>"
                                            data-full-src="<?php echo esc_url($main_full_url); ?>"
                                            alt="<?php echo esc_attr($product_name); ?>"
                                            itemprop="contentUrl"
                                            loading="eager"
                                        />
                                        <i class="icon-magnifying-glass" aria-hidden="true"></i>
                                    </a>
                                </div>

                                <?php if (!empty($attachment_ids)) : ?>
                                    <?php foreach ($attachment_ids as $attachment_id) : 
                                        $image_full = wp_get_attachment_url($attachment_id);
                                        $image_thumb = wp_get_attachment_image_url($attachment_id, 'shop_thumbnail');
                                        $image_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                                    ?>
                                        <div>
                                            <a 
                                                href="<?php echo esc_url($image_full); ?>" 
                                                data-fancybox="gallery"
                                                aria-label="<?php echo esc_attr(sprintf(__('Uvećaj sliku %s', 'dreampoint-b2b'), $product_name)); ?>"
                                            >
                                                <img
                                                    src="<?php echo esc_url($image_thumb ?: $image_full); ?>"
                                                    data-attachment-id="<?php echo esc_attr($attachment_id); ?>"
                                                    data-full-src="<?php echo esc_url($image_full); ?>"
                                                    alt="<?php echo esc_attr($image_alt ?: $product_name); ?>"
                                                    loading="lazy"
                                                />
                                                <i class="icon-magnifying-glass" aria-hidden="true"></i>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Thumbnail Navigation -->
                            <div class="slider-nav">
                                <!-- Main Thumbnail -->
                                <div class="thumb">
                                    <img
                                        src="<?php echo esc_url(wp_get_attachment_image_url($main_image_id, 'shop_thumbnail') ?: $main_full_url); ?>"
                                        data-attachment-id="<?php echo esc_attr($main_image_id); ?>"
                                        data-full-src="<?php echo esc_url($main_full_url); ?>"
                                        alt="<?php echo esc_attr($product_name); ?>"
                                        loading="eager"
                                    />
                                </div>

                                <?php if (!empty($attachment_ids)) : ?>
                                    <?php foreach ($attachment_ids as $attachment_id) :
                                        $image_thumb = wp_get_attachment_image_url($attachment_id, 'shop_thumbnail');
                                        $image_full = wp_get_attachment_url($attachment_id);
                                        $image_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                                    ?>
                                        <div class="thumb">
                                            <img
                                                src="<?php echo esc_url($image_thumb ?: $image_full); ?>"
                                                data-attachment-id="<?php echo esc_attr($attachment_id); ?>"
                                                data-full-src="<?php echo esc_url($image_full); ?>"
                                                alt="<?php echo esc_attr($image_alt ?: $product_name); ?>"
                                                loading="lazy"
                                            />
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <!-- /.col-md-6 -->
                    
                    <!-- Product Info Column -->
                    <div class="col-md-6">
                        <div class="product-summary <?php echo !$is_in_stock ? 'out-of-stock' : ''; ?>">
                            
                            <?php if ($sku) : ?>
                                <span class="product-sku">
                                    <?php esc_html_e('Šifra artikla', 'dreampoint-b2b'); ?>: 
                                    <span itemprop="sku"><?php echo esc_html($sku); ?></span>
                                </span>
                            <?php endif; ?>
                            
                            <h1 itemprop="name"><?php echo esc_html($product_name); ?></h1>
                            
                            <?php if ($product->get_price() && $is_in_stock) : ?>
                                <span class="price price-container <?php echo $product->is_on_sale() ? 'onsale' : ''; ?>" itemprop="offers" itemscope itemtype="http://schema.org/Offer">
                                    <?php echo wp_kses_post($product->get_price_html()); ?>
                                    <meta itemprop="availability" content="https://schema.org/InStock">
                                    <meta itemprop="url" content="<?php echo esc_url($permalink); ?>">
                                </span>
                            <?php endif; ?>

                            <div class="short-description" itemprop="description">
                               <?php the_excerpt(); ?>
                            </div>
                            
                            <div class="stock-status-wrapper"></div>

                            <?php
                            /**
                             * Stock Status Display
                             */
                            if ($is_variable) {
                                // Variable product - check if any variation is in stock
                                $has_in_stock_variation = false;
                                foreach ($product->get_available_variations() as $variation) {
                                    if (!empty($variation['is_in_stock'])) {
                                        $has_in_stock_variation = true;
                                        break;
                                    }
                                }
                                
                                if ($has_in_stock_variation) {
                                    echo '<p class="stock-status in-stock">' . esc_html__('Proizvod na stanju', 'dreampoint-b2b') . '</p>';
                                } else {
                                    echo '<p class="stock-status out-of-stock">' . esc_html__('Proizvod nije na stanju', 'dreampoint-b2b') . '</p>';
                                }
                            } else {
                                // Simple product
                                if ($is_in_stock) {
                                    echo '<p class="stock-status in-stock">' . esc_html__('Proizvod na stanju', 'dreampoint-b2b') . '</p>';
                                } else {
                                    echo '<p class="stock-status out-of-stock">' . esc_html__('Proizvod nije na stanju', 'dreampoint-b2b') . '</p>';
                                }
                            }
                            ?>
                            
                            <?php woocommerce_template_single_add_to_cart(); ?>

                            <a
                                href="<?php echo esc_url(home_url('/product-inquiry/' . $product_id . '/')); ?>"
                                class="product-inquiry-btn"
                                data-fancybox
                                data-type="iframe"
                                data-product-id="<?php echo esc_attr($product_id); ?>"
                                data-product-name="<?php echo esc_attr($product_name); ?>"
                                aria-label="<?php echo esc_attr(sprintf(__('Pošaljite upit za proizvod %s', 'dreampoint-b2b'), $product_name)); ?>"
                                title="<?php echo esc_attr(__('Pošaljite upit', 'dreampoint-b2b')); ?>"
                            >
                                <i class="icon-envelope" aria-hidden="true"></i>
                                <?php esc_html_e('Pošaljite upit', 'dreampoint-b2b'); ?>
                            </a>

                            <!-- Social Share -->
                            <div class="share">
                                <span class="share-label"><?php esc_html_e('Podijeli:', 'dreampoint-b2b'); ?></span>
                                <ul class="icons">
                                    <li class="icons__item">
                                        <a 
                                            class="icon" 
                                            href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($permalink); ?>" 
                                            target="_blank" 
                                            rel="noopener noreferrer"
                                            aria-label="<?php esc_attr_e('Podijeli na Facebooku', 'dreampoint-b2b'); ?>"
                                        >
                                            <i class="icon-facebook" aria-hidden="true"></i>
                                        </a>
                                    </li>
                                    <li class="icons__item">
                                        <a 
                                            class="icon" 
                                            href="https://wa.me/?text=<?php echo urlencode($product_name . ' ' . $permalink); ?>" 
                                            target="_blank" 
                                            rel="noopener noreferrer"
                                            aria-label="<?php esc_attr_e('Podijeli na WhatsApp', 'dreampoint-b2b'); ?>"
                                        >
                                            <i class="icon-whatsapp" aria-hidden="true"></i>
                                        </a>
                                    </li>
                                    <li class="icons__item">
                                        <a 
                                            class="icon" 
                                            href="viber://forward?text=<?php echo urlencode($product_name . ' ' . $permalink); ?>" 
                                            target="_blank" 
                                            rel="noopener noreferrer"
                                            aria-label="<?php esc_attr_e('Podijeli na Viber', 'dreampoint-b2b'); ?>"
                                        >
                                            <i class="icon-viber" aria-hidden="true"></i>
                                        </a>
                                    </li>
                                    <li class="icons__item">
                                        <a 
                                            class="icon" 
                                            href="https://twitter.com/intent/tweet?text=<?php echo urlencode($product_name . ' ' . $permalink); ?>" 
                                            target="_blank" 
                                            rel="noopener noreferrer"
                                            aria-label="<?php esc_attr_e('Podijeli na Twitter', 'dreampoint-b2b'); ?>"
                                        >
                                            <i class="icon-twitter" aria-hidden="true"></i>
                                        </a>
                                    </li>
                                    <li class="icons__item">
                                        <a 
                                            class="icon" 
                                            href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode($permalink); ?>&title=<?php echo urlencode($product_name); ?>" 
                                            target="_blank" 
                                            rel="noopener noreferrer"
                                            aria-label="<?php esc_attr_e('Podijeli na LinkedIn', 'dreampoint-b2b'); ?>"
                                        >
                                            <i class="icon-linkedin" aria-hidden="true"></i>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <!-- /.share -->
                        </div>
                        <!-- /.product-summary -->
                    </div>
                    <!-- /.col-md-6 -->
                </div>
                <!-- /.row -->
                
                <!-- Product Tabs -->
                <div class="wc-tabs-section block" style="display: none;">
                    <?php woocommerce_output_product_data_tabs(); ?>
                </div>
            </div>
            <!-- /.product-main-info -->
        </div>
        <!-- /.container -->
    </div>
    <!-- /#product-single-main -->

    <?php echo do_shortcode('[product_features]'); ?>
    
    <!-- Recommended Products -->
    <div class="products-row-area slider-grid block" id="recomended-products">
        <div class="container">
            <div class="section-heading">
                <h2><?php esc_html_e('Predloženo za vas', 'dreampoint-b2b'); ?></h2>
            </div>
            
            <div id="recomended-products-list">
                <div class="list-grid recommended-slider">
                    <?php
                    $upsell_ids = $product->get_upsell_ids();
                    
                    if (!empty($upsell_ids)) {
                        // Display upsell products
                        $args = [
                            'post_type'      => 'product',
                            'post__in'       => $upsell_ids,
                            'posts_per_page' => -1,
                            'orderby'        => 'post__in',
                            'post_status'    => 'publish',
                        ];
                        
                        $query = new WP_Query($args);
                        
                        if ($query->have_posts()) {
                            while ($query->have_posts()) {
                                $query->the_post();
                                wc_get_template_part('content', 'product');
                            }
                        }
                        
                        wp_reset_postdata();
                        
                    } else {
                        // Fallback: Products from same category
                        $categories = get_the_terms($product_id, 'product_cat');
                        $category_id = (!empty($categories) && !is_wp_error($categories)) ? $categories[0]->term_id : 0;
                        
                        if ($category_id) {
                            $args = [
                                'post_type'      => 'product',
                                'posts_per_page' => 4,
                                'post__not_in'   => [$product_id],
                                'post_status'    => 'publish',
                                'tax_query'      => [
                                    [
                                        'taxonomy' => 'product_cat',
                                        'field'    => 'term_id',
                                        'terms'    => $category_id,
                                    ],
                                ],
                            ];
                            
                            $query = new WP_Query($args);
                            
                            if ($query->have_posts()) {
                                while ($query->have_posts()) {
                                    $query->the_post();
                                    wc_get_template_part('content', 'product');
                                }
                            } else {
                                echo '<div class="col-md-12"><p>' . esc_html__('Trenutno nemamo povezanih proizvoda', 'dreampoint-b2b') . '</p></div>';
                            }
                            
                            wp_reset_postdata();
                        } else {
                            echo '<div class="col-md-12"><p>' . esc_html__('Trenutno nemamo povezanih proizvoda', 'dreampoint-b2b') . '</p></div>';
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    <!-- /.products-row-area -->
</div>
<!-- /#product-single -->


<style>.woocommerce-breadcrumb:not(.inner-page .inner-heading .woocommerce-breadcrumb) {
    display: none;
}</style>
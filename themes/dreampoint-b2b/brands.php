<?php
/**
 * Template name: Brands
 *
 * This is the template that displays Brands page.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site may use a
 * different template.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Dreampoint_B2B
 */

get_header();
?>

<div id="brands-page" class="block">
    <div class="inner-content">
        <div class="container">
            <?php
            // Get all brands
            $all_brands = get_terms(array(
                'taxonomy'   => 'product_brand', // Replace 'product_brand' with your actual brand taxonomy slug if it's different
                'hide_empty' => false,           // Set to true if you only want to show brands with products
            ));

            if (!empty($all_brands) && !is_wp_error($all_brands)) :
                echo '<div class="brands-list"><div class="row">'; // Keep your existing wrapper class

                foreach ($all_brands as $brand) :
                    $link = get_term_link($brand);

                    // Get logo from WooCommerce Brands plugin (if used)
                    $logo_id = get_term_meta($brand->term_id, 'thumbnail_id', true);
                    $logo_url = $logo_id ? wp_get_attachment_url($logo_id) : '';

                    // Get ACF image field (if used)
                    $acf_image = get_field('brand_image', 'product_brand_' . $brand->term_id); // 'product_brand_' should match your taxonomy slug
                    $acf_image_id = is_array($acf_image) && isset($acf_image['ID']) ? $acf_image['ID'] : 0;

                    // Use the defined custom image size name
                    $acf_image_url = $acf_image_id ? wp_get_attachment_image_url($acf_image_id, 'brand-all-page') : '';
                    ?>

                    <div class="col">
                        <div class="brand-item">
                            <a href="<?php echo esc_url($link); ?>">
                                <?php if ($logo_url) : ?>
                                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($brand->name); ?>" class="brand-logo">
                                    <?php else : 
                                    // Get default placeholder and replace 150x150 with 384x282
                                    $placeholder_url = str_replace( '150x150', '384x282', wc_placeholder_img_src() ); 
                                ?>
                                    <img src="<?php echo esc_url( $placeholder_url ); ?>" 
                                         width="384" height="282" 
                                         alt="<?php echo esc_attr( get_the_title() ); ?>"  class="brand-image" />
                                <?php endif; ?>
                                <?php if ($acf_image_url) : ?>
                                    <img src="<?php echo esc_url($acf_image_url); ?>" alt="<?php echo esc_attr($brand->name); ?>" class="brand-image">
                                <?php endif; ?>
                            </a>
                        </div>
                    </div>
                    <?php
                endforeach;
                echo '</div></div>';
            endif;
            ?>
        </div>
    </div>
</div>
<?php get_footer(); ?>
<?php
if (!defined('ABSPATH')) {
    exit;
}

$main_categories = get_field('main_categories');

if (empty($main_categories)) {
    return;
}
?>
<div class="featured-categories block">
    <div class="container">
        <div class="featured-categories-slider">
            <?php 
                if ($main_categories) : 
                    foreach ($main_categories as $main_category) : 
                        $image            = $main_category['category_image'] ?? null;
                        $button_text      = $main_category['button_text'] ?? __('Pogledaj proizvode', 'dreampoint-b2b');
                        $show_custom_link = $main_category['show_custom_link'] ?? false;
                        $custom_link      = $main_category['custom_url'] ?? null;
                        $term_field       = $main_category['category_url'] ?? null;
            
                        // Determine final link based on show_custom_link toggle
                        $final_link = null;
                        $link_target = '_self';
                        
                        if ($show_custom_link && !empty($custom_link)) {
                            // Use custom link if toggle is ON
                            $final_link = $custom_link['url'] ?? null;
                            $link_target = $custom_link['target'] ?? '_self';
                            $button_text = !empty($custom_link['title']) ? $custom_link['title'] : $button_text;
                        } elseif (!$show_custom_link && !empty($term_field)) {
                            // Use category link if toggle is OFF
                            $term_ids = is_array($term_field) ? $term_field : [$term_field];
                            $term_id = $term_ids[0] ?? null;
                            
                            if ($term_id) {
                                $term_link = get_term_link($term_id, 'product_cat');
                                if (!is_wp_error($term_link)) {
                                    $final_link = $term_link;
                                }
                            }
                        }
                        
                        // Skip if no valid link
                        if (empty($final_link)) continue;
                        ?>
                        
                        <div class="category-box">
                            <?php if (!empty($image)) : ?>
                                <?php
                                // Use wp_get_attachment_image if we have an attachment ID.
                                // This serves the 'category-slider' size (800x450, registered in functions.php)
                                // and automatically generates a srcset for responsive images,
                                // saving ~337 KiB vs. serving the full-resolution original.
                                // Falls back to the original URL if no ID is available.
                                if (!empty($image['id'])) :
                                    echo wp_get_attachment_image(
                                        $image['id'],
                                        'category-slider',
                                        false,
                                        [
                                            'class'   => 'category-image',
                                            'alt'     => esc_attr($image['alt'] ?? ''),
                                            'loading' => 'lazy',
                                            'decoding' => 'async',
                                        ]
                                    );
                                else :
                                    // Fallback: no attachment ID (e.g. external URL in ACF)
                                ?>
                                    <img
                                        src="<?php echo esc_url($image['url']); ?>"
                                        alt="<?php echo esc_attr($image['alt'] ?? ''); ?>"
                                        class="category-image"
                                        width="<?php echo absint($image['width'] ?? 460); ?>"
                                        height="<?php echo absint($image['height'] ?? 307); ?>"
                                        loading="lazy"
                                        decoding="async"
                                    >
                                <?php endif; ?>
                            <?php endif; ?>

                            <span class="categories-btn">
                                <?php echo esc_html($button_text); ?>
                            </span>
                            
                            
                            <a href="<?php echo esc_url($final_link); ?>" 
                                class="url-wrapper" 
                                target="<?php echo esc_attr($link_target); ?>"
                                aria-label="<?php echo esc_attr($button_text); ?>">
                            </a>
                        </div>
                        
                    <?php 
                    endforeach; 
                endif; 
            ?>
        </div>
        <!-- /.featured-categores -->
    </div>
    <!-- /.container -->
</div>
<!-- /.featured-categories block -->
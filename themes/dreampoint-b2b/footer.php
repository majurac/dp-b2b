<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package Dreampoint_B2B
 * @version 1.1.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// ============================================================================
// GET ACF FIELDS (with fallbacks)
// ============================================================================

// Newsletter fields
$subscribe_title = get_field('subscribe_title', 'option') ?: '';
$subscribe_text = get_field('subscribe_text', 'option') ?: '';
$subscribe_form_id = get_field('subscribe_form_id', 'option');

// Company info
$company_logo = get_field('logo', 'option');
$company_about = get_field('company_about', 'option') ?: '';
$company_phone = get_field('company_phone', 'option') ?: '';
$company_email = get_field('company_email', 'option') ?: '';
$company_address = get_field('company_address', 'option') ?: '';

// Footer structure
$footer_sitemaps = get_field('footer_sitemaps', 'option') ?: [];
$socials = get_field('socials', 'option') ?: [];
$cards = get_field('cards', 'option') ?: [];
$copyright = get_field('copyright', 'option') ?: '';

?>

    </div>
    <!-- /.inner-page -->

<?php
/**
 * Don't show footer on account login page
 */
if (is_account_page() && !is_user_logged_in()) {
    ?>
    </div><!-- #page -->
    <?php wp_footer(); ?>
    </body>
    </html>
    <?php
    return;
}
?>

<?php
/**
 * Newsletter Section
 * Hidden on cart, checkout, and account pages
 */
if (!is_cart() && !is_checkout() && !is_account_page() && !dreampoint_b2b_is_quick_order_page()) :
?>
    <div class="newsletter">
        <div class="container">
            <div class="newsletter-in">
                <?php if ($subscribe_title || $subscribe_text) : ?>
                    <div class="subscription-text">
                        <?php if ($subscribe_title) : ?>
                            <h3><?php echo esc_html($subscribe_title); ?></h3>
                        <?php endif; ?>
                        
                        <?php if ($subscribe_text) : ?>
                            <p><?php echo esc_html($subscribe_text); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="subscription-form custom-form">
                    <?php
                    // Safely render MC4WP form
                    if ($subscribe_form_id && is_numeric($subscribe_form_id)) {
                        echo do_shortcode('[mc4wp_form id="' . absint($subscribe_form_id) . '"]');
                    } else {
                        // Fallback if no form ID set
                        if (current_user_can('edit_theme_options')) {
                            echo '<p class="admin-notice">' . 
                                 esc_html__('Newsletter form ID nije postavljen u Theme Options.', 'dreampoint-b2b') . 
                                 '</p>';
                        }
                    }
                    ?>
                </div>
                <!-- /.subscription-form -->
            </div>
            <!-- /.newsletter-in -->
        </div>
        <!-- /.container -->
    </div>
    <!-- /.newsletter -->
<?php endif; ?>

<footer class="page-footer" role="contentinfo" itemscope itemtype="http://schema.org/WPFooter">
    <div class="container">
        <div class="main-footer">
            <div class="row">
                
                <!-- Company Info Column -->
                <div class="col">
                    <div class="footer-about">
                        <?php if (!empty($company_logo['url'])) : ?>
                            <div class="footer-logo">
                                <a href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>">
                                    <img 
                                        src="<?php echo esc_url($company_logo['url']); ?>" 
                                        alt="<?php echo esc_attr($company_logo['alt'] ?: get_bloginfo('name') . ' Logo'); ?>"
                                        width="<?php echo isset($company_logo['width']) ? absint($company_logo['width']) : ''; ?>"
                                        height="<?php echo isset($company_logo['height']) ? absint($company_logo['height']) : ''; ?>"
                                        loading="lazy"
                                    >
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($company_about) : ?>
                            <div class="company-description">
                                <p><?php echo esc_html($company_about); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($socials) && is_array($socials)) : ?>
                            <div class="socials">
                                <ul>
                                    <?php foreach ($socials as $social) : 
                                        $social_url = $social['social_url'] ?? '';
                                        $social_title = $social['social_title'] ?? '';
                                        
                                        // Skip if no URL
                                        if (empty($social_url)) {
                                            continue;
                                        }
                                    ?>
                                        <li>
                                            <a 
                                                href="<?php echo esc_url($social_url); ?>" 
                                                target="_blank" 
                                                rel="noopener noreferrer" 
                                                aria-label="<?php echo esc_attr($social_title ?: 'Social media'); ?>"
                                            >
                                                <i class="icon-<?php echo esc_attr(sanitize_html_class($social_title ?: 'social')); ?>"></i>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- /.footer-about -->
                </div>
                <!-- /.col -->
                
                <?php
                /**
                 * Footer Sitemap Columns
                 * Dynamic menu columns from ACF
                 */
                if (!empty($footer_sitemaps) && is_array($footer_sitemaps)) :
                    foreach ($footer_sitemaps as $footer_sitemap) : 
                        $sitemap_title = $footer_sitemap['sitemap_title'] ?? '';
                        $sitemap_items = $footer_sitemap['sitemap_items'] ?? [];
                        
                        // Skip if no title or items
                        if (empty($sitemap_title) || empty($sitemap_items)) {
                            continue;
                        }
                ?>
                    <div class="col">
                        <div class="footer-sitemap">
                            <span class="footer-title"><?php echo esc_html($sitemap_title); ?></span>
                            <ul>
                                <?php 
                                foreach ($sitemap_items as $sitemap_item) : 
                                    $link_data = $sitemap_item['sitemap_item'] ?? null;
                                    
                                    // Skip if link data invalid
                                    if (empty($link_data['url']) || empty($link_data['title'])) {
                                        continue;
                                    }
                                ?>
                                    <li>
                                        <?php 
                                        // Use helper function if available, otherwise manual output
                                        if (function_exists('the_acf_link')) {
                                            the_acf_link($link_data);
                                        } else {
                                            printf(
                                                '<a href="%s" target="%s">%s</a>',
                                                esc_url($link_data['url']),
                                                esc_attr($link_data['target'] ?: '_self'),
                                                esc_html($link_data['title'])
                                            );
                                        }
                                        ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <!-- /.footer-sitemap -->
                    </div>
                    <!-- /.col -->
                <?php 
                    endforeach; 
                endif; 
                ?>
                
                <!-- Contact Column -->
                <div class="col">
                    <div class="footer-block">
                        <span class="footer-title"><?php esc_html_e('Kontaktirajte nas', 'dreampoint-b2b'); ?></span>
                        
                        <?php if ($company_email) : ?>
                            <p>
                                <span><?php esc_html_e('Pišite nam na email', 'dreampoint-b2b'); ?>: </span>
                                <a href="mailto:<?php echo esc_attr($company_email); ?>" aria-label="<?php echo esc_attr(sprintf(__('Pošalji email na %s', 'dreampoint-b2b'), $company_email)); ?>">
                                    <?php echo esc_html($company_email); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                        
                        <?php if ($company_phone) : ?>
                            <p>
                                <span><?php esc_html_e('Pozovite nas', 'dreampoint-b2b'); ?>: </span>
                                <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $company_phone)); ?>" aria-label="<?php echo esc_attr(sprintf(__('Pozovi %s', 'dreampoint-b2b'), $company_phone)); ?>">
                                    <?php echo esc_html($company_phone); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                        
                        <?php 
                        // Optional: Display address
                        if ($company_address) : 
                        ?>
                            <p>
                                <span><?php esc_html_e('Adresa', 'dreampoint-b2b'); ?>: </span>
                                <?php echo esc_html($company_address); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <!-- /.footer-block -->
                </div>
                <!-- /.col -->
                
            </div>
            <!-- /.row -->
        </div>
        <!-- /.main-footer -->
        
        <!-- Copyright Area -->
        <div class="copyright-area">
            <div class="row">
                <div class="col-lg-5">
                    <?php if (!empty($cards) && is_array($cards)) : ?>
                        <div class="credit-cards">
                            <?php 
                            foreach ($cards as $card) : 
                                $card_image = $card['image'] ?? null;
                                $card_url = $card['url'] ?? '';
                                
                                // Skip if invalid image
                                if (empty($card_image['url'])) {
                                    continue;
                                }
                                
                                $img_tag = sprintf(
                                    '<img src="%s" alt="%s" width="%s" height="%s" loading="lazy">',
                                    esc_url($card_image['url']),
                                    esc_attr($card_image['alt'] ?: __('Sredstvo plaćanja', 'dreampoint-b2b')),
                                    isset($card_image['width']) ? absint($card_image['width']) : '',
                                    isset($card_image['height']) ? absint($card_image['height']) : ''
                                );
                                
                                // Wrap in link if URL exists
                                if (!empty($card_url)) : ?>
                                    <a href="<?php echo esc_url($card_url); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php echo $img_tag; ?>
                                    </a>
                                <?php else : ?>
                                    <?php echo $img_tag; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- /.col-lg-5 -->
                
                <div class="col-lg-7">
                    <div class="copyright">
                        © <?php echo esc_html(date_i18n('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>. 
                        <?php 
                        // Allow HTML in copyright (links, etc.) but sanitize
                        echo wp_kses_post($copyright); 
                        ?>
                    </div>
                    <!-- /.copyright -->
                </div>
                <!-- /.col-lg-7 -->
            </div>
            <!-- /.row -->
        </div>
        <!-- /.copyright-area -->
        
    </div>
    <!-- /.container -->
</footer>
<!-- /.page-footer -->

</div><!-- #page -->

<?php wp_footer(); ?>

</body>
</html>
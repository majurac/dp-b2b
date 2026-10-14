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
// FOOTER DATA (helpers: inc/footer.php). Two layouts: compact (front page) and full (everything else).
// ============================================================================

$footer_variant = dreampoint_b2b_footer_variant();
$is_compact     = 'compact' === $footer_variant;

// Newsletter fields (band renders only for a working Mailchimp for WP form)
$subscribe_title   = get_field('subscribe_title', 'option') ?: '';
$subscribe_text    = get_field('subscribe_text', 'option') ?: '';
$subscribe_form_id = get_field('subscribe_form_id', 'option');

$footer_socials  = dreampoint_b2b_footer_social_links( get_field('socials', 'option') );
$footer_columns  = $is_compact ? [] : dreampoint_b2b_footer_sitemap_columns( get_field('footer_sitemaps', 'option') );
$footer_legal    = dreampoint_b2b_footer_legal_links();
$footer_img_uri  = get_template_directory_uri() . '/img/footer/';

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
if (!is_cart() && !is_checkout() && !is_account_page() && !dreampoint_b2b_is_quick_order_page() && dreampoint_b2b_newsletter_ready($subscribe_form_id)) :
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
                    echo do_shortcode('[mc4wp_form id="' . absint($subscribe_form_id) . '"]');
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

<?php if ( ! dreampoint_b2b_is_quick_order_page() ) : ?>
<footer class="page-footer page-footer--<?php echo esc_attr( $footer_variant ); ?>" role="contentinfo" itemscope itemtype="http://schema.org/WPFooter">
    <div class="container">

        <div class="page-footer__top">
            <a class="page-footer__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
                <img src="<?php echo esc_url( $footer_img_uri . 'dreampoint-wordmark.svg' ); ?>" alt="" width="125" height="48">
            </a>

            <?php if ( ! $is_compact && has_nav_menu( 'footer-primary' ) ) : ?>
                <nav class="page-footer__nav" aria-label="<?php esc_attr_e( 'Podnožje – glavni linkovi', 'dreampoint-b2b' ); ?>">
                    <?php
                    wp_nav_menu(
                        [
                            'theme_location' => 'footer-primary',
                            'container'      => false,
                            'menu_class'     => 'page-footer__links',
                            'items_wrap'     => '<ul class="%2$s">%3$s</ul>',
                            'depth'          => 1,
                            'fallback_cb'    => false,
                        ]
                    );
                    ?>
                </nav>
            <?php endif; ?>

            <ul class="page-footer__socials">
                <?php foreach ( $footer_socials as $social ) : ?>
                    <li>
                        <?php if ( $social['url'] ) : ?>
                            <a class="page-footer__social" href="<?php echo esc_url( $social['url'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $social['label'] ); ?>">
                                <img src="<?php echo esc_url( $footer_img_uri . $social['svg'] ); ?>" alt="" width="24" height="24">
                            </a>
                        <?php else : ?>
                            <?php // No configured profile URL: decorative, non-interactive icon (no href, not focusable). ?>
                            <span class="page-footer__social" aria-hidden="true">
                                <img src="<?php echo esc_url( $footer_img_uri . $social['svg'] ); ?>" alt="" width="24" height="24">
                            </span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <!-- /.page-footer__top -->

        <?php if ( $footer_columns ) : ?>
            <div class="page-footer__columns">
                <?php foreach ( $footer_columns as $column ) : ?>
                    <nav class="page-footer__column" aria-label="<?php echo esc_attr( $column['title'] ); ?>">
                        <span class="page-footer__heading"><?php echo esc_html( $column['title'] ); ?></span>
                        <ul>
                            <?php foreach ( $column['links'] as $link ) : ?>
                                <li>
                                    <a href="<?php echo esc_url( $link['url'] ); ?>"<?php echo $link['target'] ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $link['title'] ); ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="page-footer__credits">
            <?php // Structure follows the corporate site (dream-point.hr .copyright-area): credits left, legal list right. Wording is fixed English by owner request. ?>
            <p class="page-footer__copyright">
                <?php echo esc_html( sprintf( '© %s Dreampoint. All rights reserved.', date_i18n( 'Y' ) ) ); ?>
                <br>
                Crafted with soul by <a href="https://uncledev.com/" target="_blank" rel="noopener noreferrer">Uncle Dev</a>
                <br>
                <small>Consulted by <a href="https://www.webroomagency.com/" target="_blank" rel="noopener noreferrer">webroom</a></small>
            </p>
            <ul class="page-footer__legal">
                <?php foreach ( $footer_legal as $legal ) : ?>
                    <li><a href="<?php echo esc_url( $legal['url'] ); ?>"><?php echo esc_html( $legal['label'] ); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>

    </div>
    <!-- /.container -->
</footer>
<!-- /.page-footer -->
<?php endif; ?>

</div><!-- #page -->

<?php wp_footer(); ?>

</body>
</html>
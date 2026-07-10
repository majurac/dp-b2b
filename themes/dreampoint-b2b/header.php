<?php
/**
 * The header for our theme
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package Dreampoint_B2B
 * @version 1.1.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// Get ACF logo with fallback
$company_logo = get_field('logo', 'option');
$company_phone = get_field('company_phone', 'option') ?: '';
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="https://gmpg.org/xfn/11">

    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div id="page" class="site">
    <a class="skip-link screen-reader-text" href="#primary-content"><?php esc_html_e('Preskoči na sadržaj', 'dreampoint-b2b'); ?></a>

    <?php
        $is_quick_order = dreampoint_b2b_is_quick_order_page();
    ?>
    <?php if ( $is_quick_order ) : ?>
        <header id="header" class="dp-qo-header" role="banner">
            <div class="dp-qo-header__inner">
                <div class="dp-qo-header__left">
                    <?php if ( ! empty( $company_logo['url'] ) ) : ?>
                        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="dp-qo-header__logo" aria-label="<?php esc_attr_e( 'Početna stranica', 'dreampoint-b2b' ); ?>">
                            <img
                                src="<?php echo esc_url( $company_logo['url'] ); ?>"
                                alt="<?php echo esc_attr( $company_logo['alt'] ?: get_bloginfo( 'name' ) . ' Logo' ); ?>"
                                width="<?php echo isset( $company_logo['width'] ) ? absint( $company_logo['width'] ) : ''; ?>"
                                height="<?php echo isset( $company_logo['height'] ) ? absint( $company_logo['height'] ) : ''; ?>"
                            >
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="dp-qo-header__back">
                        <i class="icon-arrow-left-long" aria-hidden="true"></i>
                        <span><?php esc_html_e( 'Povratak u katalog', 'dreampoint-b2b' ); ?></span>
                    </a>
                </div>
                <div class="dp-qo-header__center">
                    <?php esc_html_e( 'QUICK ORDER', 'dreampoint-b2b' ); ?>
                </div>
                <div class="dp-qo-header__right">
                    <?php
                    if ( function_exists( 'dreampoint_b2b_woocommerce_cart_link' ) ) {
                        dreampoint_b2b_woocommerce_cart_link();
                    }
                    ?>
                </div>
            </div>
        </header>
        <!-- Mini Cart (reused — cart icon above opens it, same as the main header) -->
        <div class="my-custom-mini-cart-container widget_shopping_cart_content" role="complementary" aria-label="<?php esc_attr_e( 'košarica', 'dreampoint-b2b' ); ?>">
            <?php
            if ( function_exists( 'woocommerce_mini_cart' ) ) {
                woocommerce_mini_cart();
            }
            ?>
        </div>
        <div class="inner-page inner-page--quick-order" id="primary-content">
    <?php elseif ( ! is_account_page() || is_user_logged_in() ) : ?>
        <style>@media (max-width: 767px) {body {padding-bottom: 72px;}}</style>
        <div class="menu-overlay" aria-hidden="true"></div>


    <?php
        // Check if notifications are enabled and exist
        $notifications_enabled = get_field('enable_notifications', 'option');
        $notifications = get_field('notifications', 'option');

        // Only display if enabled and has notifications
        if ($notifications_enabled && !empty($notifications) && is_array($notifications)):
        ?>
        <div class="notification-bar">
            <div class="container">
                <div class="notification-slider">
                    <?php foreach ($notifications as $notification): ?>
                        <?php 
                        // Security: Sanitize output and check if text exists
                        if (!empty($notification['notification_text'])): 
                            $notification_text = wp_kses_post($notification['notification_text']);
                        ?>
                        <div>
                            <p><?php echo $notification_text; ?></p>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <!-- /.notification-slider -->
            </div>
            <!-- /.container -->
        </div>
        <!-- /.notification-bar -->
        <?php endif; ?>
        <header id="header" role="banner">
            <?php if ( !wp_is_mobile() ) : ?>
            <div id="top-header" class="hide-md-sm">
                <div class="container">
                    <div class="row">
                        <div class="col">
                            <div class="main-navigation">
                                <?php wp_nav_menu(array(
                                    'menu' => 'Sitemap Menu',
                                    'container' => false,
                                    'menu_class' => 'links',
                                    'items_wrap' => '<ul class="%2$s">%3$s</ul>',
                                ));
                                ?>
                            </div>
                            <!-- /#navigation -->
                        </div>
                        <!-- /.col -->
                    </div>
                    <!-- /.row -->
                </div>
                <!-- /.container -->
            </div>
            <!-- /#top-header -->
            <?php endif; ?>
            <div id="main-header">
                <div class="container">
                    <div class="row">
                        <?php if (!empty($company_logo['url'])) : ?>
                            <div class="col-mob2 col-md-4 col-lg-2 col-xl-2">
                                <div id="logo">
                                    <a 
                                        href="<?php echo esc_url(home_url('/')); ?>" 
                                        aria-label="<?php esc_attr_e('Početna stranica', 'dreampoint-b2b'); ?>"
                                    >
                                        <img 
                                            src="<?php echo esc_url($company_logo['url']); ?>" 
                                            alt="<?php echo esc_attr($company_logo['alt'] ?: get_bloginfo('name') . ' Logo'); ?>"
                                            width="<?php echo isset($company_logo['width']) ? absint($company_logo['width']) : ''; ?>"
                                            height="<?php echo isset($company_logo['height']) ? absint($company_logo['height']) : ''; ?>"
                                        >
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="col-lg-7 col-xl-8 products-menu">
                            <nav role="navigation" aria-label="<?php esc_attr_e('Glavni meni', 'dreampoint-b2b'); ?>" style="display:none;">
                                <?php echo dreampoint_b2b_nav_categories_desktop(); ?>
                                <?php 
                                wp_nav_menu([
                                    'theme_location' => 'menu-1',
                                    'menu'           => 'Main Menu',
                                    'container'      => false,
                                    'menu_class'     => 'links',
                                    'items_wrap'     => '<ul class="%2$s">%3$s</ul>',
                                    'fallback_cb'    => false,
                                ]); 
                                ?>
                            </nav>
                        </div>
                        <!-- /.col-md-6 -->
                        
                        <div class="col-md-8 col-lg-3 col-xl-2 action-btns-holder">
                            <div class="action-btns">
                                <!-- Home Icon -->
                                <div class="home-icon action-btn">
                                    <a 
                                        href="<?php echo esc_url(home_url('/')); ?>" 
                                        aria-label="<?php esc_attr_e('Početna stranica', 'dreampoint-b2b'); ?>"
                                    >
                                        <i class="icon-house" aria-hidden="true"></i>
                                    </a>
                                </div>
                                
                                <!-- Search Button -->
                                <div class="search-area toggle-search-area action-btn">
                                    <button 
                                        class="toggle-search" 
                                        aria-label="<?php esc_attr_e('Otvori pretragu', 'dreampoint-b2b'); ?>"
                                        aria-expanded="false"
                                        aria-controls="search-popup"
                                    >
                                        <i class="icon-search" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <!-- /.search-area -->
                                
                                <!-- Wishlist -->
                                <?php if (function_exists('tinv_get_option')) : ?>
                                    <div class="wishlist-area action-btn">
                                        <?php echo do_shortcode('[ti_wishlist_products_counter]'); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- User Account -->
                                <div class="user-area action-btn">
                                    <a 
                                        href="<?php echo esc_url(get_permalink(get_option('woocommerce_myaccount_page_id'))); ?>" 
                                        aria-label="<?php esc_attr_e('Moj nalog', 'dreampoint-b2b'); ?>"
                                    >
                                        <i class="icon-user" aria-hidden="true"></i>
                                    </a>
                                </div>
                                
                                <!-- Cart -->
                                <div class="cart-btn-wrapper action-btn">
                                    <?php 
                                    if (function_exists('dreampoint_b2b_woocommerce_cart_link')) {
                                        dreampoint_b2b_woocommerce_cart_link();
                                    }
                                    ?>
                                </div>
                            </div>
                            <!-- /.action-btns -->
                            
                            <div class="btn-holder">
                                <button 
                                    class="mobile-toggler action-btn" 
                                    aria-label="<?php esc_attr_e('Otvori mobilni meni', 'dreampoint-b2b'); ?>"
                                    aria-expanded="false"
                                    aria-controls="mobile-menu"
                                >
                                    <svg width="24" height="18" viewBox="0 0 24 18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <path d="M24 9C24 9.26522 23.8946 9.51957 23.7071 9.70711C23.5196 9.89464 23.2652 10 23 10H1C0.734784 10 0.48043 9.89464 0.292893 9.70711C0.105357 9.51957 0 9.26522 0 9C0 8.73478 0.105357 8.48043 0.292893 8.29289C0.48043 8.10536 0.734784 8 1 8H23C23.2652 8 23.5196 8.10536 23.7071 8.29289C23.8946 8.48043 24 8.73478 24 9ZM1 2H23C23.2652 2 23.5196 1.89464 23.7071 1.70711C23.8946 1.51957 24 1.26522 24 1C24 0.734784 23.8946 0.48043 23.7071 0.292893C23.5196 0.105357 23.2652 0 23 0H1C0.734784 0 0.48043 0.105357 0.292893 0.292893C0.105357 0.48043 0 0.734784 0 1C0 1.26522 0.105357 1.51957 0.292893 1.70711C0.48043 1.89464 0.734784 2 1 2ZM23 16H1C0.734784 16 0.48043 16.1054 0.292893 16.2929C0.105357 16.4804 0 16.7348 0 17C0 17.2652 0.105357 17.5196 0.292893 17.7071C0.48043 17.8946 0.734784 18 1 18H23C23.2652 18 23.5196 17.8946 23.7071 17.7071C23.8946 17.5196 24 17.2652 24 17C24 16.7348 23.8946 16.4804 23.7071 16.2929C23.5196 16.1054 23.2652 16 23 16Z" fill="#111927"/>
                                    </svg>
                                </button>
                                <!-- /.mobile-toggler -->
                            </div>
                            <!-- /.btn-holder -->
                        </div>
                        <!-- /.col -->
                    </div>
                    <!-- /.row -->
                </div>
                <!-- /.container -->
            </div>
            <!-- /#main-header -->
            
            <!-- Mobile Menu -->
            <div class="mobile-menu show-sm" id="mobile-menu" aria-hidden="true">
                <div class="mobile-menu__header">
                    <button 
                        class="mobile-menu__toggler mobile-toggle-black-js" 
                        aria-label="<?php esc_attr_e('Nazad', 'dreampoint-b2b'); ?>"
                    >
                        <i class="icon-arrow-left-long" aria-hidden="true"></i>
                    </button>
                    
                    <button 
                        class="mobile-menu__toggler mobile-closer" 
                        aria-label="<?php esc_attr_e('Zatvori meni', 'dreampoint-b2b'); ?>"
                    >
                        <i class="icon-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <!-- /.mobile-menu__header -->
                
                <nav class="mobile-menu__menu" role="navigation" aria-label="<?php esc_attr_e('Mobilni meni', 'dreampoint-b2b'); ?>">
                    <?php dreampoint_b2b_nav_categories_mobile(); ?>
                    <?php 
                    wp_nav_menu([
                        'theme_location' => 'menu-1',
                        'menu'           => 'Main Menu',
                        'container'      => false,
                        'menu_class'     => 'first-menu',
                        'items_wrap'     => '<ul class="%2$s">%3$s</ul>',
                        'fallback_cb'    => false,
                    ]); 
                    ?>
                    <?php wp_nav_menu(array(
                        'menu' => 'Sitemap Menu',
                        'container' => false,
                            'menu_class' => 'first-menu',
                            'items_wrap' => '<ul class="%2$s">%3$s</ul>',
                            ));
                    ?>

                </nav>
                <!-- /.mobile-menu__menu -->
            </div>
            <!-- /.mobile-menu -->
        </header>
        <!-- /#header -->
        
        <!-- Search Popup -->
        <div class="search-popup modal" id="search-popup" role="dialog" aria-modal="true" aria-labelledby="search-title" aria-hidden="true">
            <div class="modal-content">
                <h2 id="search-title" class="screen-reader-text"><?php esc_html_e('Pretraga proizvoda', 'dreampoint-b2b'); ?></h2>
                
                <button 
                    class="sliding-content-close search-close close-modal" 
                    aria-label="<?php esc_attr_e('Zatvori pretragu', 'dreampoint-b2b'); ?>"
                >
                    <i class="icon-xmark" aria-hidden="true"></i>
                </button>
                
                <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>" autocomplete="off">
                    <label class="screen-reader-text" for="s"><?php esc_html_e('Traži proizvode:', 'dreampoint-b2b'); ?></label>
                    <div class="input-wrapper">
                        <input 
                            type="search" 
                            value="<?php echo esc_attr(get_search_query()); ?>" 
                            name="s" 
                            id="s" 
                            placeholder="<?php esc_attr_e('Traži proizvode...', 'dreampoint-b2b'); ?>" 
                            class="search-input" 
                            autocomplete="off"
                            aria-label="<?php esc_attr_e('Pretraži proizvode', 'dreampoint-b2b'); ?>"
                            tabindex="-1"
                        />
                        <input type="hidden" name="post_type" value="product">
                        <button class="submit-search" type="submit" aria-label="<?php esc_attr_e('Pretraži', 'dreampoint-b2b'); ?>">
                            <i class="icon-search" aria-hidden="true"></i>
                        </button>
                    </div>
                    <!-- /.input-wrapper -->
                </form>
                
                <div id="ajax-search-result" role="region" aria-live="polite" aria-atomic="true"></div>
            </div>
            <!-- /.modal-content -->
        </div>
        <!-- /.search-popup -->
        
        <!-- Mini Cart -->
        <div class="my-custom-mini-cart-container widget_shopping_cart_content" role="complementary" aria-label="<?php esc_attr_e('košarica', 'dreampoint-b2b'); ?>">
            <?php 
            if (function_exists('woocommerce_mini_cart')) {
                woocommerce_mini_cart();
            }
            ?>
        </div>

        <div class="inner-page" id="primary-content">
            <?php
            /**
             * Inner Page Heading
             * Shows breadcrumbs and page titles
             * Hidden on homepage and thank you page
             */
            $hide_heading = is_front_page() || 
                            is_home() || 
                            (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received'));
            
            if (!$hide_heading) : 
            ?>
            <?php
            // Dohvati bg image unaprijed
            $bg_image_url = '';
            if ( is_category() || is_tax() ) {
                $term = get_queried_object();
                $bg_image_id  = get_term_meta( $term->term_id, 'bg_image', true );
                $bg_image_url = $bg_image_id ? wp_get_attachment_image_url( $bg_image_id, 'full' ) : '';
            }
            ?>

            <div class="inner-heading<?php if ( $bg_image_url ) echo ' has-background'; ?>" <?php if ( $bg_image_url ) echo 'style="background-image: url(' . esc_url( $bg_image_url ) . ');"'; ?>>
                <div class="container">
                    <?php
                    /**
                     * Breadcrumbs
                     * Different display logic per page type
                     */
                    if (is_shop() || is_page()) {
                        if (function_exists('display_breadcrumb')) {
                            display_breadcrumb();
                        }
                    } elseif (function_exists('woocommerce_breadcrumb')) {
                        woocommerce_breadcrumb();
                    }

                    /**
                     * Page Titles
                     * Conditional H1 based on page type
                     */
                    if (!is_404()) {
                        if (is_shop()) {
                            echo '<h1>' . esc_html(woocommerce_page_title(false)) . '</h1>';

                        } elseif (is_category()) {
                            echo '<h1>' . esc_html(single_cat_title('', false)) . '</h1>';
                            $subtitle = get_term_meta($term->term_id, 'subtitle', true);
                            if ($subtitle) echo '<h2 class="category-subtitle">' . esc_html($subtitle) . '</h2>';

                        } elseif (is_tax()) {
                            echo '<h1>' . esc_html(single_term_title('', false)) . '</h1>';
                            $subtitle = get_term_meta($term->term_id, 'subtitle', true);
                            if ($subtitle) echo '<h2 class="category-subtitle">' . esc_html($subtitle) . '</h2>';

                        } elseif (!is_product()) {
                            echo '<h1>' . esc_html(get_the_title()) . '</h1>';
                        }
                    }
                    ?>

                    <?php 
                    /**
                     * My Account Intro
                     * Shows on account dashboard
                     */
                    if (is_account_page() && is_user_logged_in()) :
                        $current_user = wp_get_current_user();
                        
                        $allowed_html = [
                            'a' => [
                                'href' => [],
                            ],
                            'strong' => [],
                        ];
                    ?>
                        <div class="my-acccount-intro">
                            <p>
                                <?php
                                printf(
                                    /* translators: 1: user display name 2: logout url */
                                    wp_kses( __( 'Hello %1$s (not %1$s? <a href="%2$s">Log out</a>)', 'woocommerce' ), $allowed_html ),
                                    '<strong>' . esc_html( $current_user->display_name ) . '</strong>',
                                    esc_url( wc_logout_url() )
                                );

                                $dashboard_desc = __(
                                    ' From your account dashboard you can view and manage your <a href="%3$s"> account details</a>, <a href="%2$s">shipping and billing addresses</a> and view <a href="%1$s">recent orders</a>.',
                                    'woocommerce'
                                );
                                
                                printf(
                                    wp_kses($dashboard_desc, $allowed_html),
                                    esc_url(wc_get_endpoint_url('orders')),
                                    esc_url(wc_get_endpoint_url('edit-address')),
                                    esc_url(wc_get_endpoint_url('edit-account'))
                                );
                                ?>
                            </p>
                        </div>
                        <!-- /.my-acccount-intro -->
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
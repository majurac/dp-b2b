<?php
/**
 * Product Inquiry Form with Fancybox & Contact Form 7
 *
 * @package Dreampoint_B2B
 */

defined('ABSPATH') || exit;

add_action('init', 'dreampoint_b2b_product_inquiry_rewrite_rule');
function dreampoint_b2b_product_inquiry_rewrite_rule(): void {
    add_rewrite_rule(
        '^product-inquiry/([0-9]+)/?$',
        'index.php?product_inquiry_id=$matches[1]',
        'top'
    );
}

add_filter('query_vars', 'dreampoint_b2b_product_inquiry_query_vars');
function dreampoint_b2b_product_inquiry_query_vars(array $vars): array {
    $vars[] = 'product_inquiry_id';
    return $vars;
}

add_action('template_redirect', 'dreampoint_b2b_product_inquiry_template');
function dreampoint_b2b_product_inquiry_template(): void {
    $product_id = get_query_var('product_inquiry_id');

    if (!$product_id) {
        return;
    }

    $product = wc_get_product($product_id);

    if (!$product) {
        wp_die(esc_html__('Proizvod nije pronađen', 'dreampoint-b2b'));
    }

    $product_name = $product->get_name();
    $product_url  = get_permalink($product_id);
    $cf7_form_id  = get_field('product_inquiry_form_id', 'option');

    if (!$cf7_form_id) {
        wp_die(esc_html__('Forma nije podešena', 'dreampoint-b2b'));
    }

    add_filter('show_admin_bar', '__return_false');
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <?php wp_head(); ?>
        <style>
            html, body { height: 100%; }
            .product-inquiry-modal {
                max-width: 600px;
                margin: 0 auto;
                height: 100%;
                display: flex;
                justify-content: center;
                flex-direction: column;
            }
            @media (max-width: 767px) {
                .product-inquiry-modal { max-width: 90%; }
            }
            .modal-body { padding: 20px 0; }
        </style>
    </head>
    <body>
        <div class="product-inquiry-modal">
            <div class="modal-header">
                <h3 class="modal-title">
                    <?php esc_html_e('Pošaljite upit za proizvod:', 'dreampoint-b2b'); ?>
                    <br>
                    <strong><?php echo esc_html($product_name); ?></strong>
                </h3>
            </div>
            <div class="modal-body custom-form">
                <?php echo do_shortcode('[contact-form-7 id="' . $cf7_form_id . '"]'); ?>
                <script>
                (function () {
                    'use strict';
                    var checkCF7 = setInterval(function () {
                        var form = document.querySelector('.wpcf7-form');
                        if (!form) return;
                        clearInterval(checkCF7);
                        ['product-name', 'product-url', 'product-id'].forEach(function (name) {
                            if (form.querySelector('input[name="' + name + '"]')) return;
                            var input = document.createElement('input');
                            input.type  = 'hidden';
                            input.name  = name;
                            input.value = name === 'product-name' ? '<?php echo esc_js($product_name); ?>'
                                        : name === 'product-url'  ? '<?php echo esc_js($product_url); ?>'
                                        : '<?php echo absint($product_id); ?>';
                            form.appendChild(input);
                        });
                    }, 100);

                    document.addEventListener('wpcf7mailsent', function () {
                        setTimeout(function () {
                            if (window.parent && window.parent.Fancybox) {
                                window.parent.Fancybox.close();
                            }
                        }, 2000);
                    });
                })();
                </script>
            </div>
        </div>
        <?php wp_footer(); ?>
    </body>
    </html>
    <?php
    exit;
}

// Fancybox binding — samo na single product stranici
add_action('wp_footer', 'dreampoint_b2b_product_inquiry_fancybox_init', 30);
function dreampoint_b2b_product_inquiry_fancybox_init(): void {
    if (!is_product()) {
        return;
    }
    ?>
    <script>
    ( function () {
        'use strict';
        Fancybox.bind('[data-fancybox].product-inquiry-btn', {
            type       : 'iframe',
            preload    : false,
            autoFocus  : true,
            trapFocus  : true,
            placeFocusBack: true,
            closeButton: true,
            idle       : false,
            width      : 850,
            height     : 'auto',
        });
    } )();
    </script>
    <?php
}

<?php
/**
 * Register Products Tabs Block
 * File location: /blocks/products-tabs.php
 */

function register_block_products_tabs_block()
{
    acf_register_block_type(array(
        'name' => 'products-tabs', // ✅ Mora biti isto kao u ACF JSON!
        'title' => __('Products Tabs Section', 'dreampoint-b2b'),
        'render_template' => 'blocks/templates/products-tabs.php',
        'category' => 'blocks',
        'icon' => 'block-default',
        'keywords' => array('products-tabs', 'products', 'tabs'),
        'mode' => 'edit',
        'align' => 'wide',
        'supports' => array(
            'align' => false,
            'mode' => false,
        ),
    ));
}

if (function_exists('acf_register_block_type')) {
    add_action('acf/init', 'register_block_products_tabs_block');
}


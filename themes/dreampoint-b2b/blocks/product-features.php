<?php

function register_block_product_features_block()
{

    acf_register_block_type(array(
        'name' => 'Product Features Section',
        'title' => __('Product Features Section', 'dreampoint-b2b'),
        'render_template' => 'blocks/templates/product-features.php',
        'category' => 'blocks',
        'icon' => 'block-default',
        'keywords' => array('product-features'),
        'mode' => 'edit',
        'align' => 'wide',
        'supports' => array(
            'align' => false,
            'mode' => false,
        ),
    ));
}

if (function_exists('acf_register_block_type')) {
    add_action('acf/init', 'register_block_product_features_block');
}

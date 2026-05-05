<?php

function register_block_latest_products_block()
{

    acf_register_block_type(array(
        'name' => 'Latest Products Section',
        'title' => __('Latest Products Section', 'dreampoint-b2b'),
        'render_template' => 'blocks/templates/latest-products.php',
        'category' => 'blocks',
        'icon' => 'block-default',
        'keywords' => array('latest-products'),
        'mode' => 'edit',
        'align' => 'wide',
        'supports' => array(
            'align' => false,
            'mode' => false,
        ),
    ));
}

if (function_exists('acf_register_block_type')) {
    add_action('acf/init', 'register_block_latest_products_block');
}

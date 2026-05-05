<?php

function register_block_featured_products_block()
{

    acf_register_block_type(array(
        'name' => 'Featured Products Section',
        'title' => __('Featured Products Section', 'dreampoint-b2b'),
        'render_template' => 'blocks/templates/featured-products.php',
        'category' => 'blocks',
        'icon' => 'block-default',
        'keywords' => array('featured-products'),
        'mode' => 'edit',
        'align' => 'wide',
        'supports' => array(
            'align' => false,
            'mode' => false,
        ),
    ));
}

if (function_exists('acf_register_block_type')) {
    add_action('acf/init', 'register_block_featured_products_block');
}
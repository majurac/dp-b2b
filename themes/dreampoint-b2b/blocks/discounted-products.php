<?php

function register_block_discounted_products_block()
{

    acf_register_block_type(array(
        'name' => 'Discounted Products Section',
        'title' => __('Discounted Products Section', 'dreampoint-b2b'),
        'render_template' => 'blocks/templates/discounted-products.php',
        'category' => 'blocks',
        'icon' => 'block-default',
        'keywords' => array('discounted-products'),
        'mode' => 'edit',
        'align' => 'wide',
        'supports' => array(
            'align' => false,
            'mode' => false,
        ),
    ));
}

if (function_exists('acf_register_block_type')) {
    add_action('acf/init', 'register_block_discounted_products_block');
}

<?php

function register_block_featured_brand_block()
{

    acf_register_block_type(array(
        'name' => 'Featured Brand Section',
        'title' => __('Featured Brand Section', 'dreampoint-b2b'),
        'render_template' => 'blocks/templates/featured-brand.php',
        'category' => 'blocks',
        'icon' => 'block-default',
        'keywords' => array('featured-brand'),
        'mode' => 'edit',
        'align' => 'wide',
        'supports' => array(
            'align' => false,
            'mode' => false,
        ),
    ));
}

if (function_exists('acf_register_block_type')) {
    add_action('acf/init', 'register_block_featured_brand_block');
}

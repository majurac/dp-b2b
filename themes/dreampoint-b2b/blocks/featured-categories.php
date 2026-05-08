<?php

function register_block_featured_categories_block()
{

    acf_register_block_type(array(
        'name' => 'Featured Categories Section',
        'title' => __('Featured Categories Section', 'dreampoint-b2b'),
        'render_template' => 'blocks/templates/featured-categories.php',
        'category' => 'blocks',
        'icon' => 'block-default',
        'keywords' => array('featured-categories'),
        'mode' => 'edit',
        'align' => 'wide',
        'supports' => array(
            'align' => false,
            'mode' => false,
        ),
    ));
}

if (function_exists('acf_register_block_type')) {
    add_action('acf/init', 'register_block_featured_categories_block');
}

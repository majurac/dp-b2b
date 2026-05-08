<?php

function register_block_about_block()
{

    acf_register_block_type(array(
        'name' => 'About Section',
        'title' => __('About Section', 'dreampoint-b2b'),
        'render_template' => 'blocks/templates/about.php',
        'category' => 'blocks',
        'icon' => 'block-default',
        'keywords' => array('about'),
        'mode' => 'edit',
        'align' => 'wide',
        'supports' => array(
            'align' => false,
            'mode' => false,
        ),
    ));
}

if (function_exists('acf_register_block_type')) {
    add_action('acf/init', 'register_block_about_block');
}

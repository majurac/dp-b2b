<?php

function register_block_featured_section_block()
{

    acf_register_block_type(array(
        'name' => 'Featured Section',
        'title' => __('Featured Section', 'dreampoint-b2b'),
        'render_template' => 'blocks/templates/featured-section.php',
        'category' => 'blocks',
        'icon' => 'block-default',
        'keywords' => array('featured-section'),
        'mode' => 'edit',
        'align' => 'wide',
        'supports' => array(
            'align' => false,
            'mode' => false,
        ),
    ));
}

if (function_exists('acf_register_block_type')) {
    add_action('acf/init', 'register_block_featured_section_block');
}

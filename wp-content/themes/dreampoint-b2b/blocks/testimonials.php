<?php

function register_block_testimonials_block()
{

    acf_register_block_type(array(
        'name' => 'Testimonials Section',
        'title' => __('Testimonials Section', 'dreampoint-b2b'),
        'render_template' => 'blocks/templates/testimonials.php',
        'category' => 'blocks',
        'icon' => 'block-default',
        'keywords' => array('testimonials'),
        'mode' => 'edit',
        'align' => 'wide',
        'supports' => array(
            'align' => false,
            'mode' => false,
        ),
    ));
}

if (function_exists('acf_register_block_type')) {
    add_action('acf/init', 'register_block_testimonials_block');
}

<?php

function register_block_contact_info_block()
{

    acf_register_block_type(array(
        'name' => 'Contact Info Section',
        'title' => __('Contact Info Section', 'dreampoint-b2b'),
        'render_template' => 'blocks/templates/contact-info.php',
        'category' => 'blocks',
        'icon' => 'block-default',
        'keywords' => array('contact-info'),
        'mode' => 'edit',
        'align' => 'wide',
        'supports' => array(
            'align' => false,
            'mode' => false,
        ),
    ));
}

if (function_exists('acf_register_block_type')) {
    add_action('acf/init', 'register_block_contact_info_block');
}

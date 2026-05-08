<?php

function register_block_contact_form_block()
{

    acf_register_block_type(array(
        'name' => 'Contact Form Section',
        'title' => __('Contact Form Section', 'dreampoint-b2b'),
        'render_template' => 'blocks/templates/contact-form.php',
        'category' => 'blocks',
        'icon' => 'block-default',
        'keywords' => array('contact-form'),
        'mode' => 'edit',
        'align' => 'wide',
        'supports' => array(
            'align' => false,
            'mode' => false,
        ),
    ));
}

if (function_exists('acf_register_block_type')) {
    add_action('acf/init', 'register_block_contact_form_block');
}

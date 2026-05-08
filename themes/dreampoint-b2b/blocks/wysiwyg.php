<?php

function register_block_wysiwyg_block()
{

    acf_register_block_type(array(
        'name' => 'Wysiwyg Section',
        'title' => __('Wysiwyg Section', 'dreampoint-b2b'),
        'render_template' => 'blocks/templates/wysiwyg.php',
        'category' => 'blocks',
        'icon' => 'block-default',
        'keywords' => array('wysiwyg'),
        'mode' => 'edit',
        'align' => 'wide',
        'supports' => array(
            'align' => false,
            'mode' => false,
        ),
    ));
}

if (function_exists('acf_register_block_type')) {
    add_action('acf/init', 'register_block_wysiwyg_block');
}

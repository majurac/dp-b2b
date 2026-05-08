<?php

function register_block_gallery_block()
{

    acf_register_block_type(array(
        'name' => 'Gallery Section',
        'title' => __('Gallery Section', 'dreampoint-b2b'),
        'render_template' => 'blocks/templates/gallery.php',
        'category' => 'blocks',
        'icon' => 'block-default',
        'keywords' => array('gallery'),
        'mode' => 'edit',
        'align' => 'wide',
        'supports' => array(
            'align' => false,
            'mode' => false,
        ),
    ));
}

if (function_exists('acf_register_block_type')) {
    add_action('acf/init', 'register_block_gallery_block');
}

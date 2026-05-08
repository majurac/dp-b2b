<?php

function register_block_bestseller_block()
{

    acf_register_block_type(array(
        'name' => 'Bestseller Section',
        'title' => __('Bestseller Section', 'dreampoint-b2b'),
        'render_template' => 'blocks/templates/bestseller.php',
        'category' => 'blocks',
        'icon' => 'block-default',
        'keywords' => array('bestseller'),
        'mode' => 'edit',
        'align' => 'wide',
        'supports' => array(
            'align' => false,
            'mode' => false,
        ),
    ));
}

if (function_exists('acf_register_block_type')) {
    add_action('acf/init', 'register_block_bestseller_block');
}

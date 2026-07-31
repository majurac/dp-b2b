<?php

function register_block_brands_block() {
    acf_register_block_type([
        'name'     => 'Brands Section',
        'title'    => __('Brands Section', 'dreampoint-b2b'),
        'render_template' => 'blocks/templates/brands.php',
        'category' => 'blocks',
        'icon'     => 'block-default',
        'keywords' => ['brands'],
        'mode'     => 'edit',
        'align'    => 'wide',
        'supports' => [
            'align' => false,
            'mode'  => false,
        ],
    ]);
}

if (function_exists('acf_register_block_type')) {
    add_action('acf/init', 'register_block_brands_block');
}

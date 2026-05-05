<?php

function register_block_company_features_block()
{

    acf_register_block_type(array(
        'name' => 'Company Features Section',
        'title' => __('Company Features Section', 'dreampoint-b2b'),
        'render_template' => 'blocks/templates/company-features.php',
        'category' => 'blocks',
        'icon' => 'block-default',
        'keywords' => array('company-features'),
        'mode' => 'edit',
        'align' => 'wide',
        'supports' => array(
            'align' => false,
            'mode' => false,
        ),
    ));
}

if (function_exists('acf_register_block_type')) {
    add_action('acf/init', 'register_block_company_features_block');
}

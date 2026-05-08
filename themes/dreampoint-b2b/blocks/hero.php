<?php

function register_block_hero_block()
{

    acf_register_block_type(array(
        'name' => 'Hero Section',
        'title' => __('Hero Section', 'dreampoint-b2b'),
        'render_template' => 'blocks/templates/hero.php',
        'category' => 'blocks',
        'icon' => 'block-default',
        'keywords' => array('hero'),
        'mode' => 'edit',
        'align' => 'wide',
        'supports' => array(
            'align' => false,
            'mode' => false,
        ),
    ));
}

if (function_exists('acf_register_block_type')) {
    add_action('acf/init', 'register_block_hero_block');
}

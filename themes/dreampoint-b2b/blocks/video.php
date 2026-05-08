<?php

function register_block_video_block()
{

    acf_register_block_type(array(
        'name' => 'Video Section',
        'title' => __('Video Section', 'dreampoint-b2b'),
        'render_template' => 'blocks/templates/video.php',
        'category' => 'blocks',
        'icon' => 'block-default',
        'keywords' => array('video'),
        'mode' => 'edit',
        'align' => 'wide',
        'supports' => array(
            'align' => false,
            'mode' => false,
        ),
    ));
}

if (function_exists('acf_register_block_type')) {
    add_action('acf/init', 'register_block_video_block');
}

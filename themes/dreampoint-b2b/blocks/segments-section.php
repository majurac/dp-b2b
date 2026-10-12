<?php

/**
 * Segments Section — homepage tiles for the Lifestyle / Toys / Outdoor segment
 * landing pages. Content comes from the pages themselves (title, permalink,
 * featured image); the block has no fields of its own.
 */
function register_block_segments_section_block()
{

    acf_register_block_type(array(
        'name' => 'segments-section',
        'title' => __('Segments Section', 'dreampoint-b2b'),
        'render_template' => 'blocks/templates/segments-section.php',
        'category' => 'blocks',
        'icon' => 'block-default',
        'keywords' => array('segments', 'lifestyle', 'toys', 'outdoor'),
        'mode' => 'preview',
        'align' => 'wide',
        'supports' => array(
            'align' => false,
            'mode' => false,
        ),
        'example' => array(),
    ));
}

if (function_exists('acf_register_block_type')) {
    add_action('acf/init', 'register_block_segments_section_block');
}

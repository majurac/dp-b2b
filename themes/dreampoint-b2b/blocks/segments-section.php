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
        // Like every other ACF block: compact "Edit Block" card in the editor canvas (preview mode rendered the
        // whole tile list unstyled). Legacy saved `"mode":"preview"` attributes are normalised in the editor by
        // js/admin-segments-block-mode.js; the frontend output does not depend on the mode.
        'mode' => 'edit',
        'align' => 'wide',
        'supports' => array(
            'align' => false,
            'mode' => false,
        ),
    ));
}

if (function_exists('acf_register_block_type')) {
    add_action('acf/init', 'register_block_segments_section_block');
}

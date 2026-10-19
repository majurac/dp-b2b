/**
 * Block editor: open legacy Segments Section blocks in `edit` mode.
 *
 * Content saved while the block was registered in `preview` mode carries `"mode":"preview"`, which wins over the
 * registration default. This maps it to `edit` at parse time (official `blocks.getBlockAttributes` filter), so
 * the block shows the same compact "Edit Block" card as the other ACF blocks. Editor-only; the stored content is
 * only rewritten if the page is saved. Enqueued by dreampoint_b2b_enqueue_segments_block_mode()
 * in inc/homepage-segments.php.
 *
 * @package Dreampoint_B2B
 */

( function ( wp ) {
    'use strict';

    if ( ! wp || ! wp.hooks ) {
        return;
    }

    wp.hooks.addFilter(
        'blocks.getBlockAttributes',
        'dreampoint-b2b/segments-section-edit-mode',
        function ( attributes, blockType ) {
            if ( blockType && 'acf/segments-section' === blockType.name && attributes && 'preview' === attributes.mode ) {
                return Object.assign( {}, attributes, { mode: 'edit' } );
            }
            return attributes;
        }
    );
}( window.wp ) );

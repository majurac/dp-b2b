/**
 * Segment Landing pages: image-size note under the native Featured Image control.
 *
 * Uses the official `editor.PostFeaturedImage` filter (no DOM manipulation). Data
 * (strings, template slug) comes from dreampoint_b2b_enqueue_segment_image_hint()
 * in inc/homepage-segments.php, which only enqueues this file for pages with a
 * valid segment context. Informational only — nothing is validated or blocked.
 *
 * @package Dreampoint_B2B
 */

( function ( wp, hint ) {
    'use strict';

    if ( ! wp || ! wp.hooks || ! wp.element || ! wp.data || ! hint ) {
        return;
    }

    const { createElement, Fragment } = wp.element;
    const { useSelect } = wp.data;

    wp.hooks.addFilter(
        'editor.PostFeaturedImage',
        'dreampoint-b2b/segment-featured-image-hint',
        function ( FeaturedImage ) {
            return function ( props ) {
                // Live template, so the note follows the Page Attributes selection.
                const template = useSelect( function ( select ) {
                    return select( 'core/editor' ).getEditedPostAttribute( 'template' );
                }, [] );

                return createElement(
                    Fragment,
                    null,
                    createElement( FeaturedImage, props ),
                    template === hint.template
                        ? createElement(
                            'p',
                            { className: 'components-base-control__help' },
                            createElement( 'strong', null, hint.title ),
                            createElement( 'br' ),
                            hint.text
                        )
                        : null
                );
            };
        }
    );
}( window.wp, window.dpSegmentImageHint ) );

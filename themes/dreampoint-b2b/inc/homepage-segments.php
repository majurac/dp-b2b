<?php
/**
 * Homepage & Segment Landing — shared-surface visibility context helpers.
 *
 * ADR-009 Phase B: explicit per-page opt-in into the dormant Phase A
 * dp_visibility_context='shared_surface' primitive
 * (inc/visibility/class-query-filter.php — unchanged by this file).
 *
 * A page carries this context via the `dp_page_segment_context` ACF field
 * (acf-json/group_dp_shared_surface.json, location: Post Type == page).
 * Absence of the field (default '') means unchanged, filtered behavior —
 * no page-ID or slug detection, matching ADR-009's explicit-opt-in rule.
 *
 * @package Dreampoint_B2B
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Returns the current page's segment context, or '' if not a shared surface.
 *
 * Values: 'homepage' | 'lifestyle' | 'toys' | 'outdoor' | '' (default)
 */
function dreampoint_b2b_get_page_segment_context( ?int $post_id = null ): string {
    $post_id = $post_id ?? get_the_ID();

    if ( ! $post_id ) {
        return '';
    }

    $value = get_field( 'dp_page_segment_context', $post_id );

    return is_string( $value ) ? $value : '';
}

/** Whether the current page is a shared surface (Homepage or Segment Landing). */
function dreampoint_b2b_is_shared_surface( ?int $post_id = null ): bool {
    return '' !== dreampoint_b2b_get_page_segment_context( $post_id );
}

/** Whether the current page's context is a real product segment (excludes 'homepage'). */
function dreampoint_b2b_get_page_segment( ?int $post_id = null ): string {
    $context = dreampoint_b2b_get_page_segment_context( $post_id );

    return in_array( $context, [ 'lifestyle', 'toys', 'outdoor' ], true ) ? $context : '';
}

/**
 * Resolves product_brand term IDs assigned to the given segment via the
 * existing `brand_segment` ACF field (acf-json/group_675053191eac4.json).
 * Looked up with dp_visibility_context=shared_surface — segment membership
 * is editorial data, not customer-specific, so the lookup itself must not
 * be restricted by the current user's catalog visibility.
 *
 * @return int[]
 */
function dreampoint_b2b_get_brand_ids_for_segment( string $segment ): array {
    if ( ! in_array( $segment, [ 'lifestyle', 'toys', 'outdoor' ], true ) ) {
        return [];
    }

    $terms = get_terms( [
        'taxonomy'               => 'product_brand',
        'hide_empty'             => false,
        'fields'                 => 'ids',
        'meta_query'             => [
            [
                'key'   => 'brand_segment',
                'value' => $segment,
            ],
        ],
        'dp_visibility_context'  => 'shared_surface',
    ] );

    return is_array( $terms ) ? array_map( 'intval', $terms ) : [];
}

/**
 * If the current page is a shared surface, adds dp_visibility_context to a
 * WP_Query/get_terms $args array. Leaves $args untouched otherwise.
 */
function dreampoint_b2b_shared_surface_query_args( array $args, ?int $post_id = null ): array {
    if ( ! dreampoint_b2b_is_shared_surface( $post_id ) ) {
        return $args;
    }

    $args['dp_visibility_context'] = 'shared_surface';

    return $args;
}

/**
 * If the current page has a segment context, adds a product_brand tax_query
 * restricting results to brands assigned to that segment. Leaves $args
 * untouched on the plain 'homepage' context or no context.
 *
 * No brands assigned to the segment yet → intentionally yields zero results
 * (tax_query term 0) rather than silently falling back to the full catalog.
 */
function dreampoint_b2b_apply_segment_tax_query( array $args, ?int $post_id = null ): array {
    $segment = dreampoint_b2b_get_page_segment( $post_id );

    if ( '' === $segment ) {
        return $args;
    }

    $brand_ids = dreampoint_b2b_get_brand_ids_for_segment( $segment );

    $args['tax_query'] = [ [
        'taxonomy' => 'product_brand',
        'field'    => 'term_id',
        'terms'    => ! empty( $brand_ids ) ? $brand_ids : [ 0 ],
    ] ];

    return $args;
}

/**
 * Whether an optional Segment Landing section is enabled for the current page.
 *
 * $field is a per-page ACF true/false name (e.g. 'dp_show_discounted_products').
 * Backward compatible by design: only an EXPLICITLY stored falsy value hides
 * the section. No stored meta row (page never saved with the toggle) → shown.
 * Applies on frontend Segment Landing pages only — never in the block editor,
 * never on the Homepage or any page without a segment context.
 */
function dreampoint_b2b_segment_section_enabled( string $field, ?int $post_id = null ): bool {
    if ( is_admin() ) {
        return true;
    }

    $post_id = $post_id ?? get_the_ID();

    if ( ! $post_id || '' === dreampoint_b2b_get_page_segment( $post_id ) ) {
        return true;
    }

    // metadata_exists() distinguishes "never saved" from "explicitly OFF" —
    // ACF's default_value would otherwise mask that difference.
    if ( ! metadata_exists( 'post', $post_id, $field ) ) {
        return true;
    }

    return (bool) get_post_meta( $post_id, $field, true );
}

/**
 * Approved segment keys in canonical display order (Lifestyle → Toys → Outdoor).
 * Same values as the `dp_page_segment_context` segment choices and `brand_segment`.
 *
 * @return string[]
 */
function dreampoint_b2b_get_segment_keys(): array {
    return [ 'lifestyle', 'toys', 'outdoor' ];
}

/**
 * Published Segment Landing pages keyed by segment, in canonical order.
 *
 * A segment is eligible only when exactly one published page uses the
 * `page-segment-landing.php` template with the matching `dp_page_segment_context`.
 * Segments with no such page are omitted. Segments claimed by more than one page
 * are omitted as well (no arbitrary pick) and reported through $conflicts as
 * segment => page IDs. Pages only — no product or product_brand queries, so the
 * visibility engine is not involved.
 *
 * @param array<string,int[]> $conflicts Filled with segment => page IDs on duplicates.
 * @return array<string,WP_Post>
 */
function dreampoint_b2b_get_segment_pages( array &$conflicts = [] ): array {
    $keys = dreampoint_b2b_get_segment_keys();

    $pages = get_posts( [
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'ID',
        'order'          => 'ASC',
        'no_found_rows'  => true,
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'     => 'dp_page_segment_context',
                'value'   => $keys,
                'compare' => 'IN',
            ],
            [
                'key'   => '_wp_page_template',
                'value' => 'page-segment-landing.php',
            ],
        ],
    ] );

    $by_segment = [];
    foreach ( $pages as $page ) {
        $by_segment[ (string) get_post_meta( $page->ID, 'dp_page_segment_context', true ) ][] = $page;
    }

    $result = [];
    foreach ( $keys as $key ) {
        if ( empty( $by_segment[ $key ] ) ) {
            continue;
        }

        if ( count( $by_segment[ $key ] ) > 1 ) {
            $conflicts[ $key ] = wp_list_pluck( $by_segment[ $key ], 'ID' );
            continue;
        }

        $result[ $key ] = $by_segment[ $key ][0];
    }

    return $result;
}

/**
 * Block editor: informational image-size note under the native Featured Image control
 * of Segment Landing pages (the homepage Segments Section shows that image).
 *
 * The note itself is rendered by js/admin-segment-image-hint.js through the official
 * `editor.PostFeaturedImage` filter. Here we only decide whether it applies: the page
 * must carry a valid segment context (ACF `dp_page_segment_context`, as saved) — the
 * script additionally requires the Segment Landing template to be currently selected.
 * Nothing is enforced; uploads and assignments are not touched.
 */
add_action( 'enqueue_block_editor_assets', 'dreampoint_b2b_enqueue_segment_image_hint' );
function dreampoint_b2b_enqueue_segment_image_hint(): void {
    $screen = get_current_screen();
    $post   = get_post();

    if ( ! $screen || 'page' !== $screen->post_type || ! $post instanceof WP_Post ) {
        return;
    }

    if ( '' === dreampoint_b2b_get_page_segment( $post->ID ) ) {
        return;
    }

    $handle = 'dreampoint-b2b-segment-image-hint';

    wp_enqueue_script(
        $handle,
        get_template_directory_uri() . '/js/admin-segment-image-hint.js',
        [ 'wp-hooks', 'wp-element', 'wp-data', 'wp-editor' ],
        dreampoint_b2b_asset_ver( 'js/admin-segment-image-hint.js' ),
        true
    );

    wp_add_inline_script(
        $handle,
        'window.dpSegmentImageHint = ' . wp_json_encode( [
            'template' => 'page-segment-landing.php',
            'title'    => __( 'Preporučena veličina slike: 500 × 750 px (omjer 2:3).', 'dreampoint-b2b' ),
            'text'     => __( 'Koristite okomitu fotografiju visoke rezolucije. Slika se automatski prilagođava okviru i može biti djelomično odrezana.', 'dreampoint-b2b' ),
        ], JSON_HEX_TAG | JSON_HEX_AMP ) . ';', // HEX flags keep a translated string from closing the inline <script>.
        'before'
    );
}

/**
 * Block editor: Segments Section was registered and saved in `preview` mode (the only ACF block that was), so the
 * editor canvas rendered the whole unstyled tile list while every other ACF block shows the compact "Edit Block"
 * card. The registration now uses `edit` like the others; this script maps already-saved `"mode":"preview"`
 * attributes of `acf/segments-section` to `edit` when the editor parses the content (official
 * `blocks.getBlockAttributes` filter). Nothing is written to the database until the page is saved, and the
 * frontend output is mode-independent.
 */
add_action( 'enqueue_block_editor_assets', 'dreampoint_b2b_enqueue_segments_block_mode' );
function dreampoint_b2b_enqueue_segments_block_mode(): void {
    wp_enqueue_script(
        'dreampoint-b2b-segments-block-mode',
        get_template_directory_uri() . '/js/admin-segments-block-mode.js',
        [ 'wp-hooks' ],
        dreampoint_b2b_asset_ver( 'js/admin-segments-block-mode.js' ),
        true
    );
}

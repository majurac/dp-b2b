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

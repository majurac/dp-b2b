<?php
/**
 * Brandovi logo dropdown (Figma "Brandovi dropdown" 11148:32172, panel 11148:32547).
 *
 * The panel is rendered server-side inside the DESKTOP main-menu item that the owner tagged with
 * the CSS class `brands-toggler` (that class is the only identifier; no text/URL/ID matching).
 * The mobile menu renders the same menu location with `menu_class = first-menu`, so it never gets
 * the panel and keeps a plain link to the brand directory.
 *
 * Brands are real `product_brand` terms (name ASC) with their `thumbnail_id` term-meta logo. The query
 * goes through the normal get_terms() pipeline, so the visibility engine's filter_brand_terms()
 * applies per current user. There is intentionally NO `dp_visibility_context` bypass and NO cache of
 * the rendered HTML (it would be user-dependent).
 *
 * @package Dreampoint_B2B
 */

defined( 'ABSPATH' ) || exit;

/**
 * Visible brands for the current user, with logo markup (empty string when the brand has no logo).
 *
 * @return array<int, array{name: string, url: string, logo: string}>
 */
function dreampoint_b2b_brands_menu_items(): array {
	$terms = get_terms(
		[
			'taxonomy'   => 'product_brand',
			'hide_empty' => false, // Same as the brand directory page (brands.php).
			'orderby'    => 'name',
			'order'      => 'ASC',
		]
	);

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return [];
	}

	// Dropdown shows the first 12 visible brands (name ASC, visibility filter already applied by
	// get_terms()); the full list stays on the brand directory page.
	$terms = array_slice( $terms, 0, 12 );

	// get_terms() primes term meta, so thumbnail_id costs no extra query per brand. Prime the
	// attachment posts and their meta in one go so wp_get_attachment_image() does not run N+1.
	$logo_ids = [];
	foreach ( $terms as $term ) {
		$logo_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
		if ( $logo_id ) {
			$logo_ids[ $term->term_id ] = $logo_id;
		}
	}
	if ( $logo_ids ) {
		get_posts(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post__in'       => array_values( array_unique( $logo_ids ) ),
				'posts_per_page' => count( $logo_ids ),
				'orderby'        => 'post__in',
				'no_found_rows'  => true,
			]
		);
	}

	$items = [];
	foreach ( $terms as $term ) {
		$url = get_term_link( $term );
		if ( is_wp_error( $url ) ) {
			continue;
		}

		$logo = '';
		if ( isset( $logo_ids[ $term->term_id ] ) ) {
			// 'medium' keeps the proportions (the 'thumbnail' size is a hard square crop).
			$logo = wp_get_attachment_image(
				$logo_ids[ $term->term_id ],
				'medium',
				false,
				[
					'class'    => 'brands-menu__logo',
					'alt'      => $term->name,
					'loading'  => 'lazy',
					'decoding' => 'async',
				]
			);
		}

		$items[] = [
			'name' => $term->name,
			'url'  => $url,
			'logo' => $logo,
		];
	}

	return $items;
}

/**
 * Panel markup; empty string when the current user can see no brands.
 *
 * @param string $all_url Destination of "Vidi sve brendove" (the menu item's own URL = brand directory).
 */
function dreampoint_b2b_brands_menu_html( string $all_url ): string {
	$items = dreampoint_b2b_brands_menu_items();
	if ( ! $items ) {
		return '';
	}

	$html  = '<div class="brands-menu" id="brands-menu"><div class="brands-menu__panel">';
	$html .= '<ul class="brands-menu__grid" aria-label="' . esc_attr__( 'Brendovi', 'dreampoint-b2b' ) . '">';

	foreach ( $items as $item ) {
		$html .= '<li class="brands-menu__cell"><a class="brands-menu__link" href="' . esc_url( $item['url'] ) . '">';
		if ( '' !== $item['logo'] ) {
			$html .= $item['logo']; // Already escaped by wp_get_attachment_image().
		} else {
			// No logo: keep the brand reachable with its name (never a substitute image).
			$html .= '<span class="brands-menu__name">' . esc_html( $item['name'] ) . '</span>';
		}
		$html .= '</a></li>';
	}

	$html .= '</ul>';
	$html .= '<a class="brands-menu__all" href="' . esc_url( $all_url ) . '">' . esc_html__( 'Vidi sve brendove', 'dreampoint-b2b' ) . '</a>';
	$html .= '</div></div>';

	return $html;
}

/**
 * Append the panel to the desktop `brands-toggler` item only.
 *
 * @param string   $item_output Link markup of the item.
 * @param WP_Post  $item        Menu item.
 * @param int      $depth       Depth.
 * @param stdClass $args        wp_nav_menu() arguments.
 */
add_filter(
	'walker_nav_menu_start_el',
	static function ( $item_output, $item, $depth, $args ) {
		if (
			0 !== (int) $depth
			|| 'menu-1' !== ( $args->theme_location ?? '' )
			|| 'links' !== ( $args->menu_class ?? '' ) // Desktop render; the mobile one uses `first-menu`.
			|| ! in_array( 'brands-toggler', (array) $item->classes, true )
		) {
			return $item_output;
		}

		return $item_output . dreampoint_b2b_brands_menu_html( (string) $item->url );
	},
	10,
	4
);

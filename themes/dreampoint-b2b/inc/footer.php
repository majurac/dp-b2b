<?php
/**
 * Footer helpers (Figma Step 5): one footer, two layouts.
 *
 *  - compact: homepage only (`is_front_page()`): wordmark + socials, divider, credits.
 *  - full:    every other page: + primary links row (menu location `footer-primary`) + three ACF sitemap columns.
 *
 * Everything here only validates and normalises data that comes from ACF options, the footer menu and
 * WordPress/WooCommerce page settings, so footer.php renders valid links only (no `#`, no drafts).
 *
 * @package Dreampoint_B2B
 */

defined( 'ABSPATH' ) || exit;

/**
 * Footer layout for the current request.
 *
 * @return string 'compact' on the front page, 'full' otherwise.
 */
function dreampoint_b2b_footer_variant(): string {
	return is_front_page() ? 'compact' : 'full';
}

/**
 * A usable link target: absolute http(s) URL or site-relative path; never empty or a bare `#`.
 */
function dreampoint_b2b_footer_valid_url( $url ): bool {
	if ( ! is_string( $url ) ) {
		return false;
	}
	$url = trim( $url );
	if ( '' === $url || '#' === $url[0] ) {
		return false;
	}
	return (bool) preg_match( '#^(https?://|/(?!/))#i', $url );
}

/**
 * Social links from the ACF `socials` repeater. An entry is shown only with a valid URL and a known icon.
 * Figma has SVGs for Facebook, Instagram, X, LinkedIn and YouTube (the ACF choice `twitter` renders the X icon);
 * TikTok / Pinterest fall back to the existing icon-font glyph; WhatsApp / Viber have no icon and are skipped.
 *
 * @param mixed $rows Raw ACF repeater value.
 * @return array<int, array{label: string, url: string, svg: string, glyph: string}>
 */
function dreampoint_b2b_footer_social_links( $rows ): array {
	$map = [
		'facebook'  => [ 'Facebook', 'facebook.svg', '' ],
		'instagram' => [ 'Instagram', 'instagram.svg', '' ],
		'twitter'   => [ 'X', 'x.svg', '' ],
		'linkedin'  => [ 'LinkedIn', 'linkedin.svg', '' ],
		'youtube'   => [ 'YouTube', 'youtube.svg', '' ],
		'tiktok'    => [ 'TikTok', '', 'icon-tiktok' ],
		'pinterest' => [ 'Pinterest', '', 'icon-pinterest' ],
	];
	$out = [];
	if ( ! is_array( $rows ) ) {
		return $out;
	}
	foreach ( $rows as $row ) {
		$key = is_array( $row ) ? (string) ( $row['social_title'] ?? '' ) : '';
		$url = is_array( $row ) ? ( $row['social_url'] ?? '' ) : '';
		if ( ! isset( $map[ $key ] ) || ! dreampoint_b2b_footer_valid_url( $url ) ) {
			continue;
		}
		$out[] = [
			'label' => $map[ $key ][0],
			'url'   => $url,
			'svg'   => $map[ $key ][1],
			'glyph' => $map[ $key ][2],
		];
	}
	return $out;
}

/**
 * Sitemap columns from the ACF `footer_sitemaps` repeater: only columns with a title and at least one
 * valid link survive (so there is never an empty heading or a dead link).
 *
 * @param mixed $rows Raw ACF repeater value.
 * @return array<int, array{title: string, links: array<int, array{title: string, url: string, target: string}>}>
 */
function dreampoint_b2b_footer_sitemap_columns( $rows ): array {
	$out = [];
	if ( ! is_array( $rows ) ) {
		return $out;
	}
	foreach ( $rows as $row ) {
		$title = is_array( $row ) ? trim( (string) ( $row['sitemap_title'] ?? '' ) ) : '';
		$items = is_array( $row ) && is_array( $row['sitemap_items'] ?? null ) ? $row['sitemap_items'] : [];
		$links = [];
		foreach ( $items as $item ) {
			$link = is_array( $item ) ? ( $item['sitemap_item'] ?? null ) : null;
			if ( ! is_array( $link ) || '' === trim( (string) ( $link['title'] ?? '' ) ) || ! dreampoint_b2b_footer_valid_url( $link['url'] ?? '' ) ) {
				continue;
			}
			$links[] = [
				'title'  => (string) $link['title'],
				'url'    => (string) $link['url'],
				'target' => ( $link['target'] ?? '' ) === '_blank' ? '_blank' : '',
			];
		}
		if ( '' !== $title && $links ) {
			$out[] = [ 'title' => $title, 'links' => $links ];
		}
	}
	return $out;
}

/**
 * Legal links of the credits row. Each one appears only when its destination really exists and is published:
 * the WordPress privacy page, the WooCommerce terms page, and a cookie-settings URL supplied through the
 * `dreampoint_b2b_cookie_settings_url` filter (no consent mechanism exists yet, so none by default).
 *
 * @return array<int, array{label: string, url: string}>
 */
function dreampoint_b2b_footer_legal_links(): array {
	$out = [];

	$privacy = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';
	if ( dreampoint_b2b_footer_valid_url( $privacy ) ) {
		$out[] = [ 'label' => __( 'Politika privatnosti', 'dreampoint-b2b' ), 'url' => $privacy ];
	}

	$terms_id = function_exists( 'wc_terms_and_conditions_page_id' ) ? (int) wc_terms_and_conditions_page_id() : 0;
	if ( $terms_id && 'publish' === get_post_status( $terms_id ) ) {
		$out[] = [ 'label' => __( 'Uvjeti korištenja', 'dreampoint-b2b' ), 'url' => (string) get_permalink( $terms_id ) ];
	}

	$cookies = apply_filters( 'dreampoint_b2b_cookie_settings_url', '' );
	if ( dreampoint_b2b_footer_valid_url( $cookies ) ) {
		$out[] = [ 'label' => __( 'Postavke kolačića', 'dreampoint-b2b' ), 'url' => (string) $cookies ];
	}

	return $out;
}

/**
 * The newsletter band is rendered only for a genuinely working Mailchimp for WP setup: a numeric form ID that
 * points to a published `mc4wp-form` AND a stored API key. Otherwise no band (no empty grey block).
 *
 * @param mixed $form_id ACF `subscribe_form_id`.
 */
function dreampoint_b2b_newsletter_ready( $form_id ): bool {
	if ( ! is_numeric( $form_id ) || (int) $form_id <= 0 || ! shortcode_exists( 'mc4wp_form' ) ) {
		return false;
	}
	if ( 'publish' !== get_post_status( (int) $form_id ) || 'mc4wp-form' !== get_post_type( (int) $form_id ) ) {
		return false;
	}
	$mc4wp = get_option( 'mc4wp' );
	return is_array( $mc4wp ) && ! empty( $mc4wp['api_key'] );
}

/**
 * Footer primary menu: drop items without a real destination (`#` / empty), so a dead link never renders.
 */
add_filter(
	'wp_nav_menu_objects',
	static function ( $items, $args ) {
		if ( 'footer-primary' !== ( $args->theme_location ?? '' ) ) {
			return $items;
		}
		return array_values(
			array_filter(
				$items,
				static fn( $item ) => dreampoint_b2b_footer_valid_url( (string) $item->url )
			)
		);
	},
	10,
	2
);

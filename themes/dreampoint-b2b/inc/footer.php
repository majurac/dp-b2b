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
 * The five footer social icons, always returned in Figma order. The URL comes from the ACF `socials` repeater
 * (choice `twitter` = X); an empty / invalid URL yields '' so footer.php renders a non-interactive icon.
 * Other repeater choices (tiktok, pinterest, whatsapp, viber) are intentionally not rendered.
 *
 * @param mixed $rows Raw ACF repeater value.
 * @return array<int, array{label: string, url: string, svg: string}>
 */
function dreampoint_b2b_footer_social_links( $rows ): array {
	$icons = [
		'facebook'  => [ 'Facebook', 'facebook.svg' ],
		'instagram' => [ 'Instagram', 'instagram.svg' ],
		'twitter'   => [ 'X', 'x.svg' ],
		'linkedin'  => [ 'LinkedIn', 'linkedin.svg' ],
		'youtube'   => [ 'YouTube', 'youtube.svg' ],
	];
	$urls = [];
	foreach ( is_array( $rows ) ? $rows : [] as $row ) {
		$key = is_array( $row ) ? (string) ( $row['social_title'] ?? '' ) : '';
		$url = is_array( $row ) ? ( $row['social_url'] ?? '' ) : '';
		// First valid URL per platform wins.
		if ( isset( $icons[ $key ] ) && ! isset( $urls[ $key ] ) && dreampoint_b2b_footer_valid_url( $url ) ) {
			$urls[ $key ] = trim( $url );
		}
	}
	$out = [];
	foreach ( $icons as $key => [ $label, $svg ] ) {
		$out[] = [ 'label' => $label, 'url' => $urls[ $key ] ?? '', 'svg' => $svg ];
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
 * The three legal items of the credits row, always in this order. `url` is '' when no confirmed destination
 * exists yet, and footer.php then renders the label as plain text (never a dead link):
 *  - Politika privatnosti: the WordPress privacy page, only when published (get_privacy_policy_url() is '' for drafts);
 *  - Opći uvjeti poslovanja: the WooCommerce terms page, only when assigned and published;
 *  - Postavke kolačića: only through the `dreampoint_b2b_cookie_settings_url` filter (no consent mechanism yet).
 *
 * @return array<int, array{label: string, url: string}>
 */
function dreampoint_b2b_footer_legal_links(): array {
	$privacy = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';

	$terms    = '';
	$terms_id = function_exists( 'wc_terms_and_conditions_page_id' ) ? (int) wc_terms_and_conditions_page_id() : 0;
	if ( $terms_id && 'publish' === get_post_status( $terms_id ) ) {
		$terms = (string) get_permalink( $terms_id );
	}

	$cookies = apply_filters( 'dreampoint_b2b_cookie_settings_url', '' );

	return [
		[ 'label' => __( 'Politika privatnosti', 'dreampoint-b2b' ), 'url' => dreampoint_b2b_footer_valid_url( $privacy ) ? $privacy : '' ],
		[ 'label' => __( 'Opći uvjeti poslovanja', 'dreampoint-b2b' ), 'url' => dreampoint_b2b_footer_valid_url( $terms ) ? $terms : '' ],
		[ 'label' => __( 'Postavke kolačića', 'dreampoint-b2b' ), 'url' => dreampoint_b2b_footer_valid_url( $cookies ) ? (string) $cookies : '' ],
	];
}

/**
 * Copyright line. A non-empty ACF `copyright` replaces the whole line (owner-controlled);
 * otherwise "© {current year} DreamPoint. Sva prava pridržana."
 *
 * @param mixed $acf_text ACF `copyright` value.
 */
function dreampoint_b2b_footer_copyright( $acf_text ): string {
	$acf_text = is_string( $acf_text ) ? trim( $acf_text ) : '';
	if ( '' !== $acf_text ) {
		return $acf_text;
	}
	/* translators: %s: current year */
	return sprintf( __( '© %s DreamPoint. Sva prava pridržana.', 'dreampoint-b2b' ), date_i18n( 'Y' ) );
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

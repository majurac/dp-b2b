<?php
/**
 * E6-A: create the two DRAFT legal pages (Privacy Policy, Terms) with the Rich Text template.
 *
 * Run with WP-CLI as the site user:  wp --path=<wp root> eval-file <this file>
 * Idempotent: a page whose slug already exists is skipped, never modified. Pages are always created as drafts.
 * Does not touch any WordPress/WooCommerce setting (privacy page, WC terms page) and does not publish anything.
 * The HTML files next to this script are verbatim copies of the dream-point.hr pages (2026-11-09) and are a
 * DRAFT BASELINE only: the client reviews and adapts the legal text before go-live.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( "Run through WP-CLI: wp eval-file\n" );
}

$template = 'rich-text-page.php';
if ( ! locate_template( $template ) ) {
	exit( "ABORT: template {$template} is not in the active theme (deploy the theme code first).\n" );
}

$pages = [
	[ 'title' => 'Politika privatnosti', 'slug' => 'politika-privatnosti', 'file' => 'privacy-policy.html' ],
	[ 'title' => 'Opći uvjeti poslovanja', 'slug' => 'opci-uvjeti-poslovanja', 'file' => 'terms.html' ],
];

foreach ( $pages as $page ) {
	if ( get_page_by_path( $page['slug'], OBJECT, 'page' ) ) {
		echo "SKIP (exists): {$page['slug']}\n";
		continue;
	}

	$content = file_get_contents( __DIR__ . '/' . $page['file'] );
	if ( false === $content || '' === trim( $content ) ) {
		exit( "ABORT: cannot read {$page['file']}\n" );
	}

	$id = wp_insert_post(
		[
			'post_type'     => 'page',
			'post_status'   => 'draft',
			'post_title'    => $page['title'],
			'post_name'     => $page['slug'],
			'post_content'  => $content,
			'page_template' => $template,
		],
		true
	);

	echo is_wp_error( $id )
		? 'ERROR ' . $page['slug'] . ': ' . $id->get_error_message() . "\n"
		: "CREATED {$page['slug']} ID={$id} status=draft template={$template}\n";
}

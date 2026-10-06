<?php
/**
 * LOCAL-ONLY: renders the REAL Quick Order template + the REAL localized `dpQuickOrder` config (incl. the Croatian
 * import copy) into a static page, so the modal can be exercised in a browser without a login:
 *
 *   php wp-cli.phar eval-file wp-content/plugins/dp-b2b-quick-order/tests/import/render-harness.php
 *
 * Output: tests/import/.generated/harness.html (git-ignored). Rendering as admin inside WP-CLI is just template
 * output — no session, cookie or credential is created. REST URLs are replaced by the test runner with mocks.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! str_contains( home_url(), 'localhost' ) ) {
	WP_CLI::error( 'Local-only.' );
}

wp_set_current_user( 1 );

// Make is_page('quick-order') true so the plugin's own enqueue() runs and localizes its config.
$page = get_page_by_path( DP_Quick_Order_Config::PAGE_SLUG );
if ( ! $page ) {
	WP_CLI::error( 'quick-order page not found locally.' );
}
global $wp_query, $post;
$wp_query = new WP_Query( [ 'page_id' => $page->ID, 'post_type' => 'page' ] );
$wp_query->the_post();
do_action( 'wp_enqueue_scripts' );

$data   = wp_scripts()->get_data( 'dp-quick-order', 'data' );
$markup = do_shortcode( '[' . DP_Quick_Order_Config::SHORTCODE . ']' );

$theme = get_template_directory_uri();
$qo    = DP_QUICK_ORDER_URL;
$html  = '<!doctype html><html lang="hr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
	. '<title>QO import harness</title>'
	. '<link rel="stylesheet" href="' . esc_url( $theme . '/style.css' ) . '">'
	. '<link rel="stylesheet" href="' . esc_url( $qo . 'assets/dist/quick-order.css?ver=harness' ) . '">'
	. '</head><body class="page-template quick-order-harness"><main id="main">' . $markup . '</main>'
	. '<script>' . $data . '</script>'
	. '</body></html>';

$dir = __DIR__ . '/.generated';
if ( ! is_dir( $dir ) ) {
	mkdir( $dir, 0777, true );
}
file_put_contents( $dir . '/harness.html', $html );
WP_CLI::success( 'Wrote ' . $dir . '/harness.html (' . strlen( $html ) . ' bytes)' );

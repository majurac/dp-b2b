<?php
/**
 * The template for displaying 404 pages (not found)
 *
 * @link https://codex.wordpress.org/Creating_an_Error_404_Page
 *
 * @package Dreampoint_B2B
 * @version 1.1.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

get_header();

// Set proper 404 HTTP status header
status_header(404);
nocache_headers();
?>
	<div id="not-found" class="block">
		<div class="container">
			<div class="not-found-holder">
				<p class="nf-code" aria-hidden="true">404</p>
				<h1><?php esc_html_e( 'Stranica nije pronađena', 'dreampoint-b2b' ); ?></h1>
				<p class="nf-text"><?php esc_html_e( 'Stranica koju tražite ne postoji ili je premještena.', 'dreampoint-b2b' ); ?></p>
				<a href="<?php echo esc_url(home_url('/')); ?>" class="button button--xl button--back"><?php esc_html_e( 'Vrati se na početnu', 'dreampoint-b2b' ); ?></a>
			</div>
			<!-- /.not-found-holder -->
		</div>
		<!-- /.container -->
	</div>
	<!-- /#not-found -->

<?php
get_footer();

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
				<div class="nf-icon"><img src="<?php bloginfo('template_directory'); ?>/img/ico/face-sad.svg" alt=""></div>
				<h1><?php esc_html_e( 'Ups! Stranica nije pronađena.', 'dreampoint-b2b' ); ?></h1>
				<p><?php esc_html_e( 'Izgleda da na ovoj lokaciji ništa nije pronađeno.', 'dreampoint-b2b' ); ?></p>
				<a href="<?php echo esc_url(home_url('/')); ?>" class="button button--xl"><?php esc_html_e( 'Početna', 'dreampoint-b2b' ); ?></a>
				<!-- /.button button--xl -->
			</div>
			<!-- /.not-found-holder -->
		</div>
		<!-- /.container -->
	</div>
	<!-- /#not-found -->

<?php
get_footer();

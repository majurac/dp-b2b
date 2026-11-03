<?php
/**
 * Shared storefront "no results" component (Figma CAT-05 11148:40433).
 *
 * Rendered by woocommerce/loop/no-products-found.php, which WooCommerce also uses for the
 * WBW AJAX responses (WBW re-fetches the archive/search URL and swaps the server HTML).
 * Whether a WBW filter is active is only known in the browser (WBW's own selected-filters
 * node), so elements are tagged with data-dp-nr-show="plain|filtered" and js/no-results.js
 * toggles them. Server default = "plain" (no filters), which is also the no-JS state.
 *
 * @package Dreampoint_B2B
 */

defined( 'ABSPATH' ) || exit;

$search = is_search() ? trim( (string) get_search_query( false ) ) : '';

// Base URL: same context without WBW parameters (search keeps only `s` + `post_type`).
if ( is_search() ) {
	$base_url = add_query_arg(
		array(
			's'         => $search,
			'post_type' => 'product',
		),
		home_url( '/' )
	);
} elseif ( is_tax() || is_category() || is_tag() ) {
	$term_link = get_term_link( get_queried_object() );
	$base_url  = is_wp_error( $term_link ) ? wc_get_page_permalink( 'shop' ) : $term_link;
} else {
	$base_url = wc_get_page_permalink( 'shop' );
}

// Intro sentences: [ state ][ has search ]. Shown/hidden by js/no-results.js.
$intros = array(
	'plain'    => $search
		/* translators: %s: search query */
		? __( 'Za pojam “%s” nije pronađen nijedan proizvod. Pokušajte sljedeće:', 'dreampoint-b2b' )
		: __( 'Nije pronađen nijedan proizvod. Pokušajte sljedeće:', 'dreampoint-b2b' ),
	'filtered' => $search
		/* translators: %s: search query */
		? __( 'Za pojam “%s” i odabrane filtre nije pronađen nijedan proizvod. Pokušajte sljedeće:', 'dreampoint-b2b' )
		: __( 'Za odabrane filtre nije pronađen nijedan proizvod. Pokušajte sljedeće:', 'dreampoint-b2b' ),
);
?>
<div class="woocommerce-no-products-found dp-no-results" data-dp-no-results role="status">
	<div class="dp-no-results__inner">
		<img class="dp-no-results__icon" src="<?php echo esc_url( get_template_directory_uri() . '/img/ico/search-empty.svg' ); ?>" width="144" height="144" alt="">
		<h2 class="dp-no-results__title"><?php esc_html_e( 'Nismo pronašli proizvode', 'dreampoint-b2b' ); ?></h2>
		<?php foreach ( $intros as $state => $intro ) : ?>
			<p class="dp-no-results__text" data-dp-nr-show="<?php echo esc_attr( $state ); ?>"<?php echo 'filtered' === $state ? ' hidden' : ''; ?>><?php echo esc_html( sprintf( $intro, $search ) ); ?></p>
		<?php endforeach; ?>
		<ul class="dp-no-results__tips">
			<?php if ( '' !== $search ) : ?>
				<li><?php esc_html_e( 'Provjeriti pravopis ili koristiti drugi pojam za pretragu', 'dreampoint-b2b' ); ?></li>
			<?php endif; ?>
			<li data-dp-nr-show="filtered" hidden><?php esc_html_e( 'Ukloniti neke filtre kako biste vidjeli više rezultata', 'dreampoint-b2b' ); ?></li>
			<li><?php esc_html_e( 'Pregledati ostale kategorije ili brendove', 'dreampoint-b2b' ); ?></li>
		</ul>
		<div class="dp-no-results__actions">
			<a class="button dp-no-results__btn" href="<?php echo esc_url( $base_url ); ?>" data-dp-nr-action="clear" data-dp-nr-show="filtered" hidden><?php esc_html_e( 'Očisti sve filtre', 'dreampoint-b2b' ); ?></a>
			<a class="button button--outline dp-no-results__btn" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Pogledaj sve proizvode', 'dreampoint-b2b' ); ?></a>
		</div>
	</div>
</div>

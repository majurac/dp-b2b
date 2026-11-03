<?php
/**
 * FAQ page helpers (template faq.php): category tabs built from the `faq-category` taxonomy.
 *
 * Filtering is server-side (?faq_cat=<slug>) so the query, the page count and the
 * pagination always operate on the same result set. No AJAX, no new dependencies.
 *
 * @package Dreampoint_B2B
 */

defined( 'ABSPATH' ) || exit;

/**
 * Non-empty FAQ categories in creation order (term_id ASC).
 *
 * @return WP_Term[]
 */
function dreampoint_b2b_faq_get_terms(): array {
	$terms = get_terms(
		[
			'taxonomy'   => 'faq-category',
			'hide_empty' => true,
			'orderby'    => 'term_id',
			'order'      => 'ASC',
		]
	);

	return is_array( $terms ) ? array_values( $terms ) : [];
}

/**
 * Category selected by ?faq_cat=<slug>; the first category when missing or unknown.
 *
 * @param WP_Term[] $terms Result of dreampoint_b2b_faq_get_terms().
 */
function dreampoint_b2b_faq_get_active_term( array $terms ): ?WP_Term {
	if ( ! $terms ) {
		return null;
	}

	$requested = isset( $_GET['faq_cat'] ) ? sanitize_title( wp_unslash( $_GET['faq_cat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.

	foreach ( $terms as $term ) {
		if ( $term->slug === $requested ) {
			return $term;
		}
	}

	return $terms[0];
}

/**
 * URL of a category view (the first category uses the clean page URL).
 *
 * @param WP_Term[] $terms Result of dreampoint_b2b_faq_get_terms().
 */
function dreampoint_b2b_faq_term_url( WP_Term $term, array $terms ): string {
	$page_url = get_permalink();

	if ( $terms && $terms[0]->term_id === $term->term_id ) {
		return $page_url;
	}

	return add_query_arg( 'faq_cat', $term->slug, $page_url );
}

/**
 * Category navigation (plain links + aria-current; the page reloads with the filtered list).
 */
function dreampoint_b2b_faq_render_tabs(): void {
	$terms = dreampoint_b2b_faq_get_terms();

	if ( count( $terms ) < 2 ) {
		return;
	}

	$active = dreampoint_b2b_faq_get_active_term( $terms );
	?>
	<nav class="faq-tabs" aria-label="<?php esc_attr_e( 'Kategorije pitanja', 'dreampoint-b2b' ); ?>">
		<ul>
			<?php foreach ( $terms as $term ) : ?>
				<?php $is_active = $active && $active->term_id === $term->term_id; ?>
				<li>
					<a href="<?php echo esc_url( dreampoint_b2b_faq_term_url( $term, $terms ) ); ?>"<?php echo $is_active ? ' class="is-active" aria-current="page"' : ''; ?>>
						<?php echo esc_html( $term->name ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</nav>
	<?php
}

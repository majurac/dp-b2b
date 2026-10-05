<?php
defined( 'ABSPATH' ) || exit;

// Initial checkbox state mirrors the exact REST boolean semantics
// (rest_sanitize_boolean — the same function WP's REST schema layer uses
// for a 'type' => 'boolean' arg) so the server-rendered first paint can
// never disagree with what the REST endpoint would actually interpret.
// Absent, '0', 'false', '', etc. are all correctly falsy — never checked.
// Initial search value mirrors the URL (authoritative state) for the first paint;
// ProductList re-derives it from the URL on load/back/forward.
$dp_qo_search_term = isset( $_GET['qo_search'] ) ? sanitize_text_field( wp_unslash( $_GET['qo_search'] ) ) : '';
// Popular searches are optional presentation config supplied by the theme through a
// WP filter (it reads the ACF option). No theme/ACF => empty list => the row is simply
// not rendered; Quick Order never depends on it.
$dp_qo_popular_terms = [];
foreach ( (array) apply_filters( 'dp_qo_popular_searches', [] ) as $dp_qo_popular_term ) {
	$dp_qo_popular_term = is_string( $dp_qo_popular_term ) ? trim( sanitize_text_field( $dp_qo_popular_term ) ) : '';
	if ( '' !== $dp_qo_popular_term ) {
		$dp_qo_popular_terms[] = $dp_qo_popular_term;
	}
}
$dp_qo_popular_terms = array_slice( array_values( array_unique( $dp_qo_popular_terms ) ), 0, 6 );
$dp_qo_active_filters = [
	'qo_already_ordered' => isset( $_GET['qo_already_ordered'] ) && rest_sanitize_boolean( wp_unslash( $_GET['qo_already_ordered'] ) ),
	'qo_new'             => isset( $_GET['qo_new'] ) && rest_sanitize_boolean( wp_unslash( $_GET['qo_new'] ) ),
	'qo_best_seller'     => isset( $_GET['qo_best_seller'] ) && rest_sanitize_boolean( wp_unslash( $_GET['qo_best_seller'] ) ),
];
?>
<div id="dp-quick-order"
	class="dp-quick-order"
	data-rest-url="<?php echo esc_attr( rest_url( DP_Quick_Order_Config::REST_NAMESPACE . '/' ) ); ?>"
	data-nonce="<?php echo esc_attr( wp_create_nonce( DP_Quick_Order_Config::NONCE_ACTION ) ); ?>"
>
	<?php
	/*
	 * WBW AJAX compatibility placeholder — do not remove.
	 *
	 * WBW's native filter widgets (Brand, Sort By, In Stock, etc. — the
	 * [wpf-filters] shortcodes below) look for a product-loop container to
	 * inject their own AJAX response into. Quick Order renders its own
	 * <table> instead of a WooCommerce ul.products loop, so without a target
	 * WBW falls back to a full location.reload() before it ever dispatches
	 * its own wpfAjaxSuccess event — which is what Quick Order's JS
	 * (product-list.js) actually listens to. This element is configured as
	 * the "Product List / Loader Selector" on WBW filter views 2 and 3 (WBW
	 * admin > Filters > edit > Options tab) solely so that check finds a
	 * target and stops reloading. Quick Order never reads its content —
	 * WBW's injected HTML lands here and is discarded. See readme.md.
	 */
	?>
	<div class="dp-qo-wbw-ajax-placeholder" hidden aria-hidden="true"></div>

	<div class="container">
		<div class="row">

			<div class="col-lg-3">
				<div class="dp-qo-filters-header">
					<span class="dp-qo-filters-header__title"><?php esc_html_e( 'Filteri', 'dp-b2b-quick-order' ); ?></span>
					<button type="button" class="dp-qo-catalog-filters__clear">
						<?php esc_html_e( 'Poništi filtere', 'dp-b2b-quick-order' ); ?>
					</button>
				</div>

				<fieldset class="dp-qo-catalog-filters">
					<legend class="dp-qo-catalog-filters__legend">
						<button
							type="button"
							class="dp-qo-catalog-filters__toggle"
							aria-expanded="true"
							aria-controls="dp-qo-catalog-filters-content"
						>
							<?php esc_html_e( 'Popularno', 'dp-b2b-quick-order' ); ?>
							<span class="dp-qo-catalog-filters__toggle-icon" aria-hidden="true">−</span>
						</button>
					</legend>

					<div class="dp-qo-catalog-filters__content" id="dp-qo-catalog-filters-content">
						<label class="dp-qo-catalog-filter" for="dp-qo-filter-already-ordered">
							<input
								type="checkbox"
								id="dp-qo-filter-already-ordered"
								class="dp-qo-catalog-filter__input"
								data-qo-filter="qo_already_ordered"
								<?php checked( $dp_qo_active_filters['qo_already_ordered'] ); ?>
							>
							<span class="dp-qo-catalog-filter__label"><?php esc_html_e( 'Već naručeno', 'dp-b2b-quick-order' ); ?></span>
						</label>

						<label class="dp-qo-catalog-filter" for="dp-qo-filter-new">
							<input
								type="checkbox"
								id="dp-qo-filter-new"
								class="dp-qo-catalog-filter__input"
								data-qo-filter="qo_new"
								<?php checked( $dp_qo_active_filters['qo_new'] ); ?>
							>
							<span class="dp-qo-catalog-filter__label"><?php esc_html_e( 'Novo', 'dp-b2b-quick-order' ); ?></span>
						</label>

						<label class="dp-qo-catalog-filter" for="dp-qo-filter-best-seller">
							<input
								type="checkbox"
								id="dp-qo-filter-best-seller"
								class="dp-qo-catalog-filter__input"
								data-qo-filter="qo_best_seller"
								<?php checked( $dp_qo_active_filters['qo_best_seller'] ); ?>
							>
							<span class="dp-qo-catalog-filter__label"><?php esc_html_e( 'Best seller', 'dp-b2b-quick-order' ); ?></span>
						</label>
					</div>
				</fieldset>

				<?php if ( shortcode_exists( 'wpf-filters' ) ) : ?>
				<div class="dp-qo-filter-area">
					<?php echo do_shortcode( '[wpf-filters id="3"]' ); ?>
				</div>
				<?php endif; ?>
			</div>

			<div class="col-lg-9">

				<form class="dp-qo-search" role="search" novalidate>
					<label class="dp-qo-search__label" for="dp-qo-search-input">
						<?php esc_html_e( 'Pretraga proizvoda', 'dp-b2b-quick-order' ); ?>
					</label>
					<input
						type="text"
						id="dp-qo-search-input"
						class="dp-qo-search__input"
						value="<?php echo esc_attr( $dp_qo_search_term ); ?>"
						placeholder="<?php esc_attr_e( 'Pretražite naziv, kataloški broj, SKU ili EAN', 'dp-b2b-quick-order' ); ?>"
						autocomplete="off"
						enterkeyhint="search"
					>
					<button type="button" class="dp-qo-search__clear" aria-label="<?php esc_attr_e( 'Očisti pretragu', 'dp-b2b-quick-order' ); ?>" hidden>&times;</button>
				</form>

				<?php
				/*
				 * Search/filter state row — exactly one of the two groups is visible at a time
				 * (toggled by ProductList from the URL/WBW state, never from its own state):
				 *   popular → no active FILTER (a search term alone does not count);
				 *   active  → at least one QO-owned or WBW filter is active; chips are rendered
				 *             by ProductList (QO chips from qo_* URL state, WBW chips derived from
				 *             WBW's own checked inputs — the remove control delegates to WBW).
				 */
				?>
				<div class="dp-qo-state-row" data-qo-state="popular"<?php echo $dp_qo_popular_terms ? '' : ' hidden'; ?>>
					<span class="dp-qo-state-row__label"><?php esc_html_e( 'Popularne pretrage', 'dp-b2b-quick-order' ); ?></span>
					<ul class="dp-qo-chip-list dp-qo-chip-list--popular">
						<?php foreach ( $dp_qo_popular_terms as $dp_qo_popular_term ) : ?>
							<li><button type="button" class="dp-qo-filter-chip dp-qo-filter-chip--term" data-qo-popular="<?php echo esc_attr( $dp_qo_popular_term ); ?>"><?php echo esc_html( $dp_qo_popular_term ); ?></button></li>
						<?php endforeach; ?>
					</ul>
				</div>
				<div class="dp-qo-state-row" data-qo-state="active" hidden>
					<span class="dp-qo-state-row__label"><?php esc_html_e( 'Aktivni filteri', 'dp-b2b-quick-order' ); ?></span>
					<ul class="dp-qo-chip-list dp-qo-chip-list--active"></ul>
				</div>

				<div class="dp-qo-pagination"></div>

				<div class="dp-qo-toolbar">
					<div class="dp-qo-toolbar__filters">
						<div class="selected-prod_atributes">
							<?php echo do_shortcode('[wpf-selected-filters id=3]'); ?>
							    <?php
						    $attribute_taxonomies = wc_get_attribute_taxonomies();
						    if ( ! empty( $attribute_taxonomies ) ) {
						        foreach ( $attribute_taxonomies as $attribute ) {
						            $slug = 'pa_' . $attribute->attribute_name;

									if (empty($GLOBALS['_var_atts_in']['attribute_' . $slug])) {
										continue;
									}

						            $terms = get_terms([
						                'taxonomy'   => $slug,
						                'hide_empty' => true,
						            ]);

						            if ( empty( $terms ) || is_wp_error( $terms ) ) continue;

						            $label = wc_attribute_label( $slug );

						            // Detect WBW filter param (e.g. wpf_filter_boja=crna%7Cplava)
						            $param_key = 'wpf_filter_' . $attribute->attribute_name;
						            $selected_terms = [];
						            if ( ! empty( $_GET[ $param_key ] ) ) {
						                $selected_terms = array_filter( explode( '|', sanitize_text_field( $_GET[ $param_key ] ) ) );
						            }

						            $count = count( $selected_terms );
						            $selected_class = $count > 0 ? 'selected' : '';

						            echo '<div class="wpf_item wpf_item_' . esc_attr( $slug ) . ' ' . esc_attr( $selected_class ) . '">';
						            echo '<div class="wpf_item_name">' . esc_html( $label );
						            if ( $count ) {
						                echo ' <span class="count">' . intval( $count ) . '</span>';
						            }
						            echo '</div></div>';
						        }
						    }
						    ?>
						</div><!-- /.selected-atributes (WBW black box — untouched) -->

						<div class="dp-qo-selected-variations" hidden></div>
					</div><!-- /.dp-qo-toolbar__filters -->

					<div class="dp-qo-sort">
						<?php echo do_shortcode('[wpf-filters id=2]'); ?>
					</div>
				</div><!-- /.dp-qo-toolbar -->

				<?php
				/*
				 * Product list: one bordered card per parent product (identity on the left) holding
				 * one or more purchasable lines (option / stock / price / quantity). Rendered by
				 * ProductList. The column labels are a visual aid only — every field is
				 * self-describing for assistive technology (aria-hidden header, labelled inputs).
				 */
				?>
				<div class="dp-qo-table-wrap">
					<div class="dp-qo-table">
						<div class="dp-qo-thead" aria-hidden="true">
							<div class="dp-qo-thead__product"><?php esc_html_e( 'Proizvod', 'dp-b2b-quick-order' ); ?></div>
							<div class="dp-qo-thead__cols">
								<span><?php esc_html_e( 'Opcija', 'dp-b2b-quick-order' ); ?></span>
								<span><?php esc_html_e( 'Stanje', 'dp-b2b-quick-order' ); ?></span>
								<span><?php esc_html_e( 'Cijena', 'dp-b2b-quick-order' ); ?></span>
								<span></span>
								<span></span>
							</div>
						</div>
						<div class="dp-qo-tbody" aria-label="<?php esc_attr_e( 'Proizvodi', 'dp-b2b-quick-order' ); ?>">
							<div class="dp-qo-loading"><?php esc_html_e( 'Učitavanje...', 'dp-b2b-quick-order' ); ?></div>
						</div>
					</div>
				</div>

				<?php /* Summary: sticky inside the product column (CSS), content driven by local state. */ ?>
				<div class="dp-qo-footer" role="region" aria-label="<?php esc_attr_e( 'Sažetak narudžbe', 'dp-b2b-quick-order' ); ?>">
					<div class="dp-qo-footer__summary">
						<svg class="dp-qo-footer__icon" width="32" height="32" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M16 3.5 28 9.7v12.6L16 28.5 4 22.3V9.7L16 3.5Z"/><path d="M4 9.7 16 16l12-6.3M16 16v12.5"/></svg>
						<div class="dp-qo-footer__counts" aria-live="polite">
							<strong class="dp-qo-footer__items">0 <?php esc_html_e( 'artikala', 'dp-b2b-quick-order' ); ?></strong>
							<span class="dp-qo-footer__rows">0 <?php esc_html_e( 'različitih SKU-a', 'dp-b2b-quick-order' ); ?></span>
						</div>
					</div>
					<div class="dp-qo-footer__total">
						<span class="dp-qo-footer__total-label"><?php esc_html_e( 'Ukupno (bez PDV-a)', 'dp-b2b-quick-order' ); ?></span>
						<strong class="dp-qo-footer__subtotal-amount"></strong>
					</div>
					<div class="dp-qo-footer__actions">
						<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="dp-qo-footer__cart-link">
							<?php esc_html_e( 'Pregled košarice', 'dp-b2b-quick-order' ); ?>
						</a>
						<button type="button" class="dp-qo-footer__add-to-cart" disabled>
							<?php esc_html_e( 'Dodaj u košaricu', 'dp-b2b-quick-order' ); ?>
						</button>
					</div>
				</div>

			</div><!-- .col-lg-9 -->

		</div><!-- .row -->
	</div><!-- .container -->

</div><!-- #dp-quick-order -->

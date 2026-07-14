<?php
defined( 'ABSPATH' ) || exit;

// Initial checkbox state mirrors the exact REST boolean semantics
// (rest_sanitize_boolean — the same function WP's REST schema layer uses
// for a 'type' => 'boolean' arg) so the server-rendered first paint can
// never disagree with what the REST endpoint would actually interpret.
// Absent, '0', 'false', '', etc. are all correctly falsy — never checked.
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
	<div class="container">
		<div class="row">

			<div class="col-lg-3">
				<fieldset class="dp-qo-catalog-filters">
					<legend class="dp-qo-catalog-filters__legend"><?php esc_html_e( 'Brzi filteri', 'dp-b2b-quick-order' ); ?></legend>

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
				</fieldset>

				<?php if ( shortcode_exists( 'wpf-filters' ) ) : ?>
				<div class="dp-qo-filter-area">
					<?php echo do_shortcode( '[wpf-filters id="3"]' ); ?>
				</div>
				<?php endif; ?>
			</div>

			<div class="col-lg-9">

				<div class="dp-qo-pagination"></div>

				<div class="dp-qo-toolbar">
					<div class="dp-qo-toolbar__filters">
						<span class="dp-qo-toolbar__label"><?php esc_html_e( 'Aktivni filteri', 'dp-b2b-quick-order' ); ?></span>

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

				<div class="dp-qo-table-wrap">
					<table class="dp-qo-table">
						<thead>
							<tr>
								<th class="dp-qo-col-thumb"></th>
								<th><?php esc_html_e( 'Naziv', 'dp-b2b-quick-order' ); ?></th>
								<th><?php esc_html_e( 'Stanje', 'dp-b2b-quick-order' ); ?></th>
								<th><?php esc_html_e( 'Cijena', 'dp-b2b-quick-order' ); ?></th>
								<th><?php esc_html_e( 'Kol.', 'dp-b2b-quick-order' ); ?></th>
							</tr>
						</thead>
						<tbody class="dp-qo-tbody">
							<tr>
								<td colspan="5" class="dp-qo-loading">
									<?php esc_html_e( 'Učitavanje...', 'dp-b2b-quick-order' ); ?>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

			</div><!-- .col-lg-9 -->

		</div><!-- .row -->
	</div><!-- .container -->

	<div class="dp-qo-footer">
		<div class="dp-qo-footer__summary">
			<i class="icon-shopping-bag dp-qo-footer__icon" aria-hidden="true"></i>
			<span class="dp-qo-footer__items">0 <?php esc_html_e( 'artikala', 'dp-b2b-quick-order' ); ?></span>
			<span class="dp-qo-footer__rows">0 <?php esc_html_e( 'varijacija', 'dp-b2b-quick-order' ); ?></span>
		</div>
		<div class="dp-qo-footer__total">
			<span class="dp-qo-footer__total-label"><?php esc_html_e( 'Ukupno (bez PDV-a)', 'dp-b2b-quick-order' ); ?></span>
			<span class="dp-qo-footer__subtotal-amount"></span>
		</div>
		<div class="dp-qo-footer__actions">
			<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="dp-qo-footer__cart-link button button--outline">
				<?php esc_html_e( 'Pregled košarice', 'dp-b2b-quick-order' ); ?>
			</a>
			<button type="button" class="dp-qo-footer__add-to-cart  button" disabled>
				<?php esc_html_e( 'Dodaj u košaricu', 'dp-b2b-quick-order' ); ?>
			</button>
			
		</div>
	</div>
</div><!-- #dp-quick-order -->

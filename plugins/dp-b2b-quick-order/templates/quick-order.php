<?php
defined( 'ABSPATH' ) || exit;
?>
<div id="dp-quick-order"
	class="dp-quick-order"
	data-rest-url="<?php echo esc_attr( rest_url( DP_Quick_Order_Config::REST_NAMESPACE . '/' ) ); ?>"
	data-nonce="<?php echo esc_attr( wp_create_nonce( DP_Quick_Order_Config::NONCE_ACTION ) ); ?>"
>
	<div class="container">
		<div class="row">

			<div class="col-lg-3">
				<?php if ( shortcode_exists( 'wpf-filters' ) ) : ?>
				<div class="dp-qo-filter-area">
					<?php echo do_shortcode( '[wpf-filters id="1"]' ); ?>
				</div>
				<?php endif; ?>
			</div>

			<div class="col-lg-9">

				<div class="dp-qo-pagination"></div>

				<div class="dp-qo-table-wrap">
					<table class="dp-qo-table">
						<thead>
							<tr>
								<th class="dp-qo-col-thumb"></th>
								<th data-sort="title"><?php esc_html_e( 'Naziv', 'dp-b2b-quick-order' ); ?><span class="dp-qo-sort-arrow" aria-hidden="true"></span></th>
								<th><?php esc_html_e( 'Stanje', 'dp-b2b-quick-order' ); ?></th>
								<th data-sort="price"><?php esc_html_e( 'Cijena', 'dp-b2b-quick-order' ); ?><span class="dp-qo-sort-arrow" aria-hidden="true"></span></th>
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
			<button type="button" class="dp-qo-footer__add-to-cart" disabled>
				<?php esc_html_e( 'Dodaj u košaricu', 'dp-b2b-quick-order' ); ?>
			</button>
			<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="dp-qo-footer__cart-link">
				<?php esc_html_e( 'Pregled košarice →', 'dp-b2b-quick-order' ); ?>
			</a>
		</div>
	</div>
</div><!-- #dp-quick-order -->

<?php
defined( 'ABSPATH' ) || exit;
?>
<div id="dp-quick-order"
	class="dp-quick-order"
	data-rest-url="<?php echo esc_attr( rest_url( DP_Quick_Order_Config::REST_NAMESPACE . '/' ) ); ?>"
	data-nonce="<?php echo esc_attr( wp_create_nonce( DP_Quick_Order_Config::NONCE_ACTION ) ); ?>"
>
	<div class="dp-qo-pagination"></div>

	<div class="dp-qo-table-wrap">
		<table class="dp-qo-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Naziv', 'dp-b2b-quick-order' ); ?></th>
					<th><?php esc_html_e( 'Stanje', 'dp-b2b-quick-order' ); ?></th>
					<th><?php esc_html_e( 'Cijena', 'dp-b2b-quick-order' ); ?></th>
					<th><?php esc_html_e( 'Varijacija', 'dp-b2b-quick-order' ); ?></th>
					<th><?php esc_html_e( 'Kol.', 'dp-b2b-quick-order' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody class="dp-qo-tbody">
				<tr>
					<td colspan="6" class="dp-qo-loading">
						<?php esc_html_e( 'Učitavanje...', 'dp-b2b-quick-order' ); ?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="dp-qo-footer">
		<div class="dp-qo-footer__total"></div>
		<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="dp-qo-footer__cart-link">
			<?php esc_html_e( 'Idi na košaricu →', 'dp-b2b-quick-order' ); ?>
		</a>
	</div>
</div>

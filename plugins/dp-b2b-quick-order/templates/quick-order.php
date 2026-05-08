<?php
defined( 'ABSPATH' ) || exit;
?>
<div id="dp-quick-order"
	class="dp-quick-order"
	data-rest-url="<?php echo esc_attr( rest_url( DP_Quick_Order_Config::REST_NAMESPACE . '/' ) ); ?>"
	data-nonce="<?php echo esc_attr( wp_create_nonce( DP_Quick_Order_Config::NONCE_ACTION ) ); ?>"
>
	<div class="dp-quick-order__toolbar">
		<?php /* Filters and search — Phase 2 */ ?>
	</div>

	<div class="dp-quick-order__table">
		<?php /* Product table — Phase 2 */ ?>
	</div>

	<div class="dp-quick-order__cart-footer">
		<?php /* Sticky cart summary — Phase 2 */ ?>
	</div>
</div>

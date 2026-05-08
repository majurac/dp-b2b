<?php
defined( 'ABSPATH' ) || exit;
?>
<div id="dp-quick-order"
	class="dp-quick-order"
	data-rest-url="<?php echo esc_attr( rest_url( 'dreampoint-b2b/v1/' ) ); ?>"
	data-nonce="<?php echo esc_attr( wp_create_nonce( 'wc_store_api' ) ); ?>"
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

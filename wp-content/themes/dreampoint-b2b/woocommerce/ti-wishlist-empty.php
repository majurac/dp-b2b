<?php
/**
 * The Template for displaying empty wishlist.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/ti-wishlist-empty.php.
 *
 * @version             2.5.2
 * @package           TInvWishlist\Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>
<div class="tinv-wishlist woocommerce tinv-wishlist-clear">
	<?php do_action( 'tinvwl_before_wishlist', $wishlist ); ?>
	<?php if ( function_exists( 'wc_print_notices' ) && isset( WC()->session ) ) {
		wc_print_notices();
	} ?>
	<div id="not-found">
		<div class="container">
			<div class="not-found-holder">
				<div class="nf-icon"><img src="<?php bloginfo('template_directory'); ?>/img/ico/face-sad.svg" alt=""></div>
				<h1><?php esc_html_e( 'Nema proizvoda u listi želja!', 'dreampoint-b2b' ); ?></h1>
				<p class="cart-empty"><?php printf( esc_html__( 'Trenutno nemate ništa dodato u Listi želja. Kako biste dodali artikle, posjetite trgovinu, te kliknite na ikonicu %s koja se nalazi na 	donjem desnom uglu kartice.', 'dreampoint-b2b' ), '<i class="icon-heart"></i>'); ?></p>
				<a href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>" class="button button--xl"><?php esc_html_e( 'Posjeti trgovinu', 'dreampoint-b2b' ); ?> <i class="icon-shopping-bag"></i></a>
			</div>
			<!-- /.not-found-holder -->
		</div>
		<!-- /.container -->
	</div>
	<!-- /#not-found -->

	<?php do_action( 'tinvwl_wishlist_is_empty' ); ?>
</div>

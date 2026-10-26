<?php
/**
 * Empty cart page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/cart/cart-empty.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 7.0.1
 */

defined( 'ABSPATH' ) || exit;

/*
 * @hooked wc_empty_cart_message - 10
 */
do_action( 'woocommerce_cart_is_empty' );

// Quick Order action only when the plugin page resolves (same guard as the header button).
$quick_order_url = dreampoint_b2b_get_quick_order_url();
?>

<div id="not-found">
	<div class="container">
		<div class="not-found-holder">
			<div class="nf-icon"><img src="<?php bloginfo('template_directory'); ?>/img/ico/face-sad.svg" alt=""></div>
			<h1><?php esc_html_e( 'Vaša košarica je prazna', 'dreampoint-b2b' ); ?></h1>
			<?php if ( $quick_order_url ) : ?>
				<p><?php esc_html_e( 'Izgleda da još niste dodali nijedan proizvod. Pretražite proizvode ili koristite Quick Order za brže dodavanje.', 'dreampoint-b2b' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Čini se da još niste dodali nijedan proizvod u svoju košaricu. Pregledajte našu ponudu i pronađite nešto što vam odgovara!', 'dreampoint-b2b' ); ?></p>
			<?php endif; ?>
			<div class="nf-actions">
				<a href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>" class="button button--xl"><?php esc_html_e( 'Posjeti trgovinu', 'dreampoint-b2b' ); ?> <i class="icon-shopping-bag"></i></a>
				<?php if ( $quick_order_url ) : ?>
					<a href="<?php echo esc_url( $quick_order_url ); ?>" class="button button--xl button--outline"><?php esc_html_e( 'Quick Order', 'dreampoint-b2b' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<!-- /.not-found-holder -->
	</div>
	<!-- /.container -->
</div>
<!-- /#not-found -->


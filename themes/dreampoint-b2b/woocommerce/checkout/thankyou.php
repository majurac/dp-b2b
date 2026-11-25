<?php
/**
 * Thankyou page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/thankyou.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 11.2.0
 *
 * @var WC_Order $order
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="woocommerce-order">

	<?php
	if ( $order ) :

		do_action( 'woocommerce_before_thankyou', $order->get_id() );
		?>

		<?php if ( $order->has_status( 'failed' ) ) : ?>

			<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed"><?php esc_html_e( 'Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.', 'woocommerce' ); ?></p>

			<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed-actions">
				<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="button pay"><?php esc_html_e( 'Pay', 'woocommerce' ); ?></a>
				<?php if ( is_user_logged_in() ) : ?>
					<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="button pay"><?php esc_html_e( 'My account', 'woocommerce' ); ?></a>
				<?php endif; ?>
			</p>

		<?php else : ?>
			<?php
			// Order view is offered only to the logged-in order owner (same condition as the e-mail row below).
			$dp_can_view_order = is_user_logged_in() && $order->get_user_id() === get_current_user_id();
			?>
			<div class="ty-intro block">
				<img class="ty-icon" src="<?php echo esc_url( get_template_directory_uri() . '/img/ico/emoji-normal.svg' ); ?>" width="144" height="144" alt="">
				<h1><?php esc_html_e( 'Narudžba je uspješno poslana', 'dreampoint-b2b' ); ?></h1>
				<p class="ty-order-number">
					<?php esc_html_e( 'Broj narudžbe:', 'dreampoint-b2b' ); ?>
					<?php echo esc_html( $order->get_order_number() ); ?>
				</p>
				<p>
					<?php esc_html_e( 'Vaša narudžba je zaprimljena i proslijeđena u obradu.', 'dreampoint-b2b' ); ?>
					<?php if ( 'bacs' === $order->get_payment_method() ) : ?>
						<br>
						<?php esc_html_e( 'Plaćanje se ne vrši putem B2B shopa.', 'dreampoint-b2b' ); ?>
						<br>
						<?php esc_html_e( 'Faktura će biti poslana naknadno na vašu e-mail adresu.', 'dreampoint-b2b' ); ?>
					<?php endif; ?>
				</p>
				<div class="ty-actions">
					<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="button button--xl button--outline"><?php esc_html_e( 'Nastavi kupovinu', 'dreampoint-b2b' ); ?></a>
					<?php if ( $dp_can_view_order ) : ?>
						<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>" class="button button--xl"><?php esc_html_e( 'Pregled narudžbe', 'dreampoint-b2b' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
			<!-- /.ty-intro -->

			<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">

				<li class="woocommerce-order-overview__order order">
					<?php esc_html_e( 'Order number:', 'woocommerce' ); ?>
					<strong><?php echo $order->get_order_number(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
				</li>

				<li class="woocommerce-order-overview__status status">
					<?php esc_html_e( 'Status:', 'woocommerce' ); ?>
					<strong><?php echo wc_get_order_status_name( $order->get_status()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
				</li>

				<li class="woocommerce-order-overview__date date">
					<?php esc_html_e( 'Date:', 'woocommerce' ); ?>
					<strong><?php echo wc_format_datetime( $order->get_date_created() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
				</li>

				<?php if ( is_user_logged_in() && $order->get_user_id() === get_current_user_id() && $order->get_billing_email() ) : ?>
					<li class="woocommerce-order-overview__email email">
						<?php esc_html_e( 'Email:', 'woocommerce' ); ?>
						<strong><?php echo $order->get_billing_email(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
					</li>
				<?php endif; ?>

				<li class="woocommerce-order-overview__total total">
					<?php esc_html_e( 'Total:', 'woocommerce' ); ?>
					<strong><?php echo $order->get_formatted_order_total(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
				</li>

				<?php if ( $order->get_payment_method_title() ) : ?>
					<li class="woocommerce-order-overview__payment-method method">
						<?php esc_html_e( 'Payment method:', 'woocommerce' ); ?>
						<strong><?php echo wp_kses_post( $order->get_payment_method_title() ); ?></strong>
					</li>
				<?php endif; ?>

			</ul>

		<?php endif; ?>

		<?php do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() ); ?>
		<?php do_action( 'woocommerce_thankyou', $order->get_id() ); ?>

	<?php else : ?>

		<?php wc_get_template( 'checkout/order-received.php', array( 'order' => false ) ); ?>

	<?php endif; ?>

	<?php if ( ! $order || $order->has_status( 'failed' ) ) : ?>
		<div class="action-btn-holder"><a href="<?php echo esc_url(home_url('/')); ?>" class="button button--xl"><?php esc_html_e( 'Početna', 'dreampoint-b2b' ); ?> </a></div>
		<!-- /.action-btn-holder -->
	<?php endif; ?>

</div>

<style>
	.woocommerce-silkypress-input-field-block {display: none;}
</style>
<?php
/**
 * Order details
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/order-details.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.1.0
 *
 * @var bool $show_downloads Controls whether the downloads table should be rendered.
 */

 // phpcs:disable WooCommerce.Commenting.CommentHooks.MissingHookComment

defined( 'ABSPATH' ) || exit;

$order = wc_get_order( $order_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

if ( ! $order ) {
	return;
}

$order_items        = $order->get_items( apply_filters( 'woocommerce_purchase_order_item_types', 'line_item' ) );
$show_purchase_note = $order->has_status( apply_filters( 'woocommerce_purchase_note_order_statuses', array( 'completed', 'processing' ) ) );
$downloads          = $order->get_downloadable_items();

// We make sure the order belongs to the user. This will also be true if the user is a guest, and the order belongs to a guest (userID === 0).
$show_customer_details = $order->get_user_id() === get_current_user_id();

if ( $show_downloads ) {
	wc_get_template(
		'order/order-downloads.php',
		array(
			'downloads'  => $downloads,
			'show_title' => true,
		)
	);
}
?>

<?php if ( WC()->query->get_current_endpoint() == 'view-order' ): ?>
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
<section class="woocommerce-order-details">
	<?php do_action( 'woocommerce_order_details_before_order_table', $order ); ?>

	<div class="wod-title-holder">
		<h2 class="woocommerce-order-details__title"><?php esc_html_e( 'Order details', 'woocommerce' ); ?></h2>
	</div>
	<!-- /.wod-title-holder -->

	<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">

		<thead>
			<tr>
				<th class="woocommerce-table__product-name product-name"><?php esc_html_e( 'Product', 'woocommerce' ); ?></th>
				<th class="woocommerce-table__product-table product-total"><?php esc_html_e( 'Total', 'woocommerce' ); ?></th>
			</tr>
		</thead>

		<tbody>
			<?php
			do_action( 'woocommerce_order_details_before_order_table_items', $order );

			foreach ( $order_items as $item_id => $item ) {
				$product = $item->get_product();

				wc_get_template(
					'order/order-details-item.php',
					array(
						'order'              => $order,
						'item_id'            => $item_id,
						'item'               => $item,
						'show_purchase_note' => $show_purchase_note,
						'purchase_note'      => $product ? $product->get_purchase_note() : '',
						'product'            => $product,
					)
				);
			}

			do_action( 'woocommerce_order_details_after_order_table_items', $order );
			?>
		</tbody>


		<tfoot>
			<?php
			foreach ( $order->get_order_item_totals() as $key => $total ) {
				?>
					<tr>
						<th scope="row"><?php echo esc_html( $total['label'] ); ?></th>
						<td><?php echo wp_kses_post( $total['value'] ); ?></td>
					</tr>
					<?php
			}
			?>
			<?php if ( $order->get_customer_note() ) : ?>
				<tr>
					<th><?php esc_html_e( 'Note:', 'woocommerce' ); ?></th>
					<td>
					<?php
					$customer_note = wc_wptexturize_order_note( $order->get_customer_note() );
					echo wp_kses( nl2br( $customer_note ), array( 'br' => array() ) );
					?>
					</td>
				</tr>
			<?php endif; ?>
		</tfoot>
	</table>

	<?php do_action( 'woocommerce_order_details_after_order_table', $order ); ?>
</section>




<?php if (!is_wc_endpoint_url('order-received')) : ?>
    <div class="action-btns-row">
        <?php $orders_url = wc_get_account_endpoint_url('orders'); ?>
        <a href="<?php echo esc_url($orders_url); ?>" class="button button--outline button--sm">
            <?php esc_html_e( 'Vrati se na moje narudžbe', 'dreampoint-b2b' ); ?>
        </a>

        <?php if (!$order->has_status('completed')) : ?>
            <div class="order-again-button">
                <button class="button button--sm" disabled>Naruči ponovo</button>
                <!-- /.button button--sm -->
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>document.addEventListener('DOMContentLoaded', function () {
    // Select the 'p.order-again' element
    const orderAgain = document.querySelector('p.order-again');
    
    // Select the '.action-btns-row' container
    const actionBtnsRow = document.querySelector('.action-btns-row');
    
    // Check if both elements exist before attempting to move
    if (orderAgain && actionBtnsRow) {
        actionBtnsRow.appendChild(orderAgain);
    }
});
</script>

<?php
/**
 * Action hook fired after the order details.
 *
 * @since 4.4.0
 * @param WC_Order $order Order data.
 */
//do_action( 'woocommerce_after_order_details', $order );

//if ( $show_customer_details ) {
	//wc_get_template( 'order/order-details-customer.php', array( 'order' => $order ) );
//}
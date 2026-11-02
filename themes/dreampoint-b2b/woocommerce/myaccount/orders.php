<?php
/**
 * Orders
 *
 * Shows orders on the account page.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/orders.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.5.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_account_orders', $has_orders ); ?>

<?php if ( $has_orders ) : ?>

	<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
		<thead>
			<tr>
				<?php foreach ( wc_get_account_orders_columns() as $column_id => $column_name ) : ?>
					<th scope="col" class="woocommerce-orders-table__header woocommerce-orders-table__header-<?php echo esc_attr( $column_id ); ?>"><span class="nobr"><?php echo esc_html( $column_name ); ?></span></th>
				<?php endforeach; ?>
			</tr>
		</thead>

		<tbody>
			<?php
			foreach ( $customer_orders->orders as $customer_order ) {
				$order      = wc_get_order( $customer_order ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				?>
				<tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-<?php echo esc_attr( $order->get_status() ); ?> order">
					<?php foreach ( wc_get_account_orders_columns() as $column_id => $column_name ) :
						$is_order_number = 'order-number' === $column_id;
					?>
						<?php if ( $is_order_number ) : ?>
							<th class="woocommerce-orders-table__cell woocommerce-orders-table__cell-<?php echo esc_attr( $column_id ); ?>" data-title="<?php echo esc_attr( $column_name ); ?>" scope="row">
						<?php else : ?>
							<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-<?php echo esc_attr( $column_id ); ?>" data-title="<?php echo esc_attr( $column_name ); ?>">
						<?php endif; ?>

							<?php if ( has_action( 'woocommerce_my_account_my_orders_column_' . $column_id ) ) : ?>
								<?php do_action( 'woocommerce_my_account_my_orders_column_' . $column_id, $order ); ?>

							<?php elseif ( $is_order_number ) : ?>
								<?php /* translators: %s: the order number, usually accompanied by a leading # */ ?>
								<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'View order number %s', 'woocommerce' ), $order->get_order_number() ) ); ?>">
									<?php echo esc_html( _x( '#', 'hash before order number', 'woocommerce' ) . $order->get_order_number() ); ?>
								</a>

							<?php elseif ( 'order-status' === $column_id ) : ?>
								<span class="order-status"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span>
								<!-- /.order-status -->

							<?php elseif ( 'order-date' === $column_id ) : ?>
								<time datetime="<?php echo esc_attr( $order->get_date_created()->date( 'c' ) ); ?>"><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></time>

							

							<?php elseif ( 'order-total' === $column_id ) : ?>
								<?php echo wp_kses_post( $order->get_formatted_order_total() ); ?>

							<?php elseif ( 'order-actions' === $column_id ) : ?>
								<?php
								$actions = wc_get_account_orders_actions( $order );

								if ( ! empty( $actions ) ) {
									foreach ( $actions as $key => $action ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
										if ( empty( $action['aria-label'] ) ) {
											// Generate the aria-label based on the action name.
											/* translators: %1$s Action name, %2$s Order number. */
											$action_aria_label = sprintf( __( '%1$s order number %2$s', 'woocommerce' ), $action['name'], $order->get_order_number() );
										} else {
											$action_aria_label = $action['aria-label'];
										}
										// Figma Secondary M; only "view" carries the arrow.
										$action_classes = 'button button--sm button--outline' . ( 'view' === $key ? ' button--icon-after' : '' );
										// Figma MPR-05 label for the "view" action; the accessible name (aria-label) is untouched.
										$action_label = 'view' === $key ? __( 'Pogledaj', 'dreampoint-b2b' ) : $action['name'];
										echo '<a href="' . esc_url( $action['url'] ) . '" class="woocommerce-button' . esc_attr( $wp_button_class ) . ' ' . esc_attr( $action_classes ) . ' ' . sanitize_html_class( $key ) . '" aria-label="' . esc_attr( $action_aria_label ) . '" title="' . esc_attr( $action_label ) . '">' . esc_html( $action_label ) . '</a>';
										unset( $action_aria_label );
									}
								}
								?>
							<?php endif; ?>

						<?php if ( $is_order_number ) : ?>
							</th>
						<?php else : ?>
							</td>
						<?php endif; ?>
					<?php endforeach; ?>
				</tr>
				<?php
			}
			?>
		</tbody>
	</table>

	<?php do_action( 'woocommerce_before_account_orders_pagination' ); ?>

	<?php if ( 1 < $customer_orders->max_num_pages ) : ?>
		<?php
		// Numbered pagination built from the existing WooCommerce orders query (page count and current
		// page); the page URLs come from the same account endpoint helper WooCommerce uses for prev/next.
		$orders_page_base = str_replace( '999999999', '%#%', wc_get_endpoint_url( 'orders', 999999999 ) );
		?>
		<nav class="woocommerce-pagination woocommerce-orders-pagination" aria-label="<?php esc_attr_e( 'Paginacija narudžbi', 'dreampoint-b2b' ); ?>">
			<?php
			echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core escapes the links.
				array(
					'base'      => $orders_page_base,
					'format'    => '',
					'total'     => (int) $customer_orders->max_num_pages,
					'current'   => (int) $current_page,
					'type'      => 'list',
					'mid_size'  => 1,
					'end_size'  => 1,
					'add_args'  => false,
					'prev_text' => '<i class="icon-chevron-left" aria-hidden="true"></i> <span>' . esc_html__( 'Nazad', 'dreampoint-b2b' ) . '</span>',
					'next_text' => '<span>' . esc_html__( 'Naprijed', 'dreampoint-b2b' ) . '</span> <i class="icon-chevron-right" aria-hidden="true"></i>',
				)
			);
			?>
		</nav>
	<?php endif; ?>

<?php else : ?>
	<div class="not-found-holder">
		<div class="nf-icon"><img src="<?php bloginfo('template_directory'); ?>/img/ico/face-sad.svg" alt=""></div>
		<h2><?php esc_html_e( 'Nema povijesti narudžbi', 'dreampoint-b2b' ); ?></h2>
		<p><?php esc_html_e( 'Trenutno nemate kreiranih narudžbi. Kako biste kreirali narudžbu, posjetite trgovinu, dodajte proizvode u svoju košaricu i napravite narudžbu.', 'dreampoint-b2b' ); ?></p>
		<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="button button--xl"><?php esc_html_e( 'Posjeti trgovinu', 'dreampoint-b2b' ); ?></a>
		<!-- /.button button--xl -->
	</div>
	<!-- /.not-found-holder -->	

<?php endif; ?>

<?php do_action( 'woocommerce_after_account_orders', $has_orders ); ?>



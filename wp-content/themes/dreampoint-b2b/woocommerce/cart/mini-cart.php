<?php
/**
 * Mini-cart
 *
 * Contains the markup for the mini-cart, used by the cart widget.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/cart/mini-cart.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.0.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_mini_cart' ); ?>

<div class="sliding-content-heading mini-cart-heading">
	<span class="sliding-content-title mini-cart-title"><?php esc_html_e( 'Mini košarica', 'woocommerce' ); ?></span>
	<button class="sliding-content-close mini-cart-close" aria-label="<?php esc_attr_e( 'Zatvori mini košaricu', 'dreampoint-b2b' ); ?>"><i class="icon-xmark"></i></button>
</div>
<!-- /.mini-cart-heading -->

<?php if ( WC()->cart && ! WC()->cart->is_empty() ) : ?>

	<ul class="woocommerce-mini-cart cart_list product_list_widget <?php echo esc_attr( $args['list_class'] ); ?>">
		<?php
		do_action( 'woocommerce_before_mini_cart_contents' );

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

			if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_widget_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
				/**
				 * This filter is documented in woocommerce/templates/cart/cart.php.
				 *
				 * @since 2.1.0
				 */
				$product_name      = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );
				$thumbnail         = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key );
				$product_price     = apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key );
				$product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
				?>
				<li class="woocommerce-mini-cart-item <?php echo esc_attr( apply_filters( 'woocommerce_mini_cart_item_class', 'mini_cart_item', $cart_item, $cart_item_key ) ); ?>">
					
					<?php if ( empty( $product_permalink ) ) : ?>
					    <?php echo $thumbnail . '<span class="product-title">' . wp_kses_post( $product_name ) . '</span>'; ?>
					<?php else : ?>
					    <a href="<?php echo esc_url( $product_permalink ); ?>" class="thumb-title">
					        <?php echo $thumbnail . '<span class="product-title">' . wp_kses_post( $product_name ) . '</span>'; ?>
					    </a>
					<?php endif; ?>

					<?php echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<div class="quantity-price">
						<span class="qp-label"><?php esc_html_e( 'Cijena', 'dreampoint-b2b' ); ?>:</span>
						<!-- /.qp-label -->
						<?php
						$quantity = $cart_item['quantity'];
						$price_html = '<span class="price-container">' . $product_price . '</span>';

						echo apply_filters(
							'woocommerce_widget_cart_item_quantity',
							sprintf(
								'<span class="quantity"><span class="value">%s &times;</span> %s</span>',
								esc_html( $quantity ),
								$price_html
							),
							$cart_item,
							$cart_item_key
						); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					</div>

					<!-- /.quantity-price -->
					<?php
					echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						'woocommerce_cart_item_remove_link',
						sprintf(
							'<a href="%s" class="remove remove_from_cart_button" aria-label="%s" data-product_id="%s" data-cart_item_key="%s" data-product_sku="%s" data-success_message="%s"><i class="icon-trashcan"></i></a>',
							esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
							/* translators: %s is the product name */
							esc_attr( sprintf( __( 'Remove %s from cart', 'woocommerce' ), wp_strip_all_tags( $product_name ) ) ),
							esc_attr( $product_id ),
							esc_attr( $cart_item_key ),
							esc_attr( $_product->get_sku() ),
							/* translators: %s is the product name */
							esc_attr( sprintf( __( '&ldquo;%s&rdquo; has been removed from your cart', 'woocommerce' ), wp_strip_all_tags( $product_name ) ) )
						),
						$cart_item_key
					);
					?>
				</li>
				<?php
			}
		}

		do_action( 'woocommerce_mini_cart_contents' );
		?>
	</ul>

	<p class="woocommerce-mini-cart__total total">
		<?php
		/**
		 * Hook: woocommerce_widget_shopping_cart_total.
		 *
		 * @hooked woocommerce_widget_shopping_cart_subtotal - 10
		 */
		do_action( 'woocommerce_widget_shopping_cart_total' );
		?>
	</p>

	<?php do_action( 'woocommerce_widget_shopping_cart_before_buttons' ); ?>

	<p class="woocommerce-mini-cart__buttons buttons"> 
		<a class="button" href="<?php echo esc_url( wc_get_checkout_url() ); ?>" title="<?php echo esc_attr__( 'Idi na naplatu', 'dreampoint-b2b' ); ?>">
			<?php esc_html_e( 'Idi na naplatu', 'dreampoint-b2b' ); ?>
		</a>

		<a class="button button--outline" href="<?php echo esc_url( wc_get_cart_url() ); ?>" title="<?php echo esc_attr__( 'Vidi košaricu', 'dreampoint-b2b' ); ?>">
			<?php esc_html_e( 'Vidi košaricu', 'dreampoint-b2b' ); ?>
		</a>

     </p>

	<?php do_action( 'woocommerce_widget_shopping_cart_after_buttons' ); ?>

<?php else : ?>

	<p class="woocommerce-mini-cart__empty-message"><?php esc_html_e( 'No products in the cart.', 'woocommerce' ); ?></p>

<?php endif; ?>

<?php do_action( 'woocommerce_after_mini_cart' ); ?>

<?php
/**
 * Cart Page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/cart/cart.php.
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
 */

defined( 'ABSPATH' ) || exit;

$count = WC()->cart->get_cart_contents_count();

do_action( 'woocommerce_before_cart' ); ?>

<div id="cart" class="block">
	<div class="container">
		<div class="row">
			<div class="col-lg-7">
				<form class="woocommerce-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
				
					<div class="shop_table shop_table_responsive cart woocommerce-cart-form__contents" cellspacing="0">
					    <div>
					        <?php do_action( 'woocommerce_before_cart_contents' ); ?>

					        <?php
					        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
					            $_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
					            $product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );
					            /**
					             * Filter the product name.
					             *
					             * @since 2.1.0
					             * @param string $product_name Name of the product in the cart.
					             * @param array $cart_item The product in the cart.
					             * @param string $cart_item_key Key for the product in the cart.
					             */
					            $product_name = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );

					            if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
					                $product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
					                ?>
					                <div class="woocommerce-cart-form__cart-item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">

				               


					                    <div class="product-remove">
					                        <?php
					                            echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					                                'woocommerce_cart_item_remove_link',
					                                sprintf(
					                                    '<a href="%s" class="remove" aria-label="%s" data-product_id="%s" data-product_sku="%s"><i class="icon-trashcan"></i></a>',
					                                    esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
					                                    /* translators: %s is the product name */
					                                    esc_attr( sprintf( __( 'Remove %s from cart', 'woocommerce' ), wp_strip_all_tags( $product_name ) ) ),
					                                    esc_attr( $product_id ),
					                                    esc_attr( $_product->get_sku() )
					                                ),
					                                $cart_item_key
					                            );
					                        ?>
					                    </div>

					                    <div class="product-thumbnail">
					                    <?php
					                    $thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key );

					                    if ( ! $product_permalink ) {
					                        echo $thumbnail; // PHPCS: XSS ok.
					                    } else {
					                        printf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $thumbnail ); // PHPCS: XSS ok.
					                    }
					                    ?>
					                    </div>
					                    <div class="product-name-sm">
					                    	<div class="product-name" data-title="<?php esc_attr_e( 'Product', 'woocommerce' ); ?>">
					                    	<?php
					                    	if ( ! $product_permalink ) {
					                    	    echo wp_kses_post( $product_name . '&nbsp;' );
					                    	} else {
					                    	    /**
					                    	     * This filter is documented above.
					                    	     *
					                    	     * @since 2.1.0
					                    	     */
					                    	    echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', sprintf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $_product->get_name() ), $cart_item, $cart_item_key ) );
					                    	}
					                    	
					                    	do_action( 'woocommerce_after_cart_item_name', $cart_item, $cart_item_key );
					                    	
					                    	// Meta data.
					                    	echo wc_get_formatted_cart_item_data( $cart_item ); // PHPCS: XSS ok.
					                    	
					                    	// Backorder notification.
					                    	if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ) {
					                    	    echo wp_kses_post( apply_filters( 'woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . esc_html__( 'Available on backorder', 'woocommerce' ) . '</p>', $product_id ) );
					                    	}
					                    	?>
					                    	</div>
					                    </div>
					                    <!-- /.product-name-sm -->

					                    <div class="product-rest">
					                    	<div class="product-name hide-sm" data-title="<?php esc_attr_e( 'Product', 'woocommerce' ); ?>">
					                    	<?php
					                    	if ( ! $product_permalink ) {
					                    	    echo wp_kses_post( $product_name . '&nbsp;' );
					                    	} else {
					                    	    /**
					                    	     * This filter is documented above.
					                    	     *
					                    	     * @since 2.1.0
					                    	     */
					                    	    echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', sprintf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $_product->get_name() ), $cart_item, $cart_item_key ) );
					                    	}
					                    	
					                    	do_action( 'woocommerce_after_cart_item_name', $cart_item, $cart_item_key );
					                    	
					                    	// Meta data.
					                    	echo wc_get_formatted_cart_item_data( $cart_item ); // PHPCS: XSS ok.
					                    	
					                    	// Backorder notification.
					                    	if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ) {
					                    	    echo wp_kses_post( apply_filters( 'woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . esc_html__( 'Available on backorder', 'woocommerce' ) . '</p>', $product_id ) );
					                    	}
					                    	?>
					                    	</div>
					                    	<div class="product-footer">
					                    		<div class="product-quantity" data-title="<?php esc_attr_e( 'Quantity', 'woocommerce' ); ?>">
					                    		    <div class="qty-number">
					                    		        <div class="number">
					                    		            <span class="minus"><i class="icon-arrow-left"></i></span>
					                    		            <?php
					                    		            if ( $_product->is_sold_individually() ) {
					                    		                $min_quantity = 1;
					                    		                $max_quantity = 1;
					                    		            } else {
					                    		                $min_quantity = 0;
					                    		                $max_quantity = $_product->get_max_purchase_quantity();
					                    		            }
					                    							        
					                    		            $product_quantity = woocommerce_quantity_input(
					                    		                array(
					                    		                    'input_name'   => "cart[{$cart_item_key}][qty]",
					                    		                    'input_value'  => $cart_item['quantity'],
					                    		                    'max_value'    => $max_quantity,
					                    		                    'min_value'    => $min_quantity,
					                    		                    'product_name' => $product_name,
					                    		                ),
					                    		                $_product,
					                    		                false
					                    		            );
					                    							        
					                    		            echo apply_filters( 'woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item ); // PHPCS: XSS ok.
					                    		            ?>
					                    		            <span class="plus"><i class="icon-arrow-right"></i></span>
					                    		        </div>
					                    		    </div>
					                    		    <!-- /.qty-number -->
					                    		</div>
					                    		
					                    		<div class="product-total">
					                    			<div class="product-price" data-title="<?php esc_attr_e( 'Price', 'woocommerce' ); ?>">
					                    				<span class="label"><?php esc_html_e( 'Cijena', 'dreampoint-b2b' ); ?></span>
					                    			    <?php
					                    			        echo apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key ); // PHPCS: XSS ok.
					                    			    ?>
					                    			</div>
					                    			
					                    			
					                    			
					                    			<div class="product-subtotal" data-title="<?php esc_attr_e( 'Subtotal', 'woocommerce' ); ?>">
					                    			    <span class="label"><?php esc_html_e( 'Ukupno', 'dreampoint-b2b' ); ?></span>
					                    			    <!-- /.label -->
					                    			    <?php
					                    			        echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // PHPCS: XSS ok.
					                    			    ?>
					                    			</div>
					                    		</div>
					                    		<!-- /.product-total -->
					                    	</div>
					                    	<!-- /.product-footer -->
					                    </div>
					                    <!-- /.product-rest -->
					                </div>
					                <?php
					            }
					        }
					        ?>

					        <?php do_action( 'woocommerce_cart_contents' ); ?>

					        <div>
					            <div colspan="6" class="actions">


					                <button type="submit" class="button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="update_cart" value="<?php esc_attr_e( 'Update cart', 'woocommerce' ); ?>"><?php esc_html_e( 'Update cart', 'woocommerce' ); ?></button>

					                <?php do_action( 'woocommerce_cart_actions' ); ?>

					                <?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
					            </div>
					        </div>

					        <?php do_action( 'woocommerce_after_cart_contents' ); ?>
					    </div>
					</div>
					<?php do_action( 'woocommerce_after_cart_table' ); ?>
				</form>
				<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="button button--outline link-continue"><?php esc_html_e( 'Nastavi kupovinu', 'woocommerce' ); ?></a>
			</div>
			<!-- /.col-lg-7 -->
			
			<div class="col-lg-5">
				<?php do_action( 'woocommerce_before_cart_collaterals' ); ?>
	
				<div class="cart-collaterals">
					<div class="cart_totals">
						<h3><?php esc_html_e( 'Ukupna vrijednost košarice', 'dreampoint-b2b' ); ?></h3>
	                    <table class="shop_table shop_table_responsive">

						    <tr>
						        <td><?php esc_html_e( 'Ukupno', 'woocommerce' ); ?></td>
						        <td>
						            <div class="price-container">
						                <?php echo WC()->cart->get_cart_subtotal(); // Already formatted ?>
						            </div>
						        </td>
						    </tr>

						     <tr>
						        <td><?php esc_html_e( 'Dostava', 'woocommerce' ); ?></td>
						        <td>
						            <div class="price-container">
						               <?php echo WC()->cart->get_cart_shipping_total(); ?>
						            </div>
						        </td>
						    </tr>

						    <?php foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : ?>
					    <tr class="tax-rate tax-rate-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
					        <td><?php echo esc_html( $tax->label ); ?></td>
					        <td>
					            <div class="price-container">
					                <?php echo wp_kses_post( $tax->formatted_amount ); ?>
					            </div>
					        </td>
					    </tr>
					    <?php endforeach; ?>

						    <?php $getcoupons = WC()->cart->get_coupons(); ?>
						    <?php if ( $getcoupons ) : ?>
						        <?php foreach ( $getcoupons as $code => $coupon ) : ?>
						            <tr class="cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
						                <td><?php echo esc_html( wc_cart_totals_coupon_label( $coupon ) ); ?></td>
						                <td data-title="<?php echo esc_attr( wc_cart_totals_coupon_label( $coupon, false ) ); ?>">
						                    <?php wc_cart_totals_coupon_html( $coupon ); ?>
						                </td>
						            </tr>
						        <?php endforeach; ?>
						    <?php else : ?>
						        <tr class="coupon-expand custom-form">
						            <td colspan="2">
						                <form class="woocommerce-coupon-form" method="post">
						                    <?php if ( wc_coupons_enabled() ) : ?>
						                        <div class="content">
						                            <div class="form-group">
						                                <input type="text" name="coupon_code" class="input-text" id="coupon_code" value="" placeholder="<?php esc_attr_e( 'Unesi kupon kod', 'woocommerce' ); ?>" /> 
						                                <button type="submit" class="button" name="apply_coupon" value="<?php esc_attr_e( 'Primijeni kupon', 'woocommerce' ); ?>">
						                                    <?php esc_html_e( 'Primijeni kupon', 'woocommerce' ); ?>
						                                </button>
						                                <?php do_action( 'woocommerce_cart_coupon' ); ?>
						                            </div>
						                            <!-- /.form-group -->
						                        </div>
						                        <!-- /.content -->
						                    <?php endif; ?>
						                </form>
						            </td>
						        </tr>
						    <?php endif; ?>
						</table>

	                    <div class="total-area">
	                        <span class="total-label"><?php esc_html_e( 'Za platiti', 'dreampoint-b2b' ); ?></span>
	                        <span class="total-value"><?php wc_cart_totals_order_total_html(); ?></span>
	                    </div>
	                    <!-- /.total-area -->
	                    <a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="button button-xl proceed-to-checkout"><?php esc_html_e( 'Idi na naplatu', 'woocommerce' ); ?></a>
	                    <!-- /.button button-xl -->
					</div>
					<!-- /.cart_totals -->
				</div>
				<!-- /.cart-collaterals -->
				<?php do_action( 'woocommerce_after_cart' ); ?>
			</div>
			<!-- /.col-lg-5 -->
		</div>
		<!-- /.row -->
		<?php woocommerce_cross_sell_display(); ?>
	</div>
	<!-- /.container -->
</div>
<!-- /#cart -->

<style>
    .woocommerce button[name="update_cart"],
    .woocommerce input[name="update_cart"] {
        display: none;
    }
</style>

<?php
/**
 * The Template for displaying wishlist if a current user is owner.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/ti-wishlist.php.
 *
 * @version             2.3.3
 * @package           TInvWishlist\Template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
wp_enqueue_script( 'tinvwl' );


?>
<div class="tinv-wishlist woocommerce tinv-wishlist-clear">
	<div class="product-listing-wrap block">
		<div class="container">
			
			<div class="inner-intro">
				<?php do_action( 'tinvwl_before_wishlist', $wishlist ); ?>
				<?php if ( function_exists( 'wc_print_notices' ) && isset( WC()->session ) ) {
					wc_print_notices();
				} ?>
			</div>
			<!-- /.inner-intro -->
			<div class="sort-filter">
	            <?php echo woocommerce_output_all_notices();  ?>
	        </div>
	        <!-- /.sort-area -->
			<?php $form_url = tinv_url_wishlist( $wishlist['share_key'], $wl_paged, true ); ?>
			<form action="<?php echo esc_url( $form_url ); ?>" method="post" autocomplete="off"
				  data-tinvwl_paged="<?php echo $wl_paged; ?>" data-tinvwl_per_page="<?php echo $wl_per_page; ?>"
				  data-tinvwl_sharekey="<?php echo $wishlist['share_key'] ?>">
				<?php do_action( 'tinvwl_before_wishlist_table', $wishlist ); ?>
				<div class="tinvwl-table-manage-list row product-listing">
					<?php do_action( 'tinvwl_wishlist_contents_before' ); ?>
		
					<?php
		
					global $product, $post;
					// store global product data.
					$_product_tmp = $product;
					// store global post data.
					$_post_tmp = $post;
		
					foreach ( $products as $wl_product ) {
		
						if ( empty( $wl_product['data'] ) ) {
							continue;
						}
		
						// override global product data.
						$product = apply_filters( 'tinvwl_wishlist_item', $wl_product['data'] );
						// override global post data.
						$post = get_post( $product->get_id() );
		
						$sku = $product->get_sku();
						$is_variable = $product->is_type( 'variable' );
						$min_qty = get_post_meta($product->get_id(), 'min_quantity', true);
						$max_qty = get_post_meta($product->get_id(), 'max_quantity', true);
						$is_min_max = !empty($min_qty) || !empty($max_qty);
		
						unset( $wl_product['data'] );
						if ( $wl_product['quantity'] > 0 && apply_filters( 'tinvwl_wishlist_item_visible', true, $wl_product, $product ) ) {
							$product_url = apply_filters( 'tinvwl_wishlist_item_url', $product->get_permalink(), $wl_product, $product );
							do_action( 'tinvwl_wishlist_row_before', $wl_product, $product );
							?>
							<div class="col-lg-3 col-md-4">
								<div class="<?php echo esc_attr( apply_filters( 'tinvwl_wishlist_item_class', 'wishlist_item', $wl_product, $product ) ); ?> product-item <?php if (!$product->is_in_stock()) : ?>out-of-stock<?php endif; ?> <?php echo $is_variable ? 'variable' : ''; ?>">
									<?php display_new_product_ribbon_and_discount(); ?>

							        
							       	<div class="photo-holder">
							            <?php if ( has_post_thumbnail() ) : ?>
							                <?php the_post_thumbnail( 'product-loop' ); ?>
							            <?php else : 
							                // Get default placeholder and replace 150x150 with 340x462
							                $placeholder_url = str_replace( '150x150', '340x462', wc_placeholder_img_src() ); 
							            ?>
							                <img src="<?php echo esc_url( $placeholder_url ); ?>" 
							                     width="340" height="462" 
							                     alt="<?php echo esc_attr( get_the_title() ); ?>"  class="placeholder-img" />
							            <?php endif; ?>
							            <?php if ( isset( $wishlist_table_row['add_to_cart'] ) && $wishlist_table_row['add_to_cart'] ) { ?>
											<div class="action-holder">
												<?php
												if ( apply_filters( 'tinvwl_wishlist_item_action_add_to_cart', $wishlist_table_row['add_to_cart'], $wl_product, $product ) ) {
													?>
													<button class="button add-to-cart alt wishlist-add-to-cart categories-btn" name="tinvwl-add-to-cart"
															value="<?php echo esc_attr( $wl_product['ID'] ); ?>"
															title="<?php echo esc_html( apply_filters( 'tinvwl_wishlist_item_add_to_cart', $wishlist_table_row['text_add_to_cart'], $wl_product, $product ) ); ?>">
														<span
															class="tinvwl-txt"><?php echo wp_kses_post( apply_filters( 'tinvwl_wishlist_item_add_to_cart', $wishlist_table_row['text_add_to_cart'], $wl_product, $product ) ); ?></span><i class="icon-shopping-bag"></i>
													</button>
												<?php } elseif ( apply_filters( 'tinvwl_wishlist_item_action_default_loop_button', $wishlist_table_row['add_to_cart'], $wl_product, $product ) ) {
													woocommerce_template_loop_add_to_cart();
												} ?>
												<div class="product-remove add-to-fav">
													<button type="submit" name="tinvwl-remove"
															value="<?php echo esc_attr( $wl_product['ID'] ); ?>"
															title="<?php _e( 'Remove', 'ti-woocommerce-wishlist' ) ?>"><i class="icon-xmark"></i>
													</button>
												</div>
											</div>
										<?php } ?>
										
							        </div>

							        <div class="content-holder">
			
							        	<span class="product-title"><?php
										if ( ! $product->is_visible() ) {
											echo apply_filters( 'tinvwl_wishlist_item_name', is_callable( array(
													$product,
													'get_name'
												) ) ? $product->get_name() : $product->get_title(), $wl_product, $product ) . '&nbsp;'; // WPCS: xss ok.
										} else {
											echo apply_filters( 'tinvwl_wishlist_item_name', sprintf( '<a href="%s">%s</a>', esc_url( $product_url ), is_callable( array(
												$product,
												'get_name'
											) ) ? $product->get_name() : $product->get_title() ), $wl_product, $product ); // WPCS: xss ok.
										}
							
										echo apply_filters( 'tinvwl_wishlist_item_meta_data', tinv_wishlist_get_item_data( $product, $wl_product ), $wl_product, $product ); // WPCS: xss ok.
										?></span>
										 <?php if ( isset( $wishlist_table_row['colm_price'] ) && $wishlist_table_row['colm_price'] ) { ?>
									
											<span class="price-container <?php if ($product->is_on_sale()) : ?>onsale<?php endif; ?>">
												<?php
												echo apply_filters( 'tinvwl_wishlist_item_price', $product->get_price_html(), $wl_product, $product ); // WPCS: xss ok.
												?>
											</span>
											<!-- /.price-container -->
										
										
									<?php } ?>
		
							        </div>
							        <!-- /.content-holder -->
								    <a href="<?php echo get_permalink( $product->get_id() ); ?>" class="url-wrapper"> <?php esc_html_e( 'Pogledaj više o proizvodu', 'dreampoint-b2b' ); ?> <?php echo $product->get_name(); ?> </a>
									<!-- /.url-wrapper -->                          
								</div>
								<!-- /.product-item -->
							</div>
							<!-- /.col-md-4 col-lg-3 -->
							<?php
							do_action( 'tinvwl_wishlist_row_after', $wl_product, $product );
						} // End if().
					} // End foreach().
					// restore global product data.
					$product = $_product_tmp;
					// restore global post data.
					$post = $_post_tmp;
					?>
					<?php do_action( 'tinvwl_wishlist_contents_after' ); ?>
				
				</div>
			</form>
			<?php do_action( 'tinvwl_after_wishlist', $wishlist ); ?>
			<div class="tinv-lists-nav tinv-wishlist-clear">
				<?php do_action( 'tinvwl_pagenation_wishlist', $wishlist ); ?>
			</div>
		</div>
		<!-- /.container -->
	</div>
	<!-- /.product-listing-wrap -->
</div>

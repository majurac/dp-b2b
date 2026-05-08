<?php
/**
 * Custom Offer Admin UI
 *
 * WooCommerce → Custom Offer Products submenu.
 * Manages wp_dp_product_access assignments for custom_offer users
 * without writing SQL.
 *
 * @package Dreampoint_B2B
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu',            'dreampoint_b2b_custom_offer_menu' );
add_action( 'admin_enqueue_scripts', 'dreampoint_b2b_custom_offer_scripts' );

function dreampoint_b2b_custom_offer_menu(): void {
	add_submenu_page(
		'woocommerce',
		__( 'Custom Offer Products', 'dreampoint-b2b' ),
		__( 'Custom Offer Products', 'dreampoint-b2b' ),
		'manage_woocommerce',
		'dp-custom-offer',
		'dreampoint_b2b_custom_offer_page'
	);
}

function dreampoint_b2b_custom_offer_scripts( string $hook ): void {
	if ( 'woocommerce_page_dp-custom-offer' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'woocommerce_admin_styles' );
	wp_enqueue_script( 'wc-enhanced-select' );
	// wc-product-search (used by wc-enhanced-select) needs this object on non-WC screens.
	wp_localize_script( 'wc-enhanced-select', 'woocommerce_admin', [
		'ajax_url'              => admin_url( 'admin-ajax.php' ),
		'search_products_nonce' => wp_create_nonce( 'search-products' ),
	] );
}

function dreampoint_b2b_custom_offer_page(): void {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'Nemate dozvolu za pristup ovoj stranici.', 'dreampoint-b2b' ) );
	}

	$access_table = new Dreampoint_B2B_Access_Table();

	// ── Handle POST: save assignments ────────────────────────────────────────
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
		check_admin_referer( 'dp_custom_offer_save' );

		$save_user_id  = absint( $_POST['dp_user_id'] ?? 0 );
		$save_prod_ids = isset( $_POST['dp_product_ids'] )
			? array_values( array_filter( array_map( 'absint', (array) $_POST['dp_product_ids'] ) ) )
			: [];

		if ( $save_user_id ) {
			// Replace: clear existing rows then insert the new set.
			$access_table->remove_all_for_user( $save_user_id );
			if ( ! empty( $save_prod_ids ) ) {
				$access_table->bulk_set( $save_user_id, $save_prod_ids );
			}
		}

		wp_safe_redirect( add_query_arg(
			[ 'page' => 'dp-custom-offer', 'user_id' => $save_user_id, 'saved' => '1' ],
			admin_url( 'admin.php' )
		) );
		exit;
	}

	// ── State from GET ───────────────────────────────────────────────────────
	$selected_user_id = absint( $_GET['user_id'] ?? 0 );
	$assigned_ids     = $selected_user_id ? $access_table->get_for_user( $selected_user_id ) : [];

	// ── Load data ────────────────────────────────────────────────────────────
	$customers = get_users( [
		'role'    => 'customer',
		'orderby' => 'display_name',
		'order'   => 'ASC',
		'fields'  => [ 'ID', 'display_name', 'user_email' ],
	] );

	// Only load the already-assigned products for preselection; the rest are found via AJAX search.
	$assigned_products = array_filter( array_map( 'wc_get_product', $assigned_ids ) );

	// ── Render ───────────────────────────────────────────────────────────────
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Custom Offer Products', 'dreampoint-b2b' ); ?></h1>

		<?php if ( ! empty( $_GET['saved'] ) ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Dodjele su sačuvane.', 'dreampoint-b2b' ); ?></p>
			</div>
		<?php endif; ?>

		<!-- Odabir korisnika (GET) -->
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="dp-custom-offer">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="dp_select_user"><?php esc_html_e( 'Korisnik', 'dreampoint-b2b' ); ?></label>
					</th>
					<td>
						<?php if ( empty( $customers ) ) : ?>
							<p><?php esc_html_e( 'Nema korisnika s ulogom Customer.', 'dreampoint-b2b' ); ?></p>
						<?php else : ?>
							<select name="user_id" id="dp_select_user" class="wc-enhanced-select" style="min-width:350px">
								<option value=""><?php esc_html_e( '— Odaberi korisnika —', 'dreampoint-b2b' ); ?></option>
								<?php foreach ( $customers as $customer ) : ?>
									<option value="<?php echo esc_attr( $customer->ID ); ?>"
										<?php selected( $selected_user_id, (int) $customer->ID ); ?>>
										<?php echo esc_html( $customer->display_name . ' (' . $customer->user_email . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<?php submit_button( __( 'Učitaj', 'dreampoint-b2b' ), 'secondary', '', false ); ?>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</form>

		<?php if ( $selected_user_id ) : ?>
		<hr>

		<!-- Dodjela proizvoda (POST) -->
		<form method="post">
			<?php wp_nonce_field( 'dp_custom_offer_save' ); ?>
			<input type="hidden" name="dp_user_id" value="<?php echo esc_attr( $selected_user_id ); ?>">

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="dp_product_ids">
							<?php esc_html_e( 'Vidljivi proizvodi', 'dreampoint-b2b' ); ?>
						</label>
					</th>
					<td>
						<p class="description" style="margin-bottom:6px">
							<?php esc_html_e( 'Only selected products will be visible to this user.', 'dreampoint-b2b' ); ?>
						</p>
						<select name="dp_product_ids[]" id="dp_product_ids"
								class="wc-product-search" multiple="multiple"
								style="min-width:500px"
								data-placeholder="<?php esc_attr_e( 'Pretraži proizvode…', 'dreampoint-b2b' ); ?>"
								data-action="woocommerce_json_search_products_and_variations">
							<?php foreach ( $assigned_products as $product ) : ?>
								<option value="<?php echo esc_attr( $product->get_id() ); ?>" selected="selected">
									<?php echo esc_html( sprintf( '[%d] %s', $product->get_id(), $product->get_name() ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php printf(
								/* translators: %d: number of currently assigned products */
								esc_html__( 'Trenutno dodijeljeno: %d proizvoda.', 'dreampoint-b2b' ),
								count( $assigned_ids )
							); ?>
						</p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Spremi dodjele', 'dreampoint-b2b' ) ); ?>
		</form>
		<?php endif; ?>
	</div>
	<?php
}

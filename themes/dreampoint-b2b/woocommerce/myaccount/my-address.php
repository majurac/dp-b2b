<?php
/**
 * My Addresses
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/my-address.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.3.0
 */

defined( 'ABSPATH' ) || exit;

$customer_id = get_current_user_id();

if ( ! wc_ship_to_billing_address_only() && wc_shipping_enabled() ) {
	$get_addresses = apply_filters(
		'woocommerce_my_account_get_addresses',
		array(
			'billing'  => __( 'Billing address', 'woocommerce' ),
			'shipping' => __( 'Shipping address', 'woocommerce' ),
		),
		$customer_id
	);
} else {
	$get_addresses = apply_filters(
		'woocommerce_my_account_get_addresses',
		array(
			'billing' => __( 'Billing address', 'woocommerce' ),
		),
		$customer_id
	);
}

$oldcol = 1;
$col    = 1;
?>


<?php if ( ! wc_ship_to_billing_address_only() && wc_shipping_enabled() ) : ?>
	<div class="u-columns woocommerce-Addresses col2-set addresses">
<?php endif; ?>

<?php foreach ( $get_addresses as $name => $address_title ) : ?>
	<?php
		$address = wc_get_account_formatted_address( $name );
		$col     = $col * -1;
		$oldcol  = $oldcol * -1;
	?>

	<?php if ( 'billing' === $name ) : ?>
	    <div class="ma-holder">
	        <div class="ma-header">
	            <div class="wod-title-holder">
	                <h2>
	                    <img decoding="async" src="<?php echo get_template_directory_uri(); ?>/img/ico/lock.svg" alt="">
	                    <?php echo esc_html( $address_title ); ?>
	                </h2>
	            </div>
	            <!-- /.wod-title-holder -->
	        </div>
	        <!-- /.ma-header -->
	        <div class="ma-info">
	            <?php
	                $customer = new WC_Customer( get_current_user_id() );
	                $billing_address = array(
	                    'address'      => $customer->get_billing_address(),
	                    'postcode'     => $customer->get_billing_postcode(),
	                    'city'         => $customer->get_billing_city(),
	                    'country'      => WC()->countries->countries[ $customer->get_billing_country() ] ?? '',
	                );
	            ?>
	            <p><strong>Adresa:</strong> <?php echo esc_html( $billing_address['address'] ); ?></p>
	            <p><strong>Poštanski broj:</strong> <?php echo esc_html( $billing_address['postcode'] ); ?></p>
	            <p><strong>Grad:</strong> <?php echo esc_html( $billing_address['city'] ); ?></p>
	            <p><strong>Država:</strong> <?php echo esc_html( $billing_address['country'] ); ?></p>
	        </div>
	        <!-- /.ma-info -->
	    </div>
	<?php else : ?>
	    <div class="u-column woocommerce-Address custom-form">
	        <header class="woocommerce-Address-title title">
	            <div class="wod-title-holder">
	                <h2><?php echo esc_html( $address_title ); ?></h2>
	            </div>
	            <!-- /.wod-title-holder -->
	        </header>
	        <div class="address-holder">
	            <label for="">Adresa</label>
	            <address>
	                <?php echo $address ? wp_kses_post( $address ) : esc_html_e( 'You have not set up this type of address yet.', 'woocommerce' ); ?>
	            </address>
	        </div>
	        <!-- /.address-holder -->
	        <a href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address', $name ) ); ?>" class="edit button button--sm button--icon-after">
	            <?php echo esc_html__( 'Izmijeni', 'woocommerce' ); ?>
	        </a>
	    </div>
	<?php endif; ?>


<?php endforeach; ?>

<?php if ( ! wc_ship_to_billing_address_only() && wc_shipping_enabled() ) : ?>
	</div>
	<?php
endif;

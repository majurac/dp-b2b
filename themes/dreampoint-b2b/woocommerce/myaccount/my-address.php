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

$customer = new WC_Customer( $customer_id );

// Read-only address rows (Figma MPR-03): street, postcode, city, country. Display only; the data
// is read through the same WC_Customer getters that WooCommerce itself uses.
$address_rows = static function ( string $type ) use ( $customer ): array {
	$street = trim( implode( ', ', array_filter( array(
		$customer->{"get_{$type}_address"}(),
		$customer->{"get_{$type}_address_2"}(),
	) ) ) );
	$country_code = $customer->{"get_{$type}_country"}();

	return array(
		__( 'Adresa', 'dreampoint-b2b' )         => $street,
		__( 'Poštanski broj', 'dreampoint-b2b' ) => $customer->{"get_{$type}_postcode"}(),
		__( 'Grad', 'dreampoint-b2b' )           => $customer->{"get_{$type}_city"}(),
		__( 'Država', 'dreampoint-b2b' )         => WC()->countries->countries[ $country_code ] ?? '',
	);
};

// Empty state (Figma MPR-02): nothing set on any of the listed addresses.
$has_any_address = false;
foreach ( array_keys( $get_addresses ) as $name ) {
	if ( '' !== trim( (string) $customer->{"get_{$name}_address"}() ) ) {
		$has_any_address = true;
		break;
	}
}
?>

<?php if ( ! $has_any_address ) : ?>
	<div class="not-found-holder">
		<div class="nf-icon"><img src="<?php echo esc_url( get_template_directory_uri() . '/img/ico/face-sad.svg' ); ?>" alt=""></div>
		<h2><?php esc_html_e( 'Adresa nije uspostavljena', 'dreampoint-b2b' ); ?></h2>
		<p><?php esc_html_e( 'Trenutno nemate postavljenu adresu za naplatu kao i adresu za dostavu.', 'dreampoint-b2b' ); ?></p>
		<a href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address', 'shipping' ) ); ?>" class="button button--xl button--add"><?php esc_html_e( 'Postavi adresu', 'dreampoint-b2b' ); ?></a>
	</div>
	<!-- /.not-found-holder -->
<?php else : ?>

<div class="woocommerce-Addresses addresses">
	<?php foreach ( $get_addresses as $name => $address_title ) : ?>
		<div class="ma-holder woocommerce-Address ma-holder--<?php echo esc_attr( $name ); ?>">
			<div class="ma-header">
				<div class="wod-title-holder">
					<h2><?php echo esc_html( $address_title ); ?></h2>
				</div>
				<!-- /.wod-title-holder -->
			</div>
			<!-- /.ma-header -->
			<div class="ma-info">
				<?php foreach ( $address_rows( $name ) as $row_label => $row_value ) : ?>
					<p><span><?php echo esc_html( $row_label ); ?>:</span> <strong><?php echo esc_html( $row_value ); ?></strong></p>
				<?php endforeach; ?>
			</div>
			<!-- /.ma-info -->
			<?php if ( 'billing' !== $name ) : ?>
				<a href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address', $name ) ); ?>" class="edit button button--edit"><?php echo esc_html__( 'Izmijeni', 'woocommerce' ); ?></a>
			<?php endif; ?>
		</div>
		<!-- /.ma-holder -->
	<?php endforeach; ?>
</div>

<?php endif; ?>

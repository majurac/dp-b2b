<?php
/**
 * ERP delivery-location identity selection and persistence (AP-07 / ADR-010).
 * Owns ONLY the _apros_delivery_location_id contract consumed by the protected
 * uncle-dev-importer/order.php exporter. Does not read or write Woo shipping_*
 * fields — the relationship between the two is pending Apros clarification.
 */

const DP_B2B_DELIVERY_LOCATION_FIELD_ID = 'dreampoint-b2b/delivery-location';

// --- Data access ----------------------------------------------------------

function dreampoint_b2b_current_partner_code(): int {
	return (int) get_user_meta( get_current_user_id(), 'apros_partner_code', true );
}

/**
 * Fresh read of the current user's Apros delivery locations. Never cached
 * across requests — every checkout submission re-derives this from the
 * protected plugin's own data.
 */
function dreampoint_b2b_current_partner_locations(): array {
	$partner_code = dreampoint_b2b_current_partner_code();

	if ( ! $partner_code || ! function_exists( 'apros_get_partner_delivery_locations' ) ) {
		return [];
	}

	return apros_get_partner_delivery_locations( $partner_code );
}

/**
 * Customer-facing label: "{name} — {address}, {postal_code} {city}", with
 * missing components dropped instead of leaving stray separators.
 */
function dreampoint_b2b_format_delivery_location_label( array $location ): string {
	$name        = trim( (string) ( $location['name'] ?? '' ) );
	$address     = trim( (string) ( $location['address'] ?? '' ) );
	$locality    = trim( trim( (string) ( $location['postal_code'] ?? '' ) ) . ' ' . trim( (string) ( $location['city'] ?? '' ) ) );

	$tail_parts = array_filter( [ $address, $locality ], fn( string $part ): bool => $part !== '' );
	$tail       = implode( ', ', $tail_parts );

	if ( $name !== '' && $tail !== '' ) {
		return $name . ' — ' . $tail;
	}

	return $name !== '' ? $name : $tail;
}

// --- 2+ locations: native Woo Blocks selector ------------------------------

add_action( 'woocommerce_init', function (): void {
	if ( ! is_user_logged_in() || ! dreampoint_b2b_current_partner_code() ) {
		return; // no ERP identity — preserve current checkout behavior
	}

	$locations = dreampoint_b2b_current_partner_locations();

	if ( count( $locations ) < 2 ) {
		return; // 0/1 location — no selector, handled at order-update time
	}

	$options = [];

	foreach ( $locations as $location ) {
		$recipient_code = (int) ( $location['recipient_code'] ?? 0 );

		$options[] = [
			'value' => (string) $recipient_code,
			'label' => dreampoint_b2b_format_delivery_location_label( $location ),
		];
	}

	woocommerce_register_additional_checkout_field( [
		'id'                => DP_B2B_DELIVERY_LOCATION_FIELD_ID,
		'label'             => __( 'Dostavna lokacija', 'dreampoint-b2b' ),
		'location'          => 'order',
		'type'              => 'select',
		'required'          => true,
		'options'           => $options,
		'validate_callback' => 'dreampoint_b2b_validate_delivery_location_field',
	] );
} );

/**
 * Registered as the field's validate_callback — runs server-side on every
 * submission. Never trusts the submitted value merely because it matches an
 * option the browser rendered; re-derives the valid set fresh.
 */
function dreampoint_b2b_validate_delivery_location_field( $value ) {
	if ( $value === '' || $value === null ) {
		return new WP_Error(
			'dp_b2b_delivery_location_required',
			__( 'Molimo odaberite dostavnu lokaciju.', 'dreampoint-b2b' )
		);
	}

	$valid_codes = array_map(
		fn( array $location ): int => (int) ( $location['recipient_code'] ?? 0 ),
		dreampoint_b2b_current_partner_locations()
	);

	if ( ! in_array( (int) $value, $valid_codes, true ) ) {
		return new WP_Error(
			'dp_b2b_delivery_location_invalid',
			__( 'Odabrana dostavna lokacija nije važeća za vaš nalog.', 'dreampoint-b2b' )
		);
	}

	return true;
}

// --- 0 / 1 / 2+ resolution + persistence into _apros_delivery_location_id -

add_action( 'woocommerce_store_api_checkout_update_order_from_request', function ( \WC_Order $order ): void {
	if ( ! is_user_logged_in() || ! dreampoint_b2b_current_partner_code() ) {
		return; // no ERP identity — preserve current checkout behavior
	}

	$locations = dreampoint_b2b_current_partner_locations();
	$count     = count( $locations );

	if ( $count === 0 ) {
		/** @var \Exception $e */
		$e = new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
			'dp_b2b_no_delivery_location',
			__( 'Za vaš nalog trenutno nije evidentirana nijedna dostavna lokacija. Molimo kontaktirajte svog komercijalistu prije nego što nastavite s narudžbom.', 'dreampoint-b2b' ),
			400
		);
		throw $e;
	}

	if ( $count === 1 ) {
		$recipient_code = (int) ( $locations[0]['recipient_code'] ?? 0 );

		if ( ! $recipient_code ) {
			/** @var \Exception $e */
			$e = new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
				'dp_b2b_no_delivery_location',
				__( 'Za vaš nalog trenutno nije evidentirana nijedna dostavna lokacija. Molimo kontaktirajte svog komercijalistu prije nego što nastavite s narudžbom.', 'dreampoint-b2b' ),
				400
			);
			throw $e;
		}

		$order->update_meta_data( '_apros_delivery_location_id', $recipient_code );
		$order->save();
		return;
	}

	// 2+ locations — bridge the already-validated native Woo field value.
	$submitted   = (int) $order->get_meta( '_wc_other/' . DP_B2B_DELIVERY_LOCATION_FIELD_ID );
	$valid_codes = array_map(
		fn( array $location ): int => (int) ( $location['recipient_code'] ?? 0 ),
		$locations
	);

	if ( ! $submitted || ! in_array( $submitted, $valid_codes, true ) ) {
		/** @var \Exception $e */
		$e = new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
			'dp_b2b_delivery_location_invalid',
			__( 'Odabrana dostavna lokacija nije važeća za vaš nalog. Molimo pokušajte ponovo.', 'dreampoint-b2b' ),
			400
		);
		throw $e;
	}

	$order->update_meta_data( '_apros_delivery_location_id', $submitted );
	$order->save();
} );

<?php
/**
 * Checkout logic: billing/shipping prefill, billing data protection.
 * Works with both classic checkout and WooCommerce Blocks (Store API).
 */

// --- Billing → Shipping prefill ----------------------------------------------

function dreampoint_b2b_prefill_shipping_from_billing( array $fields ): array {
	if ( is_checkout() ) {
		foreach ( $fields['billing'] as $key => $field ) {
			$shipping_key = str_replace( 'billing_', 'shipping_', $key );
			if ( isset( $fields['shipping'][ $shipping_key ] ) ) {
				$fields['shipping'][ $shipping_key ]['default'] = $field['default'] ?? '';
			}
		}
	}
	return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'dreampoint_b2b_prefill_shipping_from_billing' );

// --- Protect master billing data on checkout ---------------------------------
// WC_Checkout::process_customer() assigns the posted data to the WC_Customer and calls $customer->save()
// BEFORE woocommerce_checkout_update_user_meta fires. The previous callback on that hook (priority 999)
// read the meta after the save, so it "restored" the values the request had just written and protected
// nothing. woocommerce_checkout_update_customer fires on the unsaved customer, right before save().
// Helpers: inc/my-account.php (billing master set, scoping, persisted-value read). Shipping, phone and
// e-mail are not touched. The classic order billing address comes from the posted data, not from the
// customer object, so order snapshots are unaffected. The Blocks / Store API checkout does not fire
// this hook; it is covered by the two Store API hooks below (section 53.7).
add_action(
	'woocommerce_checkout_update_customer',
	static function ( $customer, $data ): void {
		dreampoint_b2b_restore_billing_master( $customer );
	},
	10,
	2
);


// --- Blocks checkout / Store API: billing master protection (Option A, owner-approved 2026-10-31) ---------
// WooCommerce 11.2.1 order of operations (verified in src/StoreApi):
//   cart/update-customer:  set_props(request) -> woocommerce_store_api_cart_update_customer_from_request -> save()
//   checkout POST:         update_customer_from_request() -> woocommerce_store_api_checkout_update_customer_from_request
//                          -> save() -> draft order built from wc()->customer (update_addresses_from_cart)
//                          -> process_customer() -> sync_customer_data_with_order() (customer rebuilt from the ORDER)
// Both hooks fire on the unsaved session customer, so restoring the seven master props there (a) keeps the
// persisted customer unchanged and (b) makes the draft order billing and the later order-to-customer sync
// carry the master values. billing_phone, billing_email and shipping_* are not touched.
// POST wc/store/v1/checkout/{order_id} (pay-for-existing-order) is NOT covered by these two hooks: it sets the
// order billing from the request before them and syncs a fresh customer from the order afterwards. It is
// handled in the Gate 1 closure section below (correction 2026-10-31; see figma-alignment 53.8).
add_action(
	'woocommerce_store_api_cart_update_customer_from_request',
	static function ( $customer, $request = null ): void {
		dreampoint_b2b_restore_billing_master( $customer );
	},
	10,
	2
);
add_action(
	'woocommerce_store_api_checkout_update_customer_from_request',
	static function ( $customer, $request = null ): void {
		dreampoint_b2b_restore_billing_master( $customer );
	},
	10,
	2
);

/**
 * Place-order guard for incomplete master billing data. The billing form is locked/hidden for B2B customers,
 * so WooCommerce's generic "problem with the provided billing address" error would not be actionable.
 * Only the universally required address parts are checked (street, city, country); postcode/state depend on
 * the country locale and stay with WooCommerce's own validation. Nothing is substituted or written.
 * POST only: PATCH requests fire the same action on every change and must not error out.
 *
 * @param mixed $order   Draft order (unused).
 * @param mixed $request Store API request.
 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException When a required master value is empty.
 */
function dreampoint_b2b_require_complete_billing_master( $order, $request ): void {
	if ( ! $request instanceof WP_REST_Request || 'POST' !== $request->get_method() ) {
		return;
	}

	$user_id = get_current_user_id();
	if ( ! dreampoint_b2b_billing_master_applies( $user_id ) ) {
		return;
	}

	foreach ( array( 'billing_address_1', 'billing_city', 'billing_country' ) as $key ) {
		if ( '' === trim( dreampoint_b2b_stored_billing_master_value( $user_id, $key ) ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
				'dreampoint_billing_master_incomplete',
				esc_html__( 'Podaci za naplatu vaše tvrtke nisu potpuni. Kontaktirajte svog prodajnog predstavnika ili korisničku podršku.', 'dreampoint-b2b' ),
				400
			);
		}
	}
}
add_action( 'woocommerce_store_api_checkout_update_order_from_request', 'dreampoint_b2b_require_complete_billing_master', 10, 2 );
// --- end Store API billing master protection ---


// --- Gate 1 closure (owner-approved 2026-10-31): existing-order Store API route + classic update_order_review ---
// Verified in WooCommerce 11.2.1:
//  * POST /wc/store/v1/checkout/{id} (CheckoutOrder::get_route_post_response): update_billing_address() sets the
//    REQUEST billing on the in-memory order and on wc()->customer, fires
//    woocommerce_store_api_checkout_update_customer_from_request, saves the customer, saves the order
//    (so the request billing is persisted BEFORE woocommerce_store_api_checkout_update_order_from_request fires),
//    and process_customer() -> sync_customer_data_with_order() builds a FRESH WC_Customer from the order and saves it.
//    The earlier assumption that the customer stayed protected on this route was wrong (correction 2026-10-31).
//  * wc-ajax=update_order_review (WC_AJAX::update_order_review): set_props() of billing country/state/postcode/
//    city/address_1/address_2 from $_POST on wc()->customer, then save(); no dedicated hook.
// Mitigation 1 (existing order): before the route runs, the protected billing keys of the request are replaced by
// the order's persisted (historical) values, so the order is never given request values and no historical
// snapshot is rewritten. Phone, e-mail, names and shipping stay as requested. If shipping_address is absent,
// WooCommerce copies the billing to shipping; the ORIGINAL request billing is kept for that copy.
// Mitigation 2 (customer master): WC_Data::save() fires woocommerce_before_customer_object_save on every customer
// save, including the fresh customer of sync_customer_data_with_order() and the update_order_review save. The
// existing restore helper runs there, only inside a Store API request or a wc-ajax=update_order_review request,
// and only for the logged-in owner of an existing account (dreampoint_b2b_billing_master_applies()).

/**
 * True inside the two request types whose customer writes have no dedicated hook.
 */
function dreampoint_b2b_is_unhooked_checkout_write_context(): bool {
	if ( function_exists( 'WC' ) && WC() && method_exists( WC(), 'is_store_api_request' ) && WC()->is_store_api_request() ) {
		return true;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- context detection only, nothing is read from the value.
	return isset( $_GET['wc-ajax'] ) && 'update_order_review' === sanitize_key( wp_unslash( $_GET['wc-ajax'] ) );
}

add_action(
	'woocommerce_before_customer_object_save',
	static function ( $customer ): void {
		if ( dreampoint_b2b_is_unhooked_checkout_write_context() ) {
			dreampoint_b2b_restore_billing_master( $customer );
		}
	},
	10,
	1
);

/**
 * POST /wc/store/v1/checkout/{id}: keep the order's persisted billing master values for its logged-in owner.
 * Runs on rest_request_before_callbacks (before the permission callback and the handler); it only rewrites
 * request parameters, never the order, and leaves ownership/status/nonce checks to WooCommerce.
 *
 * @param mixed $response Response so far.
 * @param mixed $handler  Route handler.
 * @param mixed $request  REST request.
 * @return mixed
 */
function dreampoint_b2b_keep_existing_order_billing_master( $response, $handler, $request ) {
	if ( is_wp_error( $response ) || ! $request instanceof WP_REST_Request || 'POST' !== $request->get_method() ) {
		return $response;
	}

	$route = is_array( $handler ) && is_array( $handler['callback'] ?? null ) ? $handler['callback'][0] : null;
	if ( ! is_object( $route ) || ! is_a( $route, 'Automattic\WooCommerce\StoreApi\Routes\V1\CheckoutOrder' ) ) {
		return $response;
	}

	$user_id = get_current_user_id();
	if ( ! dreampoint_b2b_billing_master_applies( $user_id ) ) {
		return $response;
	}

	$order = wc_get_order( absint( $request['id'] ) );
	if ( ! $order instanceof WC_Order || (int) $order->get_customer_id() !== $user_id ) {
		return $response;
	}

	$requested = $request['billing_address'];
	if ( ! is_array( $requested ) ) {
		return $response;
	}

	// WooCommerce copies the billing to shipping when no shipping address is posted: keep the requested one.
	if ( null === $request['shipping_address'] ) {
		$request->set_param( 'shipping_address', $requested );
	}

	foreach ( dreampoint_b2b_billing_master_fields() as $key ) {
		$getter = 'get_' . $key;
		if ( is_callable( array( $order, $getter ) ) ) {
			$requested[ substr( $key, strlen( 'billing_' ) ) ] = (string) $order->$getter();
		}
	}
	$request->set_param( 'billing_address', $requested );

	return $response;
}
add_filter( 'rest_request_before_callbacks', 'dreampoint_b2b_keep_existing_order_billing_master', 10, 3 );
// --- end Gate 1 closure ---


// --- "Koristite adresu naplate za dostavu": Silkypress checkbox #custom-checkbox-use-billing ---------------------
// The checkbox is a field of the SilkyPress Input Field Block plugin (option `silkypress-input-field-block`, section
// contact-information). Its INITIAL checked state is the plugin's React useState() initializer, read from the field's
// `defaultValue` (build/fields.js), so it can only be decided on the server. Same mechanism as the Cotra B2B theme
// (`option_silkypress-input-field-block` filter in inc/woocommerce-m.php), but conditional here (A9, owner decision
// 2026-11-25). The box means "deliver to the billing address" and starts CHECKED only for:
//   A. empty shipping address + usable billing address     (js/checkout-b2b-info.js then copies billing -> shipping once)
//   B. shipping address already equal to a usable billing address, shipping itself usable
// and UNCHECKED (additional shipping form visible) for:
//   C. a different shipping address, D. a partially filled one, E. a billing address that is not usable for delivery.
// The filter only changes the value handed to the initial React state: the customer's later toggle is authoritative.
// Nothing is written to the customer here.

/**
 * Whether a customer address is usable as a delivery address: every part WooCommerce requires for its country
 * (street, city, country, postcode / state per country locale, via WC_Countries::get_address_fields()) plus the
 * first and last name, which the Blocks shipping form requires. A non-empty street alone is NOT enough.
 *
 * @param WC_Customer $customer Customer.
 * @param string      $type     'billing' or 'shipping'.
 */
function dreampoint_b2b_customer_address_usable( WC_Customer $customer, string $type ): bool {
	$value = static fn( string $part ): string => trim( (string) $customer->{"get_{$type}_{$part}"}() );

	$country = $value( 'country' );
	if ( '' === $country || '' === $value( 'address_1' ) || '' === $value( 'city' ) || '' === $value( 'first_name' ) || '' === $value( 'last_name' ) ) {
		return false;
	}

	// Locale-dependent requirements (Croatia: postcode required, state optional).
	$fields = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_address_fields( $country, $type . '_' ) : [];
	foreach ( [ 'postcode', 'state' ] as $part ) {
		$required = isset( $fields[ "{$type}_{$part}" ] ) ? ! empty( $fields[ "{$type}_{$part}" ]['required'] ) : ( 'postcode' === $part );
		if ( $required && '' === $value( $part ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Classify the customer's shipping address against the billing address.
 *
 * @return string 'billing-incomplete' | 'empty' (no shipping address part at all) | 'same' | 'different'
 */
function dreampoint_b2b_shipping_vs_billing( WC_Customer $customer ): string {
	if ( ! dreampoint_b2b_customer_address_usable( $customer, 'billing' ) ) {
		return 'billing-incomplete';
	}

	$norm = static fn( $v ): string => strtolower( trim( preg_replace( '/\s+/', ' ', (string) $v ) ) );

	// "Genuinely empty" = none of the user-entered address parts is filled: street, second line, city, postcode.
	// Country and state are NOT counted: WooCommerce fills the country with a default and the Blocks form pre-selects
	// the (optional) Croatian state, so a never-edited shipping address still arrives with them (found on the admin
	// account: only state + country filled, which wrongly classified the address as an alternative one). Names and phone
	// are not address parts. A shipping address with any of the four parts filled is an alternative address.
	$empty = true;
	foreach ( [ 'address_1', 'address_2', 'city', 'postcode' ] as $part ) {
		if ( '' !== $norm( $customer->{"get_shipping_$part"}() ) ) {
			$empty = false;
			break;
		}
	}
	if ( $empty ) {
		return 'empty';
	}

	foreach ( [ 'address_1', 'address_2', 'city', 'postcode', 'state', 'country' ] as $part ) {
		$billing  = $norm( $customer->{"get_billing_$part"}() );
		$shipping = $norm( $customer->{"get_shipping_$part"}() );

		// The state (Croatian "Županija") is optional and the Blocks form may leave the shipping one empty while the
		// billing master carries it: an empty side does not make the addresses different.
		if ( 'state' === $part && ( '' === $billing || '' === $shipping ) ) {
			continue;
		}

		if ( $billing !== $shipping ) {
			return 'different';
		}
	}

	// Equal addresses, but a shipping address without names would leave the hidden Blocks form invalid.
	return dreampoint_b2b_customer_address_usable( $customer, 'shipping' ) ? 'same' : 'different';
}

function dreampoint_b2b_use_billing_checkbox_field( $inputs ) {
	if ( ! is_array( $inputs ) || is_admin() ) {
		return $inputs;
	}

	foreach ( $inputs as $index => $input ) {
		if ( ! is_array( $input ) || 'custom-checkbox-use-billing' !== ( $input['name'] ?? '' ) || 'checkbox' !== ( $input['type'] ?? '' ) ) {
			continue;
		}

		// The box means "deliver to the billing address" (not "bill to the shipping address").
		$inputs[ $index ]['label'] = __( 'Koristite adresu naplate za dostavu', 'dreampoint-b2b' );

		// The plugin reads this option while the Checkout block integration initialises, before the main query is
		// resolved (`is_checkout()` is not reliable yet), so the customer is the only input: the session customer
		// when WooCommerce has created it, otherwise the stored customer of the logged-in user.
		$customer = ( function_exists( 'WC' ) && WC()->customer instanceof WC_Customer )
			? WC()->customer
			: ( is_user_logged_in() ? new WC_Customer( get_current_user_id() ) : null );

		if ( $customer instanceof WC_Customer ) {
			$inputs[ $index ]['defaultValue'] = in_array( dreampoint_b2b_shipping_vs_billing( $customer ), [ 'empty', 'same' ], true );
		}
	}

	return $inputs;
}
add_filter( 'option_silkypress-input-field-block', 'dreampoint_b2b_use_billing_checkbox_field' );

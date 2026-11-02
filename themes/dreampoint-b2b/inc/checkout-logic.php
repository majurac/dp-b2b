<?php
/**
 * Checkout logic: payment rules, billing/shipping prefill, billing data protection.
 * Works with both classic checkout and WooCommerce Blocks (Store API).
 */

// --- Config -------------------------------------------------------------------

function dreampoint_b2b_payment_rules(): array {
	return [
		'pickup_location' => [ 'bacs' ], // WC Blocks local pickup
		'local_pickup'    => [ 'bacs' ], // classic checkout fallback
		'gls'             => [ 'stripe', 'paypal' ],
	];
}

// --- Core resolver ------------------------------------------------------------

/**
 * Returns the allowed gateway IDs for the active shipping method,
 * or null if no rule matches (default WC behavior applies).
 */
function dreampoint_b2b_resolve_allowed_gateways(): ?array {
	if ( is_admin() ) {
		return null;
	}

	$method = '';

	if ( WC()->session ) {
		$session_methods = WC()->session->get( 'chosen_shipping_methods', [] );
		$method          = $session_methods[0] ?? '';
	}

	// Fallback: Blocks checkout may send shipping via $_REQUEST before session is populated.
	if ( ! $method && ! empty( $_REQUEST['shipping_method'][0] ) ) {
		$method = sanitize_text_field( wp_unslash( $_REQUEST['shipping_method'][0] ) );
	}

	if ( ! $method ) {
		return null;
	}

	foreach ( dreampoint_b2b_payment_rules() as $slug => $allowed ) {
		if ( str_contains( $method, $slug ) ) {
			return $allowed;
		}
	}

	return null;
}

// --- Payment filter -----------------------------------------------------------

add_filter( 'woocommerce_available_payment_gateways', function ( array $gateways ): array {
	$allowed = dreampoint_b2b_resolve_allowed_gateways();

	if ( $allowed === null ) {
		return $gateways;
	}

	return array_filter( $gateways, fn( $id ) => in_array( $id, $allowed, true ), ARRAY_FILTER_USE_KEY );
} );

// --- Validation: classic checkout --------------------------------------------

add_action( 'woocommerce_checkout_process', function (): void {
	$allowed = dreampoint_b2b_resolve_allowed_gateways();

	if ( $allowed === null ) {
		return;
	}

	$payment = sanitize_text_field( wp_unslash( $_POST['payment_method'] ?? '' ) );

	if ( ! in_array( $payment, $allowed, true ) ) {
		wc_add_notice(
			__( 'Selected payment method is not available for the chosen shipping method.', 'dreampoint-b2b' ),
			'error'
		);
	}
} );

// --- Validation: WooCommerce Blocks (Store API) ------------------------------

add_action( 'woocommerce_store_api_checkout_update_order_from_request', function ( \WC_Order $order ): void {
	$allowed = dreampoint_b2b_resolve_allowed_gateways();

	if ( $allowed === null ) {
		return;
	}

	if ( ! in_array( $order->get_payment_method(), $allowed, true ) ) {
		// RouteException extends \Exception — @var cast needed because Intelephense lacks WC StoreApi stubs.
		/** @var \Exception $e */
		$e = new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
			'invalid_payment_method',
			__( 'Selected payment method is not available for the chosen shipping method.', 'dreampoint-b2b' ),
			400
		);
		throw $e;
	}
}, 10, 2 );

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

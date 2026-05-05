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

add_action( 'woocommerce_checkout_update_user_meta', function ( int $customer_id, array $posted ): void {
	if ( ! $customer_id ) {
		return;
	}

	$protected_fields = [
		'billing_company',
		'billing_address_1',
		'billing_address_2',
		'billing_city',
		'billing_postcode',
		'billing_country',
	];

	foreach ( $protected_fields as $field ) {
		$original = get_user_meta( $customer_id, $field, true );
		update_user_meta( $customer_id, $field, $original );
	}
}, 999, 2 );

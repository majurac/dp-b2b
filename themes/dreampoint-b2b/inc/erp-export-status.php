<?php
/**
 * ERP export status (A2-A): WooCommerce order list column, order detail block and failure notification.
 *
 * Reads the structured outcome written by `uncle-dev-importer/order.php` (`_erp_export_state`,
 * `_erp_export_diag`) through `dp_erp_get_state()`, which also derives a conservative state for
 * historical orders (never a bulk migration). Nothing here exports, retries or changes an order:
 * rendering is read-only, and the only side effect is ONE e-mail per failed / ambiguous order,
 * triggered by the importer's `dp_erp_export_outcome` action (never by page rendering).
 *
 * @package Dreampoint_B2B
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Croatian label for a structured state. Text, not colour, carries the meaning. */
function dreampoint_b2b_erp_state_label( string $state ): string {
	switch ( $state ) {
		case 'SENT':
			return __( 'Sinkronizirano', 'dreampoint-b2b' );
		case 'FAILED':
			return __( 'Neuspješan izvoz', 'dreampoint-b2b' );
		case 'AMBIGUOUS':
			return __( 'Potrebna provjera u ERP-u', 'dreampoint-b2b' );
		default:
			return __( 'Nije poslano', 'dreampoint-b2b' );
	}
}

function dreampoint_b2b_erp_ambiguous_warning(): string {
	return __( 'Prije ponovnog slanja provjerite je li narudžba već evidentirana u APROS sustavu.', 'dreampoint-b2b' );
}

/** Structured state of an order, or '' when the importer layer is not available. */
function dreampoint_b2b_erp_state( WC_Order $order ): string {
	return function_exists( 'dp_erp_get_state' ) ? dp_erp_get_state( $order ) : '';
}

function dreampoint_b2b_erp_can_view(): bool {
	return current_user_can( 'manage_woocommerce' );
}

// -------------------------------------------------------------------------
// Order list column (HPOS + legacy post-based list)
// -------------------------------------------------------------------------

function dreampoint_b2b_erp_add_column( array $columns ): array {
	$out = [];

	foreach ( $columns as $key => $label ) {
		$out[ $key ] = $label;

		if ( 'order_status' === $key ) {
			$out['dp_erp_status'] = esc_html__( 'ERP', 'dreampoint-b2b' );
		}
	}

	if ( ! isset( $out['dp_erp_status'] ) ) {
		$out['dp_erp_status'] = esc_html__( 'ERP', 'dreampoint-b2b' );
	}

	return $out;
}
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'dreampoint_b2b_erp_add_column', 20 );
add_filter( 'manage_edit-shop_order_columns', 'dreampoint_b2b_erp_add_column', 20 );

function dreampoint_b2b_erp_render_column( $order ): void {
	if ( ! $order instanceof WC_Order || ! dreampoint_b2b_erp_can_view() ) {
		return;
	}

	$state = dreampoint_b2b_erp_state( $order );

	if ( '' === $state ) {
		echo '&ndash;';
		return;
	}

	echo '<span class="dp-erp-status dp-erp-status--' . esc_attr( strtolower( $state ) ) . '">' . esc_html( dreampoint_b2b_erp_state_label( $state ) ) . '</span>';

	if ( 'AMBIGUOUS' === $state ) {
		echo '<br><small>' . esc_html( dreampoint_b2b_erp_ambiguous_warning() ) . '</small>';
	}
}

// HPOS: ($column, $order)
add_action( 'manage_woocommerce_page_wc-orders_custom_column', function ( $column, $order = null ): void {
	if ( 'dp_erp_status' === $column ) {
		dreampoint_b2b_erp_render_column( $order );
	}
}, 10, 2 );

// Legacy post-based list: ($column, $post_id)
add_action( 'manage_shop_order_posts_custom_column', function ( $column, $post_id = 0 ): void {
	if ( 'dp_erp_status' === $column ) {
		dreampoint_b2b_erp_render_column( wc_get_order( (int) $post_id ) );
	}
}, 10, 2 );

// -------------------------------------------------------------------------
// Order detail block
// -------------------------------------------------------------------------

add_action( 'woocommerce_admin_order_data_after_order_details', function ( $order ): void {
	if ( ! $order instanceof WC_Order || ! dreampoint_b2b_erp_can_view() ) {
		return;
	}

	$state = dreampoint_b2b_erp_state( $order );

	if ( '' === $state ) {
		return;
	}

	$diag   = $order->get_meta( '_erp_export_diag' );
	$legacy = function_exists( 'dp_erp_is_legacy_state' ) && dp_erp_is_legacy_state( $order );

	echo '<p class="form-field form-field-wide"><strong>' . esc_html__( 'ERP status:', 'dreampoint-b2b' ) . '</strong> ' . esc_html( dreampoint_b2b_erp_state_label( $state ) );

	if ( $legacy ) {
		echo ' <em>(' . esc_html__( 'povijesni zapis bez strukturiranog ishoda', 'dreampoint-b2b' ) . ')</em>';
	}

	if ( 'AMBIGUOUS' === $state ) {
		echo '<br><strong>' . esc_html( dreampoint_b2b_erp_ambiguous_warning() ) . '</strong>';
	}

	if ( is_array( $diag ) && ! empty( $diag ) ) {
		$time = ! empty( $diag['time'] ) ? wp_date( 'd.m.Y. H:i', strtotime( (string) $diag['time'] ) ) : '';
		$bits = array_filter( [
			isset( $diag['phase'] ) ? sprintf( /* translators: %s: export phase */ __( 'faza: %s', 'dreampoint-b2b' ), (string) $diag['phase'] ) : '',
			isset( $diag['category'] ) ? sprintf( /* translators: %s: error category */ __( 'kategorija: %s', 'dreampoint-b2b' ), (string) $diag['category'] ) : '',
			! empty( $diag['http'] ) ? 'HTTP ' . (int) $diag['http'] : '',
			$time,
		] );

		echo '<br><small>' . esc_html( implode( ' | ', $bits ) );

		if ( ! empty( $diag['description'] ) ) {
			echo '<br>' . esc_html( (string) $diag['description'] );
		}

		echo '</small>';
	}

	echo '</p>';
} );

// -------------------------------------------------------------------------
// Failure notification (one e-mail per failed / ambiguous order, atomic claim)
// -------------------------------------------------------------------------

/**
 * Atomic claim: one INSERT IGNORE into wp_options (option_name is UNIQUE). True only for the process
 * that created the row. A claim is released ONLY when wp_mail() positively did not accept the message.
 */
function dreampoint_b2b_erp_claim_notice( int $order_id ): bool {
	global $wpdb;

	return 1 === (int) $wpdb->query( $wpdb->prepare(
		"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
		'dp_erp_notice_' . $order_id,
		'claimed|' . gmdate( 'c' )
	) );
}

function dreampoint_b2b_erp_set_claim( int $order_id, string $value ): void {
	global $wpdb;

	$wpdb->update( $wpdb->options, [ 'option_value' => $value ], [ 'option_name' => 'dp_erp_notice_' . $order_id ] );
}

function dreampoint_b2b_erp_release_claim( int $order_id ): void {
	global $wpdb;

	$wpdb->delete( $wpdb->options, [ 'option_name' => 'dp_erp_notice_' . $order_id ] );
}

/**
 * Listener for the importer's `dp_erp_export_outcome` action. Never throws into the export request.
 *
 * Semantics: a successful wp_mail() return means "accepted by the configured mail mechanism", not
 * delivered. If the process dies between the claim and the hand-off, the claim stays (conservative:
 * no second e-mail) and the order meta `_erp_failure_notice` keeps the visible value `claimed|...`.
 *
 * @param WC_Order             $order
 * @param string               $state FAILED | AMBIGUOUS | SENT
 * @param array<string, mixed> $diag
 */
function dreampoint_b2b_erp_notify_failure( $order, $state = '', $diag = [] ): void {
	if ( ! $order instanceof WC_Order || ! in_array( $state, [ 'FAILED', 'AMBIGUOUS' ], true ) ) {
		return;
	}

	try {
		$order_id = (int) $order->get_id();

		if ( ! dreampoint_b2b_erp_claim_notice( $order_id ) ) {
			return;
		}

		$order->update_meta_data( '_erp_failure_notice', 'claimed|' . gmdate( 'c' ) );
		$order->save();

		$reason  = isset( $diag['description'] ) ? (string) $diag['description'] : '';
		$subject = sprintf(
			/* translators: 1: order number, 2: ERP export outcome label */
			__( '[DreamPoint] ERP izvoz narudžbe #%1$s: %2$s', 'dreampoint-b2b' ),
			$order->get_order_number(),
			dreampoint_b2b_erp_state_label( $state )
		);

		$lines = [
			sprintf( __( 'Narudžba: #%s', 'dreampoint-b2b' ), $order->get_order_number() ),
			sprintf( __( 'Ishod izvoza: %s', 'dreampoint-b2b' ), dreampoint_b2b_erp_state_label( $state ) ),
			sprintf( __( 'Razlog: %s', 'dreampoint-b2b' ), $reason ),
			sprintf( __( 'Vrijeme: %s', 'dreampoint-b2b' ), wp_date( 'd.m.Y. H:i:s' ) ),
			sprintf( __( 'Narudžba u administraciji: %s', 'dreampoint-b2b' ), $order->get_edit_order_url() ),
			'',
		];

		if ( 'AMBIGUOUS' === $state ) {
			$lines[] = dreampoint_b2b_erp_ambiguous_warning();
			$lines[] = __( 'Tek nakon provjere koristite radnju „Pošalji u ERP – potvrđujem provjeru u APROS-u“ na stranici narudžbe.', 'dreampoint-b2b' );
		} else {
			$lines[] = __( 'APROS nije kontaktiran ili je narudžba sigurno odbijena prije slanja. Nakon uklanjanja uzroka koristite radnju „Pošalji u ERP“ na stranici narudžbe.', 'dreampoint-b2b' );
		}

		$sent = false;

		try {
			$sent = (bool) wp_mail( get_option( 'admin_email' ), $subject, implode( "\n", $lines ) );
		} catch ( \Throwable $e ) {
			$sent = false;
			error_log( '[dreampoint-b2b] ERP failure notice mail threw for order ' . $order_id . ': ' . get_class( $e ) );
		}

		if ( $sent ) {
			dreampoint_b2b_erp_set_claim( $order_id, 'handed_off|' . gmdate( 'c' ) );
			$order->update_meta_data( '_erp_failure_notice', 'handed_off|' . gmdate( 'c' ) );
		} else {
			// positively not accepted: release the claim so that a later outcome may notify, make the failure visible
			dreampoint_b2b_erp_release_claim( $order_id );
			$order->update_meta_data( '_erp_failure_notice', 'mail_failed|' . gmdate( 'c' ) );
			$order->add_order_note( 'ERP obavijest: e-mail administratoru nije prihvaćen za slanje (wp_mail).' );
		}

		$order->save();
	} catch ( \Throwable $e ) {
		error_log( '[dreampoint-b2b] ERP failure notice failed: ' . get_class( $e ) );
	}
}
add_action( 'dp_erp_export_outcome', 'dreampoint_b2b_erp_notify_failure', 10, 3 );

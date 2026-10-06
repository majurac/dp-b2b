<?php
/**
 * LOCAL-ONLY integration test for the Excel import validation endpoint.
 *
 *   php wp-cli.phar eval-file wp-content/plugins/dp-b2b-quick-order/tests/import/validator-test.php
 *
 * It creates throw-away synthetic products (marked `_dp_test_fixture`), exercises the REST route in-process
 * (`rest_do_request` as real users — the real permission callback, visibility engine and WooCommerce cart),
 * then deletes the fixtures. It REFUSES to run anywhere that is not localhost. It never touches staging.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! str_contains( home_url(), 'localhost' ) ) {
	WP_CLI::error( 'Refusing to run: this test creates and deletes products and is local-only.' );
}

const T_PREFIX = 'DPT-';

$GLOBALS['t_pass'] = 0;
$GLOBALS['t_fail'] = 0;

function t_ok( bool $cond, string $label, mixed $detail = null ): void {
	if ( $cond ) {
		++$GLOBALS['t_pass'];
		echo "ok    $label\n";
	} else {
		++$GLOBALS['t_fail'];
		echo "FAIL  $label" . ( null !== $detail ? '  → ' . wp_json_encode( $detail, JSON_UNESCAPED_UNICODE ) : '' ) . "\n";
	}
}

// ── fixtures ───────────────────────────────────────────────────────────────────────────────────────────────
function t_cleanup(): void {
	$ids = get_posts( [
		'post_type' => [ 'product', 'product_variation' ], 'post_status' => 'any', 'fields' => 'ids',
		'numberposts' => -1, 'suppress_filters' => true, 'meta_key' => '_dp_test_fixture',
	] );
	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}
}

function t_simple( string $name, ?string $sku, string $ac, array $o = [] ): int {
	$p = new WC_Product_Simple();
	$p->set_name( T_PREFIX . $name );
	$p->set_status( $o['status'] ?? 'publish' );
	$p->set_regular_price( '10' );
	if ( $sku ) {
		$p->set_sku( $sku );
	}
	$manage = $o['manage'] ?? true;
	$p->set_manage_stock( $manage );
	if ( $manage ) {
		$p->set_stock_quantity( $o['stock'] ?? 10 );
	} else {
		$p->set_stock_status( $o['stock_status'] ?? 'instock' );
	}
	$p->set_backorders( $o['bo'] ?? 'no' );
	$p->set_sold_individually( $o['sold'] ?? false );
	$id = $p->save();
	update_post_meta( $id, '_ARTICLE_CODE', $ac );
	update_post_meta( $id, '_dp_test_fixture', 1 );
	return $id;
}

/** @return array{parent:int, variations:int[]} */
function t_variable( string $name, string $sku, string $ac, array $vars, array $o = [] ): array {
	$p = new WC_Product_Variable();
	$p->set_name( T_PREFIX . $name );
	$p->set_status( $o['status'] ?? 'publish' );
	$p->set_sku( $sku );
	if ( isset( $o['parent_stock'] ) ) {
		$p->set_manage_stock( true );
		$p->set_stock_quantity( $o['parent_stock'] );
	}
	$attr = new WC_Product_Attribute();
	$attr->set_name( 'Boja' );
	$attr->set_options( array_keys( $vars ) );
	$attr->set_visible( true );
	$attr->set_variation( true );
	$p->set_attributes( [ $attr ] );
	$pid = $p->save();
	update_post_meta( $pid, '_ARTICLE_CODE', $ac );
	update_post_meta( $pid, '_dp_test_fixture', 1 );

	$ids = [];
	foreach ( $vars as $color => $v ) {
		$var = new WC_Product_Variation();
		$var->set_parent_id( $pid );
		$var->set_attributes( [ 'boja' => $color ] );
		$var->set_status( 'publish' );
		$var->set_regular_price( '10' );
		$var->set_sku( $v['sku'] );
		if ( 'parent' === ( $v['manage'] ?? true ) ) {
			$var->set_manage_stock( 'parent' );
		} else {
			$var->set_manage_stock( true );
			$var->set_stock_quantity( $v['stock'] ?? 5 );
		}
		$vid = $var->save();
		update_post_meta( $vid, '_ARTICLE_CODE', $v['ac'] );
		update_post_meta( $vid, '_dp_test_fixture', 1 );
		$ids[ $color ] = $vid;
	}
	WC_Product_Variable::sync( $pid );
	wc_delete_product_transients( $pid );
	return [ 'parent' => $pid, 'variations' => $ids ];
}

// ── REST helper ────────────────────────────────────────────────────────────────────────────────────────────
function t_call( int $uid, mixed $rows, bool $raw_body = false ): WP_REST_Response {
	wp_set_current_user( $uid );
	$req = new WP_REST_Request( 'POST', '/dreampoint-b2b/v1/quick-order/import/validate' );
	$req->set_header( 'Content-Type', 'application/json' );
	$req->set_body( wp_json_encode( $raw_body ? $rows : [ 'rows' => $rows ] ) );
	return rest_do_request( $req );
}

function t_r( string $id, mixed $q, int $row = 0 ): array {
	return [ 'row' => $row ?: random_int( 1, 9 ), 'identifier' => $id, 'quantity' => $q ];
}

function t_rows( int $uid, array $rows ): array {
	$res = t_call( $uid, $rows );
	return $res->get_status() === 200 ? $res->get_data()['rows'] : [ 'HTTP' => $res->get_status(), 'data' => $res->get_data() ];
}

function t_stock( int $id ): mixed {
	return wc_get_product( $id )->get_stock_quantity();
}

function t_cart_clear(): void {
	wc_load_cart();
	WC()->cart->empty_cart();
}

// ── setup ──────────────────────────────────────────────────────────────────────────────────────────────────
t_cleanup();
wp_set_current_user( 1 );

$S1   = t_simple( 'S1', 'P-T001', 'T-AC-001', [ 'stock' => 10 ] );
$S2   = t_simple( 'S2 leading zero', 'P-T002', '000046', [ 'stock' => 5 ] );
$S3   = t_simple( 'S3 draft', 'P-T003', 'T-DRAFT', [ 'status' => 'draft' ] );
$S4   = t_simple( 'S4 oos', 'P-T004', 'T-OOS', [ 'stock' => 0 ] );
$S5   = t_simple( 'S5 unmanaged', 'P-T005', 'T-UNM', [ 'manage' => false ] );
$S6   = t_simple( 'S6 bo yes', 'P-T006', 'T-BO-YES', [ 'stock' => 2, 'bo' => 'yes' ] );
$S7   = t_simple( 'S7 bo notify', 'P-T007', 'T-BO-NOTIFY', [ 'stock' => 2, 'bo' => 'notify' ] );
$S8   = t_simple( 'S8 sold individually', 'P-T008', 'T-SOLD', [ 'stock' => 10, 'sold' => true ] );
$S9   = t_simple( 'S9 odd chars', 'P-T009', 'T, AC 9 &/+ x' );
$S10  = t_simple( 'S10 ambiguous sku', 'T-AMBIG', 'T-AC-010' );
$S11  = t_simple( 'S11 ambiguous ac', 'P-T011', 'T-AMBIG' );
$S12  = t_simple( 'S12 unmanaged oos', 'P-T012', 'T-UNM-OOS', [ 'manage' => false, 'stock_status' => 'outofstock' ] );
$V1   = t_variable( 'V1 own stock', 'P-TV1', 'TV/1', [
	'A' => [ 'sku' => '910001', 'ac' => 'TV/1/A', 'stock' => 6 ],
	'B' => [ 'sku' => '910002', 'ac' => 'TV/1/B', 'stock' => 3 ],
] );
$V2   = t_variable( 'V2 shared pool', 'P-TV2', 'TV/2', [
	'A' => [ 'sku' => '920001', 'ac' => 'TV/2/A', 'manage' => 'parent' ],
	'B' => [ 'sku' => '920002', 'ac' => 'TV/2/B', 'manage' => 'parent' ],
], [ 'parent_stock' => 5 ] );
$V3   = t_variable( 'V3 draft parent', 'P-TV3', 'TV/3', [ 'A' => [ 'sku' => '930001', 'ac' => 'TV/3/A', 'stock' => 4 ] ], [ 'status' => 'draft' ] );

// Boundary-whitespace regression fixtures (real ERP data stores a trailing LF in some _ARTICLE_CODE values).
$N1   = t_simple( 'N1 trailing LF', 'P-TN01', "T-NLF-1\n" );
$N2   = t_simple( 'N2 trailing CRLF', 'P-TN02', "T-NCRLF\r\n" );
$N3   = t_simple( 'N3 trailing tab', 'P-TN03', "T-NTAB\t" );
$N4   = t_simple( 'N4 leading ws', 'P-TN04', "\t  T-NLEAD" );
$N5   = t_simple( 'N5 internal spaces', 'P-TN05', 'T INNER  SP' );
$N6   = t_simple( 'N6 leading zero + LF', 'P-TN06', "0001230\n" );
$N7a  = t_simple( 'N7a dup plain', 'P-TN07', 'T-DUPN' );
$N7b  = t_simple( 'N7b dup trailing LF', 'P-TN08', "T-DUPN\n" );
$N8a  = t_simple( 'N8a sku side', 'T-XNS', 'T-AC-N8A' );
$N8b  = t_simple( 'N8b ac side LF', 'P-TN09', "T-XNS\n" );
$N9   = t_simple( 'N9 nbsp/bom', 'P-TN10', "\u{00A0}T-NBSP\u{FEFF}" );
$V4   = t_variable( 'V4 stored LF on variation', 'P-TV4', 'TV/4', [ 'A' => [ 'sku' => '940001', 'ac' => "TV/4/A\n", 'stock' => 6 ] ] );

$ADMIN = 1;
$FULL  = 3; // vis_full — full access, B2B user

// ── 1. identifier resolution ─────────────────────────────────────────────────────────────────────────────────
echo "\n# identifier resolution\n";
$r = t_rows( $FULL, [ t_r( 'T-AC-001', '3', 1 ) ] );
t_ok( 'ready' === $r[0]['status'] && 3 === $r[0]['quantity'] && $S1 === $r[0]['product_id'] && 0 === $r[0]['variation_id'] && T_PREFIX . 'S1' === $r[0]['name'], '_ARTICLE_CODE simple → ready, ids + name', $r[0] );
$r = t_rows( $FULL, [ t_r( 'p-t001', '3', 1 ) ] );
t_ok( 'ready' === $r[0]['status'] && $S1 === $r[0]['product_id'], '_sku simple, case-insensitive', $r[0] );
$r = t_rows( $FULL, [ t_r( " \u{00A0}T-AC-001\t ", '3', 1 ) ] );
t_ok( 'ready' === $r[0]['status'], 'whitespace/NBSP trimmed', $r[0] );
$r = t_rows( $FULL, [ t_r( 'TV/1/A', '2', 1 ), t_r( '910002', '2', 2 ) ] );
t_ok( 'ready' === $r[0]['status'] && $V1['variations']['A'] === $r[0]['variation_id'] && $V1['parent'] === $r[0]['product_id'], '_ARTICLE_CODE variation → variation + parent ids', $r[0] );
t_ok( 'ready' === $r[1]['status'] && $V1['variations']['B'] === $r[1]['variation_id'], '_sku variation (numeric) resolves', $r[1] );
$r = t_rows( $FULL, [ t_r( '000046', '1', 1 ), t_r( '46', '1', 2 ) ] );
t_ok( 'ready' === $r[0]['status'] && $S2 === $r[0]['product_id'], 'leading-zero _ARTICLE_CODE resolves', $r[0] );
t_ok( 'identifier_not_found' === $r[1]['code'], 'stripped "46" fails closed (no padding / numeric coercion)', $r[1] );
$r = t_rows( $FULL, [ t_r( 'T, AC 9 &/+ x', '1', 1 ) ] );
t_ok( 'ready' === $r[0]['status'] && $S9 === $r[0]['product_id'], 'identifier with comma/space/&/+ resolves', $r[0] );
$r = t_rows( $FULL, [ t_r( 'P-TV1', '1', 1 ), t_r( 'TV/1', '1', 2 ), t_r( 'P-TV2', '1', 3 ) ] );
foreach ( $r as $i => $row ) {
	t_ok( 'variable_parent' === $row['code'] && null === $row['product_id'] && null === $row['variation_id'], "variable parent identifier #$i → variable_parent, no ids", $row );
}
$r = t_rows( $FULL, [ t_r( 'NOPE-1', '1', 1 ), t_r( 'T-DRAFT', '1', 2 ), t_r( 'P-T003', '1', 3 ), t_r( '930001', '1', 4 ), t_r( 'TV/3/A', '1', 5 ), t_r( 'P-TV3', '1', 6 ) ] );
foreach ( $r as $i => $row ) {
	t_ok( 'identifier_not_found' === $row['code'] && null === $row['name'], "nonexistent/draft/orphan-variation/draft-parent #$i → identifier_not_found", $row );
}
$r = t_rows( $FULL, [ t_r( 'T-AMBIG', '1', 1 ) ] );
t_ok( 'ambiguous_identifier' === $r[0]['code'] && null === $r[0]['name'], 'identifier = sku of A and ARTICLE_CODE of B → ambiguous (no precedence)', $r[0] );
$r = t_rows( $FULL, [ t_r( 'T-AC-001', '2', 1 ), t_r( 'P-T001', '3', 2 ) ] );
t_ok( 1 === count( $r ) && [ 1, 2 ] === $r[0]['rows'] && 5 === $r[0]['requested'] && 5 === $r[0]['quantity'], 'same unit via _ARTICLE_CODE and _sku merges into one row', $r );
$r = t_rows( $FULL, [ t_r( 'P-T012', '1', 1 ), t_r( 'T-UNM-OOS', '1', 2 ) ] );
t_ok( 1 === count( $r ) && 'unavailable' === $r[0]['code'] && [ 1, 2 ] === $r[0]['rows'], 'unmanaged out-of-stock → unavailable (both identifiers of one unit merge)', $r );

// ── 2. quantity contract ─────────────────────────────────────────────────────────────────────────────────────
echo "\n# quantity contract\n";
foreach ( [ '', ' ', '0', '-1', '1.5', '1,5', '1e3', '1E3', '1,000', '1.000', 'abc', '007', '+5', '5 pcs', '０５', "1\n2" ] as $q ) {
	$r = t_rows( $FULL, [ t_r( 'T-UNM', $q, 1 ) ] );
	t_ok( 'invalid_quantity' === $r[0]['code'], 'rejects ' . wp_json_encode( $q ), $r[0] );
}
foreach ( [ null, true, 5.5, [ 1 ], 0 ] as $q ) {
	$r = t_rows( $FULL, [ t_r( 'T-UNM', $q, 1 ) ] );
	t_ok( 'invalid_quantity' === $r[0]['code'], 'rejects JSON ' . wp_json_encode( $q ), $r[0] );
}
$r = t_rows( $FULL, [ t_r( 'T-UNM', ' 5 ', 1 ), t_r( 'T-UNM', 7, 2 ) ] );
t_ok( 1 === count( $r ) && 12 === $r[0]['quantity'], 'trimmed text "5" and JSON int 7 accepted and merged (12)', $r );
$r = t_rows( $FULL, [ t_r( 'T-UNM', '99999', 1 ) ] );
t_ok( 'ready' === $r[0]['status'] && 99999 === $r[0]['quantity'], 'cap boundary 99999 accepted (unmanaged)', $r[0] );
foreach ( [ '100000', '99999999999999999999', '2147483648' ] as $q ) {
	$r = t_rows( $FULL, [ t_r( 'T-UNM', $q, 1 ) ] );
	t_ok( 'quantity_limit' === $r[0]['code'], "over-cap $q → quantity_limit, no overflow", $r[0] );
}
$r = t_rows( $FULL, [ t_r( 'T-UNM', '60000', 1 ), t_r( 'P-T005', '60000', 2 ) ] );
t_ok( 1 === count( $r ) && 'quantity_limit' === $r[0]['code'] && [ 1, 2 ] === $r[0]['rows'], 'duplicate rows cannot bypass the 99,999 cap', $r );
$r = t_rows( $FULL, [ t_r( '', '1', 1 ), t_r( str_repeat( 'X', 65 ), '1', 2 ), t_r( "A\x01B", '1', 3 ), [ 'row' => 4, 'quantity' => '1' ], 'garbage', [ 'row' => 6, 'identifier' => [ 'x' ], 'quantity' => '1' ] ] );
t_ok( array_unique( array_column( $r, 'code' ) ) === [ 'invalid_identifier' ], 'blank / over-long / control-char / missing / non-string identifier → invalid_identifier', array_column( $r, 'code' ) );

// ── 3. stock clamp ───────────────────────────────────────────────────────────────────────────────────────────
echo "\n# stock clamp (S1 stock 10)\n";
$r = t_rows( $FULL, [ t_r( 'T-AC-001', '4', 1 ) ] );
t_ok( 'ready' === $r[0]['status'] && 4 === $r[0]['quantity'], 'requested < available → ready', $r[0] );
$r = t_rows( $FULL, [ t_r( 'T-AC-001', '10', 1 ) ] );
t_ok( 'ready' === $r[0]['status'] && 10 === $r[0]['quantity'], 'requested == available → ready', $r[0] );
$r = t_rows( $FULL, [ t_r( 'T-AC-001', '15', 1 ) ] );
t_ok( 'adjusted' === $r[0]['status'] && 10 === $r[0]['quantity'] && 15 === $r[0]['requested'] && $S1 === $r[0]['product_id'], 'requested > available → adjusted to available', $r[0] );
$r = t_rows( $FULL, [ t_r( 'T-OOS', '3', 1 ) ] );
t_ok( 'error' === $r[0]['status'] && 'unavailable' === $r[0]['code'] && 0 === $r[0]['quantity'] && null === $r[0]['product_id'], 'out-of-stock → unavailable, no ids', $r[0] );
$r = t_rows( $FULL, [ t_r( 'T-AC-001', '7', 1 ), t_r( 'T-AC-001', '7', 2 ) ] );
t_ok( 1 === count( $r ) && 14 === $r[0]['requested'] && 10 === $r[0]['quantity'] && 'adjusted' === $r[0]['status'], 'duplicate rows: combined 14 clamped to 10', $r );

echo "\n# current cart consumption (additional orderable)\n";
t_cart_clear();
WC()->cart->add_to_cart( $S1, 4 );
$r = t_rows( $FULL, [ t_r( 'T-AC-001', '8', 1 ) ] );
t_ok( 'adjusted' === $r[0]['status'] && 6 === $r[0]['quantity'], 'stock 10, cart 4, requested 8 → 6 (adjusted)', $r[0] );
$r = t_rows( $FULL, [ t_r( 'T-AC-001', '6', 1 ) ] );
t_ok( 'ready' === $r[0]['status'] && 6 === $r[0]['quantity'], 'stock 10, cart 4, requested 6 → ready', $r[0] );
t_cart_clear();
WC()->cart->add_to_cart( $S1, 10 );
$r = t_rows( $FULL, [ t_r( 'T-AC-001', '1', 1 ) ] );
t_ok( 'unavailable' === $r[0]['code'], 'cart already holds all stock → unavailable (zero additional)', $r[0] );
t_cart_clear();

echo "\n# backorders / unmanaged / sold individually\n";
$r = t_rows( $FULL, [ t_r( 'T-BO-YES', '50', 1 ), t_r( 'T-BO-NOTIFY', '50', 2 ), t_r( 'T-UNM', '500', 3 ) ] );
t_ok( [ 'ready', 'ready', 'ready' ] === array_column( $r, 'status' ) && [ 50, 50, 500 ] === array_column( $r, 'quantity' ), 'backorders yes / notify and unmanaged: no finite clamp (WooCommerce allows it)', $r );
$r = t_rows( $FULL, [ t_r( 'T-SOLD', '5', 1 ) ] );
t_ok( 'adjusted' === $r[0]['status'] && 1 === $r[0]['quantity'], 'sold individually: 5 → 1 (adjusted)', $r[0] );
t_cart_clear();
WC()->cart->add_to_cart( $S8, 1 );
$r = t_rows( $FULL, [ t_r( 'T-SOLD', '1', 1 ) ] );
t_ok( 'unavailable' === $r[0]['code'], 'sold individually already in cart → unavailable', $r[0] );
t_cart_clear();

echo "\n# variations / shared stock pool\n";
$r = t_rows( $FULL, [ t_r( 'TV/1/A', '9', 1 ), t_r( 'TV/1/B', '9', 2 ) ] );
t_ok( 6 === $r[0]['quantity'] && 3 === $r[1]['quantity'] && [ 'adjusted', 'adjusted' ] === array_column( $r, 'status' ), 'own-managed variations clamp independently (6 / 3)', $r );
t_ok( wc_get_product( $V2['variations']['A'] )->get_stock_managed_by_id() === $V2['parent'], 'fixture: parent-managed variation pool id = parent' );
$r = t_rows( $FULL, [ t_r( 'TV/2/A', '4', 1 ), t_r( 'TV/2/B', '4', 2 ) ] );
t_ok( 'ready' === $r[0]['status'] && 4 === $r[0]['quantity'] && 'adjusted' === $r[1]['status'] && 1 === $r[1]['quantity'], 'shared pool 5: rows 4 + 4 → 4 and 1 (running allocation by managed-by ID)', $r );
t_cart_clear();
WC()->cart->add_to_cart( $V2['parent'], 2, $V2['variations']['A'], [ 'attribute_boja' => 'A' ] );
$r = t_rows( $FULL, [ t_r( 'TV/2/B', '4', 1 ) ] );
t_ok( 'adjusted' === $r[0]['status'] && 3 === $r[0]['quantity'], 'shared pool 5, sibling variation 2 in cart → B gets 3', $r[0] );
t_cart_clear();

// ── 4. ordering ──────────────────────────────────────────────────────────────────────────────────────────────
echo "\n# ordering / shape\n";
$r = t_rows( $FULL, [ t_r( 'NOPE-9', '1', 10 ), t_r( 'T-AC-001', '1', 11 ), t_r( 'P-T002', '1', 12 ), t_r( 'P-T001', '1', 13 ) ] );
t_ok( [ [ 10 ], [ 11, 13 ], [ 12 ] ] === array_column( $r, 'rows' ), 'first-occurrence ordering preserved, duplicates folded into first position', array_column( $r, 'rows' ) );

// ── 4b. stored boundary whitespace (symmetric normalization) ─────────────────────────────────────────────────
echo "\n# stored boundary whitespace\n";
foreach ( [ 'T-NLF-1' => $N1, 'T-NCRLF' => $N2, 'T-NTAB' => $N3, 'T-NLEAD' => $N4, 'T-NBSP' => $N9 ] as $ident => $unit ) {
	$r = t_rows( $FULL, [ t_r( $ident, '1', 1 ) ] );
	t_ok( 'ready' === $r[0]['status'] && $unit === $r[0]['product_id'], "stored boundary whitespace: \"$ident\" resolves to its unit", $r[0] );
}
$r = t_rows( $FULL, [ t_r( "T-NLF-1\n", '1', 1 ), t_r( "\t T-NLF-1 \r\n", '1', 2 ), t_r( 't-nlf-1', '1', 3 ) ] );
t_ok( 1 === count( $r ) && [ 1, 2, 3 ] === $r[0]['rows'] && $N1 === $r[0]['product_id'], 'submitted-side whitespace/case variants of the same stored value merge into one unit row', $r );
$r = t_rows( $FULL, [ t_r( 'T INNER  SP', '1', 1 ), t_r( 'T INNER SP', '1', 2 ), t_r( 'TINNER  SP', '1', 3 ), t_r( 'T-NLF 1', '1', 4 ) ] );
t_ok( 'ready' === $r[0]['status'] && $N5 === $r[0]['product_id'], 'internal whitespace preserved: exact "T INNER  SP" (two spaces) resolves', $r[0] );
t_ok( 'identifier_not_found' === $r[1]['code'] && 'identifier_not_found' === $r[2]['code'] && 'identifier_not_found' === $r[3]['code'], 'internal whitespace NOT collapsed / removed (no fuzzy matching)', $r );
$r = t_rows( $FULL, [ t_r( '0001230', '1', 1 ), t_r( '1230', '1', 2 ), t_r( '001230', '1', 3 ), t_r( '00001230', '1', 4 ) ] );
t_ok( 'ready' === $r[0]['status'] && $N6 === $r[0]['product_id'], 'leading zeros preserved: "0001230" resolves a value stored as "0001230\n"', $r[0] );
t_ok( [ 'identifier_not_found', 'identifier_not_found', 'identifier_not_found' ] === array_column( array_slice( $r, 1 ), 'code' ), 'stripped / re-padded variants still do NOT resolve (no zero-padding)', array_slice( $r, 1 ) );
$r = t_rows( $FULL, [ t_r( 'T-DUPN', '1', 1 ), t_r( "T-DUPN\n", '1', 2 ) ] );
t_ok( 2 === count( $r ) || 1 === count( $r ), 'dup fixture present' );
t_ok( 'ambiguous_identifier' === $r[0]['code'] && null === $r[0]['name'] && null === $r[0]['product_id'], 'same normalized identifier in two DIFFERENT units → ambiguous (fail closed, no precedence)', $r );
$r = t_rows( $FULL, [ t_r( 'T-XNS', '1', 1 ) ] );
t_ok( 'ambiguous_identifier' === $r[0]['code'] && null === $r[0]['name'], '_sku of unit A == normalized _ARTICLE_CODE of unit B → ambiguous (fail closed)', $r[0] );
$r = t_rows( $FULL, [ t_r( 'TV/4/A', '2', 1 ), t_r( '940001', '2', 2 ) ] );
t_ok( 1 === count( $r ) && 'ready' === $r[0]['status'] && $V4['variations']['A'] === $r[0]['variation_id'] && 4 === $r[0]['requested'], 'variation: stored "TV/4/A\n" resolves by normalized AC and merges with its _sku', $r );
$r = t_rows( $FULL, [ t_r( 'T-NLF-1', '99', 1 ) ] );
t_ok( 'adjusted' === $r[0]['status'] && 10 === $r[0]['quantity'], 'clamp still applies to a whitespace-normalized match', $r[0] );

// ── 5. payload / protocol ────────────────────────────────────────────────────────────────────────────────────
echo "\n# payload / protocol\n";
$many = array_fill( 0, 501, t_r( 'T-UNM', '1', 1 ) );
t_ok( 400 === t_call( $FULL, $many )->get_status(), '501 rows → 400' );
t_ok( 200 === t_call( $FULL, array_slice( $many, 0, 500 ) )->get_status(), '500 rows → 200' );
t_ok( 400 === t_call( $FULL, [] )->get_status(), 'empty rows → 400' );
t_ok( 400 === t_call( $FULL, [ 'a' => 1 ] )->get_status(), 'associative rows → 400' );
t_ok( 400 === t_call( $FULL, 'x', true )->get_status(), 'bad body → 400' );
t_ok( 403 === t_call( 2, [ t_r( 'T-UNM', '1', 1 ) ] )->get_status(), 'vis_none (no bucket) → 403' );
t_ok( in_array( t_call( 0, [ t_r( 'T-UNM', '1', 1 ) ] )->get_status(), [ 401, 403 ], true ), 'logged out → 401/403' );
$res = t_call( $FULL, [ t_r( 'T-UNM', '1', 1 ) ] );
t_ok( str_contains( (string) ( $res->get_headers()['Cache-Control'] ?? '' ), 'no-store' ), 'Cache-Control: no-store' );

// ── 6. read-only / privacy contract ───────────────────────────────────────────────────────────────────────────
echo "\n# read-only + response privacy\n";
t_cart_clear();
WC()->cart->add_to_cart( $S1, 2 );
$before = [ WC()->cart->get_cart_contents_count(), t_stock( $S1 ), t_stock( $V1['variations']['A'] ), t_stock( $V2['parent'] ) ];
$res    = t_call( $FULL, [ t_r( 'T-AC-001', '99', 1 ), t_r( 'TV/1/A', '3', 2 ), t_r( 'TV/2/A', '3', 3 ), t_r( 'NOPE', '1', 4 ), t_r( 'P-TV1', '1', 5 ) ] );
wc_load_cart();
$after = [ WC()->cart->get_cart_contents_count(), t_stock( $S1 ), t_stock( $V1['variations']['A'] ), t_stock( $V2['parent'] ) ];
t_ok( $before === $after, 'cart and stock unchanged by validation', [ $before, $after ] );
t_cart_clear();
$allowed = [ 'rows', 'identifier', 'name', 'requested', 'status', 'code', 'product_id', 'variation_id', 'quantity' ];
$keys    = [];
foreach ( $res->get_data()['rows'] as $row ) {
	$keys = array_merge( $keys, array_keys( $row ) );
}
t_ok( [] === array_diff( array_unique( $keys ), $allowed ), 'row keys ⊆ whitelist (no stock/managed/pool/price/brand fields)', array_diff( array_unique( $keys ), $allowed ) );
t_ok( [ 'rows', 'summary' ] === array_keys( $res->get_data() ) && [ 'ready', 'adjusted', 'error', 'input_rows' ] === array_keys( $res->get_data()['summary'] ), 'top-level keys: rows + summary only' );
t_ok( 5 === $res->get_data()['summary']['input_rows'], 'summary.input_rows counts submitted rows' );

// ── 7. restricted users: authorization before everything, non-oracle ─────────────────────────────────────────
echo "\n# restricted users / oracle\n";
$strip = static function ( array $row ): array {
	unset( $row['identifier'], $row['rows'], $row['requested'] );
	return $row;
};
foreach ( [ 4 => 'vis_rule_cat', 5 => 'vis_rule_brand', 6 => 'vis_offer' ] as $uid => $login ) {
	wp_set_current_user( $uid );
	$visible = [];
	$hidden  = null;
	foreach ( get_posts( [ 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 40, 'fields' => 'ids', 'suppress_filters' => true, 'orderby' => 'ID', 'order' => 'ASC', 'meta_query' => [ [ 'key' => '_sku', 'value' => 'DEV-', 'compare' => 'LIKE' ] ] ] ) as $pid ) {
		( apply_filters( 'dp_b2b_product_accessible', true, $pid, $uid ) ? $visible[] = $pid : $hidden ??= $pid );
	}
	t_ok( (bool) $hidden, "$login: found a hidden DEV product to probe" );
	$probe = [
		'hidden existing simple (sku)'   => (string) wc_get_product( $hidden )->get_sku(),
		'hidden fixture (AC)'            => 'T-AC-001',
		'hidden fixture variation (AC)'  => 'TV/1/A',
		'hidden fixture variation (sku)' => '910001',
		'hidden variable parent (sku)'   => 'P-TV1',
		'hidden variable parent (AC)'    => 'TV/1',
		'unpublished draft'              => 'T-DRAFT',
		'orphan variation'               => '930001',
		'nonexistent'                    => 'NOPE-DOES-NOT-EXIST',
		'hidden ambiguous pair'          => 'T-AMBIG',
		'hidden out-of-stock'            => 'T-OOS',
		'hidden stored-LF identifier'    => 'T-NLF-1',
		'hidden stored-LF, padded input' => " \t T-NLF-1\r\n",
		'hidden stored-LF variation'     => 'TV/4/A',
		'hidden whitespace-dup pair'     => 'T-DUPN',
	];
	$rows = [];
	$n    = 0;
	foreach ( $probe as $label => $ident ) {
		$rows[] = t_r( $ident, '9999', ++$n );
	}
	$res = t_call( $uid, $rows );
	$out = $res->get_data()['rows'] ?? [];
	t_ok( 200 === $res->get_status() && count( $out ) === count( $probe ), "$login: endpoint reachable", $res->get_status() );
	$shapes = array_unique( array_map( static fn( $r ) => wp_json_encode( $strip( $r ) ), $out ) );
	t_ok( 1 === count( $shapes ), "$login: hidden / unpublished / orphan / nonexistent / hidden-variable-parent / hidden-ambiguous are byte-identical (no oracle)", $shapes );
	$h = $out[0] ?? [];
	t_ok( 'identifier_not_found' === ( $h['code'] ?? '' ) && array_key_exists( 'name', $h ) && null === $h['name'] && 0 === $h['quantity'] && null === $h['product_id'] && null === $h['variation_id'], "$login: hidden row exposes no name/id/quantity", $h );
	$blob = wp_json_encode( $out );
	t_ok( ! str_contains( $blob, T_PREFIX ) && ! str_contains( $blob, 'stock' ), "$login: no fixture name / stock word anywhere in the response" );
	if ( $visible ) {
		$vp = wc_get_product( $visible[0] );
		$rv = t_rows( $uid, [ t_r( (string) $vp->get_sku(), '1', 1 ) ] );
		t_ok( in_array( $rv[0]['status'], [ 'ready', 'adjusted', 'error' ], true ) && ( 'identifier_not_found' !== $rv[0]['code'] ), "$login: an accessible product still resolves", $rv[0] );
	}
}

// ── 8. performance (in-process; excludes HTTP) ────────────────────────────────────────────────────────────────
echo "\n# performance (in-process, local DB)\n";
global $wpdb;
$dev_ids = $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key='_sku' AND meta_value LIKE 'DEV-%' ORDER BY meta_id LIMIT 500" );
foreach ( [ 1, 50, 100, 500 ] as $n ) {
	foreach ( [ 'admin' => $ADMIN, 'vis_full' => $FULL, 'vis_rule_cat' => 4 ] as $label => $uid ) {
		$rows = [];
		for ( $i = 0; $i < $n; $i++ ) {
			$rows[] = t_r( $dev_ids[ $i % count( $dev_ids ) ], '1', $i + 1 );
		}
		wp_set_current_user( $uid );
		wp_cache_flush();
		$q0 = get_num_queries();
		$t0 = microtime( true );
		$res = t_call( $uid, $rows );
		$ms  = ( microtime( true ) - $t0 ) * 1000;
		printf( "      n=%-3d %-13s %7.1f ms  queries=%d  status=%d\n", $n, $label, $ms, get_num_queries() - $q0, $res->get_status() );
	}
}

// ── teardown ─────────────────────────────────────────────────────────────────────────────────────────────────
t_cart_clear();
t_cleanup();
$left = count( get_posts( [ 'post_type' => [ 'product', 'product_variation' ], 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => -1, 'suppress_filters' => true, 'meta_key' => '_dp_test_fixture' ] ) );
t_ok( 0 === $left, 'fixtures cleaned up' );

echo "\n{$GLOBALS['t_pass']} passed, {$GLOBALS['t_fail']} failed\n";
exit( $GLOBALS['t_fail'] ? 1 : 0 );

/**
 * Cart totals sticky eligibility (E7-C)
 *
 * Toggles `.is-sticky-fit` on `.cart-collaterals` only when the whole card fits in the viewport below the sticky
 * header (offset + 24px clearance). The sticky CSS lives in sass/pages/cart.scss (>= 1200px only); without this
 * script, or when the card is too tall, the card stays in normal document flow.
 *
 * WooCommerce cart AJAX replaces `.cart_totals` but not `.cart-collaterals`, so one ResizeObserver on the wrapper
 * re-evaluates after every totals / coupon / notice change; the window resize covers viewport changes.
 *
 * @package Dreampoint_B2B
 */
( function () {
	'use strict';

	var MIN_WIDTH = 1200; // Two-column Cart layout breakpoint (cart.scss).
	var TOP       = 96;   // 72px header + 24px gap.
	var TOP_ADMIN = 128;  // + 32px admin bar.
	var CLEARANCE = 24;   // Space reserved below the card.

	var el = document.querySelector( '#cart .cart-collaterals' );
	if ( ! el || el.dataset.dpStickyInit ) {
		return;
	}
	el.dataset.dpStickyInit = '1';

	var frame = 0;

	function update() {
		frame = 0;
		if ( ! el.isConnected ) {
			return;
		}
		var offset = document.body.classList.contains( 'admin-bar' ) ? TOP_ADMIN : TOP;
		var fits   = window.innerWidth >= MIN_WIDTH && el.offsetHeight + offset + CLEARANCE <= window.innerHeight;
		el.classList.toggle( 'is-sticky-fit', fits );
	}

	function schedule() {
		if ( ! frame ) {
			frame = window.requestAnimationFrame( update );
		}
	}

	window.addEventListener( 'resize', schedule );
	if ( 'ResizeObserver' in window ) {
		new ResizeObserver( schedule ).observe( el );
	}
	update();
}() );

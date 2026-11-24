/**
 * Cart selection and bulk removal (E7 A) + counter sync (E7 A/B).
 *
 * - Checkboxes select cart lines for BULK REMOVAL ONLY; they are never part of the submitted cart form
 *   (no `name` attribute) and never influence Checkout.
 * - "Ukloni označene" reuses the native WooCommerce update_cart request: the quantity inputs of the selected
 *   lines are set to 0 and the existing hidden `update_cart` button is clicked once (same mechanism as
 *   js/cart-quantity.js). WooCommerce validates the nonce, the cart session and every cart item key itself.
 * - The selection lives in page-local memory only (no cookies / storage / database) and is reconciled with the
 *   cart lines that still exist after every WooCommerce AJAX refresh (`updated_wc_div`).
 * - All listeners are delegated and bound exactly once, so replacing the cart form never duplicates handlers.
 * - The visible counter sits under the page heading (outside the form that WooCommerce replaces): it is refreshed
 *   from the hidden copy inside the form after every refresh.
 *
 * @package Dreampoint_B2B
 */
( function ( $ ) {
	'use strict';

	if ( ! $ || window.dpCartSelectBound ) {
		return;
	}
	window.dpCartSelectBound = true;

	var strings = window.dpCartSelect || {};
	var selected = new Set(); // cart item keys (page-local, never persisted)
	var pending = null; // { form, inputs: [ [ input, previousValue ] ] } while a bulk update is in flight

	function getForm() {
		return document.querySelector( '.woocommerce-cart-form' );
	}

	function getBoxes( form ) {
		return Array.prototype.slice.call( form.querySelectorAll( '.cart-item-select' ) );
	}

	function isBusy( form ) {
		return pending !== null || form.classList.contains( 'processing' ) || ( form.closest && null !== form.closest( '.processing' ) );
	}

	// DOM <- state: select-all / indeterminate / bulk button / status text.
	function render( form ) {
		var boxes = getBoxes( form );
		var count = boxes.filter( function ( box ) { return box.checked; } ).length;
		var all = form.querySelector( '.cart-select-all__input' );
		var button = form.querySelector( '.cart-bulk-remove' );
		var status = form.querySelector( '.cart-select-status' );

		if ( all ) {
			all.checked = boxes.length > 0 && count === boxes.length;
			all.indeterminate = count > 0 && count < boxes.length;
		}
		if ( button ) {
			button.disabled = 0 === count || null !== pending;
		}
		if ( status ) {
			status.textContent = count ? ( strings.selected || '%d' ).replace( '%d', String( count ) ) : '';
		}
	}

	// Enable the controls (progressive enhancement) and reconcile the selection with the current cart lines.
	function init() {
		var form = getForm();

		if ( ! form || ! form.querySelector( '.cart-bulk-bar' ) ) {
			return;
		}

		var boxes = getBoxes( form );
		var present = new Set( boxes.map( function ( box ) { return box.getAttribute( 'data-cart-item-key' ); } ) );

		selected.forEach( function ( key ) {
			if ( ! present.has( key ) ) {
				selected.delete( key ); // stale selection
			}
		} );
		boxes.forEach( function ( box ) {
			box.checked = selected.has( box.getAttribute( 'data-cart-item-key' ) );
		} );

		form.classList.add( 'dp-cart-select' );
		form.querySelectorAll( '[data-select-ui]' ).forEach( function ( el ) {
			el.hidden = false;
		} );

		render( form );
	}

	// The visible counter is outside the replaced form: copy the fresh text from the hidden source.
	function syncCounter() {
		var source = document.querySelector( '.dp-cart-counter-source' );
		var counter = document.getElementById( 'dp-cart-counter' );

		if ( source && counter ) {
			counter.textContent = source.textContent.trim();
		}
	}

	document.addEventListener( 'change', function ( event ) {
		var target = event.target;
		var form = getForm();

		if ( ! target || ! target.classList || ! form ) {
			return;
		}

		if ( target.classList.contains( 'cart-item-select' ) ) {
			var key = target.getAttribute( 'data-cart-item-key' );
			if ( target.checked ) {
				selected.add( key );
			} else {
				selected.delete( key );
			}
			render( form );
		} else if ( target.classList.contains( 'cart-select-all__input' ) ) {
			getBoxes( form ).forEach( function ( box ) {
				var boxKey = box.getAttribute( 'data-cart-item-key' );
				box.checked = target.checked;
				if ( target.checked ) {
					selected.add( boxKey );
				} else {
					selected.delete( boxKey );
				}
			} );
			render( form );
		}
	} );

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( '.cart-bulk-remove' ) : null;

		if ( ! button ) {
			return;
		}

		event.preventDefault();

		var form = getForm();

		if ( ! form || button.disabled || isBusy( form ) ) {
			return;
		}

		var inputs = [];
		getBoxes( form ).forEach( function ( box ) {
			if ( ! box.checked ) {
				return;
			}
			// Cart item keys are md5 hashes; the same name WooCommerce posts for the quantity.
			var input = form.querySelector( 'input[name="cart[' + box.getAttribute( 'data-cart-item-key' ) + '][qty]"]' );
			if ( input ) {
				inputs.push( [ input, input.value ] );
			}
		} );

		if ( ! inputs.length ) {
			return;
		}

		pending = { form: form, inputs: inputs };
		inputs.forEach( function ( pair ) {
			pair[0].value = '0'; // no change event: js/cart-quantity.js must not fire its own update
		} );
		render( form );

		// One native request, exactly like a quantity change.
		$( form ).find( '[name="update_cart"]' ).prop( 'disabled', false ).trigger( 'click' );
	} );

	// Failed request: WooCommerce did not replace the form, so put the real quantities back.
	$( document ).on( 'ajaxComplete', function ( event, xhr, settings ) {
		if ( null === pending || ! settings || ! settings.url || 0 !== settings.url.indexOf( pending.form.getAttribute( 'action' ) || '\u0000' ) ) {
			return;
		}

		var current = pending;

		setTimeout( function () {
			if ( pending !== current ) {
				return;
			}
			pending = null;

			if ( document.body.contains( current.form ) ) {
				current.inputs.forEach( function ( pair ) {
					pair[0].value = pair[1];
				} );
				var form = getForm();
				if ( form ) {
					render( form );
				}
			}
		}, 0 );
	} );

	// The last cart line was removed (individually or in bulk): WooCommerce swapped in the empty-cart template, but the
	// heading banner (breadcrumb, H1, counter) is rendered by header.php outside that block - remove it, so only the
	// empty-cart heading (the single H1) remains. Direct loads of an empty cart are handled server-side in header.php.
	function syncEmptyState() {
		if ( document.querySelector( '.woocommerce-cart-form' ) || ! document.querySelector( '#not-found .not-found-holder' ) ) {
			return;
		}

		document.querySelectorAll( '.inner-heading' ).forEach( function ( banner ) {
			banner.remove();
		} );
	}

	// WooCommerce replaced the cart form (quantity change, removal, bulk removal, coupon).
	$( document.body ).on( 'updated_wc_div wc_cart_emptied', function () {
		pending = null;
		init();
		syncCounter();
		syncEmptyState();
	} );

	$( init );
}( window.jQuery ) );

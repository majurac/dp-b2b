/**
 * Brandovi logo dropdown — desktop panel behaviour.
 *
 * The panel (server-rendered inside the `li.brands-toggler` main-menu item) opens with CSS on hover and
 * keyboard focus. This script only adds:
 *  - touch: the first tap on the Brandovi link opens the panel instead of navigating; a second tap
 *    follows the link to the brand directory (the "Vidi sve brendove" link is always inside the panel),
 *  - Escape to dismiss the panel (focus returns to the Brandovi link if it was inside the panel),
 *  - closing a touch-opened panel on an outside tap.
 * Mouse and keyboard clicks on the link always navigate normally.
 */
( function () {
    'use strict';

    const OPEN = 'is-touch-open';
    let lastPointerType = 'mouse';

    function getHolder( node ) {
        return node && node.closest ? node.closest( '.brands-toggler' ) : null;
    }

    function hasPanel( holder ) {
        return !! ( holder && holder.querySelector( ':scope > .brands-menu' ) );
    }

    document.addEventListener( 'pointerdown', function ( event ) {
        lastPointerType = event.pointerType || 'mouse';

        // Outside tap closes a touch-opened panel.
        document.querySelectorAll( '.brands-toggler.' + OPEN ).forEach( function ( holder ) {
            if ( ! holder.contains( event.target ) ) {
                holder.classList.remove( OPEN );
            }
        } );
    }, true );

    document.addEventListener( 'click', function ( event ) {
        const link = event.target.closest && event.target.closest( '.brands-toggler > a' );
        const holder = link && getHolder( link );
        if ( ! holder || ! hasPanel( holder ) ) {
            return;
        }

        const pointerType = event.pointerType || lastPointerType;
        if ( pointerType !== 'touch' && pointerType !== 'pen' ) {
            return;
        }

        if ( ! holder.classList.contains( OPEN ) ) {
            event.preventDefault();
            holder.removeAttribute( 'data-dismissed' );
            holder.classList.add( OPEN );
        }
    } );

    document.addEventListener( 'keydown', function ( event ) {
        if ( event.key !== 'Escape' ) {
            return;
        }

        const holder = document.querySelector( '.brands-toggler:has(> .brands-menu)' );
        if ( ! holder ) {
            return;
        }

        const focusInside = holder.contains( document.activeElement );
        if ( ! focusInside && ! holder.classList.contains( OPEN ) && ! holder.matches( ':hover' ) ) {
            return;
        }

        holder.classList.remove( OPEN );
        if ( focusInside ) {
            const link = holder.querySelector( ':scope > a' );
            if ( link ) {
                link.focus();
            }
        }
        // Set after moving focus, so the focusin handler below does not undo the dismissal.
        holder.setAttribute( 'data-dismissed', '' );
    } );

    // Re-arm after Escape: pointer re-enters the link, or focus lands outside the panel.
    document.addEventListener( 'mouseover', function ( event ) {
        const holder = getHolder( event.target );
        if ( holder && holder.hasAttribute( 'data-dismissed' ) && event.target.closest( '.brands-toggler > a' ) ) {
            holder.removeAttribute( 'data-dismissed' );
        }
    } );

    document.addEventListener( 'focusin', function ( event ) {
        const holder = document.querySelector( '.brands-toggler[data-dismissed]' );
        if ( holder && ! event.target.closest( '.brands-menu' ) && event.target !== holder.querySelector( ':scope > a' ) ) {
            holder.removeAttribute( 'data-dismissed' );
        }
    } );
}() );

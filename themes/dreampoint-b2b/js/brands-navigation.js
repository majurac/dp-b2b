( function () {
    'use strict';

    // =========================================================================
    // BRANDS — NAVIGACIJA PO SEGMENTIMA
    // =========================================================================

    /**
     * Filtrira listu brendova prema kliknutom segmentu (data-segment atribut).
     * Server vec renderuje sve brendove — ovdje se samo toggle-uje vidljivost.
     */
    function initBrandSegments() {
        const nav = document.querySelector( '.brand-segments' );
        const items = document.querySelectorAll( '.brands-list .col' );

        if ( ! nav || ! items.length ) return;

        const tabs = nav.querySelectorAll( '.brand-segments__tab' );
        const trigger = nav.querySelector( '.brand-segments__trigger' );
        const current = nav.querySelector( '.brand-segments__current' );

        // Mobile dropdown (below md, see sass/pages/brands.scss): same open/close conventions as
        // js/account-navigation.js — aria-expanded on the trigger, Escape closes and refocuses it,
        // click outside closes. Filtering below stays the single implementation.
        function setOpen( open ) {
            nav.classList.toggle( 'is-open', open );
            if ( trigger ) trigger.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
        }

        if ( trigger ) {
            trigger.addEventListener( 'click', function ( e ) {
                setOpen( ! nav.classList.contains( 'is-open' ) );
                e.stopPropagation();
            } );

            document.addEventListener( 'click', function () {
                setOpen( false );
            } );

            document.addEventListener( 'keydown', function ( e ) {
                if ( e.key === 'Escape' && nav.classList.contains( 'is-open' ) ) {
                    setOpen( false );
                    trigger.focus();
                }
            } );
        }

        nav.addEventListener( 'click', function ( e ) {
            const tab = e.target.closest( '.brand-segments__tab' );
            if ( ! tab ) return;

            const segment = tab.getAttribute( 'data-segment' );

            tabs.forEach( function ( t ) {
                t.classList.remove( 'is-active' );
                t.setAttribute( 'aria-pressed', 'false' );
            } );
            tab.classList.add( 'is-active' );
            tab.setAttribute( 'aria-pressed', 'true' );

            // Reflect the selection on the (mobile) trigger and close the dropdown.
            if ( current ) current.textContent = tab.textContent.trim();
            if ( nav.classList.contains( 'is-open' ) ) {
                setOpen( false );
                if ( trigger ) trigger.focus();
            }

            items.forEach( function ( item ) {
                const matches = segment === 'all' || item.getAttribute( 'data-segment' ) === segment;
                item.classList.toggle( 'is-hidden', ! matches );
            } );
        } );
    }

    initBrandSegments();

} )();

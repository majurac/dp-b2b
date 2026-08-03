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

            items.forEach( function ( item ) {
                const matches = segment === 'all' || item.getAttribute( 'data-segment' ) === segment;
                item.classList.toggle( 'is-hidden', ! matches );
            } );
        } );
    }

    initBrandSegments();

} )();

( function () {
    'use strict';

    // FAQ accordion: native <button aria-expanded aria-controls> + [hidden] panels.
    // The first question is rendered expanded by PHP; category switching is a normal
    // page load (links in the header), so state never goes stale between categories.
    const root = document.getElementById( 'faq-body' );

    if ( ! root ) return;

    function setState( button, open ) {
        const panel = document.getElementById( button.getAttribute( 'aria-controls' ) );
        const wrap  = button.closest( '.faq-wrap' );

        button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
        if ( wrap ) wrap.classList.toggle( 'active', open );
        if ( panel ) panel.hidden = ! open;
    }

    // Single delegated listener — Enter/Space come from the native button.
    root.addEventListener( 'click', function ( e ) {
        const button = e.target.closest( '.faq-toggle' );

        if ( ! button || ! root.contains( button ) ) return;

        const willOpen = button.getAttribute( 'aria-expanded' ) !== 'true';

        // Single-open: collapse every other question first
        root.querySelectorAll( '.faq-toggle[aria-expanded="true"]' ).forEach( function ( other ) {
            if ( other !== button ) setState( other, false );
        } );

        setState( button, willOpen );
    } );
} )();

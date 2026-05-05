( function () {
    'use strict';

    // =========================================================================
    // STICKY HEADER — vanilla JS, bez jQuery zavisnosti
    // =========================================================================

    const header = document.getElementById( 'header' );

    if ( ! header ) return;

    let ticking = false;

    /**
     * Proverava poziciju skrolovanja i dodaje/uklanja sticky klasu.
     * Poziva se kroz requestAnimationFrame za performanse na 60fps.
     */
    function checkScroll() {
        if ( window.scrollY > 0 ) {
            header.classList.add( 'sticky' );
        } else {
            header.classList.remove( 'sticky' );
        }
        ticking = false;
    }

    /**
     * requestAnimationFrame omotač — ograničava scroll event na max 60fps.
     */
    function requestTick() {
        if ( ! ticking ) {
            window.requestAnimationFrame( checkScroll );
            ticking = true;
        }
    }

    // Proveri stanje pri učitavanju stranice
    checkScroll();

    // passive: true — govori browseru da neće biti preventDefault(),
    // što omogućava scroll optimizacije (posebno na mobilnim uređajima)
    window.addEventListener( 'scroll', requestTick, { passive: true } );

} )();

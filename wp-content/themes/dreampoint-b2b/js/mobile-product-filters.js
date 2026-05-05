( function ( $ ) {
    'use strict';

    // =========================================================================
    // MOBILE FILTER PANEL — toggle logika
    // =========================================================================

    const $filterWrap = $( '.products-lhs-filter-wrap' );
    const $body       = $( 'body' );

    if ( ! $filterWrap.length ) return;

    /**
     * Otvori mobilne filtere
     */
    $( '.sort-filter .filter-btn' ).on( 'click', function ( e ) {
        e.preventDefault();
        $filterWrap.addClass( 'active' );
        $body.addClass( 'filter-open' );
    } );

    /**
     * Zatvori mobilne filtere — dugme za zatvaranje
     */
    $( '.close-mobile-filters' ).on( 'click', function ( e ) {
        e.preventDefault();
        $filterWrap.removeClass( 'active' );
        $body.removeClass( 'filter-open' );
    } );

    /**
     * Zatvori filtere klikom na overlay (klik van panela)
     */
    $filterWrap.on( 'click', function ( e ) {
        if ( $( e.target ).is( $filterWrap ) ) {
            $filterWrap.removeClass( 'active' );
            $body.removeClass( 'filter-open' );
        }
    } );

    /**
     * Zatvori filtere pritiskom na ESC tipku
     */
    $( document ).on( 'keyup', function ( e ) {
        if ( e.key === 'Escape' && $filterWrap.hasClass( 'active' ) ) {
            $filterWrap.removeClass( 'active' );
            $body.removeClass( 'filter-open' );
        }
    } );

    // =========================================================================
    // PRISTUPAČNOST
    // =========================================================================

    // Ako su .filter-btn i .close-mobile-filters implementirani kao <div> ili <span>
    // umesto <button>/<a>, tabindex="0" ih uvršćuje u redosled fokusa tastature.
    // Ako su već <button> ili <a> — ova linija je bezopasna (nema efekta).
    $( '.filter-btn, .close-mobile-filters' ).attr( 'tabindex', '0' );

} )( jQuery );

( function ( $ ) {
    'use strict';

    // =========================================================================
    // MOBILNA NAVIGACIJA NALOGA — dropdown na ekranima < 991px
    // =========================================================================

    /**
     * Konvertuje WooCommerce navigaciju naloga u dropdown na mobilnim uređajima.
     * Izvršava se samo na ekranima manjim od 991px.
     */
    function initMobileAccountDropdown() {
        if ( $( window ).width() >= 991 ) return;

        const $dropdown   = $( '#dd' );
        const $accountNav = $( '#account-page .woocommerce-MyAccount-navigation' );

        if ( ! $dropdown.length || ! $accountNav.length ) return;

        const $activeItem      = $( '.dropd li.is-active a' );
        const $tabPlaceholder  = $( '.selected.tab-placeholder' );

        // Postavi početni tekst iz aktivne stavke
        if ( $activeItem.length && $tabPlaceholder.length ) {
            $tabPlaceholder.text( $activeItem.text() );
            $tabPlaceholder.addClass( $activeItem.parent().attr( 'class' ) );
        }

        // Toggle dropdown
        $dropdown.on( 'click', function ( e ) {
            $( this ).toggleClass( 'active' );
            e.stopPropagation();
        } );

        // Zatvori dropdown klikom van njega
        $( document ).on( 'click', function () {
            $( '.wrapper-dropdown' ).removeClass( 'active' );
        } );

        // Ažuriraj placeholder pri navigaciji
        $accountNav.find( 'li' ).on( 'click', function () {
            const $li     = $( this );
            const text    = $li.find( 'a' ).html();
            const liClass = $li.attr( 'class' );

            $tabPlaceholder
                .html( text )
                .attr( 'class', 'selected tab-placeholder ' + liClass );
        } );
    }

    // =========================================================================
    // INICIJALIZACIJA
    // =========================================================================

    initMobileAccountDropdown();

    // Ponovna inicijalizacija pri promeni veličine prozora (debounced)
    let resizeTimer;
    $( window ).on( 'resize', function () {
        clearTimeout( resizeTimer );
        resizeTimer = setTimeout( function () {
            // Pokreni samo ako prelazimo sa desktop na mobilni prikaz
            const wasMobile = $( '.wrapper-dropdown' ).length > 0;
            const isMobile  = $( window ).width() < 991;

            if ( isMobile && ! wasMobile ) {
                initMobileAccountDropdown();
            }
        }, 250 );
    } );

} )( jQuery );

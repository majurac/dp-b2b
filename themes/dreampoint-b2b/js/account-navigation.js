( function ( $ ) {
    'use strict';

    // =========================================================================
    // MOBILNA NAVIGACIJA NALOGA — dropdown na ekranima < 991px
    // =========================================================================

    /**
     * Konvertuje WooCommerce navigaciju naloga u dropdown na mobilnim uređajima.
     * Izvršava se samo na ekranima manjim od 991px. Idempotentno: handleri se vezuju jednom.
     *
     * Trigger je <button> (Tab / Enter / Space nativno), stanje se izlaže kroz aria-expanded,
     * Escape zatvara i vraća fokus na trigger. Linkovi su skriveni (visibility) dok je zatvoreno.
     */
    function initMobileAccountDropdown() {
        if ( $( window ).width() >= 991 ) return;

        const $dropdown   = $( '#dd' );
        const $accountNav = $( '#account-page .woocommerce-MyAccount-navigation' );

        if ( ! $dropdown.length || ! $accountNav.length ) return;
        if ( $dropdown.data( 'dpBound' ) ) return;
        $dropdown.data( 'dpBound', true );

        const $activeItem      = $( '.dropd li.is-active a' );
        const $tabPlaceholder  = $( '.selected.tab-placeholder' );
        const $current         = $tabPlaceholder.find( '.dp-dd-current' );

        // Postavi početni tekst iz aktivne stavke
        if ( $activeItem.length && $tabPlaceholder.length ) {
            $current.text( $.trim( $activeItem.text() ) );
            $tabPlaceholder.addClass( $activeItem.parent().attr( 'class' ) );
        }

        function setOpen( open ) {
            $dropdown.toggleClass( 'active', open );
            $tabPlaceholder.attr( 'aria-expanded', open ? 'true' : 'false' );
        }

        // Toggle dropdown (klik / dodir; Enter i Space na <button> generišu klik)
        $dropdown.on( 'click', function ( e ) {
            setOpen( ! $dropdown.hasClass( 'active' ) );
            e.stopPropagation();
        } );

        // Zatvori dropdown klikom van njega
        $( document ).on( 'click', function () {
            setOpen( false );
        } );

        // Escape zatvara i vraća fokus na trigger
        $( document ).on( 'keydown', function ( e ) {
            if ( e.key === 'Escape' && $dropdown.hasClass( 'active' ) ) {
                setOpen( false );
                $tabPlaceholder.trigger( 'focus' );
            }
        } );

        // Ažuriraj placeholder pri navigaciji
        $accountNav.find( 'li' ).on( 'click', function () {
            const $li     = $( this );
            const text    = $.trim( $li.find( 'a' ).text() );
            const liClass = $li.attr( 'class' );

            $current.text( text );
            $tabPlaceholder.attr( 'class', 'selected tab-placeholder ' + liClass );
        } );
    }

    // =========================================================================
    // INICIJALIZACIJA
    // =========================================================================

    initMobileAccountDropdown();

    // Ponovna inicijalizacija pri promeni veličine prozora (debounced); init je idempotentan
    let resizeTimer;
    $( window ).on( 'resize', function () {
        clearTimeout( resizeTimer );
        resizeTimer = setTimeout( initMobileAccountDropdown, 250 );
    } );

} )( jQuery );

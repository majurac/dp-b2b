( function ( $ ) {
    'use strict';

    // =========================================================================
    // FAQ ACCORDION
    // =========================================================================

    /**
     * Inicijalizuje FAQ accordion.
     * Prvi element je otvoren po podrazumevanoj vrednosti (bez animacije).
     */
    function initFaqAccordion() {
        const $accordion = $( '.accordion' );

        if ( ! $accordion.length ) return;

        // Otvori prvi FAQ element bez animacije
        const $firstFaq = $accordion.find( '.faq-wrap' ).first();
        $firstFaq.addClass( 'active' ).find( '> h3' ).addClass( 'active' );
        $firstFaq.find( '.content' ).show();

        // Klikovi na naslove
        $accordion.on( 'click', '.faq-wrap > h3', function ( e ) {
            e.preventDefault();

            const $clickedHeader    = $( this );
            const $clickedWrap      = $clickedHeader.closest( '.faq-wrap' );
            const $currentAccordion = $clickedHeader.closest( '.accordion' );
            const isActive          = $clickedHeader.hasClass( 'active' );

            if ( isActive ) {
                // Zatvori kliknuti element
                $clickedHeader.removeClass( 'active' );
                $clickedWrap.removeClass( 'active' );
                $clickedHeader.siblings( '.content' ).slideUp( 200 );
            } else {
                // Zatvori sve elemente u ovom accordionu
                $currentAccordion.find( '.faq-wrap > h3' ).removeClass( 'active' );
                $currentAccordion.find( '.faq-wrap' ).removeClass( 'active' );
                $currentAccordion.find( '.content' ).slideUp( 200 );

                // Otvori kliknuti element
                $clickedHeader.addClass( 'active' );
                $clickedWrap.addClass( 'active' );
                $clickedHeader.siblings( '.content' ).slideDown( 200 );
            }
        } );
    }

    // =========================================================================
    // FAQ TABOVI (filtriranje po kategorijama)
    // =========================================================================

    /**
     * Inicijalizuje FAQ tabove sa podrškom za duboko linkovanje putem URL hash-a.
     */
    function initFaqTabs() {
        const $tabContainer = $( '#faq-body .accordion-tabs ul' );

        if ( ! $tabContainer.length ) return;

        $tabContainer.each( function () {
            const $links = $( this ).find( 'a' );
            let $active, $content;

            // Aktivan tab iz URL hash-a ili prvi po podrazumevanoj vrednosti
            const $fromHash = $links.filter( '[href="' + location.hash + '"]' );
            $active  = $fromHash.length ? $fromHash.first() : $links.first();
            $content = $( $active.attr( 'href' ) );

            $active.addClass( 'active' );

            // Sakrij neaktivne tabove
            $links.not( $active ).each( function () {
                $( $( this ).attr( 'href' ) ).hide();
            } );

            // Klikovi na tabove
            $( this ).on( 'click', 'a', function ( e ) {
                e.preventDefault();

                const $clickedLink = $( this );

                if ( $clickedLink.hasClass( 'active' ) ) return;

                $active.removeClass( 'active' );

                $content.fadeOut( 200, function () {
                    $active  = $clickedLink;
                    $content = $( $clickedLink.attr( 'href' ) );

                    $active.addClass( 'active' );
                    $content.fadeIn( 200 );

                    // Ažuriraj URL hash bez skrolovanja
                    if ( history.pushState ) {
                        history.pushState( null, null, $clickedLink.attr( 'href' ) );
                    } else {
                        location.hash = $clickedLink.attr( 'href' );
                    }
                } );
            } );
        } );
    }

    // =========================================================================
    // INICIJALIZACIJA
    // =========================================================================

    initFaqAccordion();
    initFaqTabs();

    // =========================================================================
    // PRISTUPAČNOST
    // =========================================================================

    // Naslovi accordiona nisu nativno fokusabilni elementi (<h3>) —
    // tabindex="0" ih uvršćuje u redosled fokusa tastature
    $( '.accordion .faq-wrap > h3' )
        .attr( 'tabindex', '0' )
        .on( 'keypress', function ( e ) {
            // Enter (13) ili Space (32) simuliraju klik
            if ( e.which === 13 || e.which === 32 ) {
                e.preventDefault();
                $( this ).trigger( 'click' );
            }
        } );

} )( jQuery );

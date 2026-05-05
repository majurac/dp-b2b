( function ( $ ) {

    // =========================================================================
    // SELECT2 INICIJALIZACIJA
    // =========================================================================

    /**
     * Inicijalizuje Select2 na zadatom selektoru.
     * Preskače elemente koji su već inicijalizovani.
     *
     * @param {string} selector      - jQuery selektor
     * @param {object} customOptions - Opcione prilagođene Select2 opcije
     */
    function initSelect2( selector, customOptions = {} ) {
        const $elements = $( selector );

        if ( ! $elements.length ) return;

        const defaultOptions = {
            dropdownAutoWidth:        true,
            width:                    'auto',
            minimumResultsForSearch:  -1,       // Sakrij polje za pretragu
            theme:                    'default',
        };

        const options = $.extend( {}, defaultOptions, customOptions );

        $elements.each( function () {
            const $select = $( this );

            // Preskoči ako je već inicijalizovan
            if ( $select.hasClass( 'select2-hidden-accessible' ) ) return;

            $select.select2( options );
        } );
    }

    // =========================================================================
    // INICIJALIZACIJA INSTANCI
    // =========================================================================

    initSelect2( '.form-group select' );
    initSelect2( '.sort-area select' );
    initSelect2( '.variations select', {
        minimumResultsForSearch: 5, // Prikaži pretragu ako ima više od 5 opcija
    } );

    // Ponovna inicijalizacija nakon WooCommerce AJAX ažuriranja varijacija
    $( document ).on( 'woocommerce_update_variation_values', function () {
        initSelect2( '.variations select', {
            minimumResultsForSearch: 5,
        } );
    } );

} )( jQuery );

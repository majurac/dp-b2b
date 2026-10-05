( function ( $ ) {

    // =========================================================================
    // SELECT2 INICIJALIZACIJA
    // =========================================================================

    const variationOptions = {
        minimumResultsForSearch: 5, // Prikaži pretragu ako ima više od 5 opcija
    };

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
    initSelect2( '.variations select', variationOptions );

    // WooCommerce (add-to-cart-variation.js, onUpdateAttributes) pri svakoj promeni
    // atributa iznova gradi <option> elemente svakog variation selecta i vraća
    // trenutnu vrednost običnim .val() bez 'change' eventa. Select2 se ne obaveštava
    // o zameni DOM-a, pa njegova lista zadržava zastarelo "selected" stanje i
    // prethodno izabrana opcija (A→B→A) ostaje ne-izabrativa. Ponovna inicijalizacija
    // nakon što WooCommerce završi rebuild resinhronizuje Select2 sa stvarnim stanjem.
    $( document ).on( 'woocommerce_update_variation_values', '.variations_form', function () {
        $( this ).find( '.variations select' ).each( function () {
            const $select = $( this );

            if ( $select.data( 'select2' ) ) {
                $select.select2( 'destroy' );
            }
        } );

        initSelect2( $( this ).find( '.variations select' ), variationOptions );
    } );

} )( jQuery );

/**
 * Header search — default panel, live AJAX results, recent searches.
 *
 * States (only one of the two panels is shown at a time):
 *   - input shorter than 2 chars  → .search-default ("Popularne pretrage", "Nedavno pretraženo", hint)
 *   - input of 2+ chars           → #ajax-search-result (Cotra-style compact AJAX rows)
 *
 * Desktop: panels open under the input in the header.
 * Mobile (md-down): .search-area is a fullscreen overlay opened by .mobile-search-toggle.
 *
 * VAŽNO (iOS/WebKit): niti jedan klik/tap handler ne uklanja i ne prazni DOM koji sadrži link
 * ili formu na koju je korisnik upravo tapnuo (to otkazuje navigaciju/submit na iOS Safari-ju).
 *   - Linkovi (čipovi, nedavne pretrage, rezultati) se samo prate; handleri samo upisuju u storage.
 *   - "×" je zasebno <button> pored linka; lista se ponovo iscrtava tek nakon njegovog klika.
 *   - Zatvaranje (Escape / klik van) samo postavlja `hidden`, ne briše sadržaj.
 *   - Rezultati se prazne samo eksplicitno ("Odustani", zatvaranje overlaya, prazan upit).
 *
 * Nedavne pretrage: isključivo localStorage (po uređaju), max 4, najnovija prva,
 * bez ikakvog slanja na server.
 *
 * @package Dreampoint_B2B
 * @version 3.0.0
 */

jQuery( function ( $ ) {
    'use strict';

    const MIN_CHARS    = 2;
    const MAX_RECENT   = 4;
    const MAX_TERM_LEN = 80;
    const STORAGE_KEY  = 'dpRecentSearches';

    const $area     = $( '.search-area' );
    const $input    = $area.find( '#s' );
    const $form     = $area.find( 'form.custom-form' );
    const $results  = $area.find( '#ajax-search-result' );
    const $default  = $area.find( '.search-default' );
    const $recent   = $default.find( '.search-default__recent' );
    const $recentUl = $recent.find( '.search-recent' );
    const $toggle   = $( '.mobile-search-toggle' );
    const $closeBtn = $area.find( '.close-mobile-search' );
    const template  = document.getElementById( 'search-recent-template' );

    if ( ! $area.length || ! $input.length || ! $results.length ) {
        return;
    }

    let currentRequest = null;

    // ------------------------------------------------------------------------
    // Recent searches (localStorage, sve u try/catch — storage može biti nedostupan)
    // ------------------------------------------------------------------------

    function normalizeTerm( value ) {
        return String( value || '' ).replace( /\s+/g, ' ' ).trim().slice( 0, MAX_TERM_LEN );
    }

    function readRecent() {
        try {
            const parsed = JSON.parse( window.localStorage.getItem( STORAGE_KEY ) || '[]' );
            if ( ! Array.isArray( parsed ) ) {
                return [];
            }
            return parsed.map( normalizeTerm ).filter( ( t ) => t.length >= MIN_CHARS ).slice( 0, MAX_RECENT );
        } catch ( e ) {
            return [];
        }
    }

    function writeRecent( list ) {
        try {
            window.localStorage.setItem( STORAGE_KEY, JSON.stringify( list.slice( 0, MAX_RECENT ) ) );
        } catch ( e ) {
            // Storage nedostupan (privatni mod, blokiran, kvota) — pretraga radi bez istorije.
        }
    }

    // Upis termina: najnoviji prvi, bez duplikata (case-insensitive), max 4.
    function rememberTerm( value ) {
        const term = normalizeTerm( value );
        if ( term.length < MIN_CHARS ) {
            return;
        }
        const key  = term.toLocaleLowerCase();
        const list = readRecent().filter( ( t ) => t.toLocaleLowerCase() !== key );
        list.unshift( term );
        writeRecent( list );
    }

    function forgetTerm( value ) {
        const key = normalizeTerm( value ).toLocaleLowerCase();
        writeRecent( readRecent().filter( ( t ) => t.toLocaleLowerCase() !== key ) );
    }

    function searchUrl( term ) {
        const action = $form.attr( 'action' ) || '/';
        const url    = new URL( action, window.location.href );
        url.searchParams.set( 's', term );
        url.searchParams.set( 'post_type', 'product' );
        return url.toString();
    }

    function renderRecent() {
        const list = readRecent();

        $recentUl.empty();

        if ( ! list.length || ! template ) {
            $recent.prop( 'hidden', true );
            return;
        }

        list.forEach( ( term ) => {
            const item = template.content.firstElementChild.cloneNode( true );
            const link = item.querySelector( '.search-recent__link' );
            const btn  = item.querySelector( '.search-recent__remove' );

            link.setAttribute( 'href', searchUrl( term ) );
            link.setAttribute( 'data-search-term', term );
            item.querySelector( '.search-recent__term' ).textContent = term;
            btn.setAttribute( 'data-search-term', term );
            btn.setAttribute( 'aria-label', ( typeof dpAjax !== 'undefined' ? dpAjax.removeRecent : 'Ukloni „%s“' ).replace( '%s', term ) );

            $recentUl[ 0 ].appendChild( item );
        } );

        $recent.prop( 'hidden', false );
    }

    // ------------------------------------------------------------------------
    // Panels
    // ------------------------------------------------------------------------

    function termLength() {
        return $input.val().trim().length;
    }

    // Pokazuje tačno jedan panel prema dužini upita.
    function syncPanels() {
        if ( termLength() < MIN_CHARS ) {
            renderRecent();
            $results.prop( 'hidden', true );
            $default.prop( 'hidden', false );
        } else {
            $default.prop( 'hidden', true );
            $results.prop( 'hidden', false );
        }
    }

    // Zatvaranje samo sakriva — sadržaj (linkovi/forme) ostaje u DOM-u.
    function hidePanels() {
        $default.prop( 'hidden', true );
        $results.prop( 'hidden', true );
    }

    function abortRequest() {
        if ( currentRequest && currentRequest.readyState !== 4 ) {
            currentRequest.abort();
        }
        currentRequest = null;
    }

    function clearResults() {
        abortRequest();
        $results.empty();
    }

    function debounce( func, wait ) {
        let timeout;
        const debounced = function () {
            const context = this;
            const args    = arguments;
            clearTimeout( timeout );
            timeout = setTimeout( () => func.apply( context, args ), wait );
        };
        debounced.cancel = function () {
            clearTimeout( timeout );
        };
        return debounced;
    }

    // ------------------------------------------------------------------------
    // Live search
    // ------------------------------------------------------------------------

    function fetchResults() {
        const searchTerm = $input.val().trim();

        if ( searchTerm.length < MIN_CHARS ) {
            clearResults();
            syncPanels();
            return;
        }

        abortRequest();

        currentRequest = $.ajax( {
            url:     dpAjax.url,
            type:    'GET',
            data:    {
                action:     'search_products',
                searchTerm: searchTerm,
                nonce:      dpAjax.nonce,
            },
            timeout: 10000,
            success: function ( response ) {
                $results.html( response );
            },
            error:   function ( xhr, status ) {
                if ( status === 'abort' ) {
                    return;
                }
                $results.empty().append(
                    $( '<div class="sajx-nofund-prod"></div>' ).append( $( '<p></p>' ).text( dpAjax.error ) )
                );
            },
            complete: function () {
                currentRequest = null;
            },
        } );
    }

    const debouncedFetch = debounce( fetchResults, 500 );

    $input.on( 'input', function () {
        // Prelaz default ↔ rezultati je trenutan; samo dohvat je debounce-ovan.
        if ( termLength() < MIN_CHARS ) {
            clearResults();
        }
        syncPanels();
        debouncedFetch();
    } );

    $input.on( 'focus click', function () {
        syncPanels();
    } );

    // ------------------------------------------------------------------------
    // Upis nedavnih pretraga (samo upis — nikakvo uklanjanje DOM-a pre navigacije)
    // ------------------------------------------------------------------------

    $form.on( 'submit', function () {
        rememberTerm( $input.val() );
        // ne pokreći/ne ostavljaj XHR u letu dok pregledač napušta stranicu
        debouncedFetch.cancel();
        abortRequest();
    } );

    // "Vidi sve rezultate" forma iz AJAX rezultata.
    $results.on( 'submit', 'form', function () {
        rememberTerm( $( this ).find( 'input[name="s"]' ).val() );
        debouncedFetch.cancel();
        abortRequest();
    } );

    // Čip / nedavna pretraga: link ide normalno na stranicu rezultata; ovde se samo upisuje.
    $default.on( 'click', '.search-chip, .search-recent__link', function () {
        rememberTerm( $( this ).attr( 'data-search-term' ) );
    } );

    // "×" — briše samo tu stavku i ponovo iscrtava listu (dugme nije link, nema navigacije).
    $default.on( 'click', '.search-recent__remove', function ( e ) {
        e.preventDefault();
        forgetTerm( $( this ).attr( 'data-search-term' ) );
        renderRecent();
    } );

    // "Odustani" u footeru rezultata.
    $results.on( 'click', '.live-search-footer .cancel', function () {
        clearResults();
        syncPanels();
    } );

    // ------------------------------------------------------------------------
    // Mobilni overlay + zatvaranje
    // ------------------------------------------------------------------------

    function openMobileSearch() {
        $area.addClass( 'active' );
        $toggle.attr( 'aria-expanded', 'true' );
        syncPanels();
        // Fokus u istom user-gesture-u (iOS dozvoljava fokus samo iz tap handlera).
        $input.trigger( 'focus' );
    }

    function closeMobileSearch() {
        $area.removeClass( 'active' );
        $toggle.attr( 'aria-expanded', 'false' );
        clearResults();
        hidePanels();
    }

    $toggle.on( 'click', function ( e ) {
        e.preventDefault();
        openMobileSearch();
    } );

    $closeBtn.on( 'click', function ( e ) {
        e.preventDefault();
        closeMobileSearch();
        $toggle.trigger( 'focus' );
    } );

    $( document ).on( 'keyup', function ( e ) {
        if ( e.key !== 'Escape' ) {
            return;
        }
        if ( $area.hasClass( 'active' ) ) {
            closeMobileSearch();
            $toggle.trigger( 'focus' );
        } else if ( ! $default.prop( 'hidden' ) || ! $results.prop( 'hidden' ) ) {
            hidePanels();
        }
    } );

    // Klik/tap van pretrage zatvara desktop panele (samo `hidden`, sadržaj ostaje).
    $( document ).on( 'click', function ( e ) {
        if ( $area.hasClass( 'active' ) ) {
            return; // mobilni overlay se zatvara samo svojim dugmetom / Escape-om
        }
        // Meta koja je u međuvremenu uklonjena iz DOM-a (npr. "×" nakon ponovnog iscrtavanja liste)
        // nema roditelja pa bi izgledala kao "klik van pretrage".
        if ( ! document.documentElement.contains( e.target ) ) {
            return;
        }
        if ( $( e.target ).closest( $area ).length || $( e.target ).closest( $toggle ).length ) {
            return;
        }
        hidePanels();
    } );

    // Početno stanje: oba panela skrivena dok korisnik ne fokusira input.
    $results.prop( 'hidden', true );
} );

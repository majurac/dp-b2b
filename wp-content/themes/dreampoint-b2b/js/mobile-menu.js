/**
 * Mobile Menu — DOM manipulation & toggle logic
 *
 * Enqueue uvjet (PHP): sve stranice osim account stranice za nelogovane korisnike.
 * Nema potrebe za JS-side provjerama tipa stranice.
 *
 * Struktura:
 *  1. DOM manipulation (vanilla JS) — reorder i inject elemenata
 *  2. Menu toggle logika (jQuery) — open/close, submenu, back dugme
 */

( function ( $ ) {

    // =========================================================================
    // 1. DOM MANIPULATION — izvršava se jednom kada je DOM spreman
    // =========================================================================

    document.addEventListener( 'DOMContentLoaded', function () {

        // --- Desktop: inject product categories lista unutar .cat-toggler ---
        const categoriesList = document.querySelector( '.product-categories-list' );
        const catToggler     = document.querySelector( '.cat-toggler' );

        if ( categoriesList && catToggler ) {
            const link = catToggler.querySelector( 'a' );
            if ( link ) {
                link.insertAdjacentElement( 'afterend', categoriesList );
            }
        }

        // --- Mobile: inject product categories submenu unutar .cat-toggler (mobile menu) ---
        const mobileSubmenu   = document.querySelector( '.sub-menu.product-categories-mobile' );
        const catTogglerLink  = document.querySelector( '.mobile-menu__menu .cat-toggler a' );

        if ( mobileSubmenu && catTogglerLink ) {
            catTogglerLink.insertAdjacentElement( 'afterend', mobileSubmenu );
        }

        // --- Reorder: premjesti .menu-item-parent-proizvodi na prvo mjesto u .first-menu ---
        const mobileMenu = document.querySelector( '.mobile-menu__menu' );
        const firstMenu  = document.querySelector( 'ul.first-menu' );

        if ( mobileMenu && firstMenu ) {
            const menuItem = mobileMenu.querySelector( '.menu-item-parent-proizvodi' );
            if ( menuItem ) {
                firstMenu.insertBefore( menuItem, firstMenu.firstChild );
            }
        }

    } );

    // =========================================================================
    // 2. MENU TOGGLE LOGIKA (jQuery)
    // =========================================================================

    $( function () {

        const $elementsToToggleVisibility = $( '.mobile-menu__bottom, .mobile-menu__contact' );

        // --- Otvori / zatvori mobile menu ---
        $( '.mobile-toggler' ).on( 'click', function () {
            $( '.mobile-menu' )
                .addClass( 'active' )
                .attr( 'aria-hidden', 'false' );
        } );

        $( '.mobile-closer' ).on( 'click', function () {
            $( '.mobile-menu' )
                .removeClass( 'active' )
                .attr( 'aria-hidden', 'true' );
            // Vrati fokus na dugme koje je otvorilo meni
            $( '.mobile-toggler' ).first().trigger( 'focus' );
        } );

        // --- Submenu toggle (do 3 nivoa dubine) ---
        $( 'li.menu-item-has-children > a' ).on( 'click', function ( e ) {
            const $parentItem = $( this ).parent();
            const $subMenu    = $parentItem.children( 'ul.sub-menu' );

            if ( ! $subMenu.length ) return;

            e.preventDefault();

            if ( $subMenu.hasClass( 'active' ) ) {

                // Zatvori ovaj submenu i sve dublje aktivne submenije unutar njega
                $subMenu.removeClass( 'active' );
                $subMenu.find( 'ul.active' ).removeClass( 'active' );

                // Ako nema više aktivnih submenija — resetuj UI
                if ( $( '.first-menu ul.active' ).length === 0 ) {
                    $( '.mobile-toggle-black-js' ).removeClass( 'active' );
                    $elementsToToggleVisibility.removeClass( 'hide' );
                }

            } else {

                // Zatvori aktivne submenije na istom nivou (siblings) i njihove potomke
                $parentItem.siblings( 'li.menu-item-has-children' )
                    .children( 'ul.active' ).removeClass( 'active' )
                    .find( 'ul.active' ).removeClass( 'active' );

                $subMenu.addClass( 'active' );
                $( '.mobile-toggle-black-js' ).addClass( 'active' );
                $elementsToToggleVisibility.addClass( 'hide' );

            }
        } );

        // --- Back dugme: zatvori najdublji aktivni submenu ---
        $( '.mobile-toggle-black-js' ).on( 'click', function () {

            // Pronađi najdublji aktivni submenu (aktivan, ali bez aktivnih potomaka)
            const $deepest = $( '.first-menu ul.active' )
                .filter( function () {
                    return $( this ).find( 'ul.active' ).length === 0;
                } )
                .last();

            if ( $deepest.length ) {
                $deepest.removeClass( 'active' );
            }

            // Ako nema više aktivnih submenija — resetuj UI
            if ( $( '.first-menu ul.active' ).length === 0 ) {
                $( this ).removeClass( 'active' );
                $elementsToToggleVisibility.removeClass( 'hide' );
            }

        } );

    } );

} )( jQuery );

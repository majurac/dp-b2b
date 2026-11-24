/**
 * Mobile Menu — DOM manipulation & toggle logic
 *
 * Enqueue uvjet (PHP): sve stranice osim account stranice za nelogovane korisnike.
 * Nema potrebe za JS-side provjerama tipa stranice.
 *
 * Struktura:
 *  1. DOM manipulation (vanilla JS) — reorder i inject elemenata
 *  2. Menu toggle logika (jQuery) — open/close, submenu, back dugme,
 *     focus management, Escape, inert (G4)
 */

( function ( $ ) {

    // =========================================================================
    // 1. DOM MANIPULATION — izvršava se jednom kada je DOM spreman
    // =========================================================================

    document.addEventListener( 'DOMContentLoaded', function () {

        // Desktop catalog panel is rendered inside li.cat-toggler by PHP (inc/nav-categories.php) — no relocation.

        // --- Mobile: inject catalog menu submenu unutar .cat-toggler (mobile menu) ---
        const mobileSubmenu   = document.querySelector( '.sub-menu.catalog-menu-mobile' );
        const catTogglerLink  = document.querySelector( '.mobile-menu__menu .cat-toggler a' );

        if ( mobileSubmenu && catTogglerLink ) {
            // Keep the separate toggle button (if any) right after the link, the drill-down panel after the button.
            const catToggleButton = catTogglerLink.parentElement.querySelector( ':scope > button.submenu-toggle' );
            ( catToggleButton || catTogglerLink ).insertAdjacentElement( 'afterend', mobileSubmenu );
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
        const $menu    = $( '.mobile-menu' );
        const $toggler = $( '.mobile-toggler' ).first();
        const $nav     = $( '.mobile-menu__menu' );
        const mqMobile = window.matchMedia( '(max-width: 991.98px)' );

        // Elements this script made `inert`. Only these are ever released again, so an `inert` that was already
        // present on an unrelated element is left untouched. An ancestor of the menu is never made inert.
        const inertBackground = [];
        const inertBranch     = [];
        const NOT_INERTABLE   = /^(SCRIPT|STYLE|LINK|TEMPLATE|NOSCRIPT)$/;

        // Makes inert every sibling along the path from `node` up to (not including) `stopEl`.
        function inertAround( node, stopEl, bucket ) {
            while ( node && node !== stopEl && node.parentElement ) {
                Array.from( node.parentElement.children ).forEach( function ( sibling ) {
                    if ( sibling === node || sibling.hasAttribute( 'inert' ) || NOT_INERTABLE.test( sibling.tagName ) ) return;
                    sibling.setAttribute( 'inert', '' );
                    bucket.push( sibling );
                } );
                node = node.parentElement;
            }
        }

        function releaseInert( bucket ) {
            bucket.forEach( function ( el ) { el.removeAttribute( 'inert' ); } );
            bucket.length = 0;
        }

        // Page content outside the menu is inert while the modal-style menu is open.
        function setBackgroundInert( on ) {
            releaseInert( inertBackground );
            if ( on && $menu.length ) inertAround( $menu[ 0 ], document.body, inertBackground );
        }

        // Deepest active submenu (active, without active descendants).
        function getDeepestActive() {
            return $( '.first-menu ul.active' )
                .filter( function () {
                    return $( this ).find( 'ul.active' ).length === 0;
                } )
                .last();
        }

        // While a drill-down panel is open, every row / control outside the deepest active panel is inert, so the
        // covered parent levels cannot take keyboard focus. The active panel itself is never an inert target.
        function syncBranchInert() {
            releaseInert( inertBranch );
            const deepest = getDeepestActive();
            if ( deepest.length && $nav.length ) inertAround( deepest[ 0 ], $nav[ 0 ], inertBranch );
        }

        // aria-expanded on submenu triggers mirrors the real state of their submenu (`ul.sub-menu.active`).
        // The trigger is the separate `button.submenu-toggle` when the row has one (E8), otherwise the row's own link.
        function syncSubmenuAria() {
            $( '.mobile-menu__menu li.menu-item-has-children' ).each( function () {
                const $sub = $( this ).children( 'ul.sub-menu' );
                if ( ! $sub.length ) return;
                let $trigger = $( this ).children( 'button.submenu-toggle' );
                if ( ! $trigger.length ) {
                    $trigger = $( this ).children( 'a' );
                }
                $trigger.attr( 'aria-expanded', $sub.hasClass( 'active' ) ? 'true' : 'false' );
            } );
            syncBranchInert();
        }
        syncSubmenuAria();

        // --- Otvori / zatvori mobile menu ---
        function openMenu() {
            // Mutually exclusive with the mobile search overlay: close it first (its own close handler).
            if ( $( '.search-area.active' ).length ) {
                $( '.close-mobile-search' ).first().trigger( 'click' );
            }
            $menu
                .addClass( 'active' )
                .attr( 'aria-hidden', 'false' );
            $toggler.attr( 'aria-expanded', 'true' );
            setBackgroundInert( true );
            // The menu is display:block now, so its close control is focusable at once.
            $menu.find( '.mobile-closer' ).first().trigger( 'focus' );
        }

        // `restoreFocus` is false only when the menu is closed because the viewport left the mobile range
        // (the hamburger is hidden there).
        function closeMenu( restoreFocus ) {
            $menu
                .removeClass( 'active' )
                .attr( 'aria-hidden', 'true' );
            $toggler.attr( 'aria-expanded', 'false' );
            // Closing the menu also closes every open drill-down level, so a reopened menu starts at the top level
            // and each submenu trigger reports aria-expanded="false".
            $( '.mobile-menu__menu ul.sub-menu.active' ).removeClass( 'active' );
            $( '.mobile-toggle-black-js' ).removeClass( 'active' );
            $elementsToToggleVisibility.removeClass( 'hide' );
            syncSubmenuAria();
            setBackgroundInert( false );
            // Vrati fokus na dugme koje je otvorilo meni (inert is released first, so it is focusable)
            if ( restoreFocus !== false ) $toggler.trigger( 'focus' );
        }

        $( '.mobile-toggler' ).on( 'click', openMenu );

        $( '.mobile-closer' ).on( 'click', function () {
            closeMenu();
        } );

        // --- Submenu toggle (do 3 nivoa dubine) ---
        // Opens or closes the drill-down panel of a `li.menu-item-has-children`. `trigger` is the control that was
        // activated; it receives focus again when the panel closes.
        function toggleSubmenu( $parentItem, trigger ) {
            const $subMenu = $parentItem.children( 'ul.sub-menu' );

            if ( ! $subMenu.length ) return;

            if ( $subMenu.hasClass( 'active' ) ) {

                // Zatvori ovaj submenu i sve dublje aktivne submenije unutar njega
                $subMenu.removeClass( 'active' );
                $subMenu.find( 'ul.active' ).removeClass( 'active' );

                // Ako nema više aktivnih submenija — resetuj UI
                if ( $( '.first-menu ul.active' ).length === 0 ) {
                    $( '.mobile-toggle-black-js' ).removeClass( 'active' );
                    $elementsToToggleVisibility.removeClass( 'hide' );
                }
                syncSubmenuAria();
                if ( trigger ) $( trigger ).trigger( 'focus' );

            } else {

                // Zatvori aktivne submenije na istom nivou (siblings) i njihove potomke
                $parentItem.siblings( 'li.menu-item-has-children' )
                    .children( 'ul.active' ).removeClass( 'active' )
                    .find( 'ul.active' ).removeClass( 'active' );

                $subMenu.addClass( 'active' ).data( 'dpTrigger', trigger || null );
                $( '.mobile-toggle-black-js' ).addClass( 'active' );
                $elementsToToggleVisibility.addClass( 'hide' );
                syncSubmenuAria();

                // Focus moves into the newly active panel (first visible link / button, else the Back button).
                const $first = $subMenu.find( 'a[href], button' ).filter( ':visible' ).first();
                ( $first.length ? $first : $( '.mobile-toggle-black-js' ) ).trigger( 'focus' );

            }
        }

        // Rows without a separate toggle button open their submenu from the link.
        // Rows with `button.submenu-toggle` (category rows and "Katalog proizvoda") keep the link as a normal link (E8).
        $( 'li.menu-item-has-children > a' ).on( 'click', function ( e ) {
            const $parentItem = $( this ).parent();

            if ( ! $parentItem.children( 'ul.sub-menu' ).length ) return;
            if ( $parentItem.children( 'button.submenu-toggle' ).length ) return;

            e.preventDefault();
            toggleSubmenu( $parentItem, this );
        } );

        $( '.mobile-menu__menu' ).on( 'click', 'button.submenu-toggle', function () {
            toggleSubmenu( $( this ).parent(), this );
        } );

        // --- Back dugme: zatvori najdublji aktivni submenu ---
        function goBack() {
            // Pronađi najdublji aktivni submenu (aktivan, ali bez aktivnih potomaka)
            const $deepest = getDeepestActive();
            const trigger  = $deepest.length ? $deepest.data( 'dpTrigger' ) : null;

            if ( $deepest.length ) {
                $deepest.removeClass( 'active' );
            }

            // Ako nema više aktivnih submenija — resetuj UI
            if ( $( '.first-menu ul.active' ).length === 0 ) {
                $( '.mobile-toggle-black-js' ).removeClass( 'active' );
                $elementsToToggleVisibility.removeClass( 'hide' );
            }
            // Inert is released by syncSubmenuAria(); focus then returns to the control that opened the panel.
            syncSubmenuAria();
            if ( trigger ) $( trigger ).trigger( 'focus' );
        }

        $( '.mobile-toggle-black-js' ).on( 'click', goBack );

        // --- Keyboard: Escape (Back, then close) and Tab containment. Active only while the menu is open. ---
        $( document ).on( 'keydown', function ( e ) {
            if ( ! $menu.hasClass( 'active' ) || e.isComposing ) return;

            if ( e.key === 'Escape' ) {
                e.preventDefault();
                if ( $( '.first-menu ul.active' ).length ) {
                    goBack();
                } else {
                    closeMenu();
                }
                return;
            }

            if ( e.key === 'Tab' ) {
                // Wrap at both ends so focus never leaves the menu.
                const $items = $menu.find( 'a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])' )
                    .filter( function () {
                        return ! this.disabled && $( this ).closest( '[inert]' ).length === 0 && $( this ).is( ':visible' );
                    } );
                if ( ! $items.length ) return;
                const first  = $items[ 0 ];
                const last   = $items[ $items.length - 1 ];
                const inside = $menu[ 0 ].contains( document.activeElement );
                if ( e.shiftKey && ( document.activeElement === first || ! inside ) ) {
                    e.preventDefault();
                    last.focus();
                } else if ( ! e.shiftKey && ( document.activeElement === last || ! inside ) ) {
                    e.preventDefault();
                    first.focus();
                }
            }
        } );

        // Leaving the mobile range (hamburger hidden) closes the menu and releases every inert element.
        function onBreakpointChange( e ) {
            if ( ! e.matches && $menu.hasClass( 'active' ) ) closeMenu( false );
        }
        if ( mqMobile.addEventListener ) {
            mqMobile.addEventListener( 'change', onBreakpointChange );
        } else if ( mqMobile.addListener ) {
            mqMobile.addListener( onBreakpointChange );
        }

    } );

} )( jQuery );

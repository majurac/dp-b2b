/**
 * Catalog menu ("Katalog proizvoda") — desktop panel behaviour.
 *
 * The panel opens with CSS (hover / focus-within). This script only adds:
 *  - expand/collapse of a category's children (one open at a time, aria-expanded),
 *  - Escape to dismiss the panel, restored on next hover/focus of the menu item,
 *  - reset of expanded rows when the panel closes.
 *
 * The panel is rendered inside the "Katalog proizvoda" menu item by PHP
 * (inc/nav-categories.php), so everything is resolved lazily on first interaction.
 */
( function () {
    'use strict';

    function getMenu() {
        return document.querySelector( '.catalog-menu' );
    }

    function setExpanded( button, expanded ) {
        const sub = document.getElementById( button.getAttribute( 'aria-controls' ) );
        button.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
        if ( sub ) {
            sub.hidden = ! expanded;
        }
        const item = button.closest( '.catalog-menu__item' );
        if ( item ) {
            item.classList.toggle( 'is-expanded', expanded );
        }
    }

    function collapseAll( menu ) {
        menu.querySelectorAll( '.catalog-menu__toggle' ).forEach( function ( button ) {
            setExpanded( button, false );
        } );
    }

    document.addEventListener( 'click', function ( event ) {
        // The "Katalog proizvoda" item is a "#" link that only opens the panel: do not jump to the top.
        const topLink = event.target.closest( '.cat-toggler > a' );
        if ( topLink && topLink.getAttribute( 'href' ) === '#' ) {
            event.preventDefault();
            return;
        }

        const button = event.target.closest( '.catalog-menu__toggle' );
        if ( ! button ) {
            return;
        }

        const menu = getMenu();
        if ( ! menu ) {
            return;
        }

        event.preventDefault();
        const wasExpanded = button.getAttribute( 'aria-expanded' ) === 'true';
        collapseAll( menu );
        setExpanded( button, ! wasExpanded );
    } );

    document.addEventListener( 'keydown', function ( event ) {
        if ( event.key !== 'Escape' ) {
            return;
        }

        const menu = getMenu();
        const holder = menu && menu.closest( '.cat-toggler' );
        if ( ! holder || ! holder.contains( document.activeElement ) ) {
            return;
        }

        collapseAll( menu );
        const link = holder.querySelector( ':scope > a' );
        if ( link ) {
            link.focus();
        }
        // Set after moving focus, so the focusin handler below does not undo the dismissal.
        holder.setAttribute( 'data-dismissed', '' );
    } );

    document.addEventListener( 'mouseover', function ( event ) {
        const holder = event.target.closest && event.target.closest( '.cat-toggler' );
        if ( holder && holder.hasAttribute( 'data-dismissed' ) && event.target.closest( '.cat-toggler > a' ) ) {
            holder.removeAttribute( 'data-dismissed' );
        }
    } );

    document.addEventListener( 'focusin', function ( event ) {
        const holder = event.target.closest && event.target.closest( '.cat-toggler' );
        if ( holder && holder.hasAttribute( 'data-dismissed' ) && ! event.target.closest( '.catalog-menu' ) ) {
            holder.removeAttribute( 'data-dismissed' );
        }
    } );

    // Collapse expanded rows when the pointer leaves the menu item (fresh state on next open).
    document.addEventListener( 'mouseout', function ( event ) {
        const holder = event.target.closest && event.target.closest( '.cat-toggler' );
        const menu = getMenu();
        if ( holder && menu && ! holder.contains( event.relatedTarget ) && ! holder.contains( document.activeElement ) ) {
            collapseAll( menu );
        }
    } );
    // ---------------------------------------------------------------------
    // ARIA disclosure state on the trigger link: aria-expanded mirrors the real open state of the CSS-driven panel
    // (hover, keyboard focus inside, unless dismissed with Escape). No aria-haspopup: this is a disclosure of
    // links, not an ARIA menu.
    // ---------------------------------------------------------------------
    function syncAria() {
        const holder = document.querySelector( '#main-header ul.links li.cat-toggler' );
        const link = holder && holder.querySelector( ':scope > a' );
        if ( ! link || ! holder.querySelector( ':scope > .catalog-menu' ) ) {
            return;
        }
        const open = ! holder.hasAttribute( 'data-dismissed' )
            && ( holder.matches( ':hover' ) || holder.matches( ':focus-within' ) );
        link.setAttribute( 'aria-controls', 'catalog-menu' );
        link.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
    }

    function scheduleSyncAria() {
        window.requestAnimationFrame( syncAria );
    }

    [ 'mouseover', 'mouseout', 'focusin', 'focusout', 'click', 'keydown', 'pointerdown' ].forEach( function ( type ) {
        document.addEventListener( type, scheduleSyncAria, true );
    } );
    // Escape handler above runs in the bubbling phase: sync again afterwards.
    document.addEventListener( 'keydown', function () {
        window.setTimeout( syncAria, 0 );
    } );
    document.addEventListener( 'DOMContentLoaded', syncAria );
    window.addEventListener( 'load', syncAria );
}() );

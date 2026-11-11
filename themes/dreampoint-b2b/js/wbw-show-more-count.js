( function () {
    'use strict';

    /**
     * Archive filter sidebar: shows the number of hidden options on WBW's native "Show More" control
     * ("Prikazi vise (8)") and drops the hardcoded "+" / "-" prefixes of WBW's markup.
     *
     * WBW Pro creates the control itself (`li.wpfShowMoreWrapper` / `li.wpfShowFewerWrapper`, handlers bound to those
     * classes) and marks the options beyond the visible limit with `li.wpfMoreHidden`. WBW has no count, so only the
     * label text is rewritten here, from WBW's own translated strings (`woobewooPfTraslate`); the count is the real
     * number of `.wpfMoreHidden` options. A MutationObserver re-applies it after WBW re-renders or toggles the list.
     */
    // Shop / product archives: `.products-lhs-filter`; Quick Order page (E1): `.dp-qo-filter-area` (WBW filter 3).
    const roots = document.querySelectorAll( '.products-lhs-filter, .dp-qo-filter-area' );
    if ( ! roots.length ) return;

    const strings = window.woobewooPfTraslate || {};
    const setLabel = ( el, text ) => {
        if ( el.dataset.dpLabel === text ) return;
        el.textContent = text;
        el.dataset.dpLabel = text;
    };

    const update = () => {
        roots.forEach( ( root ) => root.querySelectorAll( '.wpfFilterVerScroll' ).forEach( ( list ) => {
            const more = list.querySelector( ':scope > .wpfShowMoreWrapper' );
            if ( more ) {
                const hidden = list.querySelectorAll( ':scope > li.wpfMoreHidden' ).length;
                setLabel( more, ( strings.ShowMore || 'Show More' ).trim() + ' (' + hidden + ')' );
            }
            const fewer = list.querySelector( ':scope > .wpfShowFewerWrapper' );
            if ( fewer ) setLabel( fewer, ( strings.ShowFewer || 'Show Fewer' ).trim() );
        } ) );
    };

    const observer = new MutationObserver( update );
    roots.forEach( ( root ) => observer.observe( root, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: [ 'class' ],
    } ) );
    update();
}() );

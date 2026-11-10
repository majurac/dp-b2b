( function () {
    'use strict';

    /**
     * WBW (woo-product-filter 3.4.x) compatibility fix for the shop / category / brand archive filter.
     *
     * Same root cause as ADR-015 (Quick Order ships the identical lookup in its own plugin):
     * `getFilterMainSettings()` can only parse the main wrapper's `data-filter-settings` through a fallback
     * that turns `filters.order` into an ARRAY, while `getFilterParam()` then does `JSON.parse(order)` on it
     * ("[object Object]" is not valid JSON). The change handler of the category / attribute blocks therefore
     * throws, and WBW's selected-parameter x (which re-triggers `change` through jQuery) aborts, so an active
     * filter chip cannot remove its filter. This replaces only that one lookup on the instance WBW exposes as
     * `window.wpfFrontendPage` with an equivalent that accepts both shapes. WBW source is untouched; the
     * replacement is skipped if WBW's API is missing or if the Quick Order shim already patched it.
     * Remove this file once a WBW update fixes getFilterParam.
     */
    const apply = () => {
        const page = window.wpfFrontendPage;
        if ( ! page || typeof page.getFilterParam !== 'function' ) return false;
        if ( page.dpQoFilterParamPatched || page.dpThemeFilterParamPatched ) return true;

        page.getFilterParam = function ( paramSlug, mainWrapper, filterWrapper ) {
            const main = this.getFilterMainSettings( mainWrapper );
            let order  = main && main.settings && main.settings.filters ? main.settings.filters.order : null;
            if ( typeof order === 'string' ) {
                try { order = JSON.parse( order ); } catch ( e ) { order = null; }
            }
            const block = Array.isArray( order ) || ( order && typeof order === 'object' )
                ? order[ filterWrapper.attr( 'data-order-key' ) ]
                : null;
            return block && block.settings && typeof block.settings[ paramSlug ] !== 'undefined'
                ? block.settings[ paramSlug ]
                : null;
        };
        page.dpThemeFilterParamPatched = true;
        return true;
    };

    if ( apply() ) return;
    // WBW creates its page object on DOM ready; every handler reads the method at event time, so patching any
    // time before the first interaction is sufficient.
    if ( window.jQuery ) window.jQuery( apply );
    window.addEventListener( 'load', apply, { once: true } );
}() );

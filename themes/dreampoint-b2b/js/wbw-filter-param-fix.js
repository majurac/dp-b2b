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
    /**
     * Second symptom of the same invalid `data-filter-settings` JSON (C1-1, 2026-11-11): WBW's sort handler
     * `changeOrderBy()` (frontend.woofilters.js ~173-175) reads `$wrapper.data( 'filter-settings' )`. jQuery cannot
     * parse the attribute (`"order":"[{"id"...` has unescaped quotes), so `.data()` returns the raw STRING,
     * `filter.settings` is undefined and the handler throws "Cannot read properties of undefined (reading 'filters')"
     * before it assigns `location.href`. That handler `return false`s, so WooCommerce's own form submit never runs
     * either: on an archive URL with no query string, picking a sort option does nothing. (With a `?` in the URL WBW
     * takes an else branch that never touches the settings, which is why sorting worked there.)
     * Fix: seed jQuery's data cache with the object WBW's own tolerant parser (`getFilterMainSettings`, the one
     * every other WBW code path uses) returns, so `.data()` yields the same shape it would for valid JSON. An empty
     * `filters.defaults` is dropped from that copy: WBW appends `'&' + defaults` whenever the key exists, which would
     * leave a dangling "&" in the sorted URL. The attribute and WBW's handler stay untouched.
     */
    const seedMainSettings = ( page ) => {
        const $ = window.jQuery;
        if ( ! $ || typeof page.getFilterMainSettings !== 'function' ) return;
        $( '.wpfMainWrapper' ).each( function () {
            const $wrapper = $( this );
            if ( typeof $wrapper.data( 'filter-settings' ) !== 'string' ) return; // valid JSON: nothing to do
            const settings = page.getFilterMainSettings( $wrapper );
            if ( ! settings || ! settings.settings ) return;
            if ( settings.settings.filters && ! settings.settings.filters.defaults ) delete settings.settings.filters.defaults;
            $wrapper.data( 'filter-settings', settings );
        } );
    };

    const apply = () => {
        const page = window.wpfFrontendPage;
        if ( ! page || typeof page.getFilterParam !== 'function' ) return false;
        seedMainSettings( page ); // idempotent; also covers a wrapper that WBW re-rendered
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

    // WBW's AJAX refresh can swap in a fresh wrapper (no seeded data); re-seed after any AJAX call. Cheap and idempotent.
    if ( window.jQuery ) {
        window.jQuery( document ).on( 'ajaxComplete', () => {
            if ( window.wpfFrontendPage ) seedMainSettings( window.wpfFrontendPage );
        } );
    }

    if ( apply() ) return;
    // WBW creates its page object on DOM ready; every handler reads the method at event time, so patching any
    // time before the first interaction is sufficient.
    if ( window.jQuery ) window.jQuery( apply );
    window.addEventListener( 'load', apply, { once: true } );
}() );

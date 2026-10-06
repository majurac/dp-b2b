'use strict';

/**
 * WBW (woo-product-filter 3.4.5) integration fix — no filtering behavior of its own.
 *
 * `getFilterMainSettings()` can only parse the main wrapper's `data-filter-settings`
 * through a fallback that turns `filters.order` into an ARRAY, while `getFilterParam()`
 * then does `JSON.parse(order)` on it ("[object Object]" is not valid JSON). The change
 * handler of every checkbox-list attribute block (wpfAttribute, `list`) therefore throws.
 * Selecting a term still filters, but WBW's own selected-parameter × (which re-triggers
 * `change` through jQuery) aborts on the exception, so the chip could not clear an
 * attribute filter. This replaces the one broken lookup on the instance WBW exposes as
 * `window.wpfFrontendPage` with an equivalent that accepts both shapes. WBW source is
 * untouched; the replacement is skipped if WBW's API is not present.
 */
export function patchWbwFilterParam() {
    const apply = () => {
        const page = window.wpfFrontendPage;
        if (!page || typeof page.getFilterParam !== 'function') return false;
        if (page.dpQoFilterParamPatched) return true;

        page.getFilterParam = function (paramSlug, mainWrapper, filterWrapper) {
            const main = this.getFilterMainSettings(mainWrapper);
            let order  = main && main.settings && main.settings.filters ? main.settings.filters.order : null;
            if (typeof order === 'string') {
                try { order = JSON.parse(order); } catch { order = null; }
            }
            const block = Array.isArray(order) ? order[filterWrapper.attr('data-order-key')] : null;
            return block && block.settings && typeof block.settings[paramSlug] !== 'undefined'
                ? block.settings[paramSlug]
                : null;
        };
        page.dpQoFilterParamPatched = true;
        return true;
    };

    if (apply()) return;
    // WBW creates its page object on DOM ready; every handler reads the method at event
    // time, so patching any time before the first interaction is sufficient.
    if (window.jQuery) window.jQuery(apply);
    window.addEventListener('load', apply, { once: true });
}

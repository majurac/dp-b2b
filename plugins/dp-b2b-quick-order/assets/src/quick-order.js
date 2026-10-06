'use strict';

import { QuickOrderState }          from './quick-order-state.js';
import { FooterController }         from './footer-controller.js';
import { RowController }            from './row-controller.js';
import { ProductList }              from './product-list.js';
import { CartSubmit }               from './cart-submit.js';
import { VariationChipsController } from './variation-chips.js';
import { patchWbwFilterParam }        from './wbw-compat.js';

/**
 * Global submit summary. Detail of a confirmed failure lives on its row; an ambiguous request
 * failure (outcome unknown — see cart-submit.js) is stated once here and is never shown as a
 * row-level "not added".
 */
function submitStatusText(i18n, added, failed, ambiguous) {
    const parts = [];
    if (failed > 0) {
        parts.push(((added > 0 ? i18n.submitPartial : i18n.submitNoneAdded) ?? '')
            .replace('{added}', added).replace('{failed}', failed));
    } else if (added > 0 && ambiguous === 0) {
        parts.push((i18n.submitAdded ?? '').replace('{added}', added));
    }
    if (ambiguous > 0) parts.push(i18n.requestFailed ?? '');
    return parts.filter(Boolean).join(' ');
}

(function () {
    const config = window.dpQuickOrder;
    if (!config || !config.cartSyncUrl || !config.wpNonce || !config.productsUrl) return;

    patchWbwFilterParam();

    const state  = new QuickOrderState();
    const footer = new FooterController(state);
    const chips  = new VariationChipsController(state);

    // Forward reference: chips' remove-click callback needs rowCtrl.hydrateAll(),
    // but RowController's constructor needs chips — assigned right after
    // construction, before any user interaction can fire the callback.
    let rowCtrl;
    chips.onRemove(rowKey => {
        state.setQuantity(rowKey, 0);
        rowCtrl.hydrateAll();
        footer.render();
        chips.render();
    });

    rowCtrl = new RowController(state, footer, chips);
    const productList = new ProductList(config);
    const submit       = new CartSubmit(state, config);

    // Re-hydrate qty inputs from local state whenever rows (re)render —
    // pagination, sort, or a variation set arriving asynchronously.
    document.addEventListener('dp:qo:rows-rendered', () => rowCtrl.hydrateAll());

    const boot = () => productList.loadPage(1);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }

    const addBtn = document.querySelector('.dp-qo-footer__add-to-cart');

    // The last submit's summary goes stale as soon as the user edits a quantity — except the
    // "outcome unknown, check the cart" warning, which must stay until the next submit.
    let statusSticky = false;
    document.addEventListener('input', e => {
        if (!statusSticky && e.target.matches?.('.dp-qo-qty')) footer.setStatus('');
    });

    addBtn?.addEventListener('click', async () => {
        if (state.isEmpty()) return;

        addBtn.disabled = true;
        const originalLabel = addBtn.textContent;
        addBtn.textContent = config.i18n?.adding ?? '...';
        footer.setStatus('');
        statusSticky = false;

        const { addedKeys, failed, ambiguousKeys } = await submit.submit();

        // Only rows the server confirmed (added/updated/removed) are cleared (clearKeys also drops
        // their errors). Rows the server confirmed as failed stay in local state with their error so
        // the user can see and correct them; rows with an unknown outcome keep their quantity but
        // get no verdict (a previous error would be stale).
        state.clearKeys(addedKeys);
        for (const { key, error } of failed) state.setError(key, error);
        for (const key of ambiguousKeys) state.clearError(key);
        footer.render();
        rowCtrl.hydrateAll();
        chips.render();

        addBtn.textContent = originalLabel;
        addBtn.disabled = state.isEmpty();

        statusSticky = ambiguousKeys.length > 0;
        footer.setStatus(submitStatusText(config.i18n ?? {}, addedKeys.length, failed.length, ambiguousKeys.length));
    });

    // Reuse the existing WC ecosystem bridge (Toastify, mini-cart HTML, .cart-contents
    // .count), fired once per submit instead of per keystroke.
    document.addEventListener('dp:submit:complete', e => {
        if (typeof jQuery === 'undefined') return;
        if (!e.detail.addedKeys.length) return;
        jQuery(document.body).one('wc_fragments_refreshed wc_fragments_ajax_error', function () {
            jQuery(document.body).trigger('added_to_cart', [[], '', null]);
        });
        jQuery(document.body).trigger('wc_fragment_refresh');
    });

    // Expose internal instances for browser-console inspection (dev/staging only).
    config.state       = state;
    // Read-only accessor for the Excel import modal (separate bundle): lets it warn when an imported unit
    // also has an unsent manual quantity here. It never exposes or mutates the state itself.
    config.hasSelection = (rowKey) => state.getQuantity(rowKey) > 0;
    config.productList = productList;
})();

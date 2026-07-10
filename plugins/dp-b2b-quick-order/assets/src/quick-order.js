'use strict';

import { QuickOrderState }  from './quick-order-state.js';
import { FooterController } from './footer-controller.js';
import { RowController }    from './row-controller.js';
import { ProductList }      from './product-list.js';
import { CartSubmit }       from './cart-submit.js';

(function () {
    const config = window.dpQuickOrder;
    if (!config || !config.cartSyncUrl || !config.wpNonce || !config.productsUrl) return;

    const state       = new QuickOrderState();
    const footer      = new FooterController(state);
    const rowCtrl     = new RowController(state, footer);
    const productList = new ProductList(config);
    const submit      = new CartSubmit(state, config);

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

    addBtn?.addEventListener('click', async () => {
        if (state.isEmpty()) return;

        addBtn.disabled = true;
        const originalLabel = addBtn.textContent;
        addBtn.textContent = config.i18n?.adding ?? '...';

        const { addedKeys, failedItems } = await submit.submit();

        // Only rows the server actually processed (added/updated/removed) are
        // cleared. Rows that came back out_of_stock/failed stay in local
        // state so the user can see and correct them.
        state.clearKeys(addedKeys);
        footer.render();
        rowCtrl.hydrateAll();

        addBtn.textContent = originalLabel;
        addBtn.disabled = state.isEmpty();

        if (failedItems.length) {
            window.alert(config.i18n?.partialFailure ?? 'Neki artikli nisu dodani u košaricu.');
        }
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
    config.productList = productList;
})();

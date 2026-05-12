'use strict';

import { SyncQueue }   from './sync-queue.js';
import { CartSync }    from './cart-sync.js';
import { ProductList } from './product-list.js';
import { RowSync }     from './row-sync.js';

(function () {
    const config = window.dpQuickOrder;
    if (!config || !config.cartSyncUrl || !config.wpNonce || !config.productsUrl) return;

    const queue = new SyncQueue();
    const sync  = new CartSync(queue, {
        cartSyncUrl: config.cartSyncUrl,
        wpNonce:     config.wpNonce,
        debounceMs:  config.debounceMs ?? 300,
        timeoutMs:   config.timeoutMs  ?? 10000,
    });

    // RowSync binds event delegation on tbody — must init before ProductList renders rows.
    const rowSync     = new RowSync(sync);
    const productList = new ProductList(config);

    const boot = () => productList.loadPage(1);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }

    // Ecosystem bridge — connect sync results to the existing theme-wide cart lifecycle.
    // Triggers the same jQuery events that normal WC add-to-cart uses, so Toastify,
    // mini-cart HTML, and .cart-contents .count all update via the existing flow.
    document.addEventListener('dp:sync:success', e => {
        if (typeof jQuery === 'undefined') return;
        const synced     = e.detail.synced ?? [];
        const hasAdded   = synced.some(i => i.action === 'added' || i.action === 'updated');
        const hasRemoved = synced.some(i => i.action === 'removed');
        const hasOos     = synced.some(i => i.action === 'out_of_stock');

        if (hasAdded) {
            // Success: toast + cart open + fragment refresh (existing added_to_cart flow).
            jQuery(document.body).trigger('added_to_cart', [[], '', null]);
        } else if (hasRemoved && !hasOos) {
            // Pure remove: close cart + fragment refresh (existing removed_from_cart flow).
            jQuery(document.body).trigger('removed_from_cart', [[], '']);
        } else if (hasRemoved || hasOos) {
            // Mixed or oos-only: refresh fragments without toast or cart open/close.
            jQuery(document.body).trigger('wc_fragment_refresh');
        }
        // skipped/failed only → no cart change, no event.
    });

    // Footer total: update from server-authoritative sync response.
    // Never calculate client-side — WooCommerce is authoritative.
    document.addEventListener('dp:sync:success', e => {
        const { totals } = e.detail;
        if (!totals) return;
        const totalEl = document.querySelector('.dp-qo-footer__total');
        if (!totalEl) return;
        try {
            totalEl.textContent = new Intl.NumberFormat(navigator.language, {
                style:    'currency',
                currency: totals.currency,
            }).format(totals.total);
        } catch {
            totalEl.textContent = `${totals.total} ${totals.currency}`;
        }
    });

    // Expose internal instances for browser-console stress testing (dev/staging only).
    config.sync        = sync;
    config.queue       = queue;
    config.productList = productList;
    config.rowSync     = rowSync;
})();

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
    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', boot)
        : boot();

    // Expose for browser-console stress testing.
    config.sync        = sync;
    config.queue       = queue;
    config.productList = productList;
    config.rowSync     = rowSync;
})();

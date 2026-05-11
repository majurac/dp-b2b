'use strict';

import { SyncQueue } from './sync-queue.js';
import { CartSync }  from './cart-sync.js';

(function () {
    const config = window.dpQuickOrder;
    if (!config || !config.cartSyncUrl || !config.wpNonce) return;

    const queue = new SyncQueue();
    const sync  = new CartSync(queue, {
        cartSyncUrl: config.cartSyncUrl,
        wpNonce:     config.wpNonce,
        debounceMs:  config.debounceMs ?? 300,
        timeoutMs:   config.timeoutMs  ?? 10000,
    });

    // Expose on config for future UI modules and browser testing.
    config.sync  = sync;
    config.queue = queue;
})();

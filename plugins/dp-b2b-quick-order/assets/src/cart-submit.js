'use strict';

/**
 * Bulk "Dodaj u košaricu" submit.
 *
 * Reuses the existing /cart/sync REST endpoint (server-side stock/purchasability
 * validation, add_to_cart()) — chunked to respect CART_SYNC_MAX_BATCH, sequential
 * (not parallel) so cart mutation order stays deterministic.
 * See docs/frozen/quick-order-local-state-architecture.md §4.
 */
export class CartSubmit {
    /** @type {import('./quick-order-state.js').QuickOrderState} */
    #state;
    #config;
    #chunkSize;

    /**
     * @param {import('./quick-order-state.js').QuickOrderState} state
     * @param {object} config  window.dpQuickOrder
     */
    constructor(state, config) {
        this.#state     = state;
        this.#config    = config;
        this.#chunkSize = config.cartSyncMaxBatch ?? 50;
    }

    /**
     * @returns {Promise<{addedKeys: string[], failedItems: object[]}>}
     */
    async submit() {
        const items = this.#state.toItems();
        if (!items.length) return { addedKeys: [], failedItems: [] };

        const chunks = [];
        for (let i = 0; i < items.length; i += this.#chunkSize) {
            chunks.push(items.slice(i, i + this.#chunkSize));
        }

        const addedKeys   = [];
        const failedItems = [];
        let lastTotals    = null;

        for (const chunk of chunks) {
            let data;
            try {
                const res = await fetch(this.#config.cartSyncUrl, {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce':   this.#config.wpNonce,
                    },
                    body: JSON.stringify({ items: chunk }),
                });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                data = await res.json();
            } catch {
                // Whole chunk failed at the network/HTTP level — every item in it is unresolved.
                failedItems.push(...chunk.map(i => ({
                    product_id: i.product_id, variation_id: i.variation_id,
                    action: 'failed', error: 'network_error',
                })));
                continue;
            }

            for (const item of data.synced ?? []) {
                const key = `${item.product_id}_${item.variation_id}`;
                if (['added', 'updated', 'removed'].includes(item.action)) {
                    addedKeys.push(key);
                } else {
                    failedItems.push(item);
                }
            }
            if (data.totals) lastTotals = data.totals;
        }

        document.dispatchEvent(new CustomEvent('dp:submit:complete', {
            detail: { addedKeys, failedItems, totals: lastTotals },
        }));

        return { addedKeys, failedItems };
    }
}

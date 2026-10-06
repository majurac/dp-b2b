'use strict';

/**
 * Error codes the server may return for a confirmed row failure (class-cart-sync.php).
 * Anything else is mapped to 'not_addable' so a row never shows an unknown/raw value.
 */
const ROW_ERRORS = ['out_of_stock', 'quantity_unavailable', 'product_unavailable', 'not_addable'];

/**
 * Bulk "Dodaj u košaricu" submit.
 *
 * Reuses the existing /cart/sync REST endpoint (server-side stock/purchasability
 * validation, add_to_cart()) — chunked to respect CART_SYNC_MAX_BATCH, sequential
 * (not parallel) so cart mutation order stays deterministic.
 * See docs/frozen/quick-order-local-state-architecture.md §4.
 *
 * Outcome per row is one of three DISTINCT things:
 *  - added     server confirmed it reached the cart            -> addedKeys
 *  - failed    server confirmed it did NOT reach the cart      -> failed [{key, error}]
 *  - ambiguous no usable response (network/HTTP/timeout/malformed) -> ambiguousKeys.
 *    The sync is additive and the server may have processed the chunk even though the
 *    response was lost, so such rows are NOT claimed to be "not added".
 */
export class CartSubmit {
    /** @type {import('./quick-order-state.js').QuickOrderState} */
    #state;
    #config;
    #chunkSize;
    #timeoutMs;

    /**
     * @param {import('./quick-order-state.js').QuickOrderState} state
     * @param {object} config  window.dpQuickOrder
     */
    constructor(state, config) {
        this.#state     = state;
        this.#config    = config;
        this.#chunkSize = config.cartSyncMaxBatch ?? 50;
        this.#timeoutMs = Number(config.timeoutMs) > 0 ? Number(config.timeoutMs) : 0;
    }

    /**
     * @returns {Promise<{addedKeys: string[], failed: {key:string, error:string}[], ambiguousKeys: string[]}>}
     */
    async submit() {
        const items = this.#state.toItems();
        if (!items.length) return { addedKeys: [], failed: [], ambiguousKeys: [] };

        const chunks = [];
        for (let i = 0; i < items.length; i += this.#chunkSize) {
            chunks.push(items.slice(i, i + this.#chunkSize));
        }

        const addedKeys     = [];
        const failed        = [];
        const ambiguousKeys = [];
        let lastTotals      = null;

        for (const chunk of chunks) {
            const chunkKeys = chunk.map(i => `${i.product_id}_${i.variation_id}`);
            const data      = await this.#post(chunk);

            if (!data || !Array.isArray(data.synced)) {
                // No usable response — the outcome for this whole chunk is unknown.
                ambiguousKeys.push(...chunkKeys);
                continue;
            }

            const resolved = new Set();
            for (const item of data.synced) {
                const key = `${item.product_id}_${item.variation_id}`;
                resolved.add(key);
                if (['added', 'updated', 'removed'].includes(item.action)) {
                    addedKeys.push(key);
                } else if (item.action === 'failed' || item.action === 'out_of_stock') {
                    failed.push({ key, error: ROW_ERRORS.includes(item.error) ? item.error : 'not_addable' });
                } else if (item.action !== 'skipped') {
                    failed.push({ key, error: 'not_addable' });
                }
                // 'skipped' (quantity 0, no cart line) cannot be produced by the UI — a no-op.
            }
            // A row with no result is not a confirmed failure either.
            for (const key of chunkKeys) {
                if (!resolved.has(key)) ambiguousKeys.push(key);
            }
            if (data.totals) lastTotals = data.totals;
        }

        document.dispatchEvent(new CustomEvent('dp:submit:complete', {
            detail: { addedKeys, failed, ambiguousKeys, totals: lastTotals },
        }));

        return { addedKeys, failed, ambiguousKeys };
    }

    /**
     * POST one chunk. Resolves to the parsed body, or null on ANY transport/HTTP/parse
     * failure or when the configured timeout elapses (ambiguous — never retried here).
     * @param {object[]} chunk
     */
    async #post(chunk) {
        const controller = new AbortController();
        const timer = this.#timeoutMs ? setTimeout(() => controller.abort(), this.#timeoutMs) : null;
        try {
            const res = await fetch(this.#config.cartSyncUrl, {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce':   this.#config.wpNonce,
                },
                body:   JSON.stringify({ items: chunk }),
                signal: controller.signal,
            });
            if (!res.ok) return null;
            return await res.json();
        } catch {
            return null;
        } finally {
            if (timer) clearTimeout(timer);
        }
    }
}

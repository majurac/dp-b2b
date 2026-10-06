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
 *
 * Optional (used by the Excel import modal; the main Quick Order submit passes nothing and
 * behaves exactly as before):
 *  - onProgress({completed, total}) is called with REAL chunk completion only.
 *  - stopOnAmbiguous: after a chunk with an ambiguous outcome no further chunk is sent (the
 *    additive sync must not be continued blindly past an unknown boundary); the rows of the
 *    chunks that were never sent are returned in unsentKeys.
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
        // wp_localize_script() delivers top-level scalars as STRINGS ("50"): `i += "50"` would concatenate and
        // silently produce oversized chunks (the server rejects > CART_SYNC_MAX_BATCH), so coerce explicitly.
        this.#chunkSize = Number(config.cartSyncMaxBatch) > 0 ? Math.floor(Number(config.cartSyncMaxBatch)) : 50;
        this.#timeoutMs = Number(config.timeoutMs) > 0 ? Number(config.timeoutMs) : 0;
    }

    /**
     * @param {{onProgress?: (p:{completed:number,total:number}) => void, stopOnAmbiguous?: boolean}} [options]
     * @returns {Promise<{addedKeys: string[], failed: {key:string, error:string}[], ambiguousKeys: string[], unsentKeys: string[]}>}
     */
    async submit(options = {}) {
        const { onProgress, stopOnAmbiguous = false } = options;
        const items = this.#state.toItems();
        if (!items.length) return { addedKeys: [], failed: [], ambiguousKeys: [], unsentKeys: [] };

        const chunks = [];
        for (let i = 0; i < items.length; i += this.#chunkSize) {
            chunks.push(items.slice(i, i + this.#chunkSize));
        }

        const addedKeys     = [];
        const failed        = [];
        const ambiguousKeys = [];
        const unsentKeys    = [];
        let lastTotals      = null;

        onProgress?.({ completed: 0, total: chunks.length });

        for (let c = 0; c < chunks.length; c++) {
            const chunk     = chunks[c];
            const chunkKeys = chunk.map(i => `${i.product_id}_${i.variation_id}`);
            const data      = await this.#post(chunk);
            const before    = ambiguousKeys.length;

            if (!data || !Array.isArray(data.synced)) {
                // No usable response — the outcome for this whole chunk is unknown.
                ambiguousKeys.push(...chunkKeys);
            } else {
                this.#collect(data, chunkKeys, addedKeys, failed, ambiguousKeys);
                if (data.totals) lastTotals = data.totals;
            }

            onProgress?.({ completed: c + 1, total: chunks.length });

            if (stopOnAmbiguous && ambiguousKeys.length > before) {
                for (const rest of chunks.slice(c + 1)) unsentKeys.push(...rest.map(i => `${i.product_id}_${i.variation_id}`));
                break;
            }
        }

        document.dispatchEvent(new CustomEvent('dp:submit:complete', {
            detail: { addedKeys, failed, ambiguousKeys, totals: lastTotals },
        }));

        return { addedKeys, failed, ambiguousKeys, unsentKeys };
    }

    /** Fold one usable /cart/sync response into the three outcome lists. */
    #collect(data, chunkKeys, addedKeys, failed, ambiguousKeys) {
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

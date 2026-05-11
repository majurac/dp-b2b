'use strict';

/**
 * Cart synchronization engine — Task 1: debounce + batch dispatch only.
 *
 * Debounces quantity changes into batched sync requests.
 * Multiple schedule() calls within debounceMs collapse into one request.
 *
 * @typedef {{ cartSyncUrl: string, wpNonce: string, debounceMs: number, timeoutMs: number }} SyncConfig
 */
export class CartSync {
    /** @type {import('./sync-queue.js').SyncQueue} */
    #queue;
    /** @type {SyncConfig} */
    #config;
    #timer = null;
    #inflight = false;

    /**
     * @param {import('./sync-queue.js').SyncQueue} queue
     * @param {SyncConfig} config
     */
    constructor(queue, config) {
        this.#queue  = queue;
        this.#config = config;
        // timeoutMs is applied via AbortSignal.timeout() in Task 2 (AbortController phase).
    }

    /**
     * Schedule a cart sync after the debounce window.
     * Safe to call on every keypress or click.
     *
     * @param {string} rowKey   Row identity: "${productId}_${variationId}"
     * @param {number} quantity Desired quantity (0 = remove)
     */
    schedule(rowKey, quantity) {
        this.#queue.enqueue(rowKey, quantity);
        clearTimeout(this.#timer);
        this.#timer = setTimeout(() => this.#dispatch(), this.#config.debounceMs);
    }

    async #dispatch() {
        if (this.#inflight) return;
        this.#inflight = true;

        const items = this.#queue.flush();
        if (!items) {
            this.#inflight = false;
            return;
        }

        try {
            const response = await fetch(this.#config.cartSyncUrl, {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce':   this.#config.wpNonce,
                },
                body: JSON.stringify({ items }),
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            // Task 2 adds token validation here.
            // Task 3 adds optimistic state confirmation here.
        } catch (err) {
            // Task 3 adds rollback here.
            console.error('[CartSync] Sync failed:', err.message);
        } finally {
            this.#inflight = false;
            // Items queued during this request get dispatched after completion.
            if (!this.#queue.isEmpty()) {
                this.#timer = setTimeout(() => this.#dispatch(), this.#config.debounceMs);
            }
        }
    }
}

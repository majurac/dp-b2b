'use strict';

/**
 * Cart synchronization engine.
 *
 * Responsibilities:
 * - Debounce quantity changes into batched sync requests
 * - Cancel previous in-flight request when new batch dispatches (AbortController)
 * - Protect against stale out-of-order responses via monotonic token
 *
 * Usage:
 *   sync.schedule(rowKey, quantity);  // call on every quantity change
 *
 * @typedef {{ cartSyncUrl: string, wpNonce: string, debounceMs: number, timeoutMs: number }} SyncConfig
 */
export class CartSync {
    /** @type {import('./sync-queue.js').SyncQueue} */
    #queue;
    /** @type {SyncConfig} */
    #config;
    #timer      = null;
    #controller = null;
    #token      = 0;

    /**
     * @param {import('./sync-queue.js').SyncQueue} queue
     * @param {SyncConfig} config
     */
    constructor(queue, config) {
        this.#queue  = queue;
        this.#config = config;
    }

    /**
     * Schedule a cart sync after the debounce window.
     * Safe to call on every keypress or click — collapses into one request per window.
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
        const items = this.#queue.flush();
        if (!items) return;

        // Abort previous in-flight request.
        this.#controller?.abort();
        this.#controller = new AbortController();

        const token = ++this.#token;

        try {
            const response = await fetch(this.#config.cartSyncUrl, {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce':   this.#config.wpNonce,
                },
                body:   JSON.stringify({ items, token }),
                signal: this.#controller.signal,
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            // Stale response protection — discard if a newer request has dispatched.
            if (data.token !== token) return;

            // Task 3 adds optimistic state confirmation here.
        } catch (err) {
            // AbortError is expected when a newer request cancels this one.
            if (err.name === 'AbortError') return;

            // Task 3 adds rollback here.
        } finally {
            // Items queued during this request get dispatched after completion.
            if (!this.#queue.isEmpty()) {
                this.#timer = setTimeout(() => this.#dispatch(), this.#config.debounceMs);
            }
        }
    }
}

'use strict';

/**
 * Cart synchronization engine.
 *
 * Responsibilities:
 * - Debounce quantity changes into batched sync requests
 * - Cancel previous in-flight request when new batch dispatches (AbortController)
 * - Protect against stale out-of-order responses via monotonic token
 * - Maintain in-memory optimistic state for immediate UI feedback (request-scoped only)
 *
 * WooCommerce cart/session is the sole source of truth.
 * Optimistic state is temporary, in-memory, and never persisted.
 *
 * Usage:
 *   sync.schedule(rowKey, quantity);       // call on every quantity change
 *   sync.getOptimisticQuantity(rowKey);    // read for immediate UI rendering
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
    /** @type {Map<string, number>} In-memory optimistic quantity per rowKey. Never persisted. */
    #optimisticState = new Map();

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

    /**
     * Current optimistic quantity for a row. Zero if not present.
     * Use for immediate visual feedback only — WC cart is authoritative.
     *
     * @param {string} rowKey
     * @returns {number}
     */
    getOptimisticQuantity(rowKey) {
        return this.#optimisticState.get(rowKey) ?? 0;
    }

    async #dispatch() {
        const items = this.#queue.flush();
        if (!items) return;

        // Snapshot current optimistic state before applying changes.
        // Isolated copy — no shared references with pending queue or response payloads.
        const snapshot = new Map(this.#optimisticState);

        // Apply optimistic changes immediately for UI feedback.
        for (const item of items) {
            const key = `${item.product_id}_${item.variation_id}`;
            if (item.quantity === 0) {
                this.#optimisticState.delete(key);
            } else {
                this.#optimisticState.set(key, item.quantity);
            }
        }

        // Abort previous in-flight request before dispatching new one.
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

            // Stale response — a newer request has already dispatched. Discard silently.
            if (data.token !== token) return;

            this.#onSuccess(data);
        } catch (err) {
            // AbortError is expected control flow — newer request cancelled this one.
            // Never rollback for cancellations.
            if (err.name === 'AbortError') return;

            // Real server error or validation failure — restore pre-dispatch snapshot.
            this.#onError(snapshot);
        } finally {
            // Items queued during this request get dispatched after completion.
            if (!this.#queue.isEmpty()) {
                this.#timer = setTimeout(() => this.#dispatch(), this.#config.debounceMs);
            }
        }
    }

    /**
     * Confirm optimistic state from server response.
     * Adjusts row quantities to match what WC actually applied (e.g. stock clamping).
     *
     * @param {{ synced: Array<{product_id: number, variation_id: number, action: string, quantity?: number}>, totals: object }} data
     */
    #onSuccess(data) {
        if (!Array.isArray(data.synced)) return;

        for (const item of data.synced) {
            const key = `${item.product_id}_${item.variation_id}`;
            if (item.action === 'removed' || item.action === 'skipped') {
                this.#optimisticState.delete(key);
            } else if (item.quantity != null) {
                this.#optimisticState.set(key, item.quantity);
            }
        }
        // Future: dispatch custom event with data.totals for cart summary UI update.
    }

    /**
     * Restore pre-dispatch snapshot on real sync failure.
     * Only called for server errors — never for AbortError (cancelled requests).
     *
     * @param {Map<string, number>} snapshot
     */
    #onError(snapshot) {
        this.#optimisticState = snapshot;
        // Future: dispatch custom event to signal UI rollback needed.
    }
}

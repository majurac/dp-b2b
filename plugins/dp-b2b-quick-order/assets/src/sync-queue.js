'use strict';

/**
 * Pending cart update queue.
 *
 * Stores the latest desired quantity per row key.
 * Multiple updates to the same row during a debounce window collapse into one
 * (last-write-wins) — no stale intermediate quantities reach the server.
 *
 * Row key format: "${productId}_${variationId}" (variationId=0 for simple products)
 */
export class SyncQueue {
    #pending = new Map();

    /**
     * Enqueue a quantity update. Overwrites any pending update for the same row key.
     * @param {string} rowKey
     * @param {number} quantity  0 = remove from cart
     */
    enqueue(rowKey, quantity) {
        if (typeof rowKey !== 'string' || !rowKey.includes('_')) {
            console.error('[SyncQueue] Invalid rowKey:', rowKey);
            return;
        }
        this.#pending.set(rowKey, quantity);
    }

    /**
     * Flush all pending updates as an array and clear the queue.
     * Returns null if queue is empty (caller should not send a request).
     * @returns {{ product_id: number, variation_id: number, quantity: number }[] | null}
     */
    flush() {
        if (this.#pending.size === 0) return null;

        const items = [];
        for (const [rowKey, quantity] of this.#pending) {
            const [productId, variationId] = rowKey.split('_').map(Number);
            items.push({ product_id: productId, variation_id: variationId, quantity });
        }

        this.#pending.clear();
        return items;
    }

    /** @returns {boolean} */
    isEmpty() {
        return this.#pending.size === 0;
    }

    /** @returns {number} */
    size() {
        return this.#pending.size;
    }
}

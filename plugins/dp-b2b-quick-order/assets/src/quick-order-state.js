'use strict';

/**
 * Local, ephemeral Quick Order state.
 * Never touches the WooCommerce cart, never persisted (no localStorage/sessionStorage).
 * Row key format: "${productId}_${variationId}" (variationId=0 for simple products) —
 * same format used throughout Quick Order and the reused /cart/sync endpoint.
 */
export class QuickOrderState {
    /** @type {Map<string, {productId:number, variationId:number, quantity:number, unitPrice:number}>} */
    #rows = new Map();

    /**
     * Set or clear a row's quantity. quantity<=0 removes the row.
     * @param {string} rowKey
     * @param {number} quantity
     * @param {{productId:number, variationId:number, unitPrice:number}} meta
     */
    setQuantity(rowKey, quantity, meta) {
        if (quantity <= 0) {
            this.#rows.delete(rowKey);
            return;
        }
        this.#rows.set(rowKey, { productId: meta.productId, variationId: meta.variationId, unitPrice: meta.unitPrice, quantity });
    }

    /** @returns {number} */
    getQuantity(rowKey) {
        return this.#rows.get(rowKey)?.quantity ?? 0;
    }

    /** @returns {number} sum of all quantities — footer "N artikala" */
    getItemCount() {
        let total = 0;
        for (const row of this.#rows.values()) total += row.quantity;
        return total;
    }

    /** @returns {number} count of distinct rows with quantity > 0 — footer "N varijacija" */
    getRowCount() {
        return this.#rows.size;
    }

    /**
     * @returns {string[]} row keys with quantity > 0, in insertion order
     * (JS `Map` iterates in insertion order by spec — this is deterministic,
     * never sorted). Consumed by VariationChipsController so chips never
     * reorder themselves as the user edits quantities.
     */
    getActiveRowKeys() {
        return [...this.#rows.keys()];
    }

    /** @returns {number} sum(quantity * unitPrice) — product-only, no VAT/shipping/coupons */
    getSubtotal() {
        let total = 0;
        for (const row of this.#rows.values()) total += row.quantity * row.unitPrice;
        return total;
    }

    /** @returns {{product_id:number, variation_id:number, quantity:number}[]} */
    toItems() {
        return [...this.#rows.values()].map(r => ({
            product_id: r.productId,
            variation_id: r.variationId,
            quantity: r.quantity,
        }));
    }

    /** Remove specific row keys — used after a submit chunk succeeds. */
    clearKeys(rowKeys) {
        for (const key of rowKeys) this.#rows.delete(key);
    }

    /** Discard everything — used after a fully successful submit. */
    clear() {
        this.#rows.clear();
    }

    /** @returns {boolean} */
    isEmpty() {
        return this.#rows.size === 0;
    }
}

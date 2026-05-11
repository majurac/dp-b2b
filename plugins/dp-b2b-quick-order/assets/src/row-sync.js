'use strict';

/**
 * Row interaction controller.
 *
 * Wires quantity inputs and variation selects to CartSync via event delegation.
 * Row state feedback (Batch 4) and cart footer updates (Batch 4) are added separately.
 */
export class RowSync {
    /** @type {import('./cart-sync.js').CartSync} */
    #sync;
    /** @type {HTMLElement|null} */
    #tbody;

    /**
     * @param {import('./cart-sync.js').CartSync} sync
     */
    constructor(sync) {
        this.#sync  = sync;
        this.#tbody = document.querySelector('.dp-qo-tbody');
        if (!this.#tbody) return;
        this.#bindTableEvents();
    }

    #bindTableEvents() {
        // 'input' covers typing, paste, cut, and spinner clicks for qty inputs.
        this.#tbody.addEventListener('input', e => {
            if (e.target.matches('.dp-qo-qty')) this.#onQtyInput(e.target);
        });
        // 'change' fires on select commit — variation selection.
        this.#tbody.addEventListener('change', e => {
            if (e.target.matches('.dp-qo-variation')) this.#onVariationChange(e.target);
        });
    }

    #onQtyInput(input) {
        // Skip disabled inputs — variable products before variation selection.
        if (input.disabled) return;

        const rowKey = input.dataset.rowKey;
        if (!rowKey) return;

        const qty = Math.max(0, parseInt(input.value, 10) || 0);
        this.#sync.schedule(rowKey, qty);
    }

    #onVariationChange(select) {
        const row = select.closest('.dp-qo-row');
        if (!row) return;

        const productId = parseInt(row.dataset.productId, 10);
        if (!productId) return; // malformed row — prevents "undefined_N" keys

        const qtyInput    = row.querySelector('.dp-qo-qty');
        const variationId = parseInt(select.value, 10) || 0; // NaN/empty/non-numeric → 0

        if (!variationId) {
            // User reset to placeholder — disable qty, reset row key to neutral.
            row.dataset.rowKey = `${productId}_0`;
            if (qtyInput) {
                qtyInput.dataset.rowKey = `${productId}_0`;
                qtyInput.disabled       = true;
                qtyInput.value          = '0';
            }
            return;
        }

        const oldKey = row.dataset.rowKey;
        const newKey = `${productId}_${variationId}`;

        if (oldKey === newKey) return; // same variation re-selected — no-op

        const currentQty = qtyInput ? Math.max(0, parseInt(qtyInput.value, 10) || 0) : 0;

        // Update row identity before scheduling — qty input listener reads data-row-key.
        row.dataset.rowKey = newKey;
        if (qtyInput) {
            qtyInput.dataset.rowKey = newKey;
            qtyInput.disabled       = false;
        }

        // Implicit replace: remove old variation + add new variation in the same synchronous
        // call so both land in one debounce window. Feels atomic from user perspective.
        // In-flight abort (if any) is handled by CartSync's AbortController — not our concern.
        const hadOldVariation = oldKey !== `${productId}_0`;
        if (hadOldVariation && currentQty > 0) {
            this.#sync.schedule(oldKey, 0);          // remove old
            this.#sync.schedule(newKey, currentQty); // add new with same qty
        }
        // First selection or qty still 0: nothing to sync until user enters a quantity.
    }
}

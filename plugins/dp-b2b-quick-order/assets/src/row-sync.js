'use strict';

/**
 * Row interaction controller — Batch 2: quantity wiring only.
 *
 * Wires quantity inputs to CartSync via event delegation.
 * Variable product qty inputs remain disabled until variation selection
 * (Batch 3 will handle the variation→qty enable flow).
 */
export class RowSync {
    /** @type {import('./cart-sync.js').CartSync} */
    #sync;
    /** @type {HTMLElement} */
    #tbody;

    /**
     * @param {import('./cart-sync.js').CartSync} sync
     */
    constructor(sync) {
        this.#sync  = sync;
        this.#tbody = document.querySelector('.dp-qo-tbody');
        this.#bindTableEvents();
    }

    #bindTableEvents() {
        // Event delegation — handles dynamically rendered rows on every page load.
        this.#tbody.addEventListener('input', e => {
            if (e.target.matches('.dp-qo-qty')) this.#onQtyInput(e.target);
        });
        // Catch paste and spinner-click changes that fire 'change' but not 'input'.
        this.#tbody.addEventListener('change', e => {
            if (e.target.matches('.dp-qo-qty')) this.#onQtyInput(e.target);
        });
    }

    /**
     * Parse quantity safely and schedule a cart sync.
     *
     * Normalization rules:
     * - empty / non-numeric → 0
     * - negative → clamped to 0
     * - decimals → truncated via parseInt
     *
     * qty === 0 schedules a remove — CartSync handles this natively.
     */
    #onQtyInput(input) {
        const rowKey = input.dataset.rowKey;
        if (!rowKey) return;

        const qty = Math.max(0, parseInt(input.value, 10) || 0);
        this.#sync.schedule(rowKey, qty);
    }
}

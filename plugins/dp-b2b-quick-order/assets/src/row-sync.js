'use strict';

/**
 * Row interaction controller — Batch 2: quantity wiring only.
 *
 * Wires quantity inputs to CartSync via event delegation on the tbody shell.
 * Variable product qty inputs remain disabled until a variation is selected
 * (Batch 3 will handle the variation→qty enable flow).
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
        // 'input' fires on every keystroke, paste, cut, and spinner click — sufficient
        // for debounce-based cart sync. No duplicate 'change' listener needed.
        this.#tbody.addEventListener('input', e => {
            if (e.target.matches('.dp-qo-qty')) this.#onQtyInput(e.target);
        });
    }

    /**
     * Parse quantity safely and schedule a cart sync.
     *
     * Normalization rules:
     * - empty / non-numeric → 0  (schedules a remove — "quantity IS cart state")
     * - negative → clamped to 0
     * - decimals → truncated via parseInt
     */
    #onQtyInput(input) {
        // Skip disabled inputs — variable products before variation selection.
        if (input.disabled) return;

        const rowKey = input.dataset.rowKey;
        if (!rowKey) return;

        const qty = Math.max(0, parseInt(input.value, 10) || 0);
        this.#sync.schedule(rowKey, qty);
    }
}

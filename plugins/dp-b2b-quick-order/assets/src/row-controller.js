'use strict';

/**
 * Row interaction controller.
 * Wires quantity inputs and +/- buttons to local Quick Order state via event
 * delegation. No network calls — WC cart is untouched until explicit
 * "Dodaj u košaricu" submit. See docs/frozen/quick-order-local-state-architecture.md §2.
 */
export class RowController {
    /** @type {import('./quick-order-state.js').QuickOrderState} */
    #state;
    /** @type {import('./footer-controller.js').FooterController} */
    #footer;
    /** @type {HTMLElement|null} */
    #tbody;

    /**
     * @param {import('./quick-order-state.js').QuickOrderState} state
     * @param {import('./footer-controller.js').FooterController} footer
     */
    constructor(state, footer) {
        this.#state  = state;
        this.#footer = footer;
        this.#tbody  = document.querySelector('.dp-qo-tbody');
        if (!this.#tbody) return;
        this.#bindTableEvents();
    }

    /**
     * Re-hydrate every currently-rendered row's qty input from existing local
     * state. Called after any (re)render — pagination, sort, or a variation
     * set arriving asynchronously — so a value set before navigating away
     * from a page is still reflected if the user pages back to it.
     */
    hydrateAll() {
        if (!this.#tbody) return;
        this.#tbody.querySelectorAll('[data-row-key]').forEach(row => {
            const input = row.querySelector('.dp-qo-qty');
            if (!input) return;
            input.value = this.#state.getQuantity(row.dataset.rowKey);
        });
    }

    #bindTableEvents() {
        this.#tbody.addEventListener('input', e => {
            if (e.target.matches('.dp-qo-qty')) this.#onQtyInput(e.target);
        });
        this.#tbody.addEventListener('click', e => {
            if (e.target.matches('.dp-qo-qty-minus')) this.#onQtyButton(e.target, -1);
            else if (e.target.matches('.dp-qo-qty-plus'))  this.#onQtyButton(e.target, +1);
        });
    }

    #onQtyInput(input) {
        if (input.disabled) return;
        const row = input.closest('.dp-qo-row');
        const rowKey = input.dataset.rowKey;
        if (!row || !rowKey) return;

        const qty         = Math.max(0, parseInt(input.value, 10) || 0);
        const productId   = parseInt(row.dataset.productId, 10) || 0;
        const variationId = parseInt(row.dataset.variationId ?? '0', 10) || 0;
        const unitPrice    = parseFloat(row.dataset.price ?? '0') || 0;

        this.#state.setQuantity(rowKey, qty, { productId, variationId, unitPrice });
        this.#footer.render();
    }

    #onQtyButton(btn, delta) {
        if (btn.disabled) return;
        const input = btn.closest('.dp-qo-qty-wrap')?.querySelector('.dp-qo-qty');
        if (!input || input.disabled) return;
        const next = Math.max(0, (parseInt(input.value, 10) || 0) + delta);
        input.value = next;
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

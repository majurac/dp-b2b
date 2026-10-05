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
    /** @type {import('./variation-chips.js').VariationChipsController} */
    #chips;
    /** @type {HTMLElement|null} */
    #tbody;

    /**
     * @param {import('./quick-order-state.js').QuickOrderState} state
     * @param {import('./footer-controller.js').FooterController} footer
     * @param {import('./variation-chips.js').VariationChipsController} chips
     */
    constructor(state, footer, chips) {
        this.#state  = state;
        this.#footer = footer;
        this.#chips  = chips;
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
            const qty = this.#state.getQuantity(row.dataset.rowKey);
            input.value = qty;
            this.#applyAddedState(row, qty);
            this.#syncMinus(row, qty);
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
        // The purchasable unit's own container — a simple product's `.dp-qo-row`,
        // or a variable product's `.dp-qo-variation-row` nested inside the shared
        // parent `.dp-qo-row`. Deliberately class-based, not `.closest('[data-row-key]')`:
        // the qty `<input>` itself also carries `data-row-key` (read directly below,
        // and used by hydrateAll()'s lookup), so an attribute-based closest() would
        // match the input itself before reaching its container.
        const row = input.closest('.dp-qo-row, .dp-qo-variation-row');
        const rowKey = input.dataset.rowKey;
        if (!row || !rowKey) return;

        const qty         = Math.max(0, parseInt(input.value, 10) || 0);
        const productId   = parseInt(row.dataset.productId, 10) || 0;
        const variationId = parseInt(row.dataset.variationId ?? '0', 10) || 0;
        const unitPrice    = parseFloat(row.dataset.price ?? '0') || 0;

        this.#state.setQuantity(rowKey, qty, { productId, variationId, unitPrice });
        this.#footer.render();
        this.#chips.render();
        this.#applyAddedState(row, qty);
        this.#syncMinus(row, qty);
    }

    /**
     * Reflect whether `row` currently has a positive LOCAL quantity via the
     * `.is-added` class — Quick Order local state only, never the WC cart
     * (an item stays flagged here even before/without ever being submitted
     * to the cart, and loses the flag the moment its local quantity returns
     * to 0, regardless of cart contents). Single source of truth for this
     * class, called from both the qty-change path (#onQtyInput — covers
     * typing and the +/- buttons, which dispatch a real `input` event
     * through the same delegated listener) and the hydrate/restore path
     * (hydrateAll — covers initial render and any re-render where local
     * state is re-applied to fresh DOM), so the two paths can never drift.
     * `row` is always the purchasable unit's own container — a simple
     * product's `.dp-qo-row` or a variable product's `.dp-qo-variation-row`
     * — the same row resolution as #onQtyInput above. This class is also what
     * the persistent selected-row check indicator (CSS) is driven by — there is
     * no separate selection state.
     * @param {HTMLElement} row
     * @param {number} qty
     */
    #applyAddedState(row, qty) {
        row.classList.toggle('is-added', qty > 0);
    }

    /**
     * The decrement control is inert at quantity 0 (visual affordance only — the
     * quantity is already clamped at 0). Rows whose input is disabled (out of
     * stock) keep both buttons disabled as rendered.
     * @param {HTMLElement} row
     * @param {number} qty
     */
    #syncMinus(row, qty) {
        const input = row.querySelector('.dp-qo-qty');
        const minus = row.querySelector('.dp-qo-qty-minus');
        if (minus && input && !input.disabled) minus.disabled = qty <= 0;
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

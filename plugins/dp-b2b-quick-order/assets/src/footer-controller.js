'use strict';

/**
 * Renders the Quick Order footer from local state — never from a WC cart response.
 * See docs/frozen/quick-order-local-state-architecture.md §3.
 */
export class FooterController {
    /** @type {import('./quick-order-state.js').QuickOrderState} */
    #state;
    #itemsEl;
    #rowsEl;
    #subtotalEl;
    #addBtn;

    /**
     * @param {import('./quick-order-state.js').QuickOrderState} state
     */
    constructor(state) {
        this.#state      = state;
        this.#itemsEl    = document.querySelector('.dp-qo-footer__items');
        this.#rowsEl     = document.querySelector('.dp-qo-footer__rows');
        this.#subtotalEl = document.querySelector('.dp-qo-footer__subtotal-amount');
        this.#addBtn     = document.querySelector('.dp-qo-footer__add-to-cart');
        this.render();
    }

    render() {
        const config = window.dpQuickOrder ?? {};
        const items  = this.#state.getItemCount();
        const rows   = this.#state.getRowCount();

        if (this.#itemsEl) this.#itemsEl.textContent = `${items} ${config.i18n?.itemsSuffix ?? 'artikala'}`;
        if (this.#rowsEl)  this.#rowsEl.textContent  = `${rows} ${config.i18n?.rowsSuffix ?? 'varijacija'}`;

        if (this.#subtotalEl) {
            const subtotal = this.#state.getSubtotal();
            try {
                this.#subtotalEl.textContent = new Intl.NumberFormat(navigator.language, {
                    style: 'currency', currency: config.currency ?? 'EUR',
                }).format(subtotal);
            } catch {
                this.#subtotalEl.textContent = subtotal.toFixed(2);
            }
        }

        this.setSubmitEnabled(!this.#state.isEmpty());
    }

    /** @param {boolean} enabled */
    setSubmitEnabled(enabled) {
        if (this.#addBtn) this.#addBtn.disabled = !enabled;
    }
}

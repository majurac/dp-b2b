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

        // Counting is unchanged (state.getItemCount / getRowCount) — only the wording is
        // declined for Croatian: 1 artikl / 2-4 artikla / 5+ artikala, and
        // 1 različiti SKU / 2-4 različita SKU-a / 5+ različitih SKU-a.
        if (this.#itemsEl) this.#itemsEl.textContent = `${items} ${pluralHr(items, config.i18n?.itemForms ?? ['artikl', 'artikla', 'artikala'])}`;
        if (this.#rowsEl)  this.#rowsEl.textContent  = `${rows} ${pluralHr(rows, config.i18n?.skuForms ?? ['različiti SKU', 'različita SKU-a', 'različitih SKU-a'])}`;

        if (this.#subtotalEl) {
            const subtotal = this.#state.getSubtotal();
            try {
                this.#subtotalEl.textContent = new Intl.NumberFormat(config.locale ?? navigator.language, {
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

/**
 * Croatian noun declension for a count: [one, few, many].
 * 1, 21, 31… -> one (not 11); 2-4, 22-24… -> few (not 12-14); everything else (incl. 0) -> many.
 * @param {number} n
 * @param {[string,string,string]} forms
 * @returns {string}
 */
function pluralHr(n, forms) {
    const mod10  = n % 10;
    const mod100 = n % 100;
    if (mod10 === 1 && mod100 !== 11) return forms[0];
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return forms[1];
    return forms[2];
}

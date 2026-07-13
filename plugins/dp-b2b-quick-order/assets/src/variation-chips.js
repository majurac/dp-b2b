'use strict';

/**
 * Renders the Quick Order "selected variations" chip row — one chip per
 * variation row with quantity > 0, each removable via an inline × control.
 * Lives beside (never inside) the WBW Product Filter's own selected-filters
 * markup; that subtree is a black box this controller never reads from or
 * writes into. See
 * docs/superpowers/specs/2026-07-13-quick-order-toolbar-chips-design.md §3.
 */
export class VariationChipsController {
    /** @type {import('./quick-order-state.js').QuickOrderState} */
    #state;
    /** @type {HTMLElement|null} */
    #container;
    /**
     * @type {Map<string, {label:string, value:string}[]>} rowKey -> already-resolved
     * attribute label/value pairs, populated from ProductList's render
     * notifications (class-product-query.php::get_variation_details()'s
     * `attributes` field) — never derived from slugs, product names, or SKUs
     * at this layer.
     */
    #attributesByRow = new Map();
    /** @type {((rowKey: string) => void)|null} */
    #onRemove = null;

    /**
     * @param {import('./quick-order-state.js').QuickOrderState} state
     */
    constructor(state) {
        this.#state     = state;
        this.#container = document.querySelector('.dp-qo-selected-variations');

        document.addEventListener('dp:qo:rows-rendered', e => {
            for (const { rowKey, attributes } of e.detail?.variationAttributes ?? []) {
                this.#attributesByRow.set(rowKey, attributes);
            }
        });

        this.#container?.addEventListener('click', e => {
            const btn = e.target.closest('.dp-qo-chip__remove');
            if (!btn || !this.#onRemove) return;
            this.#onRemove(btn.dataset.rowKey);
        });
    }

    /**
     * @param {(rowKey: string) => void} handler Called with the row key of a
     *   removed chip. The caller owns zeroing state, re-syncing the real qty
     *   input, and re-rendering the footer — this controller only renders.
     */
    onRemove(handler) {
        this.#onRemove = handler;
    }

    render() {
        if (!this.#container) return;

        const chipsHtml = this.#state.getActiveRowKeys()
            .filter(rowKey => this.#attributesByRow.has(rowKey) && this.#attributesByRow.get(rowKey).length > 0)
            .map(rowKey => this.#chipHTML(rowKey, this.#attributesByRow.get(rowKey)))
            .join('');

        this.#container.innerHTML = chipsHtml;
        this.#container.hidden = chipsHtml === '';
    }

    /**
     * @param {string} rowKey
     * @param {{label:string, value:string}[]} attributes Already-resolved
     *   pairs, in the deterministic order the server produced them. Chip text
     *   intentionally shows only `value` (no attribute-name prefix) to stay
     *   visually consistent with WBW's own Selected Filters chips, which also
     *   display values only. `label` is still carried in the payload/cache
     *   for future use — this function just doesn't render it.
     */
    #chipHTML(rowKey, attributes) {
        const text = attributes.map(a => a.value).join(' • ');
        return `
<span class="dp-qo-chip" data-row-key="${escHtml(rowKey)}">
  ${escHtml(text)}
  <button type="button" class="dp-qo-chip__remove" data-row-key="${escHtml(rowKey)}" aria-label="Ukloni ${escHtml(text)}">×</button>
</span>`.trim();
    }
}

/** Escape HTML for safe insertion into innerHTML — same behavior as product-list.js's module-scope helper. */
function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
}

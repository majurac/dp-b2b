'use strict';

/**
 * Row interaction controller.
 *
 * Wires quantity inputs and variation selects to CartSync via event delegation.
 * Handles row state feedback (idle/pending/synced/error) via CartSync events.
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
        this.#initStateListeners();
    }

    #initStateListeners() {
        document.addEventListener('dp:sync:start', e => {
            for (const item of e.detail.items) {
                const row = this.#findRow(`${item.product_id}_${item.variation_id}`);
                if (row) this.#setState(row, 'pending');
            }
        });

        document.addEventListener('dp:sync:success', e => {
            for (const item of e.detail.synced) {
                const row = this.#findRow(`${item.product_id}_${item.variation_id}`);
                if (!row) continue;

                this.#setState(row, this.#resolveState(item.action));

                // Reflect server-corrected quantity in qty input for out_of_stock.
                if (item.action === 'out_of_stock') {
                    const input = row.querySelector('.dp-qo-qty');
                    if (input) input.value = item.quantity_allowed ?? 0;
                }
            }
        });

        document.addEventListener('dp:sync:error', () => {
            if (!this.#tbody) return;
            this.#tbody.querySelectorAll('.dp-qo-row.is-pending').forEach(row => {
                this.#setState(row, 'error');
            });
        });
    }

    /** Map a server action string to a row UI state. */
    #resolveState(action) {
        switch (action) {
            case 'removed':
            case 'skipped':      return 'idle';
            case 'failed':
            case 'out_of_stock': return 'error';
            default:             return 'synced';
        }
    }

    /**
     * Apply state class and status icon to a row.
     * Clears all state classes before applying the new one.
     * 'idle' removes all classes (default DOM state, no visual indicator).
     *
     * @param {HTMLElement} row
     * @param {'idle'|'pending'|'synced'|'error'} state
     */
    #setState(row, state) {
        row.classList.remove('is-pending', 'is-synced', 'is-error');
        if (state !== 'idle') row.classList.add(`is-${state}`);

        const icon = row.querySelector('.dp-qo-status-icon');
        if (!icon) return;
        const map = { pending: '…', synced: '✓', error: '✕', idle: '' };
        icon.textContent = map[state] ?? '';
    }

    /**
     * Find a row by its data-row-key attribute.
     * rowKey is always "${productId}_${variationId}" — safe integer format, no escaping needed.
     *
     * @param {string} rowKey
     * @returns {HTMLElement|null}
     */
    #findRow(rowKey) {
        return this.#tbody?.querySelector(`[data-row-key="${rowKey}"]`) ?? null;
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
        // +/- button clicks — adjust value and re-fire input event into existing handler.
        this.#tbody.addEventListener('click', e => {
            if (e.target.matches('.dp-qo-qty-minus')) this.#onQtyButton(e.target, -1);
            else if (e.target.matches('.dp-qo-qty-plus'))  this.#onQtyButton(e.target, +1);
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

    #onQtyButton(btn, delta) {
        if (btn.disabled) return;
        const input = btn.closest('.dp-qo-qty-wrap')?.querySelector('.dp-qo-qty');
        if (!input || input.disabled) return;
        const next = Math.max(0, (parseInt(input.value, 10) || 0) + delta);
        input.value = next;
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    #onVariationChange(select) {
        const row = select.closest('.dp-qo-row');
        if (!row) return;

        const productId = parseInt(row.dataset.productId, 10);
        if (!productId) return; // malformed row — prevents "undefined_N" keys

        const qtyInput    = row.querySelector('.dp-qo-qty');
        const variationId = parseInt(select.value, 10) || 0; // NaN/empty/non-numeric → 0

        if (!variationId) {
            // User reset to placeholder — disable qty + buttons, reset row key to neutral.
            row.dataset.rowKey = `${productId}_0`;
            if (qtyInput) {
                qtyInput.dataset.rowKey = `${productId}_0`;
                qtyInput.disabled       = true;
                qtyInput.value          = '0';
            }
            row.querySelectorAll('.dp-qo-qty-btn').forEach(b => { b.disabled = true; });
            this.#updateStockBadge(row, null);
            return;
        }

        const oldKey = row.dataset.rowKey;
        const newKey = `${productId}_${variationId}`;

        if (oldKey === newKey) return; // same variation re-selected — no-op

        const currentQty = qtyInput ? Math.max(0, parseInt(qtyInput.value, 10) || 0) : 0;

        const selectedStock = select.options[select.selectedIndex]?.dataset.stock ?? 'instock';
        const isOutOfStock  = selectedStock === 'outofstock';

        // Update row identity before scheduling — qty input listener reads data-row-key.
        row.dataset.rowKey = newKey;
        if (qtyInput) {
            qtyInput.dataset.rowKey = newKey;
            qtyInput.disabled       = isOutOfStock;
            if (isOutOfStock) qtyInput.value = '0';
        }
        row.querySelectorAll('.dp-qo-qty-btn').forEach(b => { b.disabled = isOutOfStock; });

        this.#updateStockBadge(row, selectedStock);

        // Implicit replace: remove old variation + add new variation in the same synchronous
        // call so both land in one debounce window. Feels atomic from user perspective.
        // In-flight abort (if any) is handled by CartSync's AbortController — not our concern.
        const hadOldVariation = oldKey !== `${productId}_0`;
        if (hadOldVariation && currentQty > 0) {
            this.#sync.schedule(oldKey, 0);
            if (!isOutOfStock) this.#sync.schedule(newKey, currentQty);
        }
        // First selection or qty still 0: nothing to sync until user enters a quantity.
    }

    /**
     * Update the stock badge in a row from a variation stock status string.
     * Pass null to reset to neutral (before variation selection).
     *
     * @param {HTMLElement} row
     * @param {string|null} stockStatus
     */
    #updateStockBadge(row, stockStatus) {
        const badge = row.querySelector('.dp-qo-stock');
        if (!badge) return;
        const labels = { instock: 'Na stanju', outofstock: 'Nema na stanju', onbackorder: 'Po narudžbi' };
        if (!stockStatus) {
            badge.className   = 'dp-qo-stock dp-qo-stock--neutral';
            badge.textContent = 'Odaberi varijaciju';
        } else {
            badge.className   = `dp-qo-stock dp-qo-stock--${stockStatus}`;
            badge.textContent = labels[stockStatus] ?? stockStatus;
        }
    }
}

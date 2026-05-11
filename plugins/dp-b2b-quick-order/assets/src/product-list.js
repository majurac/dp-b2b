'use strict';

/**
 * Product list renderer.
 *
 * Fetches /products (paginated), renders rows into .dp-qo-tbody,
 * lazy-loads variation options for variable products after each page render.
 */
export class ProductList {
    /** @type {object} dpQuickOrder config */
    #config;
    /** @type {HTMLElement} */
    #tbody;
    /** @type {HTMLElement} */
    #paginationEl;
    #currentPage  = 1;
    #totalPages   = 1;

    /**
     * @param {object} config  window.dpQuickOrder
     */
    constructor(config) {
        this.#config      = config;
        this.#tbody       = document.querySelector('.dp-qo-tbody');
        this.#paginationEl = document.querySelector('.dp-qo-pagination');
    }

    /**
     * Fetch and render a product page.
     * @param {number} page
     */
    async loadPage(page = 1) {
        this.#currentPage = page;
        this.#tbody.innerHTML = `<tr><td colspan="6" class="dp-qo-loading">Učitavanje...</td></tr>`;

        let data;
        try {
            const url = `${this.#config.productsUrl}?page=${page}&per_page=${this.#config.perPage ?? 50}`;
            const res = await fetch(url, { headers: { 'X-WP-Nonce': this.#config.wpNonce } });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            data = await res.json();
        } catch (err) {
            this.#tbody.innerHTML = `<tr><td colspan="6" class="dp-qo-error">Greška pri učitavanju proizvoda.</td></tr>`;
            return;
        }

        this.#totalPages = data.total_pages ?? 1;
        this.#renderRows(data.products ?? []);
        this.#renderPagination();
        this.#loadAllVariations();
    }

    #renderRows(products) {
        if (!products.length) {
            this.#tbody.innerHTML = `<tr><td colspan="6" class="dp-qo-empty">Nema dostupnih proizvoda.</td></tr>`;
            return;
        }
        this.#tbody.innerHTML = products.map(p => this.#rowHTML(p)).join('');
    }

    #rowHTML(product) {
        const isVariable = product.type === 'variable';
        const rowKey     = `${product.id}_0`;

        const stockLabel = { instock: 'Na stanju', outofstock: 'Nema na stanju', onbackorder: 'Po narudžbi' };
        const stockClass = `dp-qo-stock--${escHtml(product.stock?.status ?? 'outofstock')}`;
        const stockText  = stockLabel[product.stock?.status] ?? (product.stock?.status ?? '');

        const variationCell = isVariable
            ? `<select class="dp-qo-variation" data-product-id="${product.id}" disabled>
                 <option value="">— Učitavanje —</option>
               </select>`
            : '';

        return `
<tr class="dp-qo-row"
    data-product-id="${product.id}"
    data-type="${escHtml(product.type)}"
    data-row-key="${rowKey}">
  <td class="dp-qo-col-name">
    <strong class="dp-qo-name">${escHtml(product.name)}</strong>
    <small class="dp-qo-sku">${escHtml(product.sku)}</small>
  </td>
  <td class="dp-qo-col-stock">
    <span class="dp-qo-stock ${stockClass}">${stockText}</span>
  </td>
  <td class="dp-qo-col-price">${product.price_html ?? ''}</td>
  <td class="dp-qo-col-variation">${variationCell}</td>
  <td class="dp-qo-col-qty">
    <input type="number"
           class="dp-qo-qty"
           data-row-key="${rowKey}"
           value="0" min="0" step="1"
           ${isVariable ? 'disabled' : ''}>
  </td>
  <td class="dp-qo-col-status"><span class="dp-qo-status-icon" aria-hidden="true"></span></td>
</tr>`.trim();
    }

    /** Kick off parallel variation fetches for all variable rows on current page. */
    #loadAllVariations() {
        const rows = this.#tbody.querySelectorAll('[data-type="variable"]');
        rows.forEach(row => this.#loadVariationOptions(row));
    }

    async #loadVariationOptions(row) {
        const productId = row.dataset.productId;
        const select    = row.querySelector('.dp-qo-variation');
        if (!select) return;

        let variations;
        try {
            const url = `${this.#config.productsUrl}/${productId}/variations`;
            const res = await fetch(url, { headers: { 'X-WP-Nonce': this.#config.wpNonce } });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            variations = await res.json();
        } catch {
            select.innerHTML = `<option value="">— Greška —</option>`;
            return;
        }

        select.innerHTML =
            `<option value="">— Odaberi varijaciju —</option>` +
            variations.map(v => {
                const stockSuffix = v.stock_status === 'outofstock' ? ' (nema na stanju)' : '';
                return `<option value="${v.id}" data-stock="${escHtml(v.stock_status)}">${escHtml(v.label)}${stockSuffix}</option>`;
            }).join('');

        select.disabled = false;
    }

    #renderPagination() {
        if (!this.#paginationEl) return;

        const hasPrev = this.#currentPage > 1;
        const hasNext = this.#currentPage < this.#totalPages;

        this.#paginationEl.innerHTML =
            (hasPrev ? `<button class="dp-qo-btn" data-page="${this.#currentPage - 1}">← Prethodna</button>` : '') +
            `<span class="dp-qo-page-info">Strana ${this.#currentPage} / ${this.#totalPages}</span>` +
            (hasNext ? `<button class="dp-qo-btn" data-page="${this.#currentPage + 1}">Sljedeća →</button>` : '');

        this.#paginationEl.querySelectorAll('[data-page]').forEach(btn => {
            btn.addEventListener('click', () => this.loadPage(parseInt(btn.dataset.page, 10)));
        });
    }
}

/** Escape HTML for safe insertion into innerHTML. */
function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
}

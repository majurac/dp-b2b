'use strict';

/**
 * Product list renderer.
 *
 * Fetches /products (paginated), renders rows into .dp-qo-tbody. Variable
 * products render a temporary loading row, replaced with one independent
 * row per variation once /products/{id}/variations resolves — no dropdown.
 * Integrates with WOOF/WBW filter URL changes: when WOOF updates the browser URL
 * with wpf_filter_pa_* or pr_min/pr_max params, re-fetches with those filters mapped
 * to Quick Order's server-side REST params.
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
    #orderBy      = 'title';
    #orderDir     = 'asc';
    /** WOOF-sourced filter state. Reset to {} on each URL change parse. */
    #woofFilters  = {};
    /** @type {Map<number, {name:string, image:string}>} Parent product meta, keyed by product id — used when expanding variation rows. */
    #parentMeta   = new Map();

    /**
     * @param {object} config  window.dpQuickOrder
     */
    constructor(config) {
        this.#config       = config;
        this.#tbody        = document.querySelector('.dp-qo-tbody');
        this.#paginationEl = document.querySelector('.dp-qo-pagination');
        this.#bindSortHeaders();
        this.#updateSortIndicators();
        this.#bindWoofIntegration();
    }

    /**
     * Fetch and render a product page.
     * @param {number} page
     */
    async loadPage(page = 1) {
        if (!this.#tbody) return;
        this.#currentPage = page;
        this.#tbody.innerHTML = `<tr><td colspan="5" class="dp-qo-loading">Učitavanje...</td></tr>`;

        let data;
        try {
            const url = this.#buildProductsUrl(page);
            const res = await fetch(url, { headers: { 'X-WP-Nonce': this.#config.wpNonce } });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            data = await res.json();
        } catch (err) {
            this.#tbody.innerHTML = `<tr><td colspan="5" class="dp-qo-error">Greška pri učitavanju proizvoda.</td></tr>`;
            return;
        }

        this.#totalPages = data.total_pages ?? 1;
        this.#renderRows(data.products ?? []);
        this.#renderPagination();
        document.dispatchEvent(new CustomEvent('dp:qo:rows-rendered'));
        this.#loadAllVariations();
    }

    /**
     * Build the REST URL for a product page, including sort and any active WOOF filters.
     * @param {number} page
     * @returns {string}
     */
    #buildProductsUrl(page) {
        const params = new URLSearchParams({
            page,
            per_page:   this.#config.perPage ?? 50,
            qo_orderby: this.#orderBy,
            qo_order:   this.#orderDir,
        });

        const f = this.#woofFilters;
        if (f.price_min > 0)                           params.set('price_min',    f.price_min);
        if (f.price_max > 0)                           params.set('price_max',    f.price_max);
        if (f.stock_status)                            params.set('stock_status', f.stock_status);
        if (f.category > 0)                            params.set('category',     f.category);
        if (f.brand > 0)                                params.set('brand',        f.brand);
        if (f.attributes && Object.keys(f.attributes).length) {
            params.set('attributes', JSON.stringify(f.attributes));
        }

        return `${this.#config.productsUrl}?${params.toString()}`;
    }

    #renderRows(products) {
        if (!products.length) {
            this.#tbody.innerHTML = `<tr><td colspan="5" class="dp-qo-empty">Nema dostupnih proizvoda.</td></tr>`;
            return;
        }
        this.#parentMeta.clear();
        this.#tbody.innerHTML = products.map(p => this.#rowHTML(p)).join('');
    }

    #rowHTML(product) {
        const isVariable = product.type === 'variable';
        const thumbSrc    = product.image || this.#config.placeholderImg || '';

        if (isVariable) {
            this.#parentMeta.set(Number(product.id), { name: product.name, image: thumbSrc });
            return `
<tr class="dp-qo-row dp-qo-row--loading" data-product-id="${product.id}" data-type="variable">
  <td colspan="5" class="dp-qo-loading">${escHtml(this.#config.i18n?.loadingVariations ?? 'Učitavanje varijacija...')}</td>
</tr>`.trim();
        }

        const rowKey     = `${product.id}_0`;
        const disableQty = product.stock?.status === 'outofstock';
        const stockLabel = { instock: 'Na stanju', outofstock: 'Nema na stanju', onbackorder: 'Po narudžbi' };
        const stockClass = `dp-qo-stock--${escHtml(product.stock?.status ?? 'outofstock')}`;
        const stockText  = stockLabel[product.stock?.status] ?? (product.stock?.status ?? '');
        const thumbCell  = thumbSrc
            ? `<img src="${escHtml(thumbSrc)}" alt="" class="dp-qo-thumb" width="40" height="40" loading="lazy">`
            : '';

        return this.#dataRowHTML({
            rowKey, productId: product.id, variationId: 0,
            name: escHtml(product.name), sku: escHtml(product.sku),
            stockClass, stockText, priceHtml: product.price_html ?? '', price: product.price ?? 0,
            thumbCell, disableQty,
        });
    }

    /**
     * Shared row template for both simple-product rows and expanded variation rows.
     * Product links are intentionally omitted — Quick Order keeps the user on-page (brief §5).
     */
    #dataRowHTML({ rowKey, productId, variationId, name, sku, stockClass, stockText, priceHtml, price, thumbCell, disableQty }) {
        const skuLabel = escHtml(this.#config.i18n?.skuLabel ?? 'Kataloški broj:');
        return `
<tr class="dp-qo-row"
    data-product-id="${productId}"
    data-variation-id="${variationId}"
    data-row-key="${rowKey}"
    data-price="${price}">
  <td class="dp-qo-col-thumb">${thumbCell}</td>
  <td class="dp-qo-col-name">
    <strong class="dp-qo-name">${name}</strong>
    <small class="dp-qo-sku">${skuLabel} ${sku}</small>
  </td>
  <td class="dp-qo-col-stock">
    <span class="dp-qo-stock ${stockClass}">${stockText}</span>
  </td>
  <td class="dp-qo-col-price">${priceHtml}</td>
  <td class="dp-qo-col-qty">
    <div class="dp-qo-qty-wrap">
      <button class="dp-qo-qty-btn dp-qo-qty-minus" type="button" aria-label="Smanji količinu"${disableQty ? ' disabled' : ''}>−</button>
      <input type="number"
             class="dp-qo-qty"
             data-row-key="${rowKey}"
             value="0" min="0" step="1"
             ${disableQty ? 'disabled' : ''}>
      <button class="dp-qo-qty-btn dp-qo-qty-plus" type="button" aria-label="Povećaj količinu"${disableQty ? ' disabled' : ''}>+</button>
    </div>
  </td>
</tr>`.trim();
    }

    /** Kick off parallel variation fetches for all variable rows on current page. */
    #loadAllVariations() {
        const rows = this.#tbody.querySelectorAll('[data-type="variable"]');
        rows.forEach(row => this.#loadVariationOptions(row));
    }

    /**
     * Fetch variation details and replace the loading row with one independent
     * row per variation (brief §7 — no dropdown). Reuses the parent's thumbnail
     * for every variation row; variation-specific images are not fetched
     * (`get_variation_details()` doesn't return them — avoids extra hydration
     * cost per project performance rules).
     */
    async #loadVariationOptions(row) {
        const productId = row.dataset.productId;

        let variations;
        try {
            const url = `${this.#config.productsUrl}/${productId}/variations`;
            const res = await fetch(url, { headers: { 'X-WP-Nonce': this.#config.wpNonce } });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            variations = await res.json();
        } catch {
            row.innerHTML = `<td colspan="5" class="dp-qo-error">${escHtml(this.#config.i18n?.variationLoadError ?? 'Greška pri učitavanju varijacija.')}</td>`;
            return;
        }

        if (!variations.length) {
            row.remove();
            return;
        }

        const meta      = this.#parentMeta.get(Number(productId)) ?? { name: '', image: '' };
        const thumbCell = meta.image
            ? `<img src="${escHtml(meta.image)}" alt="" class="dp-qo-thumb" width="40" height="40" loading="lazy">`
            : '';
        const stockLabel = { instock: 'Na stanju', outofstock: 'Nema na stanju', onbackorder: 'Po narudžbi' };

        const rowsHtml = variations.map(v => {
            const rowKey     = `${productId}_${v.id}`;
            const stockClass = `dp-qo-stock--${escHtml(v.stock_status)}`;
            const stockText  = stockLabel[v.stock_status] ?? v.stock_status;
            return this.#dataRowHTML({
                rowKey, productId: Number(productId), variationId: v.id,
                name: `${escHtml(meta.name)} — ${escHtml(v.label)}`, sku: escHtml(v.sku),
                stockClass, stockText, priceHtml: v.price_html, price: v.price,
                thumbCell, disableQty: v.stock_status === 'outofstock',
            });
        }).join('');

        row.outerHTML = rowsHtml;
        document.dispatchEvent(new CustomEvent('dp:qo:rows-rendered'));
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

    #bindSortHeaders() {
        document.querySelectorAll('.dp-qo-table th[data-sort]').forEach(th => {
            th.addEventListener('click', () => {
                const col = th.dataset.sort;
                if (this.#orderBy === col) {
                    this.#orderDir = this.#orderDir === 'asc' ? 'desc' : 'asc';
                } else {
                    this.#orderBy  = col;
                    this.#orderDir = 'asc';
                }
                this.#updateSortIndicators();
                this.loadPage(1);
            });
        });
    }

    #updateSortIndicators() {
        document.querySelectorAll('.dp-qo-table th[data-sort]').forEach(th => {
            const arrow = th.querySelector('.dp-qo-sort-arrow');
            if (!arrow) return;
            arrow.textContent = th.dataset.sort === this.#orderBy
                ? (this.#orderDir === 'asc' ? ' ↑' : ' ↓')
                : '';
        });
    }

    /**
     * Intercept history.pushState and popstate so that when WOOF updates the browser
     * URL with filter params, Quick Order re-fetches its product list from the server.
     */
    #bindWoofIntegration() {
        this.#woofFilters = this.#extractWoofFilters(new URLSearchParams(window.location.search));

        const onUrlChange = () => this.#onWoofUrlChange();

        const origPushState = history.pushState.bind(history);
        history.pushState = (state, title, url) => {
            origPushState(state, title, url);
            onUrlChange();
        };

        window.addEventListener('popstate', onUrlChange);
    }

    #onWoofUrlChange() {
        const params  = new URLSearchParams(window.location.search);
        const next    = this.#extractWoofFilters(params);
        const current = JSON.stringify(this.#woofFilters);

        if (JSON.stringify(next) !== current) {
            this.#woofFilters = next;
            this.loadPage(1);
        }
    }

    /**
     * Extract Quick Order filter params from a WOOF-updated URL.
     * @param {URLSearchParams} params
     * @returns {{ price_min?: number, price_max?: number, stock_status?: string, category?: number, brand?: number, attributes?: object }}
     */
    #extractWoofFilters(params) {
        const result = {};

        const prMin = parseFloat(params.get('wpf_min_price') ?? '');
        const prMax = parseFloat(params.get('wpf_max_price') ?? '');
        if (!isNaN(prMin) && prMin > 0) result.price_min = prMin;
        if (!isNaN(prMax) && prMax > 0) result.price_max = prMax;

        const stockStatus = params.get('pr_stock');
        if (stockStatus && ['instock', 'outofstock', 'onbackorder'].includes(stockStatus)) {
            result.stock_status = stockStatus;
        }

        for (const [key, val] of params) {
            if (!/^wpf_filter_cat_\d+$/.test(key)) continue;
            const termId = parseInt(val, 10);
            if (termId > 0) { result.category = termId; break; }
        }

        for (const [key, val] of params) {
            if (!/^wpf_filter_product_brand_\d+$/.test(key)) continue;
            const termId = parseInt(val, 10);
            if (termId > 0) { result.brand = termId; break; }
        }

        const attrs = {};
        for (const [key, val] of params) {
            if (!key.startsWith('wpf_filter_pa_')) continue;
            const attrName = key.slice('wpf_filter_pa_'.length);
            if (!attrName) continue;
            attrs[attrName] = val.split('|').filter(Boolean);
        }
        if (Object.keys(attrs).length) result.attributes = attrs;

        return result;
    }
}

/** Escape HTML for safe insertion into innerHTML. */
function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
}

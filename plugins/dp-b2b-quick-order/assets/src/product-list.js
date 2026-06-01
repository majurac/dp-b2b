'use strict';

/**
 * Product list renderer.
 *
 * Fetches /products (paginated), renders rows into .dp-qo-tbody,
 * lazy-loads variation options for variable products after each page render.
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
        this.#tbody.innerHTML = `<tr><td colspan="7" class="dp-qo-loading">Učitavanje...</td></tr>`;

        let data;
        try {
            const url = this.#buildProductsUrl(page);
            const res = await fetch(url, { headers: { 'X-WP-Nonce': this.#config.wpNonce } });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            data = await res.json();
        } catch (err) {
            this.#tbody.innerHTML = `<tr><td colspan="7" class="dp-qo-error">Greška pri učitavanju proizvoda.</td></tr>`;
            return;
        }

        this.#totalPages = data.total_pages ?? 1;
        this.#renderRows(data.products ?? []);
        this.#renderPagination();
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
        if (f.brand > 0)                               params.set('brand',        f.brand);
        if (f.attributes && Object.keys(f.attributes).length) {
            params.set('attributes', JSON.stringify(f.attributes));
        }

        return `${this.#config.productsUrl}?${params.toString()}`;
    }

    #renderRows(products) {
        if (!products.length) {
            this.#tbody.innerHTML = `<tr><td colspan="7" class="dp-qo-empty">Nema dostupnih proizvoda.</td></tr>`;
            return;
        }
        this.#tbody.innerHTML = products.map(p => this.#rowHTML(p)).join('');
    }

    #rowHTML(product) {
        const isVariable  = product.type === 'variable';
        const rowKey      = `${product.id}_0`;
        const disableQty  = isVariable || product.stock?.status === 'outofstock';

        let stockClass, stockText;
        if (isVariable) {
            stockClass = 'dp-qo-stock--neutral';
            stockText  = 'Odaberi varijaciju';
        } else {
            const stockLabel = { instock: 'Na stanju', outofstock: 'Nema na stanju', onbackorder: 'Po narudžbi' };
            stockClass = `dp-qo-stock--${escHtml(product.stock?.status ?? 'outofstock')}`;
            stockText  = stockLabel[product.stock?.status] ?? (product.stock?.status ?? '');
        }

        const variationCell = isVariable
            ? `<select class="dp-qo-variation" data-product-id="${product.id}" disabled>
                 <option value="">— Učitavanje —</option>
               </select>`
            : '';

        const thumbSrc = product.image || this.#config.placeholderImg || '';
        const thumbCell = thumbSrc
            ? `<img src="${escHtml(thumbSrc)}" alt="" class="dp-qo-thumb" width="40" height="40" loading="lazy">`
            : '';

        const nameInner = product.permalink
            ? `<a href="${escHtml(product.permalink)}" class="dp-qo-name-link"><strong class="dp-qo-name">${escHtml(product.name)}</strong></a>`
            : `<strong class="dp-qo-name">${escHtml(product.name)}</strong>`;

        return `
<tr class="dp-qo-row"
    data-product-id="${product.id}"
    data-type="${escHtml(product.type)}"
    data-row-key="${rowKey}">
  <td class="dp-qo-col-thumb">${thumbCell}</td>
  <td class="dp-qo-col-name">
    ${nameInner}
    <small class="dp-qo-sku">${escHtml(product.sku)}</small>
  </td>
  <td class="dp-qo-col-stock">
    <span class="dp-qo-stock ${stockClass}">${stockText}</span>
  </td>
  <td class="dp-qo-col-price">${product.price_html ?? ''}</td>
  <td class="dp-qo-col-variation">${variationCell}</td>
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
     *
     * WOOF uses history.pushState to apply filters without a page reload.
     * We wrap pushState once and listen for popstate (back/forward navigation).
     * If WOOF is not on the page, no wpf_ or pr_ params ever appear and this is a no-op.
     *
     * #woofFilters is hydrated from the current URL immediately so that the first
     * loadPage() call (from the entry point) respects any pre-existing WOOF params
     * in the URL (e.g. bookmarked filtered pages).
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
     *
     * WOOF URL format → QO REST params:
     *   wpf_min_price=100                    → price_min: 100
     *   wpf_max_price=500                    → price_max: 500
     *   pr_stock=instock                     → stock_status: 'instock'
     *   wpf_filter_pa_color=red|blue         → attributes.color: ['red', 'blue']
     *   wpf_filter_cat_{N}=30                → category: 30  (term ID)
     *   wpf_filter_product_brand_{N}=42      → brand: 42     (term ID)
     *
     * Category and brand values are sent by WOOF as numeric term IDs.
     * Non-numeric values (slugs) are ignored — parseInt returns NaN, guard fails safely.
     *
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

        // Category: wpf_filter_cat_{N}=term_id
        for (const [key, val] of params) {
            if (!/^wpf_filter_cat_\d+$/.test(key)) continue;
            const termId = parseInt(val, 10);
            if (termId > 0) { result.category = termId; break; }
        }

        // Brand: wpf_filter_product_brand_{N}=term_id
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
            // WOOF uses | as multi-value delimiter
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

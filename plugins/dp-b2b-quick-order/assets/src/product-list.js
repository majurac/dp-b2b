'use strict';

/**
 * Product list renderer.
 *
 * Fetches /products (paginated), renders rows into .dp-qo-tbody. Simple
 * products render as one `.dp-qo-row` using the table's real columns (thumb,
 * name, stock, price, qty). Variable products also render as exactly one
 * `.dp-qo-row--variable` using the SAME real columns — no `colspan`, no
 * simulated table. Parent info (thumb/name/SKU) is shown once in the
 * thumb/name columns; the stock/price/qty columns (and the name column,
 * below the parent info) each hold a vertically-stacked list with one line
 * per variation, populated in place once /products/{id}/variations resolves
 * — no dropdown, no promotion to sibling top-level rows.
 * Integrates with WOOF/WBW filter URL changes: when WOOF updates the browser URL
 * with wpf_filter_pa_* or pr_min/pr_max params, re-fetches with those filters mapped
 * to Quick Order's server-side REST params.
 */
/** Search debounce — matches the project's 300 ms debounce convention (DP_Quick_Order_Config::CART_SYNC_DEBOUNCE_MS). */
const SEARCH_DEBOUNCE_MS = 300;

/** Quick Order-owned filter URL param -> #woofFilters state key. */
const QO_FILTER_STATE = {
    qo_already_ordered: 'qoAlreadyOrdered',
    qo_new:             'qoNew',
    qo_best_seller:     'qoBestSeller',
};

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
    /** @type {HTMLInputElement|null} */
    #searchInput;
    /** @type {HTMLElement|null} */
    #searchClear;
    /** Pending search debounce timer id, or null when nothing is pending. */
    #searchTimer  = null;
    /** Monotonic request counter — an older, slower response never overwrites a newer one. */
    #reqSeq       = 0;
    /** @type {HTMLElement|null} "Popularne pretrage" row (shown while no FILTER is active). */
    #popularRow;
    /** @type {HTMLElement|null} "Aktivni filteri" row (shown while at least one FILTER is active). */
    #activeRow;
    /** @type {HTMLElement|null} */
    #activeList;
    /** @type {HTMLElement|null} WBW's own selected-filters node, once adopted into #activeRow. */
    #wbwSelected = null;
    /** @type {MutationObserver|null} */
    #wbwObserver = null;

    /**
     * @param {object} config  window.dpQuickOrder
     */
    constructor(config) {
        this.#config       = config;
        this.#tbody        = document.querySelector('.dp-qo-tbody');
        this.#paginationEl = document.querySelector('.dp-qo-pagination');
        this.#searchInput  = document.querySelector('.dp-qo-search__input');
        this.#searchClear  = document.querySelector('.dp-qo-search__clear');
        this.#popularRow   = document.querySelector('.dp-qo-state-row[data-qo-state="popular"]');
        this.#activeRow    = document.querySelector('.dp-qo-state-row[data-qo-state="active"]');
        this.#activeList   = this.#activeRow?.querySelector('.dp-qo-chip-list--active') ?? null;
        this.#bindWoofIntegration();
        this.#bindQoOwnedFilters();
        this.#bindSearch();
        this.#bindStateRow();
        this.#renderStateRow();
        this.#bindFilterToggle();
    }

    /**
     * Fetch and render a product page.
     * @param {number} page
     */
    async loadPage(page = 1) {
        if (!this.#tbody) return;
        this.#currentPage = page;
        this.#tbody.innerHTML = `<tr><td colspan="5" class="dp-qo-loading">Učitavanje...</td></tr>`;

        const reqId = ++this.#reqSeq;
        let data;
        try {
            const url = this.#buildProductsUrl(page);
            const res = await fetch(url, { headers: { 'X-WP-Nonce': this.#config.wpNonce } });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            data = await res.json();
        } catch (err) {
            if (reqId !== this.#reqSeq) return;
            this.#tbody.innerHTML = `<tr><td colspan="5" class="dp-qo-error">Greška pri učitavanju proizvoda.</td></tr>`;
            return;
        }

        if (reqId !== this.#reqSeq) return; // superseded by a newer search/filter request
        this.#totalPages = data.total_pages ?? 1;
        this.#renderRows(data.products ?? []);
        this.#renderPagination();
        // Skeleton render: variable-product parent rows exist but their
        // variations (and resolved attributes) haven't been fetched yet — empty payload.
        document.dispatchEvent(new CustomEvent('dp:qo:rows-rendered', { detail: { variationAttributes: [] } }));
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
        if (f.category?.length)                        params.set('category',     JSON.stringify(f.category));
        if (f.brand?.length)                            params.set('brand',        JSON.stringify(f.brand));
        if (f.attributes && Object.keys(f.attributes).length) {
            params.set('attributes', JSON.stringify(f.attributes));
        }
        if (f.qoSearch)         params.set('search', f.qoSearch);
        if (f.qoNew)            params.set('qo_new', '1');
        if (f.qoBestSeller)     params.set('qo_best_seller', '1');
        if (f.qoAlreadyOrdered) params.set('qo_already_ordered', '1');

        return `${this.#config.productsUrl}?${params.toString()}`;
    }

    #renderRows(products) {
        if (!products.length) {
            this.#tbody.innerHTML = this.#emptyStateHTML();
            return;
        }
        this.#tbody.innerHTML = products.map(p => this.#rowHTML(p)).join('');
    }

    #rowHTML(product) {
        const isVariable = product.type === 'variable';
        const thumbSrc    = product.image || this.#config.placeholderImg || '';

        if (isVariable) {
            const skuLabel  = escHtml(this.#config.i18n?.skuLabel ?? 'Kataloški broj:');
            const thumbCell = thumbSrc
                ? `<img src="${escHtml(thumbSrc)}" alt="" class="dp-qo-thumb" width="40" height="40" loading="lazy">`
                : '';
            const loadingText = escHtml(this.#config.i18n?.loadingVariations ?? 'Učitavanje varijacija...');
            return `
<tr class="dp-qo-row dp-qo-row--variable" data-product-id="${product.id}" data-type="variable">
  <td class="dp-qo-col-thumb">${thumbCell}</td>
  <td class="dp-qo-col-name dp-qo-col-name--variable">
    <div class="dp-qo-row__product-info">
      <strong class="dp-qo-name">${escHtml(product.name)}</strong>
      <small class="dp-qo-sku">${skuLabel} ${escHtml(product.catalog_number)}</small>
    </div>
    <div class="dp-qo-variation-labels dp-qo-variation-list--loading">${loadingText}</div>
  </td>
  <td class="dp-qo-col-stock"><div class="dp-qo-variation-stocks"></div></td>
  <td class="dp-qo-col-price"><div class="dp-qo-variation-prices"></div></td>
  <td class="dp-qo-col-qty"><div class="dp-qo-variation-qtys"></div></td>
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
            name: escHtml(product.name), sku: escHtml(product.catalog_number),
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
  <td class="dp-qo-col-qty">${this.#qtyControlsHTML(rowKey, disableQty)}</td>
</tr>`.trim();
    }

    /** Shared qty +/- controls markup, used by both simple-product/data rows and variation rows. */
    #qtyControlsHTML(rowKey, disableQty) {
        return `
<div class="dp-qo-qty-wrap">
  <button class="dp-qo-qty-btn dp-qo-qty-minus" type="button" aria-label="Smanji količinu"${disableQty ? ' disabled' : ''}>−</button>
  <input type="number"
         class="dp-qo-qty"
         data-row-key="${rowKey}"
         value="0" min="0" step="1"
         ${disableQty ? 'disabled' : ''}>
  <button class="dp-qo-qty-btn dp-qo-qty-plus" type="button" aria-label="Povećaj količinu"${disableQty ? ' disabled' : ''}>+</button>
  <span class="dp-qo-qty-check" aria-hidden="true">✓</span>
</div>`.trim();
    }

    /**
     * One variation's line within the Naziv (attrs+SKU) column — no parent
     * name repeated per variation.
     */
    #variationLabelLineHTML(label, sku) {
        const skuLabel = escHtml(this.#config.i18n?.skuLabel ?? 'Kataloški broj:');
        return `
<div class="dp-qo-variation-line">
  <span class="dp-qo-variation-line__attrs">${label}</span>
  <small class="dp-qo-sku">${skuLabel} ${sku}</small>
</div>`.trim();
    }

    /** One variation's line within the Stanje (stock) column. */
    #variationStockLineHTML(stockClass, stockText) {
        return `
<div class="dp-qo-variation-line">
  <span class="dp-qo-stock ${stockClass}">${stockText}</span>
</div>`.trim();
    }

    /** One variation's line within the Cijena (price) column. */
    #variationPriceLineHTML(priceHtml) {
        return `<div class="dp-qo-variation-line">${priceHtml}</div>`;
    }

    /**
     * One variation's line within the Kol. (qty) column — carries the
     * variation's full dataset (product/variation id, row key, price) on
     * the same `.dp-qo-variation-row` class RowController already resolves
     * via `.closest('.dp-qo-row, .dp-qo-variation-row')`. `data-variation-label`
     * is a debugging byproduct only — VariationChipsController's canonical
     * label source is the `dp:qo:rows-rendered` event payload, not this
     * attribute (see #loadVariationOptions below).
     */
    #variationQtyLineHTML({ rowKey, productId, variationId, price, disableQty, label }) {
        return `
<div class="dp-qo-variation-row dp-qo-variation-line"
     data-product-id="${productId}"
     data-variation-id="${variationId}"
     data-row-key="${rowKey}"
     data-price="${price}"
     data-variation-label="${label}">
  ${this.#qtyControlsHTML(rowKey, disableQty)}
</div>`.trim();
    }

    /** Kick off parallel variation fetches for all variable rows on current page. */
    #loadAllVariations() {
        const rows = this.#tbody.querySelectorAll('[data-type="variable"]');
        rows.forEach(row => this.#loadVariationOptions(row));
    }

    /**
     * Fetch variation details and populate the parent row's four real
     * columns (Naziv/Stanje/Cijena/Kol.) each with one stacked line per
     * variation (no dropdown, no colspan). The parent `.dp-qo-row` and its
     * real `<td>` columns are left in place — only each column's inner list
     * content changes.
     */
    async #loadVariationOptions(row) {
        const productId = row.dataset.productId;
        const labelsEl   = row.querySelector('.dp-qo-variation-labels');
        const stocksEl   = row.querySelector('.dp-qo-variation-stocks');
        const pricesEl   = row.querySelector('.dp-qo-variation-prices');
        const qtysEl     = row.querySelector('.dp-qo-variation-qtys');
        if (!labelsEl || !stocksEl || !pricesEl || !qtysEl) return;

        let variations;
        try {
            const url = `${this.#config.productsUrl}/${productId}/variations`;
            const res = await fetch(url, { headers: { 'X-WP-Nonce': this.#config.wpNonce } });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            variations = await res.json();
        } catch {
            labelsEl.classList.remove('dp-qo-variation-list--loading');
            labelsEl.innerHTML = `<div class="dp-qo-error">${escHtml(this.#config.i18n?.variationLoadError ?? 'Greška pri učitavanju varijacija.')}</div>`;
            return;
        }

        if (!variations.length) {
            row.remove();
            return;
        }

        const stockLabel          = { instock: 'Na stanju', outofstock: 'Nema na stanju', onbackorder: 'Po narudžbi' };
        const labelLines          = [];
        const stockLines          = [];
        const priceLines          = [];
        const qtyLines            = [];
        const variationAttributes = [];

        variations.forEach(v => {
            const rowKey       = `${productId}_${v.id}`;
            const stockClass   = `dp-qo-stock--${escHtml(v.stock_status)}`;
            const stockText    = stockLabel[v.stock_status] ?? v.stock_status;
            const disableQty   = v.stock_status === 'outofstock';
            const escapedLabel = escHtml(v.label);

            labelLines.push(this.#variationLabelLineHTML(escapedLabel, escHtml(v.catalog_number)));
            stockLines.push(this.#variationStockLineHTML(stockClass, stockText));
            priceLines.push(this.#variationPriceLineHTML(v.price_html));
            qtyLines.push(this.#variationQtyLineHTML({
                rowKey, productId: Number(productId), variationId: v.id, price: v.price, disableQty,
                label: escapedLabel,
            }));
            // v.attributes is already-resolved { label, value } pairs from
            // class-product-query.php::get_variation_details() — passed
            // through untouched, no re-parsing at this layer.
            variationAttributes.push({ rowKey, attributes: v.attributes ?? [] });
        });

        labelsEl.classList.remove('dp-qo-variation-list--loading');
        labelsEl.innerHTML = labelLines.join('');
        stocksEl.innerHTML = stockLines.join('');
        pricesEl.innerHTML = priceLines.join('');
        qtysEl.innerHTML   = qtyLines.join('');
        document.dispatchEvent(new CustomEvent('dp:qo:rows-rendered', { detail: { variationAttributes } }));
    }

    #renderPagination() {
        if (!this.#paginationEl) return;
        if (this.#totalPages < 1) { // empty result set — no "Strana 1 / 0"
            this.#paginationEl.innerHTML = '';
            return;
        }

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

    /**
     * React to WBW's own `wpfAjaxSuccess` DOM event — a plain, undocumented-as-typed
     * but explicitly author-documented third-party integration hook (WBW's own source,
     * `frontend.woofilters.js`, ships the exact usage example in an inline comment, and
     * relies on the same event internally for its own Fusion Builder/Bricks/Divi/YITH/
     * WooCommerce-Products-Per-Page compatibility shims). It fires after every completed
     * filter/sort/Clear-All AJAX update — category, brand, price, sort and Clear All all
     * funnel through WBW's single internal `.filtering()` pipeline before it dispatches,
     * and WBW has already called `history.pushState()` with the new URL by that point —
     * so `window.location.search` is guaranteed current when this listener runs.
     *
     * This replaces a previous `history.pushState` monkey-patch: reacting to WBW's own
     * signal is more robust than wrapping a global browser API to detect it indirectly,
     * and avoids any conflict with other code that also wraps `pushState`.
     *
     * `popstate` is kept for direct browser back/forward navigation — WBW's OWN
     * `popstate` handler does a hard `location.reload()` (frontend.woofilters.js,
     * ~line 1113), which would discard Quick Order's entire local state (frozen
     * local-state architecture) on every back/forward; ours must stay independent
     * of WBW's and only re-read the URL, never reload the page.
     *
     * WBW's native Sort By control (view id=2, rendered in `.dp-qo-sort`) goes through
     * this same event/URL-based path — see #applyOrderbyParam().
     */
    #bindWoofIntegration() {
        const params = new URLSearchParams(window.location.search);
        this.#woofFilters = this.#extractWoofFilters(params);
        Object.assign(this.#woofFilters, this.#extractQoOwnedFilters(params));
        this.#reflectQoCheckboxes();
        this.#reflectSearchInput();
        this.#applyOrderbyParam(params);

        const onUrlChange = () => this.#onWoofUrlChange();

        document.addEventListener('wpfAjaxSuccess', onUrlChange);
        window.addEventListener('popstate', onUrlChange);
    }

    #onWoofUrlChange() {
        const params  = new URLSearchParams(window.location.search);
        const next    = this.#extractWoofFilters(params);
        const current = JSON.stringify(this.#woofFilters);
        const orderbyChanged = this.#applyOrderbyParam(params);
        Object.assign(next, this.#extractQoOwnedFilters(params)); // next is always a FRESH object — safe merge, no stale-key risk

        if (JSON.stringify(next) !== current || orderbyChanged) {
            this.#woofFilters = next;
            this.#reflectQoCheckboxes();
            this.#reflectSearchInput();
            this.loadPage(1);
        }

        // After the state update above. Always runs: WBW's DOM (the source of WBW chips) can change
        // without any REST-relevant diff in #woofFilters.
        this.#renderStateRow();
    }

    /**
     * Quick Order search box. The URL's `qo_search` param is the single source
     * of truth (same doctrine as the QO filter checkboxes): this control only
     * ever WRITES the URL, then calls the one URL-to-state resync path
     * (#onWoofUrlChange), which reads it back, resets to page 1 and refetches.
     * Terms shorter than `searchMinChars` stay in the URL/input but are never
     * executed as a search (see #extractQoOwnedFilters).
     */
    #bindSearch() {
        const input = this.#searchInput;
        if (!input) return;

        input.closest('form')?.addEventListener('submit', e => {
            e.preventDefault();
            this.#commitSearch();
        });
        input.addEventListener('input', () => {
            this.#syncSearchClear();
            clearTimeout(this.#searchTimer);
            this.#searchTimer = setTimeout(() => this.#commitSearch(), SEARCH_DEBOUNCE_MS);
        });
        input.addEventListener('keydown', e => {
            if (e.key === 'Escape' && input.value) {
                e.preventDefault();
                input.value = '';
                this.#commitSearch();
            }
        });
        this.#searchClear?.addEventListener('click', () => {
            input.value = '';
            this.#commitSearch();
            input.focus();
        });
        this.#syncSearchClear();
    }

    /** Write the input's trimmed value to `qo_search` (removing it when empty), then resync from the URL. */
    #commitSearch() {
        clearTimeout(this.#searchTimer);
        this.#searchTimer = null;

        const term   = this.#searchInput.value.trim();
        const params = new URLSearchParams(window.location.search);
        this.#syncSearchClear();
        if ((params.get('qo_search') ?? '').trim() === term) return;

        if (term) params.set('qo_search', term);
        else params.delete('qo_search');
        const query = params.toString();
        history.pushState(null, '', window.location.pathname + (query ? `?${query}` : ''));
        this.#onWoofUrlChange();
    }

    /** Show the URL's term in the input (initial load, back/forward) — never while a typed value is still pending. */
    #reflectSearchInput() {
        if (!this.#searchInput || this.#searchTimer !== null) return;
        const fromUrl = new URLSearchParams(window.location.search).get('qo_search') ?? '';
        if (this.#searchInput.value.trim() !== fromUrl.trim()) this.#searchInput.value = fromUrl;
        this.#syncSearchClear();
    }

    #syncSearchClear() {
        if (this.#searchClear && this.#searchInput) this.#searchClear.hidden = this.#searchInput.value === '';
    }

    /** @param {string} key  @param {string} fallback */
    #t(key, fallback) {
        return this.#config.i18n?.[key] ?? fallback;
    }

    /**
     * Search/filter state row + no-results actions. Everything here is a VIEW of
     * state owned elsewhere (qo_* URL params, WBW's own inputs) — it keeps no state
     * of its own that could disagree with the URL:
     *   - popular chip      -> same search path as typing (#commitSearch -> qo_search);
     *   - QO filter chip ×  -> same URL write + #onWoofUrlChange() as unchecking its checkbox;
     *   - WBW filter chips  -> WBW's own native selected-filters node, relocated into the row (WBW
     *                          renders and removes them); fallback chips click WBW's own checkbox;
     *   - clear-all / view-all (no-results) -> #clearAll().
     */
    #bindStateRow() {
        this.#popularRow?.addEventListener('click', e => {
            const chip = e.target.closest?.('[data-qo-popular]');
            if (!chip || !this.#searchInput) return;
            this.#searchInput.value = chip.dataset.qoPopular;
            this.#commitSearch();
        });

        this.#activeRow?.addEventListener('click', e => {
            const btn = e.target.closest?.('[data-qo-remove]');
            if (!btn) return;
            if (btn.dataset.qoRemove === 'qo') {
                this.#removeQoFilter(btn.dataset.qoParam);
            } else {
                const wrapper = [...document.querySelectorAll('.wpfFilterWrapper')]
                    .find(w => w.getAttribute('data-get-attribute') === btn.dataset.wbwAttr);
                const input = [...(wrapper?.querySelectorAll('li[data-term-slug]') ?? [])]
                    .find(li => li.dataset.termSlug === btn.dataset.wbwSlug)?.querySelector('input');
                input?.click();
            }
        });

        this.#tbody?.addEventListener('click', e => {
            if (e.target.closest?.('[data-qo-action]')) this.#clearAll();
        });

        // WBW changes its checkboxes before its AJAX completes — reflect that right away;
        // wpfAjaxSuccess / popstate (-> #onWoofUrlChange) re-render again afterwards.
        document.addEventListener('change', e => {
            if (e.target.matches?.('.wpfFilterWrapper input')) this.#renderStateRow();
        });

        // Keyboard activation of WBW's native chip × (WBW itself only listens for click).
        this.#activeRow?.addEventListener('keydown', e => {
            const del = e.target.closest?.('.wpfSelectedDelete');
            if (del && (e.key === 'Enter' || e.key === ' ')) {
                e.preventDefault();
                del.click();
            }
        });

        // WBW builds its selected-filters node after our script runs and mutates it on every
        // filter change: follow it (adopt once, re-render on any change) instead of polling.
        const filterArea = document.querySelector('.dp-qo-filter-area');
        this.#wbwObserver = new MutationObserver(() => this.#renderStateRow());
        if (filterArea) this.#wbwObserver.observe(filterArea, { childList: true, subtree: true });
    }

    /** Actual FILTERS (not the search term) currently active: QO-owned from state, WBW from its checked inputs. */
    #collectActiveFilters() {
        const chips = [];

        for (const [param, key] of Object.entries(QO_FILTER_STATE)) {
            if (!this.#woofFilters[key]) continue;
            const label = document.querySelector(`.dp-qo-catalog-filter__input[data-qo-filter="${param}"]`)
                ?.closest('label')?.querySelector('.dp-qo-catalog-filter__label')?.textContent.trim() ?? param;
            chips.push({ kind: 'qo', param, label });
        }

        // WBW filters: while WBW's own selected-filters node is adopted into our row, WBW renders
        // (and removes) those chips itself. Only when that node is unavailable do we fall back to
        // deriving chips from WBW's checked inputs — the × then clicks WBW's own checkbox.
        if (!this.#adoptWbwSelected()) {
            const seen = new Set();
            const wbwInputs = document.querySelectorAll('.wpfFilterWrapper:not([data-filter-type="wpfSortBy"]) input:checked');
            for (const input of wbwInputs) {
                const li      = input.closest('li');
                const wrapper = input.closest('.wpfFilterWrapper');
                const label   = (input.closest('.wpfLiLabel')?.querySelector('.wpfFilterTaxNameWrapper')?.textContent ?? li?.textContent ?? '')
                    .replace(/\s+/g, ' ').trim();
                const attr = wrapper?.getAttribute('data-get-attribute') ?? '';
                const slug = li?.dataset.termSlug ?? '';
                if (!label || !slug || seen.has(`${attr}|${slug}`)) continue; // no stable handle => cannot be removed safely
                seen.add(`${attr}|${slug}`);
                chips.push({ kind: 'wbw', attr, slug, label });
            }
        }

        return chips;
    }

    /**
     * WBW's NATIVE selected-filters node (`.wpfSelectedParameters`, created by WBW JS inside its
     * view-3 wrapper — it already carries WBW's own × handlers, delegated on <body>). Adopting it =
     * relocating that node into our "Aktivni filteri" row and tagging it with its view id, which is
     * WBW's documented "external selected filters" placement (data-filter), so WBW keeps updating
     * the very same node. WBW stays authoritative; we only decide where it is shown.
     * @returns {boolean} true while the native node is adopted
     */
    #adoptWbwSelected() {
        if (this.#wbwSelected?.isConnected) return true;
        if (!this.#activeRow) return false;

        const node = document.querySelector('.dp-qo-filter-area .wpfSelectedParameters');
        const view = document.querySelector('.dp-qo-filter-area .wpfMainWrapper')?.dataset.filter;
        if (!node || !view) return false;

        node.dataset.filter = view;
        this.#activeRow.append(node);
        this.#wbwSelected = node;
        this.#wbwObserver?.observe(node, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
        return true;
    }

    #hasWbwNativeActive() {
        return !!this.#wbwSelected?.isConnected && this.#wbwSelected.querySelectorAll('.wpfSelectedParameter').length > 0;
    }

    /** Any actual FILTER (QO-owned, WBW native/derived) — the search term alone never counts. */
    #hasActiveFilters() {
        return this.#collectActiveFilters().length > 0 || this.#hasWbwNativeActive();
    }

    /** Toggle popular-vs-active row and (re)build the active chips. A search term alone never counts as a filter. */
    #renderStateRow() {
        if (!this.#popularRow && !this.#activeRow) return;

        const chips     = this.#collectActiveFilters();
        const hasActive = chips.length > 0 || this.#hasWbwNativeActive();

        // Keyboard/AT access for WBW's native × (a plain div upstream): expose it as a button.
        const removeText = this.#t('removeFilter', 'Ukloni filter: %s');
        this.#wbwSelected?.querySelectorAll('.wpfSelectedParameter').forEach(p => {
            const del = p.querySelector('.wpfSelectedDelete');
            if (!del || del.hasAttribute('role')) return;
            del.setAttribute('role', 'button');
            del.setAttribute('tabindex', '0');
            del.setAttribute('aria-label', removeText.replace('%s', p.querySelector('.wpfSelectedTitle')?.textContent.trim() ?? ''));
        });

        if (this.#popularRow) this.#popularRow.hidden = hasActive || !this.#popularRow.querySelector('[data-qo-popular]');
        if (this.#activeRow)  this.#activeRow.hidden  = !hasActive;
        if (!this.#activeList) return;

        const removeLabel = this.#t('removeFilter', 'Ukloni filter: %s');
        this.#activeList.replaceChildren(...chips.map(chip => {
            const li  = document.createElement('li');
            const el  = document.createElement('span');
            const txt = document.createElement('span');
            const btn = document.createElement('button');
            el.className  = 'dp-qo-filter-chip dp-qo-filter-chip--active';
            txt.textContent = chip.label;
            btn.type      = 'button';
            btn.className = 'dp-qo-filter-chip__remove';
            btn.textContent = '×';
            btn.setAttribute('aria-label', removeLabel.replace('%s', chip.label));
            btn.dataset.qoRemove = chip.kind;
            if (chip.kind === 'qo') {
                btn.dataset.qoParam = chip.param;
            } else {
                btn.dataset.wbwAttr = chip.attr;
                btn.dataset.wbwSlug = chip.slug;
            }
            el.append(txt, btn);
            li.append(el);
            return li;
        }));
    }

    /** Same write-URL-then-resync path as unchecking the filter's checkbox. */
    #removeQoFilter(param) {
        if (!(param in QO_FILTER_STATE)) return;
        const params = new URLSearchParams(window.location.search);
        params.delete(param);
        const query = params.toString();
        history.pushState(null, '', window.location.pathname + (query ? `?${query}` : ''));
        this.#onWoofUrlChange();
    }

    /**
     * Reset search + every QO-owned filter + every WBW filter, in place (never
     * navigates — leaving the page would discard the local quantity state).
     * Sort and local quantities are not touched: the quantity Map lives in
     * QuickOrderState and re-hydrates onto re-rendered rows.
     */
    #clearAll() {
        clearTimeout(this.#searchTimer);
        this.#searchTimer = null;
        if (this.#searchInput) this.#searchInput.value = '';

        const params = new URLSearchParams(window.location.search);
        ['qo_search', ...Object.keys(QO_FILTER_STATE)].forEach(k => params.delete(k));
        const query = params.toString();
        history.pushState(null, '', window.location.pathname + (query ? `?${query}` : ''));
        this.#onWoofUrlChange();

        // WBW's own native Clear (its real AJAX / wpfAjaxSuccess pipeline). Only when a WBW filter is active.
        if (document.querySelector('.wpfFilterWrapper:not([data-filter-type="wpfSortBy"]) input:checked')) {
            const clearBtn = document.querySelector('.wpfClearButton');
            if (clearBtn) {
                clearBtn.click();
            } else {
                document.querySelectorAll('.wpfFilterWrapper:not([data-filter-type="wpfSortBy"]) input:checked')
                    .forEach(input => input.click());
            }
        }
    }

    /**
     * Zero-result markup. Case A (search and/or a filter is active): structured no-results
     * state with reset actions. Case B (nothing active): a neutral message with no filter
     * advice — the user simply has no accessible products (and we never say why).
     */
    #emptyStateHTML() {
        const search     = this.#woofFilters.qoSearch ?? '';
        const hasFilters = this.#hasActiveFilters();

        if (!search && !hasFilters) {
            return `<tr><td colspan="5" class="dp-qo-empty">${escHtml(this.#t('emptyCatalog', 'Nema dostupnih proizvoda.'))}</td></tr>`;
        }

        const intro = search && hasFilters ? this.#t('noResultsSearchFilters', 'Za pojam “%s” i odabrane filtre nije pronađen nijedan proizvod. Pokušajte sljedeće:')
                    : search               ? this.#t('noResultsSearch', 'Za pojam “%s” nije pronađen nijedan proizvod. Pokušajte sljedeće:')
                    :                        this.#t('noResultsFilters', 'Za odabrane filtre nije pronađen nijedan proizvod. Pokušajte sljedeće:');
        const tips = [
            search     ? this.#t('noResultsTipSearch', 'Provjeriti pravopis ili koristiti drugi pojam za pretragu') : null,
            hasFilters ? this.#t('noResultsTipFilters', 'Ukloniti neke filtre kako biste vidjeli više rezultata') : null,
            this.#t('noResultsTipBrowse', 'Pregledati ostale kategorije ili brendove'),
        ].filter(Boolean);

        return `
<tr><td colspan="5" class="dp-qo-no-results">
  <div class="dp-qo-no-results__inner">
    <svg class="dp-qo-no-results__icon" width="96" height="96" viewBox="0 0 96 96" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="42" cy="42" r="30"/><path d="M64 64l22 22"/></svg>
    <h2 class="dp-qo-no-results__title">${escHtml(this.#t('noResultsTitle', 'Nismo pronašli proizvode'))}</h2>
    <p class="dp-qo-no-results__text">${escHtml(intro).replace('%s', () => escHtml(search))}</p>
    <ul class="dp-qo-no-results__tips">${tips.map(t => `<li>${escHtml(t)}</li>`).join('')}</ul>
    <div class="dp-qo-no-results__actions">
      <button type="button" class="button dp-qo-no-results__btn" data-qo-action="clear-all">${escHtml(this.#t('clearAllFilters', 'Očisti sve filtre'))}</button>
      <button type="button" class="button button--outline dp-qo-no-results__btn" data-qo-action="view-all">${escHtml(this.#t('viewAllProducts', 'Pogledaj sve proizvode'))}</button>
    </div>
  </div>
</td></tr>`.trim();
    }

    /** Reflect current #woofFilters QO booleans onto the checkbox DOM elements. */
    #reflectQoCheckboxes() {
        document.querySelectorAll('.dp-qo-catalog-filter__input').forEach(cb => {
            const key = { qo_new: 'qoNew', qo_best_seller: 'qoBestSeller', qo_already_ordered: 'qoAlreadyOrdered' }[cb.dataset.qoFilter];
            cb.checked = !!this.#woofFilters[key];
        });
    }

    /** Bind checkbox change + Clear All — the one genuinely custom event
     * path in this file: WBW has no integration point for controls it
     * doesn't render. Both handlers below only ever WRITE the URL, then
     * call the same #onWoofUrlChange() used for WBW-originated changes —
     * they never touch #woofFilters directly, so there is exactly one
     * URL-to-state resync path in the whole file, not two. */
    #bindQoOwnedFilters() {
        document.querySelectorAll('.dp-qo-catalog-filter__input').forEach(cb => {
            cb.addEventListener('change', () => {
                const params = new URLSearchParams(window.location.search);
                if (cb.checked) params.set(cb.dataset.qoFilter, '1');
                else params.delete(cb.dataset.qoFilter);
                history.pushState(null, '', `${window.location.pathname}?${params.toString()}`);
                this.#onWoofUrlChange();
            });
        });

        document.querySelector('.dp-qo-catalog-filters__clear')?.addEventListener('click', () => {
            const params = new URLSearchParams(window.location.search);
            ['qo_new', 'qo_best_seller', 'qo_already_ordered'].forEach(k => params.delete(k));
            history.pushState(null, '', `${window.location.pathname}?${params.toString()}`);
            this.#onWoofUrlChange();
            // Real click on WBW's own rendered Clear All (if View 3 renders
            // one) — reuses WBW's actual user-facing control and its real
            // AJAX/wpfAjaxSuccess pipeline, never a private WBW method.
            document.querySelector('.wpfClearButton')?.click();
        });
    }

    /**
     * Collapse/expand toggle for the Quick Order-owned filter fieldset —
     * visually matches WBW's own +/- collapsible filter widgets (same
     * affordance and click behavior), but is a fully independent
     * implementation: no WBW DOM, classes, JS, or collapse state is read or
     * reused. Scoped to this fieldset's own `.dp-qo-catalog-filters__content`
     * only — never touches WBW's own widgets.
     */
    #bindFilterToggle() {
        const toggle   = document.querySelector('.dp-qo-catalog-filters__toggle');
        const fieldset = document.querySelector('.dp-qo-catalog-filters');
        const icon     = toggle?.querySelector('.dp-qo-catalog-filters__toggle-icon');
        if (!toggle || !fieldset) return;

        toggle.addEventListener('click', () => {
            const wasExpanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!wasExpanded));
            fieldset.classList.toggle('is-collapsed', wasExpanded);
            if (icon) icon.textContent = wasExpanded ? '+' : '−';
        });
    }

    /**
     * Reads WBW's native Sort By selection from the URL's public `orderby`
     * query parameter (WooCommerce's own convention — WBW's Sort By widget,
     * view id=2, writes this same param via history.pushState on change) and
     * translates it into the existing #orderBy/#orderDir fields. No WBW
     * DOM/JS API is touched — this only reads a stable, public URL param.
     *
     * WBW view id=2 is configured (WBW admin → Show All Filters → Sort by
     * filter → Filters tab → Sort options) with exactly four enabled values:
     * `title`, `title-desc`, `price`, `price-desc`. Any other value (e.g. an
     * unconfigured `popularity`/`rand`/`sku` or no param at all) is ignored,
     * leaving the current #orderBy/#orderDir unchanged.
     *
     * @param {URLSearchParams} params
     * @returns {boolean} true if #orderBy/#orderDir changed
     */
    #applyOrderbyParam(params) {
        const raw = params.get('orderby');
        if (!raw) return false;

        const isDesc = raw.endsWith('-desc');
        const field  = isDesc ? raw.slice(0, -('-desc'.length)) : raw;
        if (field !== 'title' && field !== 'price') return false;

        const orderDir = isDesc ? 'desc' : 'asc';
        if (field === this.#orderBy && orderDir === this.#orderDir) return false;

        this.#orderBy  = field;
        this.#orderDir = orderDir;
        return true;
    }

    /**
     * Delimiter used to split a multi-value WBW filter param, sourced from the
     * SAME DOM contract WBW's own frontend reads — `.wpfFilterWrapper[data-get-attribute]`
     * and its `data-query-logic` attribute — rather than a hardcoded `|` or `,`.
     * Mirrors WBW's own (private, non-exported) `getDelimiterForFilter()`
     * (`frontend.woofilters.js` ~line 536) 1:1: `,` when the filter instance can't
     * be found in the DOM, `,` for AND-logic, `|` for OR-logic (WBW's own default).
     * That function itself isn't exposed for reuse, but the DOM attributes it
     * reads are public rendered markup — reading them here (instead of guessing
     * a fixed delimiter) keeps this parser correct regardless of how a given
     * filter instance is configured in WBW admin.
     * @param {string} paramKey
     * @returns {string}
     */
    #delimiterForParam(paramKey) {
        const el = document.querySelector(`.wpfFilterWrapper[data-get-attribute="${paramKey}"]`);
        if (!el) return ',';
        const logic = el.getAttribute('data-query-logic') || 'or';
        return logic === 'and' ? ',' : '|';
    }

    /**
     * Extract Quick Order filter params from a WOOF-updated URL.
     *
     * ONE consistent strategy for every taxonomy-based WBW filter (category, brand,
     * product attributes) — none of their param NAMES are matched by a guessed
     * prefix. WBW admin lets the base name for any filter instance be renamed to
     * anything (e.g. on this install the category filter param is
     * `wpf_filter_cat_list_1s` while the brand filter param is the unrelated
     * `product_brand_list`; confirmed live, not a fixed convention). The only
     * stable, native source of truth for "which URL param belongs to which
     * taxonomy" is the `data-taxonomy` / `data-get-attribute` pair WBW itself
     * renders on every `.wpfFilterWrapper` via its own shared
     * `setCommonFitlerDataAttr()` helper (`views/woofilters.php` — category, brand,
     * AND attribute widgets all go through this same helper, so all three carry
     * identical, reliable metadata). Attribute widgets set `data-taxonomy` to the
     * full `pa_*` taxonomy name (`generateAttributeFilterHtml()`,
     * `wc_attribute_taxonomy_name_by_id()`), which is what routes their values into
     * `result.attributes` below — same loop, same delimiter lookup, no separate
     * prefix-matching branch. Reading this DOM contract directly — instead of
     * pattern-matching param names — works regardless of how an admin has
     * named/reconfigured any given filter instance.
     *
     * @param {URLSearchParams} params
     * @returns {{ price_min?: number, price_max?: number, stock_status?: string, category?: string[], brand?: string[], attributes?: object }}
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

        for (const wrapper of document.querySelectorAll('.wpfFilterWrapper[data-taxonomy][data-get-attribute]')) {
            const taxonomy = wrapper.getAttribute('data-taxonomy');
            const paramKey = wrapper.getAttribute('data-get-attribute');
            const rawVal   = paramKey ? params.get(paramKey) : null;
            if (!rawVal) continue;

            const values = rawVal.split(this.#delimiterForParam(paramKey)).filter(Boolean);
            if (!values.length) continue;

            if (taxonomy === 'product_cat') {
                result.category = [...(result.category ?? []), ...values];
            } else if (taxonomy === 'product_brand' || taxonomy === 'pwb-brand') {
                result.brand = [...(result.brand ?? []), ...values];
            } else if (taxonomy.startsWith('pa_')) {
                const attrName = taxonomy.slice('pa_'.length);
                result.attributes = result.attributes ?? {};
                result.attributes[attrName] = [...(result.attributes[attrName] ?? []), ...values];
            }
        }

        return result;
    }

    /**
     * Extract Quick Order-owned filter params — never WBW-managed, no
     * DOM-metadata lookup needed (Quick Order owns both the param name and
     * the control that writes it, unlike the WBW-driven extraction above).
     * @param {URLSearchParams} params
     * @returns {{ qoSearch?: string, qoNew?: boolean, qoBestSeller?: boolean, qoAlreadyOrdered?: boolean }}
     */
    #extractQoOwnedFilters(params) {
        const result = {};
        // Only an EXECUTABLE term (>= min chars) enters state, so typing a single
        // character neither triggers a refetch nor changes the result set.
        const search = (params.get('qo_search') ?? '').trim();
        if (search.length >= (this.#config.searchMinChars ?? 2)) result.qoSearch = search;
        if (params.has('qo_new'))            result.qoNew = true;
        if (params.has('qo_best_seller'))     result.qoBestSeller = true;
        if (params.has('qo_already_ordered')) result.qoAlreadyOrdered = true;
        return result;
    }
}

/** Escape HTML for safe insertion into innerHTML. */
function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
}

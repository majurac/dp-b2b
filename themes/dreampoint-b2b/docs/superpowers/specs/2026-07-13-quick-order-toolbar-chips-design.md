# Quick Order — Selected Variations Chips, WBW-Native Sort, Qty Checkmark

Date: 2026-07-13
Status: Implemented, locally validated. Superseded §4 below — sort is now
WBW's own native "Sort By" control (view id=2), not a Quick Order-owned
`<select>`. See "Revision — 2026-07-13 (WBW-native sort)" note in §4 and §3.

## Goal

Three UX additions to the Quick Order interface (`wp-content/plugins/dp-b2b-quick-order/`):

1. A chip showing each selected variation (qty > 0), merged into the existing WBW
   Product Filter "Active Filters" toolbar, with a remove (×) control.
2. Sort is owned entirely by WBW's native "Sort By" filter view (id=2,
   rendered via `[wpf-filters id=2]` in `.dp-qo-sort`) — Quick Order adapts
   WBW's public `?orderby=` URL parameter into its existing REST
   `qo_orderby`/`qo_order` params. No Quick Order-owned sort UI exists.
3. A transient checkmark next to the qty controls confirming a successful
   quantity change.

This is a UI/UX layer only. `QuickOrderState`'s business shape, REST endpoints,
footer calculation formulas, bulk add-to-cart, pagination, filtering, and stock
validation are unchanged. See `docs/frozen/quick-order-local-state-architecture.md`
for the frozen local-state architecture this build must not alter.

## Verified runtime facts (browser-checked, not assumed)

- The Quick Order page currently renders `wc_get_attribute_taxonomies()` as empty
  locally (no global `pa_*` taxonomies registered on this dev DB) — the
  `.prod_atributes` loop in `templates/quick-order.php` prints "No product
  attributes found." This is pre-existing, out of scope, and unaffected by this
  work.
- With WBW's "Display selected parameters of filters" option enabled, applying a
  filter renders (verified via a live HTTP fetch of the rendered page, not just
  DOM inspection):
  ```html
  <div class="selected-prod_atributes">
    <div class="wpfSelectedParameters" data-filter="1">
      <div class="wpfSelectedParameter" data-filter-type="wpfBrand" ...>
        <div class="wpfSelectedDelete">x</div>
        <div class="wpfSelectedTitle"><span class="wpfFilterTaxNameWrapper">[DEV] AlphaGrade</span></div>
      </div>
      <div class="wpfSelectedParametersClear">Clear All</div>
    </div>
    ...
  </div>
  ```
- `.wpfSelectedParameters`'s entire inner content is replaced by WBW's own JS on
  every filter interaction (confirmed: empty + `wpfHidden` class with no filters,
  fully repopulated after applying one). Anything placed as a child of
  `.wpfSelectedParameters` would be destroyed on the next filter change.
- No native WC sort dropdown (`woocommerce_catalog_ordering()`) exists on the
  Quick Order page — it exists only on the regular `/shop/` archive via
  `header-shop-archive.php`'s `woocommerce_before_shop_loop` hook. At the time
  of this investigation Quick Order's sort mechanism was independent
  (clickable `data-sort` table headers) — superseded later the same day by
  WBW-native sort; see §4's revision note.

## Architecture decisions

### 1. `.selected-prod_atributes` is a black box

Quick Order must never read, depend on, or write into `.selected-prod_atributes`
or any of its descendants (`.wpfSelectedParameters`, `.wpfSelectedParameter`,
`.wpfSelectedParametersClear`, etc.). WBW owns that subtree entirely and
re-renders it independently of Quick Order.

### 2. New `.dp-qo-toolbar` wrapper — two logical groups

`templates/quick-order.php` gets a new wrapping element with exactly two flex
children — a LEFT group and a RIGHT group:

```
.dp-qo-toolbar                       (flex row, flex-wrap: wrap, justify-content: space-between, align-items: flex-start)
├── .dp-qo-toolbar__filters          (LEFT GROUP — flex row, flex-wrap: wrap, align-items: center)
│   ├── .selected-prod_atributes     (existing markup, byte-for-byte unchanged — WBW's black box)
│   └── .dp-qo-selected-variations   (new — Quick Order's own chips, sibling, never nested inside WBW markup)
└── .dp-qo-sort                      (RIGHT GROUP — new sort dropdown, flex-shrink: 0)
```

The LEFT group's own `flex-wrap: wrap` makes `.selected-prod_atributes` and
`.dp-qo-selected-variations` behave as **one continuous chip row** — WBW's
chips and Quick Order's chips wrap together as a unit, independently of the
sort dropdown. `.dp-qo-selected-variations` sits immediately after
`.selected-prod_atributes` in source order, so it visually continues right
after the last WBW chip.

The RIGHT group (`.dp-qo-sort`) never participates in the left group's
wrapping — `justify-content: space-between` on `.dp-qo-toolbar` keeps it
pinned to the right whenever the left group fits on one line. Only when the
left group's own wrapped content grows tall enough to need the full row width
does `.dp-qo-toolbar`'s own `flex-wrap: wrap` drop the sort dropdown to a new
line below — an acceptable degradation on narrow viewports, not a violation of
"remains aligned right whenever possible."

Quick Order's own chips are never injected inside `.selected-prod_atributes`
or `.wpfSelectedParameters` — this is what makes them immune to WBW's
re-renders.

### 3. Chip data stays out of `QuickOrderState` — resolved, structured attributes

`QuickOrderState` remains business-only: `productId`, `variationId`, `quantity`,
`unitPrice` per row. The variation's display data is presentation data
produced by the existing renderer and is not duplicated into state.

**Revision — 2026-07-13 (structured attributes):** the chip data source was
changed from a flat, already-formatted `label` string (`"Boja: Crna / Veličina:
XL"`, split on `" / "` by `VariationChipsController` to re-insert a `" • "`
separator) to a structured, already-resolved list —
`{ label: string, value: string }[]` per variation — produced once at the
correct layer and never re-parsed downstream:

- `class-product-query.php`'s `get_variation_details()` builds this list
  (`$attributes`) per variation: for each `WC_Product_Variation::get_attributes()`
  entry, `wc_attribute_label()` resolves the human-readable attribute name and
  `get_term_by('slug', ...)` (falling back to the raw stored value for
  non-taxonomy/custom attributes) resolves the human-readable value — in the
  variation's own deterministic iteration order. The REST
  `/products/{id}/variations` response now includes this `attributes` field
  alongside the existing flat `label` (the flat string is still used, unchanged,
  by the Naziv-column display — `#variationLabelLineHTML()` — so that rendering
  path is untouched).
- Verified against the local catalog's two existing custom-attribute shapes —
  single attribute (`"Pack Size"` → `[{label:"Pack Size", value:"1pc"}]`) and
  multiple attributes (`"Size"` + `"Color"` →
  `[{label:"Size",value:"S"},{label:"Color",value:"Black"}]`) — both resolve
  correctly. No global `pa_*` taxonomy exists in this local dev catalog
  (`wc_get_attribute_taxonomies()` is empty here — see §"stray attribute
  message" below), so the taxonomy-attribute path (`get_term_by()` returning a
  real `WP_Term`) could not be exercised live; the code path is identical to
  the already-working custom-attribute path and relies on the same,
  already-proven `get_term_by()`/`wc_attribute_label()` WooCommerce APIs.
- `ProductList` remains the sole producer and canonical synchronization path:
  it notifies `VariationChipsController` via the existing `dp:qo:rows-rendered`
  CustomEvent (both dispatch sites — initial skeleton render sends
  `detail.variationAttributes: []`; the post-`#loadVariationOptions()`
  dispatch sends the real `{rowKey, attributes}[]` pairs). No DOM-scanning
  fallback exists or is needed — `VariationChipsController` is constructed
  (and its listener bound) before the first render fires.
  `data-variation-label` (the flat string) remains on `.dp-qo-variation-row`
  as a debugging byproduct only.
- `VariationChipsController` performs **zero** attribute inference: it never
  parses slugs, product names, or SKUs, and never re-derives label/value
  text — it only joins already-resolved pairs for display:
  `attributes.map(a => \`${a.label}: ${a.value}\`).join(' • ')`. The old
  `formatVariationLabel()` string-replace hack (`" / " → " • "`) no longer
  exists — there is no flat string to transform, so there is no separator
  ambiguity if a resolved value ever legitimately contains `" / "`.
- `QuickOrderState` gains exactly one new getter: `getActiveRowKeys(): string[]`
  (row keys with quantity > 0) — pure business data, no presentation leakage.
  `setQuantity()`'s signature and stored shape are unchanged.

### 4. Sort — WBW-native, not a Quick Order-owned control

**Revision — 2026-07-13 (WBW-native sort):** this section originally
described a Quick Order-owned `<select>` fed by `dp_qo_get_sort_options()`.
That implementation was replaced before release with WBW's own native
"Sort By" filter, per explicit follow-up instruction. `dp_qo_get_sort_options()`
and `#bindSortDropdown()` no longer exist. This section describes the
as-shipped architecture.

**Investigation findings** (WBW admin — Show All Filters, and live browser
testing against `http://localhost:8080/dp-b2b/quick-order/`):

- WBW supports multiple filter "views" on one page. View id=1 ("Products
  filter") is the existing sidebar; a second view, id=2 ("Sort by filter"),
  was created containing only the "Sort By" widget, rendered via
  `[wpf-filters id=2]` inside `.dp-qo-sort`.
- WBW's Sort By widget ships with only "Default"/"Popularity" enabled by
  default — **not** a plugin limitation, an admin configuration gap. The
  widget's own "Sort options" checklist (WBW admin → view 2 → Filters tab →
  Sort by → Sort options) exposes `title`, `title-desc`, `price`,
  `price-desc` (plus `date`, `date-asc`, `rand`, `sku`, `sku-desc`,
  unused here). These four were enabled with Croatian labels ("Naziv
  (A-Ž)", "Naziv (Ž-A)", "Cijena: niža prema višoj", "Cijena: viša prema
  nižoj") and `default`/`popularity` were disabled — a WBW admin settings
  change, not a plugin file edit.
- On change, WBW's Sort By `<select id="wpfSortProducts">` writes the
  **standard WooCommerce `orderby` URL query parameter** (`?orderby=price`,
  `?orderby=price-desc`, `?orderby=title`, `?orderby=title-desc`) via
  `history.pushState` — confirmed via a `window` marker surviving the
  interaction (no full page reload). This is the same public convention
  `woocommerce_catalog_ordering()` itself uses; it is a stable, documented
  WooCommerce URL parameter, not a WBW-internal API.
- View 1 (sidebar filters) and view 2 (sort) share the same URL query
  string namespace and coexist without conflict — applying a category
  filter preserves `orderby`, and WBW's "Clear All" (view 1's own, scoped to
  its own filter chips) does **not** clear `orderby`. Both confirmed live.
- Browser back/forward: the URL's `orderby` value correctly reverts via
  `popstate` (confirmed), and Quick Order's own re-fetch (below) correctly
  follows it. WBW's own `<select>` visual state does **not** resync itself
  on back/forward (confirmed: after `history.back()`, the dropdown still
  visually showed the previous selection even though the URL and Quick
  Order's product list were both correct) — an observed WBW-side cosmetic
  limitation, not fixed here (would require patching WBW's own JS, which is
  out of bounds).

**Integration** — `product-list.js`'s existing `#bindWoofIntegration()` /
`#onWoofUrlChange()` (already intercepting `pushState`/`popstate` for WOOF
sidebar filters) gained one addition, `#applyOrderbyParam(params)`:

```js
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
```

Called once at construction (so a cold page load with `?orderby=...` already
in the URL sorts correctly on the very first fetch) and again inside
`#onWoofUrlChange()` (so a live Sort By change — or a sidebar filter change
that happens to coexist with an existing `orderby` — triggers `loadPage(1)`
with the correct `qo_orderby`/`qo_order`). No new visible UI, no duplicated
WBW state (the URL is read, not cached), no WBW plugin files touched.

### 5. Chip visual style

`.dp-qo-chip` is a new, self-contained CSS component in
`assets/dist/quick-order.css` (hand-written, no build step for CSS in this
plugin). It visually approximates the verified WBW chip look (light gray
background, rounded, compact padding, inline × remove control) but has zero
dependency on WBW's CSS classes or HTML structure, so it keeps working
unmodified if WBW is updated, reconfigured, or removed.

## Feature 1 — Selected Variations chips

- New file `assets/src/variation-chips.js` exporting `VariationChipsController`.
- Constructor: `(state)` — queries `.dp-qo-selected-variations`, binds a
  delegated click listener for `.dp-qo-chip__remove`, and binds the
  `dp:qo:rows-rendered` listener described in §3 (attribute cache sync,
  keyed `rowKey -> {label,value}[]`).
- `render()`: for each `state.getActiveRowKeys()` with a cached, non-empty
  attribute list, render a chip (text built by joining
  `${label}: ${value}` pairs with `' • '` — see §3), plus a × button carrying
  `data-row-key`. Toggle the container's `hidden` attribute based on whether
  any chip was rendered. Rows without cached attributes (simple products —
  they never appear in `ProductList`'s notifications) are skipped, so chips
  are variation-only as required.
- **Order**: `QuickOrderState.getActiveRowKeys()` returns row keys in
  deterministic insertion order (the backing `#rows` field is a JS `Map`,
  which iterates in insertion order by specification — `[...this.#rows.keys()]`
  is stable, not sorted or hashed). `render()` iterates this order directly,
  so chips appear in the order the user selected each variation and do not
  reorder themselves as quantities are edited up or down. A row that is
  removed (qty → 0) and later re-selected is treated as a new insertion and
  appends at the end — expected `Map` semantics, not a defect.
- Remove click: `state.setQuantity(rowKey, 0)` → `rowCtrl.hydrateAll()` (syncs
  the real qty input back to 0) → `footer.render()` → `this.render()`. Behaves
  identically to manually decrementing to zero.
- Wiring in `quick-order.js`: `chips` is constructed with a `let rowCtrl`
  forward reference (assigned immediately after) so the remove-click callback
  can call `hydrateAll()` without a circular constructor dependency. `chips` is
  also `.render()`-ed after every `RowController` qty change and after a
  successful cart submit (`state.clearKeys(addedKeys)` already clears
  submitted rows from state — chips must reflect that).

## Feature 2 — Sort (WBW-native)

Covered above (§4, "Revision — 2026-07-13"). Files:
`assets/src/product-list.js` (`#applyOrderbyParam`), WBW admin configuration
(view id=2's "Sort options"). No Quick Order template/CSS changes remain for
sort beyond the `.dp-qo-sort` layout wrapper (§2).

## Feature 3 — Quantity change checkmark

Unchanged from the earlier agreed design:

- `product-list.js`'s shared `#qtyControlsHTML()` gains a
  `<span class="dp-qo-qty-check" aria-hidden="true">✓</span>`, space always
  reserved (fixed width), opacity/transform-only transition — no layout shift.
- `RowController` gains a `#checkTimers` `WeakMap<HTMLElement, number>` so a
  flash-in-progress restarts its own timeout instead of stacking. Triggered only
  from `#onQtyInput` (real user interaction), never from `hydrateAll()`. Hidden
  immediately, no animation, if quantity returns to 0.
- **Explicit rapid-change behaviour**: on each qty change, any existing timeout
  for that row's checkmark element is cleared via the `WeakMap` lookup *before*
  a new one is scheduled. This guarantees that repeated rapid changes (e.g.
  clicking `+` five times quickly) restart the same single fade-out timer
  rather than stacking concurrent animations or leaving orphaned timers — at
  most one active timeout per checkmark element at any time. The `WeakMap`
  keying (by DOM element, not row key) also means a checkmark's timer is
  automatically released once its row element is removed from the DOM (e.g.
  page/sort change), with no manual cleanup required.

## Consistency with the frozen local-state architecture

Checked against `docs/frozen/quick-order-local-state-architecture.md`:

- §2 (Local State Model), §3 (Footer), §4 (Submit Flow), §5 (Leaving Quick
  Order), §6 (Variation Rendering) — no contradiction. `getActiveRowKeys()` is
  a purely additive getter (same pattern as the existing `getItemCount()` /
  `getRowCount()` / `getSubtotal()`), and the stored row shape
  (`quantity, unitPrice, productId, variationId`) is untouched, per §3
  ("Chip label stays out of `QuickOrderState`") above.
- §8's disposition table states `ProductList (JS row rendering, pagination,
  sort, WOOF) | Modified — row HTML changes (§6, ...), pagination/sort/WOOF
  logic unchanged`. This build **does** change sort logic (click-to-sort
  headers → WBW-native Sort By, adapted via a public URL param) — a real
  deviation from that table entry. This is covered by the frozen doc's own
  escape hatch ("DO NOT REFACTOR WITHOUT EXPLICIT APPROVAL" — approval was
  given explicitly, in-session, for this specific change; §Architecture
  decision 4 above records it, including its own later revision from a
  Quick Order-owned dropdown to WBW-native). The frozen doc received two
  small §8 addenda (see its own revision notes) — not a rewrite, just notes
  that sort is now URL-param-driven while remaining
  client-side/REST-param-identical.

No other section of this spec touches anything the frozen doc marks as
closed.

- `templates/quick-order.php` — `.dp-qo-toolbar` wrapper with
  `.dp-qo-toolbar__filters` (left group: `.selected-prod_atributes` +
  `.dp-qo-selected-variations`) and `.dp-qo-sort` (right group, now rendering
  `[wpf-filters id=2]` — WBW's native Sort By view); removal of
  `data-sort`/sort-arrow markup from `<th>` Naziv/Cijena; removal of the
  "No product attributes found." fallback branch (renders nothing when no
  global `pa_*` taxonomies exist, instead of a user-facing stray message).
- `inc/class-frontend.php` — `dp_qo_get_sort_options()` added then removed
  (superseded by WBW-native sort — see §4 revision note).
- `inc/class-product-query.php` — `get_variation_details()` gains a
  structured `attributes: {label,value}[]` field per variation (§3), built
  from the same already-proven `wc_attribute_label()`/`get_term_by()` calls;
  the flat `label` field is unchanged and still drives the Naziv-column
  display.
- `assets/src/product-list.js` — `data-variation-label` attribute on variation
  rows (byproduct only, see §3); `dp:qo:rows-rendered` CustomEvent now carries
  `detail.variationAttributes: {rowKey, attributes}[]`; checkmark span in
  `#qtyControlsHTML()`; `#bindSortDropdown()` added then removed — replaced by
  `#applyOrderbyParam()` inside the existing `#bindWoofIntegration()` /
  `#onWoofUrlChange()` (§4).
- `assets/src/quick-order-state.js` — new `getActiveRowKeys()` getter only.
- `assets/src/row-controller.js` — `chips` dependency, `#checkTimers` flash logic.
- `assets/src/variation-chips.js` — new file, `VariationChipsController`
  (structured-attribute chip rendering, §3).
- `assets/src/quick-order.js` — wiring.
- `assets/dist/quick-order.css` — `.dp-qo-toolbar`, `.dp-qo-toolbar__filters`,
  `.dp-qo-selected-variations`, `.dp-qo-chip` (+ `__remove`), `.dp-qo-sort`
  (layout wrapper only — WBW owns view 2's internal markup/styling),
  `.dp-qo-qty-check`. `.dp-qo-sort-select` (the old custom `<select>`'s
  styling) was added then removed.
- `assets/dist/quick-order.js` — esbuild rebuild.
- WBW admin (Show All Filters → "Sort by filter", id=2 → Filters tab) — Sort
  options reconfigured: `title`, `title-desc`, `price`, `price-desc` enabled
  with Croatian labels; `default`/`popularity` disabled. An admin settings
  change, not a plugin file edit.
- `docs/frozen/quick-order-local-state-architecture.md` — two small §8
  revision notes (see "Consistency with the frozen local-state architecture"
  above). Not a rewrite.

## Explicitly out of scope / unchanged

- `QuickOrderState` business logic, REST endpoints, footer subtotal/count
  formulas, bulk add-to-cart, pagination, filtering, stock validation.
- `.selected-prod_atributes` and all WBW-owned markup/behavior — untouched,
  treated as a black box.
- The `prod_atributes` global-attribute loop (currently a no-op locally since no
  `pa_*` taxonomies exist) — left as-is.

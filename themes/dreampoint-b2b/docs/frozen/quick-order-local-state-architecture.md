# Quick Order — Local State Workspace Architecture

## Status
FROZEN — DO NOT REFACTOR WITHOUT EXPLICIT APPROVAL

Architecture status:
STABLE / PRODUCTION-VALIDATED (2026-07-10) — implementation plan executed,
verified via Playwright against local XAMPP, deployed to staging
(`dreampoint.b2b.uncledev.cloud`) and re-verified end-to-end there, including
>50-row batch chunking, mini-cart/Toastify fragment refresh, cart icon
counter, and cache-busting. See `docs/active/status.md` for the full staging
regression report reference.

Canonical reference for the Quick Order local-state ordering workspace.
Supersedes `docs/frozen/quick-order-sync-architecture.md` (real-time
debounce-to-cart model) — see that document's Supersession Note for why.

**Revision — 2026-07-10 (variation rendering reconciliation):** §6 originally
described variable-product variations as being promoted to sibling top-level
rows. That model was superseded by a grouped layout (one top-level
`.dp-qo-row` per parent, variations nested inside it) before production use —
§6 below has been rewritten to describe the grouped model as implemented and
staging-validated. This is a documentation correction, not an architecture
change: the local-state model, footer computation, and submit flow in §2–§5
were not affected by the grouping change and remain as written.

**Revision — 2026-07-10 (real-column correction):** the grouped layout's
first implementation collapsed a variable row into a single
`<td colspan="5">` housing a flex-simulated `.dp-qo-row__product` /
`.dp-qo-row__variations` layout — this broke native table column alignment
with the rest of the table and was corrected before production use. §6 below
now describes the corrected, staging-validated model: a variable row keeps
the table's real `<td>` columns (no `colspan`), with per-variation content
stacked vertically inside the existing Naziv/Stanje/Cijena/Kol. cells.
`.dp-qo-row__product` and `.dp-qo-row__variations` no longer exist. As with
the prior revision, this is a documentation/markup correction only — §2–§5
are unaffected.

---

## 1. System Overview

Quick Order is an independent ordering workspace. Changing a quantity updates
only local, in-page JS state — it never touches the WooCommerce cart, never
calls a REST endpoint, and never triggers cart fragment refreshes. The
WooCommerce cart is written to exactly once per user action: when the user
clicks **"Dodaj u košaricu"**, at which point the entire local state is pushed
to the cart in one operation, then discarded.

**Architectural goals:**
- Zero network traffic for quantity changes — server is only contacted on
  explicit submit
- WC cart/session remain untouched until submit — no partial or optimistic
  cart writes
- Reuse the existing validated PHP add-to-cart path (`class-cart-sync.php`)
  as the bulk-write mechanism at submit time — do not reimplement stock/
  purchasability validation
- No persistence layer — local state is memory-only and dies with the page

**Authority model:**
- Frontend (while browsing Quick Order): full authority over displayed
  quantities, row count, subtotal — all computed from local state, not from WC
- Server/WC (only at submit time): variation validity, purchasability, stock,
  `add_to_cart()` acceptance — identical authority model to the superseded
  architecture, just invoked once instead of per keystroke

---

## 2. Local State Model

A single in-memory state object (keyed by row key, same format as the
superseded architecture: `"${productId}_${variationId}"`, `variationId=0` for
simple products) holds `{ quantity, unitPrice, productId, variationId }` per
row with `quantity > 0`.

**On quantity change (input, +/- buttons):**
1. Update local state (`Map.set(rowKey, {...})` or `Map.delete(rowKey)` if
   quantity becomes 0)
2. Re-render the row's own quantity control (no full table re-render)
3. Recompute and re-render the footer (item count, row count, subtotal)

**No debounce, no queue, no network call.** There is nothing to protect
against races or stale responses because nothing is sent until submit — the
entire concurrency-protection apparatus from the superseded architecture
(`SyncQueue`, `AbortController`, monotonic token) has no role here and must
not be carried over.

**No persistence.** Refresh, navigation, or tab close discards state
unconditionally. No `localStorage`, no `sessionStorage`, no draft-order
mechanism. This is an explicit product decision (see brief §4), not a
follow-up item — do not add restore-on-reload behavior without new approval.

**Pagination interaction:** local state must survive pagination within the
same page load (the state Map is not scoped to the currently-rendered page),
since a user can set quantities on page 1, browse to page 2, and still expect
"Dodaj u košaricu" to submit both. Row quantity inputs re-hydrate from local
state when a page is re-rendered (read `getQuantity(rowKey)` when building row
HTML), rather than always starting at 0 as the superseded architecture did.

---

## 3. Footer

Footer content is derived entirely from local state — never from a WC cart
response.

**Item count ("N artikala"):** `sum(quantity)` across all rows in local state.

**Row count ("N varijacija"):** `count of rows with quantity > 0` in local
state. A simple product is one row. Each distinct variation is its own row.

**Subtotal ("Ukupno (bez PDV-a)"):** `sum(quantity × unitPrice)` across all
rows in local state. This is a product-only subtotal — no VAT, shipping,
coupons, or fees. `unitPrice` is captured from the product/variation payload
at the time the row is rendered (same price source the row displays), not
fetched separately.

**Do not** wire this footer to `data.totals` from a cart-sync response, and do
not call `calculate_totals()` for display purposes — that pattern belongs to
the superseded architecture and conflated "what's in Quick Order" with "what's
in the WC cart," which are now deliberately different things until submit.

---

## 4. Add to Cart (Submit) Flow

Triggered only by the "Dodaj u košaricu" button.

1. Serialize local state into the same item shape the reused endpoint expects:
   `{ product_id, variation_id, quantity }` (the `variation` attribute-map
   field from the old payload is not needed — Quick Order always resolves a
   concrete `variation_id`, never an attribute combination that WC must
   resolve).
2. **Chunk into batches of `CART_SYNC_MAX_BATCH` (50) items.** Local state can
   exceed 50 rows (multi-page selection); the reused endpoint's batch cap is a
   payload-size guard, not a Quick Order limit, so the frontend must split one
   logical submit into multiple sequential requests to the same endpoint and
   await each before sending the next — not in parallel, to keep server-side
   cart mutation deterministic.
3. For each chunk, `POST` to the existing `/cart/sync` REST route
   (`class-rest-api.php` → `class-cart-sync.php::sync()`) — this PHP path is
   reused unchanged; it already does exactly "add/update/remove WC cart items
   with stock and purchasability validation."
4. After all chunks complete: trigger the existing WC ecosystem bridge once
   (the `wc_fragment_refresh` / `added_to_cart` jQuery event dance already in
   `quick-order.js` — reuse it, just call it once at the end instead of after
   every debounce dispatch).
5. Clear local state, re-render footer (now empty), re-render visible rows
   (quantities reset to 0).
6. **Partial failure handling:** if any chunk reports `out_of_stock` or
   `failed` items, those rows are NOT cleared from local state (so the user
   can see and correct them) — only rows that came back `added`/`updated`/
   `removed` are cleared. The footer recomputes from whatever remains.

**Do not** send requests during quantity changes under any circumstance —
that is the exact behavior this architecture replaces.

---

## 5. Leaving Quick Order

Quick Order state is not persistent by design (see §2). No draft orders, no
session restore, no beforeunload confirmation prompt (out of scope unless
separately requested). Leaving the page — refresh, navigation, tab close —
silently discards everything not yet submitted via "Dodaj u košaricu".

---

## 6. Variation Rendering

The `<select>` dropdown from the superseded architecture (`.dp-qo-variation`)
is removed entirely — there is no separate "select a variation first" step.
Every variation of a variable product is independently purchasable and
visible immediately, with its own quantity controls, no separate step to
select it first.

**Grouped layout — real table columns, no `colspan`:** a variable product
renders as exactly one top-level `.dp-qo-row` (modifier
`.dp-qo-row--variable`), not one row per variation. Critically, it uses the
SAME five real `<td>` columns as a simple-product row and the table header
(thumb, Naziv, Stanje, Cijena, Kol.) — there is no `<td colspan>` and no
simulated table. Native table column widths still apply to variable rows,
exactly as they do to simple-product rows.

- **Thumb column:** parent thumbnail, rendered once.
- **Naziv column:** parent name + catalog number/SKU (`.dp-qo-row__product-info`),
  rendered once at the top of the cell, followed by a vertically-stacked list
  (`.dp-qo-variation-labels`) with one line per variation — its attribute
  label and SKU, not the parent name repeated.
- **Stanje / Cijena / Kol. columns:** each holds its own vertically-stacked
  list (`.dp-qo-variation-stocks` / `.dp-qo-variation-prices` /
  `.dp-qo-variation-qtys`) with one line per variation — stock badge, price,
  and qty controls respectively. A spacer matching the Naziv column's
  once-shown product-info block keeps line 1 of these three lists aligned
  with line 1 of the variation-labels list.
- Every line across these four lists shares the class `.dp-qo-variation-line`
  for consistent per-line height. Long names/SKUs are truncated
  (`white-space: nowrap` + ellipsis) rather than wrapped — wrapping in only
  one of the four stacked columns would grow that column's line height
  without growing its siblings', breaking row-for-row alignment. The table's
  existing horizontal scroll (`.dp-qo-table-wrap`) remains available for full
  text.

Each variation's qty-controls line (`.dp-qo-variation-row`, inside
`.dp-qo-variation-qtys`) is the fully independent purchasable unit and
retains its own:
- `variation_id` (and parent `product_id`)
- row key (`"${productId}_${variationId}"`) — its local-state identity
- quantity input and +/- controls
- stock state (in stock / out of stock / on backorder — controls disabled
  when out of stock, same rule as a simple-product row)
- validation/error state — a variation rejected at submit (§4.6) keeps its
  own quantity and error display without affecting sibling variations of the
  same parent, or any other row
- pricing (unit price captured from the variation payload, same as a
  simple-product row)
- submit behavior — included in the batch submit exactly like a
  simple-product row (§4), via its own `product_id`/`variation_id`/`quantity`

`RowController` requires no special-casing for this: it resolves the
purchasable unit via `.closest('.dp-qo-row, .dp-qo-variation-row')`, which
correctly stops at the qty line (not at the shared parent `.dp-qo-row`)
regardless of how many other columns/lists exist alongside it.

The `/products/{id}/variations` REST route (`class-product-query.php::
get_variation_details()`) is unchanged and still avoids
`get_available_variations()` — only the frontend rendering target changes:
the parent's four column lists (loading placeholder shown in the Naziv
column: "Učitavanje varijacija...") are populated in place with the fetched
variations; the parent `.dp-qo-row` and its five `<td>` columns are never
replaced, only each list's inner content.

Functionally, a variable product's variations behave exactly like N
independent simple-product rows once loaded — same row-key/local-state/
submit contract — they are simply rendered as stacked lines within the
existing table columns instead of as flat sibling `<tr>`s. This is a
DOM/markup detail; it does not change the local-state model (§2), footer
computation (§3), or submit flow (§4), all of which operate on row keys and
are agnostic to DOM structure.

---

## 7. Header

The Quick Order page reuses the theme's existing header render path via a
conditional branch — not a separate page template — for a minimal header:
Dream Point logo + "← Povratak u katalog" (left), "QUICK ORDER" (center), cart
icon only (right). No main navigation, no account menu, no mega menu. See the
implementation plan for the exact conditional and file location.

---

## 8. Reused vs. Replaced (from the superseded architecture)

| Component | Disposition |
|-----------|-------------|
| `class-product-query.php` (product list query, filters, sort) | Reused unchanged |
| `class-filter-bridge.php` / WOOF integration | Reused unchanged |
| `class-visibility-integration.php` | Reused unchanged — visibility gate still fires on `add_to_cart` only, same as before |
| `/products` REST route | Reused unchanged |
| `/products/{id}/variations` REST route | Reused unchanged — rendering target changes (§6), payload does not |
| `class-cart-sync.php` (`sync()` / `sync_item()`) | Reused unchanged — becomes the bulk-write path for §4, invoked once (in chunks) instead of per keystroke |
| `/cart/sync` REST route | Reused unchanged — same request/response shape, different caller pattern (batch submit vs. per-keystroke) |
| `CartSync` (JS debounce/token/abort engine) | Removed |
| `SyncQueue` (JS pending-map) | Removed — local state (§2) replaces its role, with no flush-to-network semantics |
| `RowSync` (JS event delegation) | Rewritten — same event-delegation shape (input/change/click on `.dp-qo-tbody`), but writes to local state instead of calling `sync.schedule()` |
| `ProductList` (JS row rendering, pagination, sort, WOOF) | Modified — row HTML changes (§6, product links removed, SKU label), pagination/sort/WOOF logic unchanged |
| Footer (`data.totals` wiring) | Replaced — see §3 |
| `dp:sync:start/success/error` custom events | Removed as a per-keystroke signal; the WC ecosystem bridge they fed is reused but invoked directly once at submit completion (§4.4), not via these events |

---

## 9. Known Limitations

- **No offline/retry support for the submit action** — if a chunked submit
  fails partway, already-processed chunks have already mutated the WC cart;
  the user sees which rows failed (§4.6) and must retry manually
- **No cross-tab awareness** — each tab's local state is independent; this is
  now the entire model, not a documented tradeoff on top of a shared sync
  layer
- **No draft/session persistence by design** — see §5, not a future item

---

## 10. Future Phases

Unchanged from the superseded architecture's forward-looking scope: inline
attribute-selector UX refinements, matrix ordering, SKU quick search, saved
order templates, favorites/frequent products — all out of scope for this
transformation.

# Quick Order — Cart Sync Architecture

Operational reference for the Quick Order synchronization system.
Covers JS engine, PHP endpoint, concurrency model, and variation handling.

---

## 1. System Overview

The Quick Order sync system debounces frontend quantity changes into batched `POST /cart/sync`
requests against a custom WooCommerce REST endpoint. The WC cart and session remain the only
source of truth — the frontend maintains a transient optimistic state for immediate visual
feedback only, never as a persistent cache.

**Architectural goals:**
- Single batched request per debounce window (no per-keystroke traffic)
- Stale response protection for rapid or overlapping changes
- WC-native cart operations throughout (no cart reimplementation)
- Lightweight payloads — no full product object hydration

**Authority model:**
- Frontend: quantity intent, optimistic UI state
- Server/WC: variation validity, purchasability, stock, add_to_cart acceptance

---

## 2. Synchronization Lifecycle

### Debounce and queue

Every call to `CartSync.schedule(rowKey, quantity)`:
1. Calls `SyncQueue.enqueue(rowKey, quantity)` — Map.set, last-write-wins
2. Clears the pending timer
3. Schedules a new `#dispatch()` call after `debounceMs` (300ms)

Rapid changes to the same row collapse — only the last quantity is sent. Changes to
different rows accumulate in the Map and all flush together.

### Dispatch lifecycle

When the timer fires, `#dispatch()` runs:

```
flush queue → items[] (null if empty → return)
snapshot = new Map(#optimisticState)      // isolated copy before changes
apply optimistic changes to #optimisticState
abort previous #controller (if any)
#controller = new AbortController()
token = ++#token
POST /cart/sync  {items, token}  with signal
```

If the queue is empty after flush, dispatch returns without sending a request.

### Request and response lifecycle

On successful response:
```
data = response.json()
if data.token !== token → discard (stale)
#onSuccess(data) → reconcile #optimisticState with data.synced
```

On error:
```
AbortError → return immediately (expected control flow)
any other error → #onError(snapshot) → restore pre-dispatch snapshot
```

`finally` block: if the queue is non-empty (items arrived during the request),
reschedule dispatch after `debounceMs`.

### Token lifecycle

`#token` is a monotonic integer incremented on every `#dispatch()` call.
It is sent in the request body and echoed by the server in the response.
The server applies `absint()` and includes the token unconditionally in the response
when the request used the `{items, token}` format.

A stale token (`data.token !== token`) means a newer dispatch has already run.
The stale response is silently discarded — no state changes, no rollback.

---

## 3. Concurrency Model

### Last-write-wins

`SyncQueue` uses a `Map` keyed by `rowKey`. `Map.set()` overwrites — the last quantity
written within a debounce window is the only one flushed. Intermediate values are
intentionally discarded.

### In-flight handling

`AbortController` is created on every `#dispatch()`. When a new dispatch fires while
a request is still in-flight, the previous `AbortController.abort()` is called before
the new request starts. Only one request is in-flight at any moment.

If the queue fills during a request, the `finally` block reschedules dispatch after
the request completes — no parallel requests are ever created.

### Stale response protection

Token check: `if (data.token !== token) return`.

This guards against edge cases where a response arrives after a newer dispatch has
already processed. The discarded response does not modify optimistic state, does not
trigger rollback, and does not affect the running token counter.

### AbortError behavior

`AbortError` is expected control flow, not an application error. It occurs when a newer
dispatch cancels an in-flight request. The catch block checks `err.name === 'AbortError'`
and returns immediately — no rollback, no logging, no UI signal.

### Rollback boundaries

Rollback restores the pre-dispatch snapshot. It is triggered only by:
- HTTP error responses (non-2xx)
- Network failures
- JSON parse errors

It is never triggered by:
- `AbortError` (cancelled requests)
- Stale token discards
- `out_of_stock` or `invalid_product` action codes (those are success responses with
  typed error information — the server processed the request correctly)

---

## 4. Optimistic State

### Lifecycle

`#optimisticState` is an in-memory `Map<rowKey, quantity>`. It is never persisted,
never written to localStorage or sessionStorage, and never shared across tabs.

State is applied optimistically in `#dispatch()` before the request is sent:
- quantity > 0 → `Map.set(rowKey, quantity)`
- quantity === 0 → `Map.delete(rowKey)`

### Snapshot isolation

The snapshot is `new Map(#optimisticState)` — a full copy created before optimistic
changes are applied. It holds no shared references with the pending queue, the items
array, or the response payload. Each dispatch call has its own snapshot variable in
closure scope.

### Reconciliation on success

`#onSuccess(data)` iterates `data.synced` and adjusts `#optimisticState` to match
what WC actually applied:
- `action: removed` or `action: skipped` → `Map.delete(key)`
- `item.quantity != null` → `Map.set(key, item.quantity)` (corrects for stock clamping)

This handles cases where WC accepted a lower quantity than requested (stock limit).

### Rollback on failure

`#onError(snapshot)` replaces `#optimisticState` with the pre-dispatch snapshot.
Since the snapshot was captured before optimistic changes were applied, this restores
only the rows that were modified in this dispatch cycle.

### Authority boundary

`getOptimisticQuantity(rowKey)` is a read-only hint for UI rendering. The WC cart
response (`data.synced`, `data.totals`) is always the authoritative source. Optimistic
state exists only to avoid a visible quantity flicker during the network round-trip.

---

## 5. Variation Synchronization

### Payload structure

Frontend sends per-item:
```json
{
  "product_id": 123,
  "variation_id": 456,
  "quantity": 2,
  "variation": { "attribute_pa_color": "red" }
}
```

`variation_id` is the preferred identifier. `variation` (attribute map) is optional —
used only when the client wants WC to store attribute labels in the cart item.
If `variation_id` is known and valid, `variation` attrs are passed through but not used
for resolution.

### Server-side validation

Per item in `sync_item()`:

1. `wc_get_product($variation_id ?: $product_id)` — loads only the specific variation
   object (or parent for simple products). Uses WC object cache — no redundant DB hits.
2. `$product->is_purchasable()` — WC-native check covering status, visibility, price,
   and any plugin-added purchasability rules.
3. If adding new: `$product->is_in_stock()` — quick guard for fully out-of-stock products.
   For managed-stock products where `is_in_stock()` is true but requested qty exceeds
   available stock: pre-check via `get_stock_quantity()` returns `out_of_stock` with
   `quantity_allowed: N` before calling `add_to_cart()`. This prevents `add_to_cart()`
   from silently returning false with no typed error information.
4. If updating existing: `get_manage_stock()` + `get_stock_quantity()` — validates the
   requested quantity against available stock, returns `quantity_allowed` for UI feedback.

### WC authority at add_to_cart

`$cart->add_to_cart($product_id, $quantity, $variation_id, $variation_attrs)` is the
final gate. WC performs attribute validation, sold-individually checks, and any
filter-based restrictions internally. If WC returns false, the server responds with
`action: failed, error: add_failed` — no further diagnosis.

### Why `get_available_variations()` is intentionally avoided

`get_available_variations()` loads the full variation tree for a product — potentially
hundreds of objects per product, multiplied by catalog size. It exists to power
frontend variation pickers, not server-side validation. On server, `wc_get_product()`
on the specific `variation_id` is sufficient and O(1).

### Invalid and stale variation handling

| Scenario | Response |
|----------|----------|
| variation_id deleted/deactivated | `action: failed, error: invalid_product` |
| variation not purchasable | `action: failed, error: invalid_product` |
| out of stock (new item, fully) | `action: out_of_stock` |
| out of stock (new item, managed stock, qty > stock) | `action: out_of_stock, quantity_allowed: N` |
| out of stock (update, managed) | `action: out_of_stock, quantity_allowed: N` |
| add_to_cart rejected (attribute mismatch, etc.) | `action: failed, error: add_failed` |

### Visibility gate scope

The visibility check (`dp_b2b_product_accessible` filter) fires **only on new `add_to_cart()` calls**.
Items already in the WC cart are not revalidated when a user's bucket rules or overrides change
after the initial add.

This is intentional. Retroactive revalidation on every sync would cause cart instability when
bucket rules are edited mid-session, create unpredictable UX on stale sessions, and break the
deterministic WooCommerce-native sync model. **This is not a bug — do not add background
revalidation or cart-cleanup logic to address it.**

---

## 6. Performance Decisions

**Cart index built once per sync() call.**
`$cart->get_cart()` is iterated once to build a `"pid_vid" => cart_item_key` map.
All subsequent lookups per item are O(1). No repeated `get_cart()` calls in the loop.

**`calculate_totals()` called once.**
Runs after all items are processed, not per-item. Totals returned in every sync
response: `{subtotal, total, currency}`. Frontend can update the cart summary without
a separate request.

**Batch size guard (50 items max).**
`CART_SYNC_MAX_BATCH = 50` enforced in the REST endpoint before the sync runs.
Returns HTTP 400 `payload_too_large` for oversized batches.

**No full product object hydration.**
`wc_get_product()` loads one product or variation per item. No parent product loaded
for variations unless `variation_id = 0` (treated as invalid for variable products).
Response includes only: `product_id`, `variation_id`, `action`, `quantity` (when relevant),
`error` code, `quantity_allowed` (for managed stock mismatches only).

**AbortController prevents stale response processing.**
Cancelled requests never reach the JSON parse or state update phase. No processing
overhead from superseded requests.

**Debounce collapses rapid changes.**
300ms window means a user typing a quantity manually (3 keystrokes in ~200ms) produces
one request, not three.

---

## 7. Locked Architectural Decisions

| Concern | Decision |
|---------|----------|
| Row identity | `"${productId}_${variationId}"` — deterministic, no hash, same format JS + PHP |
| Debounce | 300ms timer, reset on every `schedule()` call |
| Queue strategy | `Map` (last-write-wins per row), cleared on flush |
| Stale protection | Monotonic integer token, echoed in response, discarded if mismatch |
| Inflight cancellation | `AbortController` — one instance per dispatch, replaced on new dispatch |
| AbortError | Expected control flow — silent return, no rollback, no logging |
| Optimistic state | In-memory `Map`, request-scoped, isolated snapshot per dispatch |
| Rollback trigger | Real errors only — never AbortError, never stale discard |
| Variation validation | `wc_get_product(variation_id)` + `is_purchasable()` — no `get_available_variations()` |
| WC authority | `add_to_cart()` is the final validation gate — server does not replicate WC logic |
| Cart source of truth | WC cart/session only — no frontend persistence layer |
| Payload format | `{items: [...], token: N}` — backwards-compatible with legacy flat array |

---

## 8. Known Limitations

- **No offline support** — requests fail silently if network is unavailable; no queue persistence or retry
- **No realtime multi-tab synchronization** — each tab maintains independent optimistic state; WC cart is shared server-side but tabs do not notify each other of changes
- **Optimistic state is request-scoped only** — state resets on page reload; no persistence across navigations
- **WooCommerce remains authoritative** — optimistic quantities may temporarily diverge from WC cart during inflight requests; server response is always final
- **No realtime stock synchronization** — stock availability is checked at sync time only; stock depletion by other users between syncs is not surfaced until the next request
- **REST endpoint response time** — `wc_load_cart()` initializes WC session on each REST request; on environments without persistent PHP workers this adds overhead on first request per process (local: ~1500ms, production PHP-FPM: significantly faster on warm workers)
- **V1: Pagination quantity reset** — qty inputs reset to 0 on every page navigation; there is no pre-population from current WC cart state. Users who have added items and then change pages will see empty qty fields on return. This is intentionally deferred: cross-page cart hydration requires solving stale hydration, synchronization drift, hidden optimistic state, multi-tab consistency, and visibility invalidation — complexity out of scope for V1. Considered a known usability tradeoff for V1 multi-page workflows. Future V2 consideration.

---

## 9. Future Phases

- **Filtering integration** — sync requests must remain compatible with bucket/brand/category visibility rules already enforced by the visibility system
- **Frontend rendering** — UI modules will read `getOptimisticQuantity()` and subscribe to custom events dispatched from `#onSuccess`/`#onError` (hooks are in place, events not yet dispatched)
- **Cart summary UI** — `data.totals` is already included in every sync response; summary component reads from it directly
- **Variation selection UX** — inline attribute selectors on Quick Order rows; `variation_id` resolution stays server-side

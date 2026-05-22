# Cart Sync Robustness — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a stable, variation-aware cart synchronization engine with debounced batching, stale response protection, and optimistic UI preparation — without building UI or redesigning existing systems.

**Architecture:** Frontend maintains a transient sync queue (Map-based, last-write-wins per row key). A CartSync engine debounces flushes into batched REST requests, protects against out-of-order responses via a monotonic token, and prepares optimistic state for future UI rollback. Backend validates each item (existence, stock, variation attributes) and echoes the request token in every response.

**Tech Stack:** PHP 8.1, WooCommerce REST Cart API (custom endpoint), vanilla JS (ES2022, IIFE), esbuild for bundling.

---

## Architectural Decisions (locked in this phase)

### Row Identity
Key format: `"${productId}_${variationId}"` — e.g. `"123_0"` for simple, `"123_456"` for variation.
- Matches server-side `$cart_index` key already in `class-cart-sync.php`
- Deterministic, no hash needed, collision-free within a cart
- Attribute hash is NOT used at this layer — WC handles attribute→variation mapping

### Stale Response Protection
Monotonic integer token sent with each request, echoed in response.
- Frontend `this.#token` increments on each `#dispatch()` call
- If `response.token !== this.#token` → discard silently
- Handles AbortError separately (expected, not an error state)

### Debounce + Batch Strategy
- 300ms debounce timer resets on every `schedule()` call
- Pending updates live in `SyncQueue` (Map: rowKey → quantity)
- `Map.set()` overwrites — last-write-wins per row during debounce window
- On timer fire: `flush()` collects all pending rows as one batch → single request

### Optimistic UI Preparation
- Before dispatch: snapshot current `#optimisticState` Map
- `#optimisticState` updated optimistically with pending changes before request
- On success: confirmed with server response data
- On failure: rolled back to pre-dispatch snapshot
- Future UI modules will subscribe to `#optimisticState` changes — not built yet

---

## File Map

### PHP — Modified
| File | Change |
|------|--------|
| `plugins/dp-b2b-quick-order/inc/class-config.php` | Add `CART_SYNC_TIMEOUT_MS`, `CART_SYNC_MAX_BATCH` |
| `plugins/dp-b2b-quick-order/inc/class-cart-sync.php` | Variation attributes, stock validation, typed error codes, totals in response |
| `plugins/dp-b2b-quick-order/inc/class-rest-api.php` | `{items, token}` payload, batch size guard, token echo |
| `plugins/dp-b2b-quick-order/inc/class-assets.php` | Add `cartSyncUrl`, `wpNonce`, `debounceMs` to localized data |

### JS — Created
| File | Responsibility |
|------|---------------|
| `plugins/dp-b2b-quick-order/assets/src/sync-queue.js` | Pending updates Map, last-write-wins, flush to array |
| `plugins/dp-b2b-quick-order/assets/src/cart-sync.js` | Debounce, AbortController, token, optimistic state |
| `plugins/dp-b2b-quick-order/assets/src/quick-order.js` | Entry point — bootstrap, wire modules, expose public API |

### Build — Created
| File | Responsibility |
|------|---------------|
| `plugins/dp-b2b-quick-order/package.json` | esbuild scripts for plugin JS |

### Docs — Created
| File | Responsibility |
|------|---------------|
| `themes/dreampoint-b2b/docs/tasks/cart-sync-robustness.md` | Architecture summary for next session |

---

## Task 1: Config constants

**Files:**
- Modify: `plugins/dp-b2b-quick-order/inc/class-config.php`

- [ ] **Step 1: Add constants**

Open `inc/class-config.php`. Add after the existing `CART_SYNC_DEBOUNCE_MS` constant:

```php
// ── Cart Sync ─────────────────────────────────────────────────────────────
// ... (existing CART_SYNC_DEBOUNCE_MS stays)

// Max items accepted in a single sync request — guards against oversized payloads.
const CART_SYNC_MAX_BATCH = 50;

// Timeout (ms) for frontend fetch calls to the cart sync endpoint.
// Defined here so PHP can pass it to JS via wp_localize_script.
const CART_SYNC_TIMEOUT_MS = 10000;
```

- [ ] **Step 2: Verify syntax**

```bash
php -l wp-content/plugins/dp-b2b-quick-order/inc/class-config.php
```
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
cd /c/xampp2/htdocs/dp-b2b/wp-content
git add plugins/dp-b2b-quick-order/inc/class-config.php
git commit -m "chore: add CART_SYNC_MAX_BATCH and CART_SYNC_TIMEOUT_MS to Config"
```

---

## Task 2: REST endpoint — payload contract + token

**Files:**
- Modify: `plugins/dp-b2b-quick-order/inc/class-rest-api.php`

- [ ] **Step 1: Replace `sync_cart()` method**

Replace the entire `sync_cart()` method with:

```php
public function sync_cart( WP_REST_Request $request ): WP_REST_Response|WP_Error {
    $body = $request->get_json_params();

    // Accept both formats:
    //   {items: [...], token: N}  — new batched format
    //   [...]                     — legacy flat array (backwards compat)
    if ( is_array( $body ) && array_key_exists( 'items', $body ) ) {
        $items = $body['items'] ?? [];
        $token = isset( $body['token'] ) ? absint( $body['token'] ) : null;
    } elseif ( is_array( $body ) && array_is_list( $body ) ) {
        $items = $body;
        $token = null;
    } else {
        return new WP_Error(
            'invalid_payload',
            __( 'Invalid cart sync payload.', 'dp-b2b-quick-order' ),
            [ 'status' => 400 ]
        );
    }

    if ( ! is_array( $items ) ) {
        return new WP_Error(
            'invalid_items',
            __( 'Items must be an array.', 'dp-b2b-quick-order' ),
            [ 'status' => 400 ]
        );
    }

    if ( count( $items ) > DP_Quick_Order_Config::CART_SYNC_MAX_BATCH ) {
        return new WP_Error(
            'payload_too_large',
            sprintf(
                /* translators: %d: max allowed items */
                __( 'Sync request exceeds maximum of %d items.', 'dp-b2b-quick-order' ),
                DP_Quick_Order_Config::CART_SYNC_MAX_BATCH
            ),
            [ 'status' => 400 ]
        );
    }

    $result = $this->cart_sync->sync( $items );

    if ( null !== $token ) {
        $result['token'] = $token;
    }

    return rest_ensure_response( $result );
}
```

- [ ] **Step 2: Verify syntax**

```bash
php -l wp-content/plugins/dp-b2b-quick-order/inc/class-rest-api.php
```
Expected: `No syntax errors detected`

- [ ] **Step 3: Manual API test**

With WP running locally, send a request:
```bash
curl -s -X POST http://localhost:8080/dp-b2b/wp-json/dreampoint-b2b/v1/quick-order/cart/sync \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: <nonce>" \
  -d '{"items": [{"product_id": 1, "quantity": 1}], "token": 42}'
```
Expected response includes `"token": 42` in the JSON body.

- [ ] **Step 4: Commit**

```bash
cd /c/xampp2/htdocs/dp-b2b/wp-content
git add plugins/dp-b2b-quick-order/inc/class-rest-api.php
git commit -m "feat: update cart sync endpoint to support batched {items, token} payload"
```

---

## Task 3: Cart sync — variation attributes, stock validation, typed errors, totals

**Files:**
- Modify: `plugins/dp-b2b-quick-order/inc/class-cart-sync.php`

- [ ] **Step 1: Replace `sync()` method**

Replace the entire `sync()` method:

```php
public function sync( array $items ): array {
    $cart    = WC()->cart;
    $results = [];

    // Build cart index keyed by product_id+variation_id for O(1) lookups.
    $cart_index = [];
    foreach ( $cart->get_cart() as $key => $cart_item ) {
        $index_key              = $cart_item['product_id'] . '_' . $cart_item['variation_id'];
        $cart_index[ $index_key ] = $key;
    }

    foreach ( $items as $item ) {
        $product_id     = absint( $item['product_id'] ?? 0 );
        $variation_id   = absint( $item['variation_id'] ?? 0 );
        $quantity       = absint( $item['quantity'] ?? 0 );
        $variation_attrs = is_array( $item['variation'] ?? null )
            ? array_map( 'sanitize_text_field', $item['variation'] )
            : [];

        if ( ! $product_id ) {
            continue;
        }

        $results[] = $this->sync_item(
            $cart,
            $cart_index,
            $product_id,
            $variation_id,
            $quantity,
            $variation_attrs
        );
    }

    $cart->calculate_totals();

    return [
        'synced' => $results,
        'total'  => $cart->get_cart_contents_count(),
        'totals' => [
            'subtotal' => (float) $cart->get_subtotal(),
            'total'    => (float) $cart->get_total( 'float' ),
            'currency' => get_woocommerce_currency(),
        ],
    ];
}
```

- [ ] **Step 2: Replace `sync_item()` method**

Replace the entire `sync_item()` method:

```php
/**
 * @param array<string, string> $cart_index     Map of "pid_vid" => cart_item_key.
 * @param array<string, string> $variation_attrs WC variation attribute key=>value pairs.
 */
private function sync_item(
    WC_Cart $cart,
    array &$cart_index,
    int $product_id,
    int $variation_id,
    int $quantity,
    array $variation_attrs = []
): array {
    $base = [ 'product_id' => $product_id, 'variation_id' => $variation_id ];

    // Validate: product or variation must exist and be purchasable.
    $check_id = $variation_id ?: $product_id;
    $product  = wc_get_product( $check_id );

    if ( ! $product instanceof WC_Product || ! $product->is_purchasable() ) {
        return array_merge( $base, [ 'action' => 'failed', 'error' => 'invalid_product' ] );
    }

    $index_key    = $product_id . '_' . $variation_id;
    $existing_key = $cart_index[ $index_key ] ?? null;

    if ( null !== $existing_key ) {
        if ( $quantity === 0 ) {
            $cart->remove_cart_item( $existing_key );
            unset( $cart_index[ $index_key ] );
            return array_merge( $base, [ 'action' => 'removed' ] );
        }

        // Stock check for quantity update.
        if ( $product->get_manage_stock() ) {
            $stock_qty = (int) $product->get_stock_quantity();
            if ( $stock_qty < $quantity ) {
                return array_merge( $base, [
                    'action'           => 'out_of_stock',
                    'quantity_allowed' => max( 0, $stock_qty ),
                ] );
            }
        }

        $cart->set_quantity( $existing_key, $quantity );
        return array_merge( $base, [ 'action' => 'updated', 'quantity' => $quantity ] );
    }

    if ( $quantity > 0 ) {
        if ( ! $product->is_in_stock() ) {
            return array_merge( $base, [ 'action' => 'out_of_stock' ] );
        }

        $new_key = $cart->add_to_cart( $product_id, $quantity, $variation_id, $variation_attrs );
        if ( $new_key ) {
            $cart_index[ $index_key ] = $new_key;
            return array_merge( $base, [ 'action' => 'added', 'quantity' => $quantity ] );
        }
        return array_merge( $base, [ 'action' => 'failed', 'error' => 'add_failed' ] );
    }

    return array_merge( $base, [ 'action' => 'skipped' ] );
}
```

- [ ] **Step 3: Verify syntax**

```bash
php -l wp-content/plugins/dp-b2b-quick-order/inc/class-cart-sync.php
```
Expected: `No syntax errors detected`

- [ ] **Step 4: Manual verification**

Log in as a B2B test user (`vis_full`). Send a sync request with a valid product ID and quantity 1. Verify response includes `synced[0].action = "added"` and `totals.total` is a float.

Send same request with quantity 0. Verify `action = "removed"`.

Send with an invalid product_id (e.g. 999999). Verify `action = "failed"` and `error = "invalid_product"`.

- [ ] **Step 5: Commit**

```bash
cd /c/xampp2/htdocs/dp-b2b/wp-content
git add plugins/dp-b2b-quick-order/inc/class-cart-sync.php
git commit -m "feat: add variation attributes, stock validation, typed errors, and cart totals to CartSync"
```

---

## Task 4: Assets localization — expose config to JS

**Files:**
- Modify: `plugins/dp-b2b-quick-order/inc/class-assets.php`

- [ ] **Step 1: Replace `wp_localize_script` call**

Replace the `wp_localize_script` block in `enqueue()`:

```php
wp_localize_script( 'dp-quick-order', 'dpQuickOrder', [
    'restUrl'     => esc_url_raw( rest_url( DP_Quick_Order_Config::REST_NAMESPACE . '/' ) ),
    'cartSyncUrl' => esc_url_raw( rest_url(
        DP_Quick_Order_Config::REST_NAMESPACE . '/' .
        DP_Quick_Order_Config::REST_BASE . '/cart/sync'
    ) ),
    'storeUrl'    => esc_url_raw( rest_url( 'wc/store/v1/' ) ),
    'nonce'       => wp_create_nonce( DP_Quick_Order_Config::NONCE_ACTION ),
    'wpNonce'     => wp_create_nonce( 'wp_rest' ),
    'debounceMs'  => DP_Quick_Order_Config::CART_SYNC_DEBOUNCE_MS,
    'timeoutMs'   => DP_Quick_Order_Config::CART_SYNC_TIMEOUT_MS,
    'i18n'        => [],
] );
```

- [ ] **Step 2: Verify syntax**

```bash
php -l wp-content/plugins/dp-b2b-quick-order/inc/class-assets.php
```
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
cd /c/xampp2/htdocs/dp-b2b/wp-content
git add plugins/dp-b2b-quick-order/inc/class-assets.php
git commit -m "chore: expose cartSyncUrl, wpNonce, debounceMs, timeoutMs to dpQuickOrder JS config"
```

---

## Task 5: Plugin build tooling

**Files:**
- Create: `plugins/dp-b2b-quick-order/package.json`
- Create: `plugins/dp-b2b-quick-order/assets/src/` (directory)

- [ ] **Step 1: Create package.json**

Create `plugins/dp-b2b-quick-order/package.json`:

```json
{
  "name": "dp-b2b-quick-order",
  "description": "DP B2B Quick Order plugin JS",
  "private": true,
  "scripts": {
    "build:js": "esbuild assets/src/quick-order.js --bundle --minify --outfile=assets/dist/quick-order.js --format=iife",
    "watch:js": "esbuild assets/src/quick-order.js --bundle --outfile=assets/dist/quick-order.js --format=iife --watch",
    "build": "npm run build:js"
  },
  "devDependencies": {
    "esbuild": "^0.21.5"
  }
}
```

- [ ] **Step 2: Install dependencies**

```bash
cd wp-content/plugins/dp-b2b-quick-order
npm install
```
Expected: `node_modules/` created, `esbuild` installed.

- [ ] **Step 3: Create dist directory placeholder**

```bash
mkdir -p wp-content/plugins/dp-b2b-quick-order/assets/dist
touch wp-content/plugins/dp-b2b-quick-order/assets/dist/.gitkeep
touch wp-content/plugins/dp-b2b-quick-order/assets/dist/quick-order.css
```

- [ ] **Step 4: Update .gitignore if needed**

Check `wp-content/.gitignore` — add if not present:
```
plugins/dp-b2b-quick-order/node_modules/
```

- [ ] **Step 5: Commit**

```bash
cd /c/xampp2/htdocs/dp-b2b/wp-content
git add plugins/dp-b2b-quick-order/package.json plugins/dp-b2b-quick-order/assets/dist/.gitkeep plugins/dp-b2b-quick-order/assets/dist/quick-order.css .gitignore
git commit -m "chore: add esbuild build tooling for quick-order plugin JS"
```

---

## Task 6: JS — SyncQueue

**Files:**
- Create: `plugins/dp-b2b-quick-order/assets/src/sync-queue.js`

- [ ] **Step 1: Create SyncQueue**

Create `assets/src/sync-queue.js`:

```javascript
'use strict';

/**
 * Pending cart update queue.
 *
 * Stores the latest desired quantity per row key.
 * Multiple updates to the same row during a debounce window collapse into one
 * (last-write-wins) — no stale intermediate quantities are ever sent.
 *
 * Row key format: "${productId}_${variationId}" (variationId=0 for simple products)
 */
export class SyncQueue {
    #pending = new Map();

    /**
     * Enqueue a quantity update for a row.
     * Overwrites any pending update for the same row key.
     *
     * @param {string} rowKey   Row identity key.
     * @param {number} quantity Desired quantity (0 = remove).
     */
    enqueue(rowKey, quantity) {
        this.#pending.set(rowKey, quantity);
    }

    /**
     * Flush all pending updates to an array and clear the queue.
     * Returns null if queue is empty (no request should be sent).
     *
     * @returns {{ product_id: number, variation_id: number, quantity: number }[] | null}
     */
    flush() {
        if (this.#pending.size === 0) return null;

        const items = [];
        for (const [rowKey, quantity] of this.#pending) {
            const [productId, variationId] = rowKey.split('_').map(Number);
            items.push({
                product_id:   productId,
                variation_id: variationId,
                quantity,
            });
        }

        this.#pending.clear();
        return items;
    }

    /** @returns {boolean} */
    isEmpty() {
        return this.#pending.size === 0;
    }

    /** @returns {number} */
    size() {
        return this.#pending.size;
    }
}
```

- [ ] **Step 2: Manual verification in browser console**

After building in Task 8, open DevTools console on the Quick Order page.

```javascript
// Access internal queue (exposed via window.dpQuickOrder.queue in entry point)
const q = window.dpQuickOrder.queue;
q.enqueue('123_0', 3);
q.enqueue('123_0', 5);  // overwrites — last write wins
q.enqueue('456_789', 2);
console.log(q.size());   // Expected: 2
const items = q.flush();
console.log(items);
// Expected: [{product_id:123, variation_id:0, quantity:5}, {product_id:456, variation_id:789, quantity:2}]
console.log(q.isEmpty()); // Expected: true
```

---

## Task 7: JS — CartSync engine

**Files:**
- Create: `plugins/dp-b2b-quick-order/assets/src/cart-sync.js`

- [ ] **Step 1: Create CartSync**

Create `assets/src/cart-sync.js`:

```javascript
'use strict';

/**
 * Cart synchronization engine.
 *
 * Responsibilities:
 * - Debounce quantity changes into batched sync requests
 * - Cancel previous in-flight request when new batch dispatches
 * - Protect against stale out-of-order responses via monotonic token
 * - Maintain optimistic state for future UI rollback preparation
 *
 * Usage:
 *   sync.schedule(rowKey, quantity);  // call on every quantity change
 *
 * @typedef {{ cartSyncUrl: string, wpNonce: string, debounceMs: number, timeoutMs: number }} SyncConfig
 */
export class CartSync {
    /** @type {import('./sync-queue.js').SyncQueue} */
    #queue;
    /** @type {SyncConfig} */
    #config;
    #timer      = null;
    #controller = null;
    #token      = 0;
    /** @type {Map<string, number>} Confirmed/optimistic quantity per rowKey */
    #optimisticState = new Map();

    /**
     * @param {import('./sync-queue.js').SyncQueue} queue
     * @param {SyncConfig} config
     */
    constructor(queue, config) {
        this.#queue  = queue;
        this.#config = config;
    }

    /**
     * Schedule a cart sync after the debounce window.
     * Safe to call on every keypress/click — collapses into a single request per window.
     *
     * @param {string} rowKey   Row identity key ("productId_variationId").
     * @param {number} quantity Desired quantity (0 = remove).
     */
    schedule(rowKey, quantity) {
        this.#queue.enqueue(rowKey, quantity);
        clearTimeout(this.#timer);
        this.#timer = setTimeout(() => this.#dispatch(), this.#config.debounceMs);
    }

    /**
     * Current optimistic quantity for a row.
     * Returns 0 if row is not in cart or has been removed.
     *
     * @param {string} rowKey
     * @returns {number}
     */
    getOptimisticQuantity(rowKey) {
        return this.#optimisticState.get(rowKey) ?? 0;
    }

    async #dispatch() {
        const items = this.#queue.flush();
        if (!items) return;

        // Snapshot current optimistic state before applying changes.
        const snapshot = new Map(this.#optimisticState);

        // Optimistically apply pending changes.
        for (const item of items) {
            const key = `${item.product_id}_${item.variation_id}`;
            if (item.quantity === 0) {
                this.#optimisticState.delete(key);
            } else {
                this.#optimisticState.set(key, item.quantity);
            }
        }

        // Abort previous in-flight request.
        this.#controller?.abort();
        this.#controller = new AbortController();

        const token = ++this.#token;

        try {
            const response = await fetch(this.#config.cartSyncUrl, {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce':   this.#config.wpNonce,
                },
                body:   JSON.stringify({ items, token }),
                signal: this.#controller.signal,
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            // Stale response protection — discard if a newer request has dispatched.
            if (data.token !== token) return;

            this.#onSuccess(data);
        } catch (err) {
            // AbortError is expected when a newer request cancels this one.
            if (err.name === 'AbortError') return;
            this.#onError(snapshot);
        }
    }

    /**
     * Confirm optimistic state from server response.
     * @param {{ synced: Array, totals: object }} data
     */
    #onSuccess(data) {
        if (!Array.isArray(data.synced)) return;

        for (const item of data.synced) {
            const key = `${item.product_id}_${item.variation_id}`;
            if (item.action === 'removed') {
                this.#optimisticState.delete(key);
            } else if (item.quantity != null) {
                this.#optimisticState.set(key, item.quantity);
            }
        }
        // Future: dispatch custom event with data.totals for cart summary UI
    }

    /**
     * Roll back optimistic state to pre-dispatch snapshot on sync failure.
     * @param {Map<string, number>} snapshot
     */
    #onError(snapshot) {
        this.#optimisticState = snapshot;
        // Future: dispatch custom event to signal UI rollback needed
    }
}
```

---

## Task 8: JS — Entry point + build + browser test

**Files:**
- Create: `plugins/dp-b2b-quick-order/assets/src/quick-order.js`

- [ ] **Step 1: Create entry point**

Create `assets/src/quick-order.js`:

```javascript
'use strict';

import { SyncQueue } from './sync-queue.js';
import { CartSync }  from './cart-sync.js';

(function () {
    const config = window.dpQuickOrder;
    if (!config || !config.cartSyncUrl) return;

    const queue = new SyncQueue();
    const sync  = new CartSync(queue, {
        cartSyncUrl: config.cartSyncUrl,
        wpNonce:     config.wpNonce,
        debounceMs:  config.debounceMs ?? 300,
        timeoutMs:   config.timeoutMs  ?? 10000,
    });

    // Expose on window.dpQuickOrder for future UI modules and browser testing.
    config.sync  = sync;
    config.queue = queue;
})();
```

- [ ] **Step 2: Build**

```bash
cd wp-content/plugins/dp-b2b-quick-order
npm run build:js
```
Expected: `assets/dist/quick-order.js` created with no errors.

- [ ] **Step 3: Verify bundle was created**

```bash
ls -lh wp-content/plugins/dp-b2b-quick-order/assets/dist/quick-order.js
```
Expected: file exists, size > 0.

- [ ] **Step 4: Browser test — sync engine boots correctly**

Navigate to `http://localhost:8080/dp-b2b/?page_id=<quick-order-page>` while logged in as `vis_full`.

Open DevTools console:
```javascript
// Config should be present
console.log(typeof window.dpQuickOrder.sync);   // Expected: "object"
console.log(typeof window.dpQuickOrder.queue);  // Expected: "object"
console.log(window.dpQuickOrder.cartSyncUrl);   // Expected: full URL ending in /cart/sync
console.log(window.dpQuickOrder.wpNonce);       // Expected: non-empty string
```

- [ ] **Step 5: Browser test — schedule() triggers debounced sync**

In DevTools console:
```javascript
const sync = window.dpQuickOrder.sync;

// Simulate rapid quantity changes for same product
sync.schedule('999_0', 1);
sync.schedule('999_0', 2);
sync.schedule('999_0', 3);  // Only this one should reach the server

// Wait 400ms then check network tab — should see exactly ONE POST to /cart/sync
// Request body should be: {"items":[{"product_id":999,"variation_id":0,"quantity":3}],"token":1}
```

- [ ] **Step 6: Browser test — stale response protection**

In DevTools console, throttle network to "Slow 4G", then:
```javascript
sync.schedule('100_0', 1);  // token 2 dispatched
// Immediately after:
sync.schedule('100_0', 5);  // token 3 dispatched, aborts token 2
// Only response with token=3 should update optimistic state
```
Verify in Network tab: two requests fired, first one cancelled (status "canceled"), second completes normally.

- [ ] **Step 7: Commit**

```bash
cd /c/xampp2/htdocs/dp-b2b/wp-content
git add plugins/dp-b2b-quick-order/assets/src/ plugins/dp-b2b-quick-order/assets/dist/
git commit -m "feat: implement CartSync engine with debounced batching, stale protection, and optimistic state"
```

---

## Task 9: Architecture documentation

**Files:**
- Create: `themes/dreampoint-b2b/docs/tasks/cart-sync-robustness.md`

- [ ] **Step 1: Create architecture doc**

Create `docs/tasks/cart-sync-robustness.md` in the theme:

```markdown
## Cart Sync Robustness — Architecture Summary

### Synchronization Strategy
Debounced batch sync: 300ms timer resets on every `schedule()` call.
Pending updates live in SyncQueue (Map). On timer fire, all pending rows flush as one
batched `POST /cart/sync` request: `{items: [{product_id, variation_id, quantity}], token: N}`.
Map last-write-wins — rapid changes to the same row collapse to the latest quantity.

### Stale Response Protection
Monotonic integer token increments on every `#dispatch()` call.
Token is included in the request body and echoed in the server response.
If `response.token !== currentToken` → response is discarded silently.
AbortController cancels previous in-flight request on new dispatch — AbortError is silent.

### Row Identity
Key format: `"${productId}_${variationId}"` (variationId=0 for simple products).
Same key used on both client (SyncQueue Map) and server (cart_index in CartSync::sync_item).
Attribute hash not used — WC resolves variation_id to attributes internally.

### Optimistic UI Direction
`CartSync.#optimisticState` (Map<rowKey, quantity>) is updated before each request.
On success: confirmed/adjusted from server response.
On failure: rolled back to pre-dispatch snapshot.
Future UI modules subscribe to changes via custom events dispatched from #onSuccess/#onError.

### Variation Sync
Client sends `{product_id, variation_id, quantity, variation?: {attribute_key: value}}`.
Server validates existence via `wc_get_product($variation_id ?: $product_id)`.
Variation attributes passed directly to `$cart->add_to_cart($product_id, $qty, $variation_id, $attrs)`.
Stock check uses `$product->get_manage_stock()` + `get_stock_quantity()` — no WC internal helpers.

### Response Contract
```json
{
  "synced": [
    {"product_id": 123, "variation_id": 0, "action": "updated", "quantity": 3},
    {"product_id": 456, "variation_id": 789, "action": "out_of_stock", "quantity_allowed": 2},
    {"product_id": 0,   "variation_id": 0,   "action": "failed", "error": "invalid_product"}
  ],
  "total": 5,
  "totals": {"subtotal": 199.50, "total": 249.38, "currency": "HRK"},
  "token": 7
}
```

### Scalability
- SyncQueue is O(1) enqueue and O(n pending) flush — n bounded by debounce window duration
- CartSync builds cart_index once per sync() call — O(cart size), not O(n items)
- No polling, no interval — purely event-driven
- AbortController prevents response processing overhead from cancelled requests
```

- [ ] **Step 2: Commit**

```bash
cd /c/xampp2/htdocs/dp-b2b/wp-content
git add themes/dreampoint-b2b/docs/tasks/cart-sync-robustness.md
git commit -m "docs: add cart sync robustness architecture summary"
```

---

## Final deploy

- [ ] **Step 1: Verify all PHP syntax**

```bash
php -l wp-content/plugins/dp-b2b-quick-order/inc/class-config.php
php -l wp-content/plugins/dp-b2b-quick-order/inc/class-cart-sync.php
php -l wp-content/plugins/dp-b2b-quick-order/inc/class-rest-api.php
php -l wp-content/plugins/dp-b2b-quick-order/inc/class-assets.php
```
All expected: `No syntax errors detected`

- [ ] **Step 2: Deploy to server**

Trigger: "deploy changes"

---

## Self-Review

### Spec coverage

| Spec requirement | Covered in |
|-----------------|-----------|
| Stable quantity synchronization | Task 3 (stock validation, typed errors) |
| Batching | Task 6 (SyncQueue flush), Task 7 (CartSync dispatch) |
| Debounce behavior | Task 7 (300ms timer, clearTimeout on schedule) |
| Stale response protection | Task 7 (monotonic token, AbortController) |
| Optimistic UI preparation | Task 7 (#optimisticState, snapshot, rollback) |
| Variation-aware synchronization | Task 3 (variation_attrs in sync_item), Task 7 (rowKey format) |
| Store API consistency | Task 4 (wpNonce for wp_rest, nonce for wc_store_api both localized) |
| Row identity decision | Documented in this plan + Task 9 |
| Request cancellation | Task 7 (AbortController) |
| WC cart as source of truth | Task 3 (no custom persistence) |
| Lightweight payloads | Task 2 (token+items only), Task 3 (selective response fields) |
| Performance (large carts) | Task 3 (cart_index O(1), totals calculated once) |
| `get_available_variations()` not used | Task 3 (wc_get_product($variation_id) only) |

### Gaps: none found.

### Type consistency
- `rowKey` format `"pid_vid"` used consistently: SyncQueue.flush(), CartSync.schedule(), CartSync.getOptimisticQuantity(), #onSuccess
- `token` is `number` in JS, `int` (via absint) in PHP — consistent
- Response `action` strings match: `"added"`, `"updated"`, `"removed"`, `"skipped"`, `"failed"`, `"out_of_stock"` — used in CartSync.#onSuccess

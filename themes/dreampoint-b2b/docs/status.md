# Quick Order — Implementation Status

Last updated: 2026-05-13

---

| System | Status | Stable | Staging Ready | Notes |
|--------|--------|--------|---------------|-------|
| CartSync (debounce, token, abort) | ACTIVE | Yes | Yes | Architecture locked. See `docs/tasks/quick-order-sync-architecture.md`. |
| Pagination | ACTIVE | Yes | Yes | In-place re-render. Qty resets on page change — known V1 tradeoff. |
| Variation handling | ACTIVE | Yes | Yes | Implicit replace flow (remove-old + add-new in one debounce window). |
| Visibility integration | ACTIVE | Yes | Yes | Gate fires on `add_to_cart` only. No retroactive revalidation — intentional. |
| Sorting | ACTIVE | Yes | Yes | `qo_orderby` / `qo_order` params. Title and price sort, ASC/DESC toggle. Isolated from WOOF `orderby` detection. |
| Filter integration (WOOF/WBW) | ACTIVE | Yes | Yes | Price range + pa_* attribute filters via REST. WOOF URL change propagation via pushState wrapper. Isolation guard strips wpf_query from QO WP_Query instances. |
| Cart totals footer | EXPERIMENTAL | Partial | No | `data.totals` in every sync response. Footer div in template. Render not wired to response data. |
| Variable stock neutral state | DEFERRED | No | No | V1.1 plan — variable rows currently show incorrect stock badge before variation selection. |
| Qty +/- buttons | DEFERRED | No | No | V1.1 plan — qty input only, no +/- controls yet. |
| Admin bypass | DEFERRED | No | No | V1.1 plan — `manage_woocommerce` users currently blocked by Quick Order access guard. |
| Cross-page cart hydration | DEFERRED | No | No | V2 consideration. Documented as known limitation in sync architecture doc. |
| Offline / network failure handling | DEFERRED | No | No | Requests fail silently. No queue persistence or retry. |
| Performance guards | ACTIVE | Yes | Yes | Batch cap 50 items, no object hydration, AbortController prevents stale processing. |
| Playwright E2E coverage | DEFERRED | No | No | Manual staging checklist only (`docs/staging-quick-order-checklist.md`). No automated suite. |

---

## Status Key

- **ACTIVE** — built, tested locally, works as designed
- **EXPERIMENTAL** — partially built; behavior may be incomplete or untested
- **DEFERRED** — intentionally not built yet; no regression, just missing functionality

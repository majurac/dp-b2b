# DP B2B Quick Order

Performance-oriented WooCommerce B2B Quick Order plugin for Dreampoint B2B.

## Requirements

- WordPress 6.0+
- WooCommerce 8.0+
- PHP 8.3+

## Architecture

Custom plugin. Consumes the existing Dreampoint B2B theme's visibility engine,
bucket system, and checkout infrastructure. Does not duplicate or replace any
existing B2B system.

## Shortcode

```
[dp_quick_order]
```

Place on a page with slug `quick-order`.

## REST API

Namespace: `dreampoint-b2b/v1`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/quick-order/products` | Paginated, visibility-filtered product list |
| POST | `/quick-order/cart/sync` | Batch cart sync via WooCommerce cart |

## File Structure

```
dp-b2b-quick-order/
├── dp-b2b-quick-order.php      — Plugin bootstrap, autoloader
├── inc/
│   ├── class-plugin.php        — Main plugin class, wires up all components
│   ├── class-assets.php        — Script/style enqueue
│   ├── class-rest-api.php      — REST endpoint registration
│   ├── class-cart-sync.php     — WooCommerce cart batch sync
│   ├── class-product-query.php — Optimized product queries
│   ├── class-visibility-integration.php — Hooks into existing visibility engine
│   └── class-frontend.php      — Shortcode + template rendering
├── assets/
│   ├── js/                     — Source JS
│   ├── css/                    — Source CSS
│   └── dist/                   — Built assets (quick-order.js, quick-order.css)
└── templates/
    └── quick-order.php         — Main frontend template shell
```

## Phases

- **Phase 1 (current):** Foundation architecture, bootstrap, REST skeleton
- **Phase 2:** Product table UI, inline quantity controls
- **Phase 3:** Variation handling, stock display
- **Phase 4:** Filters, search, sorting
- **Phase 5:** Cart footer, order summary

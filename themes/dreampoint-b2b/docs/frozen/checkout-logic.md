# Checkout Logic

## Status
FROZEN — DO NOT REFACTOR WITHOUT EXPLICIT APPROVAL

Architecture status:
STABLE / PRODUCTION-VALIDATED

## Goal
Centralize all checkout-related logic: payment method restrictions based on selected shipping method, billing→shipping field prefill, and protection of master B2B billing data from being overwritten at checkout.

## Implementation
- Config-driven rules array maps shipping method slugs to allowed payment gateway IDs
- Resolver reads chosen shipping from WC session, with `$_REQUEST` fallback for Blocks async state
- `str_contains()` used for matching to handle compound IDs (e.g. `local_pickup:1`, `pickup_location:0`)
- Payment filter applies via `woocommerce_available_payment_gateways`
- Server-side validation on both `woocommerce_checkout_process` (classic) and `woocommerce_store_api_checkout_update_order_from_request` (Blocks)
- Billing→shipping prefill via `woocommerce_checkout_fields` filter (works in Blocks)
- Billing data protection restores original meta values at priority 999 on `woocommerce_checkout_update_user_meta`

## Files
- `inc/checkout-logic.php`

## Notes
- WooCommerce Blocks uses `pickup_location` as shipping ID, not `local_pickup` — both are in the rules config
- `RouteException` (Store API) requires `@var \Exception` cast due to missing Intelephense stubs
- `dreampoint_b2b_copy_billing_to_shipping()` already exists in `inc/my-account.php` (different purpose: copies on customer creation) — checkout prefill function is named `dreampoint_b2b_prefill_shipping_from_billing`
- Billing protection applies to all checkout types (no REST_REQUEST condition) — admin and My Account remain the only channels for changing master billing data

## Status
DONE ✅

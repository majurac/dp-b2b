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

## Superseded / Current Implementation (2026-11-10)

The sections above are kept as history. Where they conflict with this section, this section is current.

- **Payment-gateway rules removed (A8, `a078fe9`, owner verified 2026-11-10):** the config-driven rules array, the resolver, the `woocommerce_available_payment_gateways` filter and the two server-side validations (`woocommerce_checkout_process`, `woocommerce_store_api_checkout_update_order_from_request`) described under "Goal" and "Implementation" no longer exist. No theme code restricts payment methods by shipping method. ADR-037 establishes BACS as the only intended gateway; which gateways are offered is a WooCommerce setting (BACS enabled, COD and cheque disabled on staging), not a theme restriction. The note about `pickup_location` / `local_pickup` in the rules config no longer applies.
- **Still active in `inc/checkout-logic.php`:** billing -> shipping prefill (`woocommerce_checkout_fields`, `dreampoint_b2b_prefill_shipping_from_billing`) and billing master protection.
- **Billing master protection (T7.1 / T7.2, `figma-alignment.md` section 53.5; Store API details in sections 53.7-53.9; ADR-037 context for the payment model):** the priority-999 callback on `woocommerce_checkout_update_user_meta` described above is superseded. Current hooks: classic checkout `woocommerce_checkout_update_customer`; Blocks / Store API `woocommerce_store_api_cart_update_customer_from_request`, `woocommerce_store_api_checkout_update_customer_from_request`, `woocommerce_store_api_checkout_update_order_from_request` (`dreampoint_b2b_require_complete_billing_master`), `rest_request_before_callbacks` (`dreampoint_b2b_keep_existing_order_billing_master`) and `woocommerce_before_customer_object_save`. The helper `dreampoint_b2b_restore_billing_master()` lives in `inc/my-account.php`.
- **Billing update channels:** the note "admin and My Account remain the only channels for changing master billing data" is superseded by section 53.5 / 53.7 (Option A: a new order of an authenticated B2B customer carries the stored billing master; shipping, phone and e-mail stay editable; see those sections for the exact My Account behaviour).
- **Process note:** this file stays frozen; changes to `inc/checkout-logic.php` still need explicit approval and a checkout verification.

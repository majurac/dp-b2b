<?php
defined( 'ABSPATH' ) || exit;

/**
 * Centralized plugin constants.
 * Static class — no instances, no runtime loading, no dynamic config.
 */
class DP_Quick_Order_Config {

	// ── REST API ──────────────────────────────────────────────────────────────

	const REST_NAMESPACE = 'dreampoint-b2b/v1';
	const REST_BASE      = 'quick-order';

	// ── Frontend ──────────────────────────────────────────────────────────────

	const PAGE_SLUG = 'quick-order';
	const SHORTCODE = 'dp_quick_order';

	// ── Nonce ─────────────────────────────────────────────────────────────────

	// WC Store API nonce — required for block-based checkout cart compatibility
	const NONCE_ACTION = 'wc_store_api';

	// ── Product Query ─────────────────────────────────────────────────────────

	const PRODUCTS_PER_PAGE_DEFAULT = 50;
	const PRODUCTS_PER_PAGE_MAX     = 200;

	// ── Variation Architecture ────────────────────────────────────────────────

	// Max variation IDs returned per product in lightweight summary payloads.
	// Full per-variation data is fetched on demand — never via get_available_variations().
	const MAX_VARIATIONS_PER_REQUEST = 50;

	// ── Cart Sync ─────────────────────────────────────────────────────────────

	// Frontend debounce before dispatching cart sync AJAX — prevents excessive requests
	// during rapid quantity changes. Applied in JS, defined here for documentation parity.
	const CART_SYNC_DEBOUNCE_MS = 300;

	// Maximum items per single sync batch dispatch.
	const CART_SYNC_MAX_BATCH  = 50;

	// Fetch timeout for cart sync requests (ms). Prevents stalled requests blocking the queue.
	const CART_SYNC_TIMEOUT_MS = 10000;
}

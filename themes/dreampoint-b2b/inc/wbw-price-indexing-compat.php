<?php
/**
 * WBW Product Filter — indexing compatibility layer for newly created products.
 *
 * PURPOSE
 * -------
 * WBW Product Filter (Free + PRO) keeps its own denormalized meta index
 * (`{$wpdb->prefix}wpf_meta_data`) used for price/attribute sorting and
 * filtering. That index is kept in sync incrementally via
 * `modules/meta/mod.php`, which listens to `woocommerce_update_product` only:
 *
 *     add_action( 'woocommerce_update_product', array( $this, 'recalcProductMetaValues' ), 99999, 1 );
 *
 * WooCommerce fires a DIFFERENT action, `woocommerce_new_product`, the first
 * time a product is created (`WC_Product::save()` on an object with no ID
 * yet → `WC_Product_Data_Store_CPT::create()`). `woocommerce_update_product`
 * only fires on subsequent saves of an already-existing product ID
 * (`WC_Product_Data_Store_CPT::update()`). WBW does not hook
 * `woocommerce_new_product` anywhere in either the Free or PRO plugin
 * (verified by full-text search across both plugin directories) — so a
 * product created once and never re-saved is silently absent from WBW's
 * index forever, even though it was created through the fully standard,
 * WooCommerce-native `WC_Product::save()` API.
 *
 * CONFIRMED IMPACT
 * -----------------
 * This gap was root-caused after `orderby=price` was found to sort
 * incorrectly on `/shop/`: WBW's own price-ordering SQL LEFT JOINs against
 * its meta index and orders by the joined value — unindexed products yield
 * SQL NULL, which MySQL sorts before every real price in ASC order,
 * regardless of the product's actual price. On this catalog, 220 of 427
 * published products (~51%) were affected, confirmed via WP-CLI/SQL
 * comparison against `wc_product_meta_lookup` and `wc_get_product()->get_price()`.
 * See `docs/decisions.md` ADR (WBW price ordering / indexing gap) for the
 * full investigation record.
 *
 * FIX
 * ---
 * This file hooks `woocommerce_new_product` and calls WBW's own supported
 * per-product recalculation entry point — the exact same method
 * `woocommerce_update_product` already calls — so a newly created product
 * enters the index immediately, without waiting for a later edit, WBW's
 * manual "recalculate index tables" admin button, or its optional hourly
 * background reindex cron. No vendor file is modified and no indexing SQL
 * is reproduced here; this is a thin wiring layer over WBW's own API.
 *
 * `woocommerce_update_product` (existing WBW hook) is left completely
 * untouched — updates to already-existing products are already handled.
 * Variable products/variations are handled consistently with WBW's own
 * model: `MetaModelWpf::doRecalcMetaValues()` already expands a variable
 * parent ID to include its `get_children()` variation IDs internally, so
 * calling the same per-product method WBW itself uses is sufficient; no
 * separate `woocommerce_new_product_variation` handling is added here,
 * matching how WBW's own `woocommerce_update_product` hook already relies
 * on `WC_Product_Variable::sync()` (which re-saves the parent) to index
 * variations rather than reacting to variation saves directly.
 *
 * VENDOR VERSIONS VERIFIED AGAINST
 * ---------------------------------
 * - woo-product-filter (Free): 3.2.0
 * - woofilter-pro (PRO):       3.2.0
 *
 * The exact per-product recalculation method this file calls lives at:
 * - woo-product-filter/modules/meta/mod.php → MetaWpf::recalcProductMetaValues()
 *
 * REMOVAL / RE-VERIFICATION
 * --------------------------
 * If a future WBW release adds its own `woocommerce_new_product` handling,
 * this file becomes redundant (harmless — WBW's own de-dupe guard,
 * `MetaWpf::$wpfPreviousProductId`, already prevents a double recalc within
 * the same request) but should be reviewed and removed once confirmed.
 * After any WBW/WBW-PRO update, re-check `modules/meta/mod.php` for the
 * `woocommerce_update_product` hook and `recalcProductMetaValues()` still
 * existing with the same signature.
 *
 * @package Dreampoint_B2B
 */

defined( 'ABSPATH' ) || exit;

// Guard against the file running while WBW Product Filter is inactive
// (plugin deactivated/removed) — the hook below would just never fire in
// that case, but this keeps the function definition from being a landmine.
if ( ! defined( 'WPF_VERSION' ) ) {
    return;
}

// MAINTENANCE NOTE:
// The verified WBW versions listed in the file header do not index
// products created via `woocommerce_new_product`, so this compatibility
// hook fills that gap.
//
// Re-check this after WBW upgrades. If WBW begins indexing newly created
// products natively, review this hook and remove it once confirmed
// redundant.
//
// Until then, leaving this hook in place is safe because the underlying
// WBW indexing path is idempotent (see file header).
add_action( 'woocommerce_new_product', 'dreampoint_b2b_wbw_index_new_product', 10, 1 );

/**
 * Indexes a newly created product into WBW's own meta index immediately,
 * using WBW's own supported per-product recalculation method — the same
 * one `woocommerce_update_product` already calls for edits.
 *
 * @param int $product_id
 *
 * @return void
 */
function dreampoint_b2b_wbw_index_new_product( int $product_id ): void {
    if ( $product_id <= 0 ) {
        return;
    }

    if ( ! class_exists( 'FrameWpf' ) ) {
        return;
    }

    $meta_module = FrameWpf::_()->getModule( 'meta' );

    if ( ! $meta_module || ! method_exists( $meta_module, 'recalcProductMetaValues' ) ) {
        return;
    }

    $meta_module->recalcProductMetaValues( $product_id );
}

<?php
/**
 * WBW Product Filter — Search compatibility layer for "Multi" frontend type.
 *
 * PURPOSE
 * -------
 * WBW Product Filter (Free + PRO) only renders the "Show search" input for
 * Category and Brand filter blocks when the block's Frontend Type = "List".
 * It does not currently support the search box for Frontend Type = "Multi"
 * (multi-select checkboxes) — the admin "Show search" option is not even
 * exposed for a Multi-type block, and no search markup is ever rendered for
 * it, on any WBW version available at the time this file was written.
 *
 * On this project, [wpf-filters id=1] (main shop archive filter) and
 * [wpf-filters id=3] (Quick Order filter, dp-b2b-quick-order plugin) both
 * have Category and/or Brand blocks configured as Frontend Type = "Multi",
 * so neither gets a native search box from WBW.
 *
 * This file adds ONLY that missing search `<input>` markup, for those two
 * filters, via WBW's own documented extension point
 * (`wpf_addHtmlAfterFilter`) — it does not touch a single vendor file, does
 * not duplicate vendor JavaScript, and does not add any custom CSS. The
 * existing WBW frontend JS (`frontend.woofilters.js`, the
 * `.wpfSearchFieldsFilter` keyup handler) is generic and type-agnostic — it
 * already filters "Multi" checkbox markup correctly with zero changes, once
 * the input element exists in the DOM. The existing WBW CSS
 * (`frontend.woofilters.css`, `.wpfSearchWrapper` / `.wpfSearchFieldsFilter`)
 * is loaded unconditionally whenever any WBW filter is on the page.
 *
 * VENDOR VERSIONS VERIFIED AGAINST
 * ---------------------------------
 * - woo-product-filter (Free): 3.1.8
 * - woofilter-pro (PRO):       3.1.8
 *
 * The exact search-markup logic this file mirrors lives at:
 * - woo-product-filter/modules/woofilters/views/woofilters.php
 *   generateCategoryFilterHtml() — search block, 'list' === $type gate
 * - woofilter-pro/woofilterpro/views/woofilterpro.php
 *   generateBrandFilterHtml() — search block, 'list' === $type gate
 *
 * REMOVAL / RE-VERIFICATION
 * --------------------------
 * If a future WBW or WBW-PRO release adds native "Multi" search support,
 * this file's duplicate-prevention check (see
 * dreampoint_b2b_wbw_multi_search_compat()) will detect the vendor's own
 * `.wpfSearchWrapper` and skip injection automatically — no double search
 * boxes will appear. At that point this entire file should be reviewed and,
 * once the native implementation is confirmed correct, deleted; it exists
 * purely to fill a gap that is expected to eventually be closed upstream.
 *
 * After ANY WBW/WBW-PRO update, compare the two vendor locations listed
 * above against dreampoint_b2b_wbw_build_search_markup() below.
 *
 * @package Dreampoint_B2B
 */

defined( 'ABSPATH' ) || exit;

// Guard against the file being loaded while WBW Product Filter is inactive
// (e.g. plugin deactivated/removed) — the hook this file relies on would
// simply never fire in that case, but this keeps the constant/function
// definitions from being a no-op landmine if something else calls them.
if ( ! defined( 'WPF_VERSION' ) ) {
    return;
}

/**
 * Filter ids this compatibility layer is scoped to:
 * - 1: main shop archive filter (Kategorije / Brendovi blocks are Multi)
 * - 3: Quick Order filter (dp-b2b-quick-order plugin) — Brendovi block is Multi
 *
 * Any other [wpf-filters id=N] on the site is returned completely untouched.
 */
const DREAMPOINT_B2B_WBW_SEARCH_COMPAT_FILTER_IDS = array( 1, 3 );

add_filter( 'wpf_addHtmlAfterFilter', 'dreampoint_b2b_wbw_multi_search_compat', 10, 3 );

/**
 * Injects the WBW Category/Brand "List"-type search markup into Multi-type
 * Category/Brand blocks of the scoped [wpf-filters id] instances.
 *
 * @param string $html     Fully rendered filter widget HTML (WBW has not yet
 *                          appended the closing </div> for .wpfMainWrapper —
 *                          that happens right after this filter runs).
 * @param array  $settings Filter-wide (Options tab) settings — not per-block.
 * @param int    $filterId The wpf-filters shortcode id being rendered.
 *
 * @return string
 */
function dreampoint_b2b_wbw_multi_search_compat( string $html, $settings, $filterId ): string {
    if ( ! in_array( (int) $filterId, DREAMPOINT_B2B_WBW_SEARCH_COMPAT_FILTER_IDS, true ) ) {
        return $html;
    }

    // Cheap guard before paying for a DOM parse.
    if ( false === strpos( $html, 'data-taxonomy="product_cat"' ) && false === strpos( $html, 'data-taxonomy="product_brand"' ) ) {
        return $html;
    }

    $blockSettings = dreampoint_b2b_wbw_get_block_settings( (int) $filterId );

    $dom = new DOMDocument();

    // WBW deliberately has not closed <div class="wpfMainWrapper"> yet at this
    // point (it appends '</div>' right after this filter runs) — wrap the
    // fragment in our own root so DOMDocument always receives well-formed
    // markup to parse, regardless of that trailing open tag.
    $wasInternalErrors = libxml_use_internal_errors( true );
    $loaded = $dom->loadHTML(
        '<?xml encoding="utf-8"?><div id="dp-wbw-search-compat-root">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors( $wasInternalErrors );

    if ( ! $loaded ) {
        return $html;
    }

    $xpath = new DOMXPath( $dom );
    $modified = false;

    // Per-taxonomy config: WBW block id (for settings lookup), search input
    // class, default placeholder, and which per-block attributes apply.
    // This is the ONLY place that differs between Category and Brand —
    // dreampoint_b2b_wbw_build_search_markup() below is shared by both.
    $targets = array(
        'product_cat'   => array(
            'blockId'          => 'wpfCategory',
            'inputClass'       => 'wpfSearchFieldsFilter passiveFilter',
            'defaultLabel'     => __( 'Search categories', 'dreampoint-b2b' ),
            'supportsUnfolding' => true,
        ),
        'product_brand' => array(
            'blockId'          => 'wpfBrand',
            'inputClass'       => 'wpfSearchFieldsFilter',
            'defaultLabel'     => __( 'Search brands', 'dreampoint-b2b' ),
            // Brands are not hierarchical — WBW itself does not offer
            // unfolding/collapse-by-search options for this block type.
            'supportsUnfolding' => false,
        ),
    );

    foreach ( $targets as $taxonomy => $config ) {
        $wrappers = $xpath->query(
            '//div[@data-taxonomy="' . $taxonomy . '" and contains(concat(" ", normalize-space(@class), " "), " wpfFilterWrapper ")]'
        );

        foreach ( $wrappers as $wrapper ) {
            $existingSearch = $xpath->query( './/div[contains(concat(" ", normalize-space(@class), " "), " wpfSearchWrapper ")]', $wrapper );
            if ( $existingSearch->length > 0 ) {
                // A search box is already present for this block. Either:
                // (a) it is still Frontend Type = "List" (native WBW search,
                //     nothing to do here), or
                // (b) WBW has introduced native "Multi" search support in a
                //     newer version.
                // Either way, skipping is the correct, intentional behaviour
                // — never duplicate a search box. If (b) turns out to be the
                // case after a WBW update, verify the native implementation
                // covers everything this compatibility layer provided
                // (data-unfolding / data-collapse-search for Category), then
                // this whole file can likely be deleted.
                continue;
            }

            // .wpfCheckboxHier is nested one level inside .wpfFilterContent
            // (not a direct child of .wpfFilterWrapper) — search anywhere in
            // the subtree and insert as its sibling, matching WBW's own
            // placement (inside .wpfFilterContent, right before .wpfCheckboxHier).
            $checkboxHier = $xpath->query( './/div[contains(concat(" ", normalize-space(@class), " "), " wpfCheckboxHier ")]', $wrapper )->item( 0 );
            if ( ! $checkboxHier || ! $checkboxHier->parentNode ) {
                continue;
            }

            $blockConfig = $blockSettings[ $config['blockId'] ] ?? array();

            $extraAttrs = array();
            if ( $config['supportsUnfolding'] ) {
                if ( ! empty( $blockConfig['f_unfolding_by_search'] ) ) {
                    $extraAttrs['data-unfolding'] = '1';
                }
                if ( ! empty( $blockConfig['f_collapse_by_delete'] ) ) {
                    $extraAttrs['data-collapse-search'] = '1';
                }
            }

            $placeholder = ! empty( $blockConfig['f_search_label'] )
                ? sanitize_text_field( $blockConfig['f_search_label'] )
                : $config['defaultLabel'];

            $searchNode = dreampoint_b2b_wbw_build_search_markup( $dom, $config['inputClass'], $placeholder, $extraAttrs );
            $checkboxHier->parentNode->insertBefore( $searchNode, $checkboxHier );
            $modified = true;
        }
    }

    if ( ! $modified ) {
        return $html;
    }

    $root = $xpath->query( '//div[@id="dp-wbw-search-compat-root"]' )->item( 0 );
    if ( ! $root ) {
        return $html;
    }

    $output = '';
    foreach ( $root->childNodes as $child ) {
        $output .= $dom->saveHTML( $child );
    }

    // DOMDocument auto-closed the one tag WBW intentionally left open
    // (.wpfMainWrapper) when it repaired our synthetic fragment. Remove
    // that single trailing closing tag so WBW's own '</div>' (appended
    // right after this filter returns) is not duplicated.
    $lastClosingDiv = strripos( $output, '</div>' );
    if ( false !== $lastClosingDiv ) {
        $output = substr( $output, 0, $lastClosingDiv ) . substr( $output, $lastClosingDiv + strlen( '</div>' ) );
    }

    return $output;
}

/**
 * Builds a single `<div class="wpfSearchWrapper">` search node, matching
 * exactly what WBW itself renders for the "List" frontend type — NOT the
 * simplified Attribute-filter variant. Shared by both Category and Brand;
 * only the values that actually differ between them are passed in.
 *
 * Compare against these two vendor locations after any WBW/WBW-PRO update:
 * - woo-product-filter/modules/woofilters/views/woofilters.php
 *   generateCategoryFilterHtml() search block (~line 1625)
 * - woofilter-pro/woofilterpro/views/woofilterpro.php
 *   generateBrandFilterHtml() search block (~line 1906)
 *
 * @param DOMDocument $dom
 * @param string      $inputClass  Full class attribute value for the <input>.
 * @param string      $placeholder Already-sanitized placeholder text.
 * @param array<string,string> $extraAttrs Additional attributes to set
 *                                          verbatim (e.g. data-unfolding).
 *
 * @return DOMElement
 */
function dreampoint_b2b_wbw_build_search_markup( DOMDocument $dom, string $inputClass, string $placeholder, array $extraAttrs = array() ): DOMElement {
    $wrapper = $dom->createElement( 'div' );
    $wrapper->setAttribute( 'class', 'wpfSearchWrapper' );

    $input = $dom->createElement( 'input' );
    $input->setAttribute( 'type', 'text' );
    $input->setAttribute( 'class', $inputClass );
    $input->setAttribute( 'placeholder', $placeholder );

    foreach ( $extraAttrs as $name => $value ) {
        $input->setAttribute( $name, $value );
    }

    $wrapper->appendChild( $input );

    return $wrapper;
}

/**
 * Reads per-block settings (f_search_label, f_unfolding_by_search,
 * f_collapse_by_delete) for the Category/Brand blocks of a given
 * [wpf-filters id] directly from WBW's own storage table, keyed by WBW's
 * own block id (wpfCategory / wpfBrand).
 *
 * These fields exist in the database whenever an admin has previously
 * configured them (e.g. while the block was temporarily set to "List"
 * type) — reading them here preserves that configuration instead of
 * hardcoding a fixed behaviour, matching what WBW itself would render.
 *
 * Read-only, single primary-key lookup — no caching, so admin changes are
 * reflected on the very next page load.
 *
 * @param int $filterId
 *
 * @return array<string, array> Keyed by WBW block id (wpfCategory, wpfBrand).
 */
function dreampoint_b2b_wbw_get_block_settings( int $filterId ): array {
    global $wpdb;

    $table = $wpdb->prefix . 'wpf_filters';
    $raw   = $wpdb->get_var( $wpdb->prepare( "SELECT setting_data FROM {$table} WHERE id = %d", $filterId ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

    if ( empty( $raw ) ) {
        return array();
    }

    $data = @unserialize( $raw ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
    if ( ! is_array( $data ) || empty( $data['settings']['filters']['order'] ) ) {
        return array();
    }

    $order = json_decode( $data['settings']['filters']['order'], true );
    if ( ! is_array( $order ) ) {
        return array();
    }

    $result = array();
    foreach ( $order as $block ) {
        $blockId = $block['id'] ?? '';
        if ( 'wpfCategory' === $blockId || 'wpfBrand' === $blockId ) {
            $result[ $blockId ] = $block['settings'] ?? array();
        }
    }

    return $result;
}

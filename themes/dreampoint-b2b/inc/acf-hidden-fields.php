<?php
/**
 * Hides the Newsletter, Cards and Popup tabs (and their fields) of the "General Settings" ACF group
 * (`group_6733549273639`, options page `theme-settings`) from the WordPress admin.
 *
 * Admin-only and reversible: the filter just stops ACF from rendering these fields on the edit screen. Field
 * definitions, ACF JSON, stored values and frontend `get_field()` reads (e.g. footer.php `subscribe_*`) are untouched,
 * and ACF only saves submitted fields, so hidden values are never modified when other fields are saved.
 *
 * Restore: add `define( 'DP_SHOW_HIDDEN_ACF_FIELDS', true );` to wp-config.php (the only place the constant is
 * defined; this file only reads it). Remove the line to hide the tabs again.
 *
 * @package Dreampoint_B2B
 */

defined( 'ABSPATH' ) || exit;

/**
 * Exact ACF field keys to hide: each tab field plus every field that belongs to it.
 *
 * @return string[]
 */
function dreampoint_b2b_acf_hidden_field_keys(): array {
	return [
		// Newsletter tab.
		'field_681dd4904b40c', // Tab: Newsletter.
		'field_681dd4a04b40d', // subscribe_title.
		'field_681dd4a84b40e', // subscribe_text.
		'field_681dd4c14b40f', // subscribe_form_id.
		// Cards tab.
		'field_683414b4aefe1', // Tab: Cards.
		'field_68341555eb4fa', // cards_title.
		'field_683414c3aefe2', // cards (repeater; its sub fields are not rendered without it).
		// Popup tab.
		'field_68343caea15e7', // Tab: Popup.
		'field_68343cbea15e8', // popup_title.
		'field_68343d73a15e9', // popup_show.
	];
}

add_filter(
	'acf/prepare_field',
	static function ( $field ) {
		if ( ! is_array( $field ) || ( defined( 'DP_SHOW_HIDDEN_ACF_FIELDS' ) && DP_SHOW_HIDDEN_ACF_FIELDS ) ) {
			return $field;
		}
		// Returning false makes ACF skip rendering the field (admin edit screens only).
		return in_array( $field['key'] ?? '', dreampoint_b2b_acf_hidden_field_keys(), true ) ? false : $field;
	}
);
